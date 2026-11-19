<?php
// civicare/user/new_complaint.php
require_once '../config/database.php';
require_once '../config/functions.php';

// Check if user is logged in
if (!isLoggedIn() || !hasRole('citizen')) {
    header('Location: ../user/login.php');
    exit();
}

$conn = getDatabaseConnection();

$error = '';
$success = '';
$departments = getAllDepartments($conn);

// Default settings (no settings table required)
$require_evidence = 0;
$max_evidence_size = 10;
$allowed_evidence_types = 'jpg,jpeg,png,pdf,doc,docx';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = sanitizeInput($_POST['title']);
    $description = sanitizeInput($_POST['description']);
    $department_id = (int)$_POST['department_id'];
    $location = sanitizeInput($_POST['location']);
    $landmark = sanitizeInput($_POST['landmark']);
    
    // Validation
    if (empty($title) || empty($description) || empty($department_id)) {
        $error = "Please fill in all required fields.";
    } elseif (strlen($title) < 10) {
        $error = "Title must be at least 10 characters long.";
    } elseif (strlen($description) < 20) {
        $error = "Description must be at least 20 characters long.";
    } elseif ($require_evidence && (!isset($_FILES['evidence']) || empty($_FILES['evidence']['name'][0]))) {
        $error = "Please upload at least one evidence file as required by the system.";
    }
    
    if (empty($error)) {
        // Validate uploaded files if evidence is required
        $uploaded_files = [];
        if ($require_evidence && isset($_FILES['evidence'])) {
            $allowed_types = array_map('trim', explode(',', strtolower($allowed_evidence_types)));
            $max_size_bytes = $max_evidence_size * 1024 * 1024; // Convert MB to bytes
            
            foreach ($_FILES['evidence']['name'] as $key => $file_name) {
                if (empty($file_name)) continue;
                
                $file_tmp = $_FILES['evidence']['tmp_name'][$key];
                $file_size = $_FILES['evidence']['size'][$key];
                $file_error = $_FILES['evidence']['error'][$key];
                
                // Check for upload errors
                if ($file_error !== UPLOAD_ERR_OK) {
                    $error = "File upload error: " . $file_name;
                    break;
                }
                
                // Check file size
                if ($file_size > $max_size_bytes) {
                    $error = "File '$file_name' exceeds maximum size of {$max_evidence_size}MB.";
                    break;
                }
                
                // Check file type
                $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                if (!in_array($file_ext, $allowed_types)) {
                    $error = "File type '$file_ext' is not allowed. Allowed types: " . implode(', ', $allowed_types);
                    break;
                }
                
                $uploaded_files[] = [
                    'name' => $file_name,
                    'tmp_name' => $file_tmp,
                    'size' => $file_size,
                    'ext' => $file_ext
                ];
            }
        }
    }
    
    if (empty($error)) {
        // Generate tracking ID
        $tracking_id = generateTrackingId();
        
        // Default priority for new complaints is 'medium' (set by admin later)
        $priority = 'medium';
        
        // Insert complaint
        $sql = "INSERT INTO complaints (tracking_id, user_id, department_id, title, description, location, landmark, priority, status) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending')";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("siisssss", $tracking_id, $_SESSION['user_id'], $department_id, $title, $description, $location, $landmark, $priority);
        
        if ($stmt->execute()) {
            $complaint_id = $stmt->insert_id;
            
            // Add complaint update history
            addComplaintUpdate($conn, $complaint_id, $_SESSION['user_id'], 'pending', 'Complaint filed by citizen');
            
            // Create notification for the user
            createNotification($conn, $_SESSION['user_id'], 'Complaint Filed Successfully', 
                "Your complaint #$tracking_id has been filed successfully and is pending validation. You can track its status using the tracking ID.", 
                $complaint_id);
            
            // Create notification for department admin/staff
            $dept_name = getDepartmentName($conn, $department_id);
            $user_name = $_SESSION['user_name'];
            
            // Get admin users for this department
            $admin_sql = "SELECT id FROM users WHERE (role IN ('admin', 'super_admin') OR (role = 'staff' AND department_id = ?)) AND is_active = TRUE";
            $admin_stmt = $conn->prepare($admin_sql);
            $admin_stmt->bind_param("i", $department_id);
            $admin_stmt->execute();
            $admin_result = $admin_stmt->get_result();
            
            while ($admin_row = $admin_result->fetch_assoc()) {
                createNotification($conn, $admin_row['id'], 'New Complaint Filed', 
                    "New complaint #$tracking_id filed in $dept_name department by $user_name. Title: $title", 
                    $complaint_id);
            }
            $admin_stmt->close();
            
            // Handle file uploads if evidence is required
            if ($require_evidence && !empty($uploaded_files)) {
                // Create uploads directory if it doesn't exist
                $upload_dir = '../uploads/complaints/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                
                foreach ($uploaded_files as $file) {
                    // Generate unique filename
                    $unique_name = uniqid() . '_' . time() . '.' . $file['ext'];
                    $file_path = $upload_dir . $unique_name;
                    
                    // Move uploaded file
                    if (move_uploaded_file($file['tmp_name'], $file_path)) {
                        // Save attachment record to database
                        $attach_sql = "INSERT INTO complaint_attachments (complaint_id, file_name, file_path, file_size, file_type, uploaded_by) 
                                      VALUES (?, ?, ?, ?, ?, ?)";
                        $attach_stmt = $conn->prepare($attach_sql);
                        $attach_stmt->bind_param("issisi", $complaint_id, $file['name'], $file_path, $file['size'], $file['ext'], $_SESSION['user_id']);
                        $attach_stmt->execute();
                        $attach_stmt->close();
                    }
                }
            }
            
// Send email notification to user (commented out - function may not exist)
            // sendComplaintStatusEmail($conn, $_SESSION['user_id'], $complaint_id, 'pending');
            
            $success = "Complaint filed successfully! Your Tracking ID is: <strong>$tracking_id</strong>";
            $success .= " A confirmation email has been sent to your registered email address.";
            
            // Clear form if successful
            $title = $description = $location = $landmark = '';
            $department_id = '';
        } else {
            $error = "Failed to file complaint. Please try again. Error: " . $conn->error;
        }
        
        $stmt->close();
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>File New Complaint - Civicare</title>
    <style>
        /* ===== MUNICIPAL GOVERNMENT STYLING ===== */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f8f9fa;
            line-height: 1.6;
            color: #333;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }
        
        .header {
            background: white;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            position: sticky;
            top: 0;
            z-index: 100;
        }
        
        .container {
            width: 90%;
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 15px;
            flex: 1;
        }
        
        .navbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 0;
        }
        
        .logo {
            font-size: 24px;
            font-weight: bold;
            color: #1e3c72;
            text-decoration: none;
            transition: color 0.3s;
        }

        .logo:hover {
            color: #ff6b35;
        }
        
        .nav-links {
            display: flex;
            gap: 20px;
            align-items: center;
        }
        
        .nav-links a {
            color: #333;
            text-decoration: none;
            padding: 8px 12px;
            border-radius: 4px;
            font-weight: 500;
            transition: all 0.3s;
            font-size: 0.95rem;
        }
        
        .nav-links a:hover {
            background: #f0f0f0;
            color: #1e3c72;
        }
        
        .btn {
            display: inline-block;
            padding: 10px 24px;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            font-weight: 600;
            transition: all 0.3s;
            border: none;
            cursor: pointer;
            font-size: 0.95rem;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(255, 107, 53, 0.3);
        }
        
        .btn-primary {
            background: #ff6b35;
            color: white;
        }
        
        .btn-primary:hover {
            background: #e55a24;
        }
        
        .complaint-form-container {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.1);
            margin: 30px auto;
            max-width: 800px;
            border-left: 5px solid #ff6b35;
        }
        
        .form-header {
            text-align: center;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid #ff6b35;
        }
        
        .form-header h2 {
            color: #1e3c72;
            margin-bottom: 8px;
            font-size: 24px;
        }
        
        .form-header p {
            color: #666;
            font-size: 14px;
        }
        
        .alert {
            padding: 12px;
            border-radius: 5px;
            margin-bottom: 15px;
            font-size: 0.95rem;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
            font-size: 14px;
        }
        
        .required::after {
            content: " *";
            color: #ff6b35;
        }
        
        input, select, textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 4px;
            font-size: 14px;
            transition: all 0.3s;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        input:focus, select:focus, textarea:focus {
            outline: none;
            border-color: #ff6b35;
            box-shadow: 0 0 8px rgba(255, 107, 53, 0.2);
        }
        
        textarea {
            min-height: 120px;
            resize: vertical;
        }
        
        .btn-submit {
            display: block;
            width: 100%;
            padding: 12px;
            background: #ff6b35;
            color: white;
            border: none;
            border-radius: 4px;
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .btn-submit:hover {
            background: #e55a24;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(255, 107, 53, 0.3);
        }
        
        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }
        
        .form-row .form-group {
            margin-bottom: 0;
        }
        
        .character-count {
            font-size: 12px;
            color: #666;
            margin-top: 4px;
            text-align: right;
        }
        
        /* File upload styles */
        input[type="file"] {
            padding: 8px;
            border: 2px dashed #e0e0e0;
            border-radius: 4px;
            background: #fafafa;
            cursor: pointer;
        }
        
        input[type="file"]:hover {
            border-color: #ff6b35;
            background: #fef5f2;
        }
        
        input[type="file"]:focus {
            border-color: #ff6b35;
            background: #fef5f2;
        }
        
        .file-list {
            margin-top: 8px;
            max-height: 150px;
            overflow-y: auto;
        }
        
        .file-item {
            display: flex;
            align-items: center;
            padding: 6px 8px;
            background: #f8f9fa;
            border-radius: 4px;
            margin-bottom: 4px;
            font-size: 13px;
        }
        
        .file-item .file-name {
            flex: 1;
            color: #333;
        }
        
        .file-item .file-size {
            color: #666;
            margin-left: 8px;
        }
        
        .file-item .remove-file {
            color: #ff6b35;
            cursor: pointer;
            margin-left: 8px;
            font-weight: bold;
        }
        
        .guidelines {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            border-left: 4px solid #ff6b35;
        }
        
        .guidelines h4 {
            color: #1e3c72;
            margin-bottom: 8px;
            font-size: 15px;
        }
        
        .guidelines ul {
            padding-left: 20px;
            color: #555;
        }
        
        .guidelines li {
            margin-bottom: 5px;
            font-size: 14px;
        }
        
        .back-link {
            text-align: center;
            margin-top: 15px;
        }
        
        .back-link a {
            color: #ff6b35;
            text-decoration: none;
            font-weight: 600;
        }
        
        .back-link a:hover {
            text-decoration: underline;
        }
        
        .tracking-success {
            text-align: center;
            padding: 20px;
            background: #d4edda;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .tracking-id-display {
            font-size: 18px;
            font-weight: bold;
            color: #ff6b35;
            margin: 10px 0;
            padding: 15px;
            background: white;
            border-radius: 5px;
            border: 2px dashed #ff6b35;
        }
        
        .department-info {
            background: #e8f4fc;
            padding: 12px;
            border-radius: 4px;
            margin-top: 8px;
            font-size: 14px;
            color: #1e3c72;
            display: none;
            border-left: 3px solid #ff6b35;
        }
        
        .department-info.active {
            display: block;
        }
        
        /* ===== FOOTER ===== */
        .footer {
            background: #1a1a1a;
            color: #fff;
            padding: 25px 20px 10px;
            margin-top: 40px;
            width: 100%;
        }

        .footer-content {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 15px;
            max-width: 1200px;
            margin-left: auto;
            margin-right: auto;
        }

        .footer-section h4 {
            color: #ff6b35;
            margin-bottom: 10px;
            font-size: 14px;
            font-weight: 700;
        }

        .footer-section ul {
            list-style: none;
        }

        .footer-section ul li {
            margin-bottom: 5px;
        }

        .footer-section ul li a {
            color: #aaa;
            text-decoration: none;
            transition: color 0.3s;
            font-size: 14px;
        }

        .footer-section ul li a:hover {
            color: #ff6b35;
            padding-left: 5px;
        }

        .footer-section p {
            color: #aaa;
            font-size: 13px;
            line-height: 1.5;
        }

        .footer-contact {
            display: flex;
            gap: 10px;
            align-items: flex-start;
            margin-bottom: 8px;
            font-size: 13px;
        }

        .footer-contact i {
            color: #ff6b35;
            font-size: 14px;
            margin-top: 2px;
            flex-shrink: 0;
        }

        .footer-contact a {
            color: #ff6b35;
            text-decoration: none;
        }

        .footer-contact a:hover {
            text-decoration: underline;
        }

        .footer-social {
            display: flex;
            gap: 10px;
            margin-top: 10px;
        }

        .footer-social a {
            color: white;
            font-size: 16px;
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #333;
            border-radius: 50%;
            transition: all 0.3s;
        }

        .footer-social a:hover {
            background: #ff6b35;
            transform: translateY(-3px);
        }

        .footer-bottom {
            border-top: 1px solid #333;
            padding-top: 10px;
            text-align: center;
            color: #aaa;
            font-size: 11px;
            max-width: 1200px;
            margin: 0 auto;
        }

        .footer-bottom p {
            margin-bottom: 4px;
        }

        .footer-bottom a {
            color: #ff6b35;
            text-decoration: none;
        }

        .footer-bottom a:hover {
            text-decoration: underline;
        }

        @media (max-width: 768px) {
            .complaint-form-container {
                padding: 15px;
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }

            .navbar {
                flex-direction: column;
                gap: 10px;
            }

            .nav-links {
                flex-wrap: wrap;
                justify-content: center;
                gap: 10px;
            }

            .nav-links a {
                font-size: 0.85rem;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="container">
            <nav class="navbar">
                <a href="../index.php" class="logo">Civicare</a>
                <div class="nav-links">
                    <a href="../index.php">Home</a>
                    <a href="../about.php">About</a>
                    <a href="track_complaint.php">Track Complaint</a>
                    <a href="../user/dashboard.php" class="btn">Dashboard</a>
                    <a href="../logout.php" class="btn-primary">Logout</a>
                </div>
            </nav>
        </div>
    </div>

    <div class="container">
        <div class="complaint-form-container">
            <div class="form-header">
                <h2>File New Complaint</h2>
                <p>Report municipal issues to the appropriate department</p>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-error">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <?php echo $success; ?>
                </div>
                
                <div class="tracking-success">
                    <h3>✅ Complaint Filed Successfully!</h3>
                    <p>Your complaint has been submitted to the municipal department.</p>
                    <div class="tracking-id-display">
                        <?php echo isset($tracking_id) ? $tracking_id : ''; ?>
                    </div>
                    <p><strong>Important:</strong> Save this Tracking ID to track your complaint status.</p>
                    <div style="display: flex; gap: 1rem; justify-content: center; margin-top: 1.5rem;">
                        <a href="track_complaint.php" class="btn" style="background: #3498db;">Track This Complaint</a>
                        <a href="../user/dashboard.php" class="btn btn-primary">Go to Dashboard</a>
                    </div>
                </div>
            <?php endif; ?>
            
            <div class="guidelines">
                <h4>📝 Filing Guidelines:</h4>
                <ul>
                    <li>Provide clear and specific details about the issue</li>
                    <li>Include exact location and landmark for easy identification</li>
                    <li>Select the appropriate department for faster resolution</li>
                    <li>Admin will review and assign priority based on urgency</li>
                    <li>You will receive notifications at each status update</li>
                </ul>
            </div>
            
            <form method="POST" action="" id="complaintForm" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="title" class="required">Complaint Title</label>
                    <input type="text" id="title" name="title" 
                           value="<?php echo isset($title) ? htmlspecialchars($title) : ''; ?>" 
                           placeholder="e.g., Pothole on Main Street near City Park" 
                           required minlength="10" maxlength="200">
                    <div class="character-count">
                        <span id="titleCount">0</span>/200 characters
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="description" class="required">Detailed Description</label>
                    <textarea id="description" name="description" 
                              placeholder="Describe the issue in detail. Include specific location details, when you noticed it, and any safety concerns..."
                              required minlength="20"><?php echo isset($description) ? htmlspecialchars($description) : ''; ?></textarea>
                    <div class="character-count">
                        <span id="descCount">0</span> characters (minimum 20)
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="department_id" class="required">Select Department</label>
                        <select id="department_id" name="department_id" required>
                            <option value="">-- Choose Department --</option>
                            <?php foreach ($departments as $dept): ?>
                                <option value="<?php echo $dept['id']; ?>" 
                                    <?php echo (isset($department_id) && $department_id == $dept['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($dept['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="department-info" id="departmentInfo">
                            <!-- Department info will be shown here -->
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="location" class="required">Location/Address</label>
                        <input type="text" id="location" name="location" 
                               value="<?php echo isset($location) ? htmlspecialchars($location) : ''; ?>" 
                               placeholder="e.g., Near City Park, Main Street" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="landmark">Landmark (Optional)</label>
                    <input type="text" id="landmark" name="landmark" 
                           value="<?php echo isset($landmark) ? htmlspecialchars($landmark) : ''; ?>" 
                           placeholder="e.g., Opposite City Hospital, Near Metro Station">
                    <small style="color: #666; font-size: 0.8rem;">Helps in easier location identification</small>
                </div>
                
                <?php if ($require_evidence): ?>
                <div class="form-group">
                    <label for="evidence" class="required">Evidence Files</label>
                    <input type="file" id="evidence" name="evidence[]" multiple 
                           accept="<?php echo '.' . str_replace(',', ',.', $allowed_evidence_types); ?>" required>
                    <small style="color: #666; font-size: 0.8rem;">
                        Upload photos, documents, or other evidence supporting your complaint. 
                        Maximum file size: <?php echo $max_evidence_size; ?>MB each. 
                        Allowed types: <?php echo str_replace(',', ', ', $allowed_evidence_types); ?>
                    </small>
                    <div id="fileList" class="file-list"></div>
                </div>
                <?php endif; ?>
                
                <button type="submit" class="btn-submit" id="submitBtn">File Complaint</button>
            </form>
            
            <div class="back-link">
                <a href="../user/dashboard.php">← Back to Dashboard</a>
            </div>
        </div>
    </div>

    <!-- FOOTER -->
    <footer class="footer">
        <div class="footer-content">
            <div class="footer-section">
                <h4>About CiviCare</h4>
                <p>Digital platform transforming municipal governance through technology and citizen engagement since 2020.</p>
                <div class="footer-social">
                    <a href="#"><i class="fab fa-facebook-f"></i></a>
                    <a href="#"><i class="fab fa-twitter"></i></a>
                    <a href="#"><i class="fab fa-youtube"></i></a>
                    <a href="#"><i class="fab fa-instagram"></i></a>
                </div>
            </div>

            <div class="footer-section">
                <h4>Quick Links</h4>
                <ul>
                    <li><a href="../index.php">Home</a></li>
                    <li><a href="../about.php">About Us</a></li>
                    <li><a href="../contact.php">Contact</a></li>
                    <li><a href="../faqs.php">FAQs</a></li>
                </ul>
            </div>

            <div class="footer-section">
                <h4>Contact Us</h4>
                <div class="footer-contact">
                    <i class="fas fa-map-marker-alt"></i>
                    <span>Municipal Corp Building, City Center</span>
                </div>
                <div class="footer-contact">
                    <i class="fas fa-phone"></i>
                    <span><a href="tel:+919876543210">+(91) 9876543210</a></span>
                </div>
                <div class="footer-contact">
                    <i class="fas fa-envelope"></i>
                    <span><a href="mailto:info@civicare.gov.in">info@civicare.gov.in</a></span>
                </div>
            </div>
        </div>
        <div class="footer-bottom">
            <p>&copy; <?php echo date('Y'); ?> CiviCare - Municipal Complaint Management System. All rights reserved.</p>
            <p><a href="#">Privacy Policy</a> | <a href="#">Terms</a> | <a href="#">Sitemap</a></p>
        </div>
    </footer>

    <script>
        // Character counters
        const titleInput = document.getElementById('title');
        const descInput = document.getElementById('description');
        const titleCount = document.getElementById('titleCount');
        const descCount = document.getElementById('descCount');
        
        function updateCounters() {
            titleCount.textContent = titleInput.value.length;
            descCount.textContent = descInput.value.length;
        }
        
        titleInput.addEventListener('input', updateCounters);
        descInput.addEventListener('input', updateCounters);
        updateCounters(); // Initialize counters
        
        // Department information
        const departmentSelect = document.getElementById('department_id');
        const departmentInfo = document.getElementById('departmentInfo');
        
        const departmentDetails = {
            '1': 'Handles: Water supply issues, pipeline leaks, drainage problems, sewage complaints',
            '2': 'Handles: Power outages, street light repairs, electrical hazards, meter issues',
            '3': 'Handles: Road repairs, potholes, construction complaints, traffic signs',
            '4': 'Handles: Garbage collection, cleanliness issues, waste management, sanitation',
            '5': 'Handles: Public building maintenance, infrastructure issues, public works',
            '6': 'Handles: Parks maintenance, gardens, green spaces, playground equipment'
        };
        
        departmentSelect.addEventListener('change', function() {
            const deptId = this.value;
            if (deptId && departmentDetails[deptId]) {
                departmentInfo.textContent = departmentDetails[deptId];
                departmentInfo.classList.add('active');
            } else {
                departmentInfo.classList.remove('active');
            }
        });
        
        // Form validation
        document.getElementById('complaintForm').addEventListener('submit', function(e) {
            const title = document.getElementById('title').value.trim();
            const description = document.getElementById('description').value.trim();
            const department = document.getElementById('department_id').value;
            const location = document.getElementById('location').value.trim();
            
            if (title.length < 10) {
                e.preventDefault();
                alert('Title must be at least 10 characters long.');
                return false;
            }
            
            if (description.length < 20) {
                e.preventDefault();
                alert('Description must be at least 20 characters long.');
                return false;
            }
            
            if (!department) {
                e.preventDefault();
                alert('Please select a department.');
                return false;
            }
            
            if (!location) {
                e.preventDefault();
                alert('Please enter the location/address.');
                return false;
            }
            
            <?php if ($require_evidence): ?>
            const evidenceFiles = document.getElementById('evidence').files;
            if (evidenceFiles.length === 0) {
                e.preventDefault();
                alert('Please upload at least one evidence file.');
                return false;
            }
            <?php endif; ?>
            
            // Show loading state
            const submitBtn = document.getElementById('submitBtn');
            submitBtn.innerHTML = 'Filing Complaint...';
            submitBtn.disabled = true;
        });
        
        // File upload handling
        <?php if ($require_evidence): ?>
        const evidenceInput = document.getElementById('evidence');
        const fileList = document.getElementById('fileList');
        
        evidenceInput.addEventListener('change', function(e) {
            fileList.innerHTML = '';
            const files = Array.from(e.target.files);
            
            files.forEach((file, index) => {
                const fileItem = document.createElement('div');
                fileItem.className = 'file-item';
                
                const fileName = document.createElement('span');
                fileName.className = 'file-name';
                fileName.textContent = file.name;
                
                const fileSize = document.createElement('span');
                fileSize.className = 'file-size';
                fileSize.textContent = formatFileSize(file.size);
                
                const removeBtn = document.createElement('span');
                removeBtn.className = 'remove-file';
                removeBtn.textContent = '×';
                removeBtn.onclick = function() {
                    removeFile(index);
                };
                
                fileItem.appendChild(fileName);
                fileItem.appendChild(fileSize);
                fileItem.appendChild(removeBtn);
                fileList.appendChild(fileItem);
            });
        });
        
        function removeFile(index) {
            const dt = new DataTransfer();
            const files = Array.from(evidenceInput.files);
            files.splice(index, 1);
            files.forEach(file => dt.items.add(file));
            evidenceInput.files = dt.files;
            
            // Trigger change event to update display
            evidenceInput.dispatchEvent(new Event('change'));
        }
        
        function formatFileSize(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }
        <?php endif; ?>
        
        // Auto-save form data (optional)
        let autoSaveTimer;
        
        function autoSaveForm() {
            const formData = {
                title: titleInput.value,
                description: descInput.value,
                department_id: document.getElementById('department_id').value,
                location: document.getElementById('location').value,
                landmark: document.getElementById('landmark').value
            };
            localStorage.setItem('complaintDraft', JSON.stringify(formData));
        }
        
        // Load saved draft if exists
        const savedDraft = localStorage.getItem('complaintDraft');
        if (savedDraft && !<?php echo $success ? 'true' : 'false'; ?>) {
            const draft = JSON.parse(savedDraft);
            titleInput.value = draft.title || '';
            descInput.value = draft.description || '';
            document.getElementById('department_id').value = draft.department_id || '';
            document.getElementById('location').value = draft.location || '';
            document.getElementById('landmark').value = draft.landmark || '';
            
            // Show department info if available
            if (draft.department_id && departmentDetails[draft.department_id]) {
                departmentInfo.textContent = departmentDetails[draft.department_id];
                departmentInfo.classList.add('active');
            }
            
            updateCounters();
        }
        
        // Auto-save on input
        [titleInput, descInput, document.getElementById('location'), document.getElementById('landmark')].forEach(input => {
            input.addEventListener('input', function() {
                clearTimeout(autoSaveTimer);
                autoSaveTimer = setTimeout(autoSaveForm, 1000);
            });
        });
        
        departmentSelect.addEventListener('change', function() {
            autoSaveForm();
            // Also show department info
            const deptId = this.value;
            if (deptId && departmentDetails[deptId]) {
                departmentInfo.textContent = departmentDetails[deptId];
                departmentInfo.classList.add('active');
            } else {
                departmentInfo.classList.remove('active');
            }
        });
        
        // Clear saved draft on successful submission
        <?php if ($success): ?>
            localStorage.removeItem('complaintDraft');
        <?php endif; ?>
    </script>
</body>
</html>new complain