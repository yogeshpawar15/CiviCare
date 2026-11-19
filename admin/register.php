<?php
// civicare/admin/register.php
require_once '../config/database.php';
require_once '../config/functions.php';

$conn = getDatabaseConnection();

$error = '';
$success = '';

// Get all departments for staff assignment
$departments = getAllDepartments($conn);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize and validate input
    $username = sanitizeInput($_POST['username']);
    $email = sanitizeInput($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $full_name = sanitizeInput($_POST['full_name']);
    $phone = sanitizeInput($_POST['phone']);
    $role = sanitizeInput($_POST['role']);
    $department_id = !empty($_POST['department_id']) ? (int)$_POST['department_id'] : null;
    
    // Basic validation
    if (empty($username) || empty($email) || empty($password) || empty($full_name) || empty($role)) {
        $error = "Please fill in all required fields.";
    } elseif (!validateEmail($email)) {
        $error = "Please enter a valid email address.";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match.";
    } elseif (strlen($password) < 8) {
        $error = "Password must be at least 8 characters long.";
    } elseif (!in_array($role, ['staff', 'admin', 'super_admin'])) {
        $error = "Invalid role selected.";
    } elseif (usernameExists($conn, $username)) {
        $error = "Username already exists. Please choose another.";
    } elseif (emailExists($conn, $email)) {
        $error = "Email already registered. Please use another email.";
    } else {
        // Hash the password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        // Determine if department_id should be set
        // Only staff members are assigned to departments
        if ($role !== 'staff') {
            $department_id = null;
        }
        
        // Insert user into database
        $sql = "INSERT INTO users (username, email, password, full_name, phone, role, department_id) 
                VALUES (?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssssssi", $username, $email, $hashed_password, $full_name, $phone, $role, $department_id);
        
        if ($stmt->execute()) {
            $user_id = $stmt->insert_id;
            $success = "New $role account created successfully!";
            
            // Create welcome notification for the new user
            $welcome_message = "Welcome to Civicare Staff Portal! Your $role account has been created.";
            if ($role === 'staff' && $department_id) {
                $dept_name = getDepartmentName($conn, $department_id);
                $welcome_message .= " You have been assigned to the $dept_name department.";
            }
            
            createNotification($conn, $user_id, 'Account Created', $welcome_message);
            
            // Clear form fields
            $username = $email = $full_name = $phone = '';
            $role = 'staff';
            $department_id = '';
            
        } else {
            $error = "Registration failed. Please try again. Error: " . $conn->error;
        }
        
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register Admin/Staff - Civicare</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #333;
            background: #f8f9fa;
        }

        /* ===== MAIN HEADER ===== */
        .main-header {
            background: white;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .header-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo-section {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .logo {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #1e3c72, #2a5298);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 28px;
            font-weight: bold;
            text-decoration: none;
        }

        .logo-text h1 {
            font-size: 24px;
            color: #1e3c72;
            margin: 0;
        }

        .logo-text p {
            font-size: 12px;
            color: #666;
            margin: 3px 0 0 0;
        }

        /* ===== NAVIGATION ===== */
        .nav-links {
            display: flex;
            gap: 10px;
        }

        .nav-links a {
            display: inline-block;
            padding: 10px 18px;
            color: #333;
            text-decoration: none;
            font-weight: 500;
            border-radius: 6px;
            transition: all 0.3s;
            font-size: 14px;
        }

        .nav-links a:hover {
            background: #f0f0f0;
            color: #1e3c72;
        }

        .nav-links a.active {
            background: #1e3c72;
            color: white;
        }

        /* ===== CONTAINER ===== */
        .container {
            width: 90%;
            max-width: 1200px;
            margin: 0 auto;
            padding: 40px 15px;
        }

        /* ===== AUTH CARD ===== */
        .auth-card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
            max-width: 650px;
            margin: 0 auto;
            overflow: hidden;
        }

        .auth-header {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            color: white;
            padding: 30px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .auth-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1200 600"><rect fill="%23fff" opacity="0.05" width="1200" height="600"/></svg>');
            opacity: 0.1;
        }

        .auth-header h1 {
            font-size: 26px;
            margin-bottom: 8px;
            position: relative;
            z-index: 1;
        }

        .auth-header p {
            font-size: 14px;
            opacity: 0.95;
            position: relative;
            z-index: 1;
        }

        .auth-body {
            padding: 30px;
        }

        /* ===== ALERTS ===== */
        .alert {
            padding: 14px 18px;
            border-radius: 6px;
            margin-bottom: 24px;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-success i {
            color: #28a745;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .alert-error i {
            color: #dc3545;
        }

        /* ===== FORM ===== */
        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin-bottom: 6px;
            font-weight: 600;
            color: #333;
            font-size: 14px;
        }

        .required::after {
            content: " *";
            color: #ff6b35;
        }

        input, select {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 6px;
            font-size: 15px;
            transition: all 0.3s;
            font-family: inherit;
        }

        input:focus, select:focus {
            outline: none;
            border-color: #2a5298;
            box-shadow: 0 0 0 3px rgba(42, 82, 152, 0.1);
        }

        .password-info {
            font-size: 12px;
            color: #666;
            margin-top: 4px;
        }

        /* ===== BUTTONS ===== */
        .btn {
            display: inline-block;
            padding: 14px 28px;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 600;
            transition: all 0.3s;
            border: none;
            cursor: pointer;
            font-size: 15px;
            width: 100%;
            text-align: center;
        }

        .btn-primary {
            background: #ff6b35;
            color: white;
        }

        .btn-primary:hover {
            background: #e55a24;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(255, 107, 53, 0.3);
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
            margin-top: 12px;
        }

        .btn-secondary:hover {
            background: #5a6268;
        }

        /* ===== ROLE INFO ===== */
        .role-info {
            background: #e8f4fd;
            padding: 16px 20px;
            border-radius: 6px;
            margin-bottom: 24px;
            border-left: 4px solid #1e3c72;
        }

        .role-info h4 {
            color: #1e3c72;
            margin-bottom: 10px;
            font-size: 15px;
        }

        .role-info ul {
            padding-left: 20px;
            color: #555;
            font-size: 13px;
        }

        .role-info li {
            margin-bottom: 4px;
        }

        /* ===== FORM ROW ===== */
        .form-row {
            display: flex;
            gap: 15px;
        }

        .form-row .form-group {
            flex: 1;
        }

        /* ===== DEPARTMENT FIELD ===== */
        .department-field {
            display: none;
        }

        /* ===== RESPONSIVE ===== */
        @media (max-width: 768px) {
            .header-content {
                flex-direction: column;
                gap: 15px;
            }

            .nav-links {
                flex-wrap: wrap;
                justify-content: center;
            }

            .auth-body {
                padding: 24px;
            }

            .form-row {
                flex-direction: column;
                gap: 0;
            }
        }

        @media (max-width: 480px) {
            .container {
                padding: 20px 10px;
            }

            .auth-header {
                padding: 24px;
            }

            .auth-header h1 {
                font-size: 22px;
            }

            .auth-body {
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <!-- MAIN HEADER -->
    <header class="main-header">
        <div class="header-content">
            <!-- LOGO -->
            <div class="logo-section">
                <a href="index.php" class="logo">
                    <i class="fas fa-city"></i>
                </a>
                <div class="logo-text">
                    <h1>CIVICARE</h1>
                    <p>Admin Portal</p>
                </div>
            </div>

            <!-- NAVIGATION -->
            <div class="nav-links">
                <a href="index.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                <a href="register.php" class="active"><i class="fas fa-user-plus"></i> Register Staff</a>
                <a href="users.php"><i class="fas fa-users"></i> Manage Users</a>
                <a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </div>
        </div>
    </header>

    <!-- AUTH CARD -->
    <div class="container">
        <div class="auth-card">
            <div class="auth-header">
                <h1><i class="fas fa-user-shield"></i> Register New Staff/Admin</h1>
                <p>Create new administrator or staff accounts</p>
            </div>
            
            <div class="auth-body">
                <div class="role-info">
                    <h4><i class="fas fa-info-circle"></i> Role Permissions:</h4>
                    <ul>
                        <li><strong>Staff:</strong> Can manage assigned department complaints</li>
                        <li><strong>Admin:</strong> Full system access, can manage all departments</li>
                        <li><strong>Super Admin:</strong> Highest privileges, can manage everything including other admins</li>
                    </ul>
                </div>
                
                <?php if ($error): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-circle"></i>
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i>
                        <?php echo htmlspecialchars($success); ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="" id="adminRegisterForm">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="username" class="required">Username</label>
                            <input type="text" id="username" name="username" 
                                   value="<?php echo isset($username) ? htmlspecialchars($username) : ''; ?>" 
                                   required maxlength="50" placeholder="Choose a username">
                        </div>
                        
                        <div class="form-group">
                            <label for="email" class="required">Email Address</label>
                            <input type="email" id="email" name="email" 
                                   value="<?php echo isset($email) ? htmlspecialchars($email) : ''; ?>" 
                                   required placeholder="Enter email address">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="password" class="required">Password</label>
                            <input type="password" id="password" name="password" required minlength="8" placeholder="Create a password">
                            <div class="password-info">Minimum 8 characters</div>
                        </div>
                        
                        <div class="form-group">
                            <label for="confirm_password" class="required">Confirm Password</label>
                            <input type="password" id="confirm_password" name="confirm_password" required placeholder="Confirm password">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="full_name" class="required">Full Name</label>
                        <input type="text" id="full_name" name="full_name" 
                               value="<?php echo isset($full_name) ? htmlspecialchars($full_name) : ''; ?>" 
                               required placeholder="Enter full name">
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="phone">Phone Number</label>
                            <input type="tel" id="phone" name="phone" 
                                   value="<?php echo isset($phone) ? htmlspecialchars($phone) : ''; ?>"
                                   placeholder="Enter phone number (optional)">
                        </div>
                        
                        <div class="form-group">
                            <label for="role" class="required">Role</label>
                            <select id="role" name="role" required>
                                <option value="staff" <?php echo (isset($role) && $role == 'staff') ? 'selected' : ''; ?>>Staff</option>
                                <option value="admin" <?php echo (isset($role) && $role == 'admin') ? 'selected' : ''; ?>>Admin</option>
                                <?php if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'super_admin'): ?>
                                    <option value="super_admin" <?php echo (isset($role) && $role == 'super_admin') ? 'selected' : ''; ?>>Super Admin</option>
                                <?php endif; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group department-field" id="departmentField">
                        <label for="department_id" class="required">Assign Department</label>
                        <select id="department_id" name="department_id">
                            <option value="">-- Select Department --</option>
                            <?php foreach ($departments as $dept): ?>
                                <option value="<?php echo $dept['id']; ?>" 
                                    <?php echo (isset($department_id) && $department_id == $dept['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($dept['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-user-plus"></i> Create Account
                    </button>
                    <a href="index.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Dashboard
                    </a>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Show/hide department field based on role selection
        const roleSelect = document.getElementById('role');
        const departmentField = document.getElementById('departmentField');
        const departmentSelect = document.getElementById('department_id');
        
        function toggleDepartmentField() {
            if (roleSelect.value === 'staff') {
                departmentField.style.display = 'block';
                departmentSelect.required = true;
            } else {
                departmentField.style.display = 'none';
                departmentSelect.required = false;
            }
        }
        
        roleSelect.addEventListener('change', toggleDepartmentField);
        toggleDepartmentField(); // Initial call
        
        // Password validation
        document.getElementById('adminRegisterForm').addEventListener('submit', function(e) {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            if (password !== confirmPassword) {
                e.preventDefault();
                alert('Passwords do not match!');
                return false;
            }
            
            if (password.length < 8) {
                e.preventDefault();
                alert('Password must be at least 8 characters long!');
                return false;
            }
            
            // If role is staff, department is required
            if (roleSelect.value === 'staff' && !departmentSelect.value) {
                e.preventDefault();
                alert('Please select a department for staff member.');
                return false;
            }
            
            // Show loading state
            const submitBtn = this.querySelector('button[type="submit"]');
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Creating Account...';
            submitBtn.disabled = true;
        });
        
        // Real-time password match validation
        const passwordField = document.getElementById('password');
        const confirmField = document.getElementById('confirm_password');
        
        function validatePasswordMatch() {
            if (confirmField.value && passwordField.value !== confirmField.value) {
                confirmField.style.borderColor = '#e74c3c';
            } else if (confirmField.value) {
                confirmField.style.borderColor = '#28a745';
            } else {
                confirmField.style.borderColor = '#e0e0e0';
            }
        }
        
        passwordField.addEventListener('input', validatePasswordMatch);
        confirmField.addEventListener('input', validatePasswordMatch);
    </script>
</body>
</html>

<?php $conn->close(); ?>
