<?php
// civicare/staff/track_complaint.php
require_once '../config/database.php';
require_once '../config/functions.php';

// Check if user is logged in and is staff
if (!isLoggedIn() || !hasRole('staff')) {
    header('Location: login.php');
    exit();
}


$conn = getDatabaseConnection();

$error = '';
$complaint = null;
$updates = [];

// Get tracking ID from URL or POST
$tracking_id = '';
if (isset($_GET['tracking_id'])) {
    $tracking_id = sanitizeInput($_GET['tracking_id']);
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tracking_id'])) {
    $tracking_id = sanitizeInput($_POST['tracking_id']);
}

if (!empty($tracking_id)) {
    // Get complaint details (staff can only see complaints from their department)
    $sql = "SELECT c.*, u.full_name as user_name, u.email as user_email, u.phone as user_phone,
                   d.name as department_name, staff.full_name as staff_name
            FROM complaints c
            JOIN users u ON c.user_id = u.id
            JOIN departments d ON c.department_id = d.id
            LEFT JOIN users staff ON c.assigned_to = staff.id
            WHERE c.tracking_id = ? AND c.department_id = ?";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("si", $tracking_id, $_SESSION['department_id']);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $result->num_rows === 1) {
        $complaint = $result->fetch_assoc();

        // Get complaint timeline/updates
        $sql_updates = "SELECT cu.*, u.full_name as updated_by_name, u.role
                        FROM complaint_updates cu
                        LEFT JOIN users u ON cu.user_id = u.id
                        WHERE cu.complaint_id = ?
                        ORDER BY cu.created_at DESC";
        $stmt_up = $conn->prepare($sql_updates);
        $stmt_up->bind_param("i", $complaint['id']);
        $stmt_up->execute();
        $updates_result = $stmt_up->get_result();

        while ($row = $updates_result->fetch_assoc()) {
            $updates[] = $row;
        }
    } else {
        $error = "No complaint records found for ID: <strong>" . htmlspecialchars($tracking_id) . "</strong> in your department.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Track Complaint - Civicare Staff</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f8f9fa;
            color: #333;
            min-height: 100vh;
        }
        
        .header { 
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white; 
            padding: 1rem 0; 
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .container { 
            width: 90%; 
            max-width: 1100px; 
            margin: 0 auto; 
        }
        
        .navbar { 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
        }
        
        .logo { 
            font-size: 1.5rem; 
            font-weight: bold; 
            color: white; 
            text-decoration: none; 
        }
        
        .nav-links a { 
            color: white; 
            text-decoration: none; 
            margin-left: 15px; 
            font-size: 0.9rem; 
            padding: 0.5rem 1rem; 
            border-radius: 4px; 
        }
        
        .nav-links a:hover { 
            background: rgba(255,255,255,0.2); 
        }

        /* Search Section */
        .search-container {
            background: white;
            padding: 2rem;
            border-radius: 8px;
            margin: 2rem 0;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            text-align: center;
        }
        
        .search-container h2 { 
            margin-bottom: 1rem; 
            color: #333;
        }
        
        .search-box { 
            display: flex; 
            max-width: 500px; 
            margin: 0 auto; 
            gap: 10px; 
        }
        
        input[type="text"] { 
            flex: 1; 
            padding: 10px 15px; 
            border: 1px solid #ddd; 
            border-radius: 4px; 
            font-size: 1rem; 
        }
        
        input[type="text"]:focus { 
            border-color: #667eea; 
            outline: none; 
        }
        
        .btn-search { 
            background: #667eea; 
            color: white; 
            border: none; 
            padding: 10px 20px; 
            border-radius: 4px; 
            font-weight: bold; 
            cursor: pointer; 
        }
        
        .btn-search:hover { 
            background: #5a6fd6; 
        }

        /* Result Cards */
        .complaint-main { 
            background: white; 
            border-radius: 8px; 
            box-shadow: 0 2px 8px rgba(0,0,0,0.1); 
            margin-bottom: 2rem; 
        }
        
        .comp-header { 
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white; 
            padding: 1.5rem; 
            display: flex; 
            justify-content: space-between; 
            align-items: flex-start; 
            gap: 1rem;
            border-radius: 8px 8px 0 0;
        }
        
        .comp-title h1 { 
            font-size: 1.5rem; 
            margin: 5px 0; 
        }
        
        .badge-row { 
            display: flex; 
            align-items: center; 
            gap: 0.75rem; 
            flex-wrap: wrap; 
        }
        
        .status-pill { 
            padding: 6px 14px; 
            border-radius: 20px; 
            font-size: 0.8rem; 
            font-weight: 600; 
            text-transform: uppercase; 
            color: white;
        }
        
        .status-pending { background: #f39c12; }
        .status-validated { background: #3498db; }
        .status-rejected { background: #e74c3c; }
        .status-assigned { background: #9b59b6; }
        .status-in_progress { background: #1abc9c; }
        .status-resolved { background: #27ae60; }
        .status-escalated { background: #e67e22; }
        .status-closed { background: #7f8c8d; }

        .section-title {
            padding: 0 1.5rem; 
            margin-top: 1.5rem; 
            font-size: 1rem; 
            color: #333; 
            font-weight: 600;
        }
        
        .details-grid { 
            display: grid; 
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); 
            gap: 15px; 
            padding: 1.5rem; 
        }
        
        .info-card {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 6px;
            border-left: 3px solid #667eea;
        }
        
        .info-label { 
            font-size: 0.75rem; 
            color: #7f8c8d; 
            text-transform: uppercase; 
            margin-bottom: 5px; 
        }
        
        .info-value { 
            font-weight: 600; 
            color: #333; 
            font-size: 1rem; 
        }

        .desc-section { 
            padding: 0 1.5rem 1.5rem; 
        }
        
        .desc-content { 
            background: #f8f9fa;
            border: 1px solid #eee; 
            padding: 1.5rem; 
            border-radius: 6px; 
            line-height: 1.6; 
            color: #444; 
        }

        .rating-box { 
            background: #fff9e6; 
            border: 1px solid #eee; 
            padding: 1.5rem; 
            border-radius: 6px; 
            border-left: 4px solid #f39c12; 
            line-height: 1.6; 
            color: #444;
        }
        
        .rating-stars { 
            color: #f39c12; 
            font-size: 1.2rem; 
            letter-spacing: 2px; 
        }
        
        .rating-note { 
            margin-top: 0.5rem; 
            color: #666; 
            font-size: 0.9rem; 
        }

        /* Timeline */
        .updates-section { 
            padding: 1.5rem; 
            border-top: 1px solid #eee; 
            background: #f8f9fa;
            border-radius: 0 0 8px 8px;
        }
        
        .timeline-box { 
            position: relative; 
            padding-left: 30px; 
            margin-top: 15px; 
        }
        
        .timeline-box::before { 
            content: ''; 
            position: absolute; 
            left: 0; 
            top: 0; 
            bottom: 0; 
            width: 2px; 
            background: #667eea;
        }
        
        .update-item { 
            position: relative; 
            margin-bottom: 20px; 
        }
        
        .update-item::after { 
            content: ''; 
            position: absolute; 
            left: -34px; 
            top: 5px; 
            width: 10px; 
            height: 10px; 
            border-radius: 50%; 
            background: #667eea; 
            border: 2px solid white; 
        }
        
        .update-date { 
            font-size: 0.8rem; 
            color: #7f8c8d; 
            margin-bottom: 4px; 
        }
        
        .update-text { 
            background: white; 
            padding: 12px; 
            border-radius: 6px; 
            border: 1px solid #eee; 
            font-size: 0.95rem;
        }

        .alert { 
            background: #e74c3c; 
            color: white; 
            padding: 1rem; 
            border-radius: 6px; 
            margin: 1rem auto; 
            max-width: 500px; 
        }

        @media (max-width: 768px) {
            .comp-header { 
                flex-direction: column; 
                gap: 15px; 
            }
            .search-box { 
                flex-direction: column; 
            }
        }

        /* ===== FOOTER ===== */
        .footer {
            background: #1a1a1a;
            color: #fff;
            padding: 25px 20px 10px;
            margin-top: 40px;
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
            font-size: 14px;
        }

        .footer-section ul li a:hover {
            color: #ff6b35;
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
        }

        .footer-social a:hover {
            background: #ff6b35;
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
    </style>
</head>
<body>

<div class="header">
    <div class="container">
        <nav class="navbar">
            <a href="dashboard.php" class="logo">Civicare Staff</a>
            <div class="nav-links">
                <a href="dashboard.php">Dashboard</a>
                <a href="complaints.php">Complaints List</a>
                <a href="../logout.php" style="color: #ff7675;">Logout</a>
            </div>
        </nav>
    </div>
</div>

<div class="container">
    <div class="search-container">
        <h2>Track Complaint Status</h2>
        <form method="POST" class="search-box">
            <input type="text" name="tracking_id" placeholder="Enter Tracking ID (e.g. CIV24...)"
                   value="<?php echo htmlspecialchars($tracking_id); ?>" required>
            <button type="submit" class="btn-search">Search Record</button>
        </form>
    </div>

    <?php if ($error): ?>
        <div class="alert"><?php echo $error; ?></div>
    <?php endif; ?>

    <?php if ($complaint): ?>
        <div class="complaint-main">
            <div class="comp-header">
                <div class="comp-title">
                    <span style="font-size: 0.8rem; opacity: 0.8;">Case Reference: #<?php echo $complaint['tracking_id']; ?></span>
                    <h1><?php echo htmlspecialchars($complaint['title']); ?></h1>
                </div>
                <div class="badge-row">
                    <div class="status-pill status-<?php echo $complaint['status']; ?>">
                        <?php echo getStatusText($complaint['status']); ?>
                    </div>
                </div>
            </div>

            <div class="section-title">Case Details</div>
            <div class="details-grid">
                <div class="info-card">
                    <div class="info-label">Complainant</div>
                    <div class="info-value"><?php echo htmlspecialchars($complaint['user_name']); ?></div>
                    <div style="font-size: 0.8rem; color: #7f8c8d;"><?php echo htmlspecialchars($complaint['user_email']); ?></div>
                    <?php if ($complaint['user_phone']): ?>
                        <div style="font-size: 0.8rem; color: #7f8c8d;"><?php echo htmlspecialchars($complaint['user_phone']); ?></div>
                    <?php endif; ?>
                </div>
                <div class="info-card">
                    <div class="info-label">Department</div>
                    <div class="info-value"><?php echo htmlspecialchars($complaint['department_name']); ?></div>
                </div>
                <div class="info-card">
                    <div class="info-label">Assignee</div>
                    <div class="info-value">
                        <?php if ($complaint['staff_name']): ?>
                            <?php echo htmlspecialchars($complaint['staff_name']); ?>
                            <?php if ($complaint['assigned_to'] == $_SESSION['user_id']): ?>
                                <span style="color: #27ae60; font-size: 0.8rem;"> (You)</span>
                            <?php endif; ?>
                        <?php else: ?>
                            <span style="color: #95a5a6;">Not Assigned</span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="info-card">
                    <div class="info-label">Registration Date</div>
                    <div class="info-value"><?php echo date('M d, Y', strtotime($complaint['created_at'])); ?></div>
                </div>
                <div class="info-card">
                    <div class="info-label">Last Updated</div>
                    <div class="info-value"><?php echo date('M d, Y', strtotime($complaint['updated_at'])); ?></div>
                </div>
            </div>

            <div class="desc-section">
                <div class="section-title" style="padding: 0; margin-bottom: 8px;">Detailed Complaint Statement</div>
                <div class="desc-content">
                    <?php echo nl2br(htmlspecialchars($complaint['description'])); ?>
                </div>
            </div>

            <?php if ($complaint['location'] || $complaint['landmark']): ?>
            <div class="desc-section">
                <div class="section-title" style="padding: 0; margin-bottom: 8px;">Location Information</div>
                <div class="desc-content">
                    <?php if ($complaint['location']): ?>
                        <strong>Location:</strong> <?php echo htmlspecialchars($complaint['location']); ?><br>
                    <?php endif; ?>
                    <?php if ($complaint['landmark']): ?>
                        <strong>Landmark:</strong> <?php echo htmlspecialchars($complaint['landmark']); ?>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <?php if (in_array($complaint['status'], ['resolved', 'closed'], true)): ?>
            <div class="desc-section">
                <div class="section-title" style="padding: 0; margin-bottom: 8px;">Citizen Feedback</div>
                <div class="rating-box">
                    <?php if (!empty($complaint['citizen_rating'])): ?>
                        <div class="rating-stars">
                            <?php echo str_repeat('★', (int)$complaint['citizen_rating']) . str_repeat('☆', 5 - (int)$complaint['citizen_rating']); ?>
                            <span>(<?php echo (int)$complaint['citizen_rating']; ?>/5)</span>
                        </div>
                        <?php if (!empty($complaint['citizen_feedback'])): ?>
                            <div class="rating-note">
                                <?php echo nl2br(htmlspecialchars($complaint['citizen_feedback'])); ?>
                            </div>
                        <?php else: ?>
                            <div class="rating-note">No feedback comment provided.</div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="rating-note">Not rated yet.</div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <div class="updates-section">
                <h3 style="color: #333; margin-bottom: 15px;">Action Log / History</h3>
                <div class="timeline-box">
                    <?php if (empty($updates)): ?>
                        <p style="color: #7f8c8d; font-style: italic;">No history logs found for this case.</p>
                    <?php else: ?>
                        <?php foreach ($updates as $update): ?>
                            <div class="update-item">
                                <div class="update-date">
                                    <?php echo date('d M Y - h:i A', strtotime($update['created_at'])); ?>
                                </div>
                                <div class="update-text">
                                    <strong>Status: <?php echo getStatusText($update['new_status']); ?></strong><br>
                                    <span style="color: #555;"><?php echo nl2br(htmlspecialchars($update['remarks'])); ?></span>
                                    <div style="margin-top: 8px; font-size: 0.75rem; color: #3498db;">
                                        Updated by: <?php echo htmlspecialchars($update['updated_by_name'] ?? 'System'); ?>
                                        <?php if ($update['role']): ?>
                                            (<?php echo ucfirst($update['role']); ?>)
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>

                    <div class="update-item">
                        <div class="update-date"><?php echo date('d M Y', strtotime($complaint['created_at'])); ?></div>
                        <div class="update-text" style="background: #f0fdf4;">
                            <strong>Complaint Filed</strong><br>
                            The case was successfully registered in the system.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- FOOTER -->
<footer class="footer">
    <div class="footer-content">
        <div class="footer-section" style="flex: 2;">
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
                <li><a href="login.php">Staff Login</a></li>
                <li><a href="../user/login.php">Citizen Login</a></li>
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
</body>
</html>