<?php
// civicare/admin/login.php

// Start session FIRST
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Debug: Log what's happening
error_log("=== LOGIN PAGE LOADED ===");
error_log("Session ID: " . session_id());
error_log("POST Data: " . print_r($_POST, true));

require_once '../config/database.php';
require_once '../config/functions.php';

$conn = getDatabaseConnection();

// Check connection
if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}

$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitizeInput($_POST['username']);
    $password = $_POST['password'];
    
    error_log("Login attempt: username=$username");
    
    if (empty($username) || empty($password)) {
        $error = "Please enter both username and password.";
    } else {
        // First, check if user exists at all (any role)
        $check_sql = "SELECT id, username, password, full_name, role, is_active FROM users WHERE username = ?";
        $check_stmt = $conn->prepare($check_sql);
        
        if ($check_stmt === false) {
            $error = "Database error: " . $conn->error;
            error_log("SQL Prepare failed: " . $conn->error);
        } else {
            $check_stmt->bind_param("s", $username);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            
            if ($check_result->num_rows > 0) {
                $db_user = $check_result->fetch_assoc();
                error_log("User found: " . print_r($db_user, true));
                
                // Test password
                $password_ok = password_verify($password, $db_user['password']);
                error_log("Password verify result: " . ($password_ok ? "TRUE" : "FALSE"));
                
                if ($password_ok) {
                    // Check if user can login here (has correct role and is active)
                    if (in_array($db_user['role'], ['admin', 'super_admin']) && $db_user['is_active']) {
                        error_log("User authorized for admin panel");
                        
                        // Set session variables
                        $_SESSION['user_id'] = $db_user['id'];
                        $_SESSION['user_name'] = $db_user['full_name'];
                        $_SESSION['user_role'] = $db_user['role'];
                        $_SESSION['username'] = $db_user['username'];
                        
                        error_log("Session set, redirecting to index.php");
                        error_log("Session data: " . print_r($_SESSION, true));
                        
                        // SIMPLE REDIRECT - No debug output
                        header("Location: index.php");
                        exit();
                    } else {
                        error_log("User not authorized: role=" . $db_user['role'] . ", is_active=" . $db_user['is_active']);
                        if (!in_array($db_user['role'], ['admin', 'staff', 'super_admin'])) {
                            $error = "This account type cannot access admin panel.";
                        } else if (!$db_user['is_active']) {
                            $error = "Account is deactivated. Contact administrator.";
                        }
                    }
                } else {
                    $error = "Invalid username or password.";
                    error_log("Password incorrect for user: $username");
                }
            } else {
                $error = "Invalid username or password.";
                error_log("User not found: $username");
            }
            $check_stmt->close();
        }
    }
}

// If we get here, login failed or no POST
error_log("Login failed or no POST, showing login form");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - Civicare</title>
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
            max-width: 450px;
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

        input {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 6px;
            font-size: 15px;
            transition: all 0.3s;
            font-family: inherit;
        }

        input:focus {
            outline: none;
            border-color: #2a5298;
            box-shadow: 0 0 0 3px rgba(42, 82, 152, 0.1);
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

        /* ===== LINKS ===== */
        .auth-links {
            display: flex;
            justify-content: space-between;
            margin-top: 24px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }

        .auth-links a {
            color: #ff6b35;
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.3s;
        }

        .auth-links a:hover {
            color: #1e3c72;
        }

        .role-switch {
            text-align: center;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #eee;
            font-size: 13px;
            color: #666;
        }

        .role-switch a {
            color: #1e3c72;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s;
        }

        .role-switch a:hover {
            color: #ff6b35;
            text-decoration: underline;
        }

        /* ===== RESPONSIVE ===== */
        @media (max-width: 768px) {
            .header-content {
                flex-direction: column;
                gap: 15px;
            }

            .auth-body {
                padding: 24px;
            }

            .auth-links {
                flex-direction: column;
                gap: 12px;
                align-items: center;
                text-align: center;
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
                <a href="../index.php" class="logo">
                    <i class="fas fa-city"></i>
                </a>
                <div class="logo-text">
                    <h1>CIVICARE</h1>
                    <p>Admin Portal</p>
                </div>
            </div>
        </div>
    </header>

    <!-- AUTH CARD -->
    <div class="container">
        <div class="auth-card">
            <div class="auth-header">
                <h1><i class="fas fa-user-shield"></i> Admin Login</h1>
                <p>Civicare Complaint Management System</p>
            </div>
            
            <div class="auth-body">
                <?php if ($error): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-circle"></i>
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="">
                    <div class="form-group">
                        <label for="username">Username</label>
                        <input type="text" id="username" name="username" required 
                               placeholder="Enter username">
                    </div>
                    
                    <div class="form-group">
                        <label for="password">Password</label>
                        <input type="password" id="password" name="password" required 
                               placeholder="Enter password">
                    </div>
                    
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-sign-in-alt"></i> Login
                    </button>
                </form>
                
                <div class="auth-links">
                    <a href="../index.php"><i class="fas fa-arrow-left"></i> Back to Homepage</a>
                    <a href="forgot_password.php"><i class="fas fa-key"></i> Forgot Password?</a>
                </div>
                
                <div class="role-switch">
                    Are you a staff member? <a href="../staff/login.php">Staff Login</a>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        document.querySelector('form').addEventListener('submit', function(e) {
            const username = document.getElementById('username').value.trim();
            const password = document.getElementById('password').value.trim();
            
            if (!username || !password) {
                e.preventDefault();
                alert('Please enter both username and password.');
                return false;
            }
        });
    </script>
</body>
</html>

<?php 
$conn->close(); 
?>