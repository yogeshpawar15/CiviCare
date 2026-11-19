<?php
// civicare/admin/forgot_password.php

// Start session FIRST
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../config/database.php';
require_once '../config/functions.php';

$conn = getDatabaseConnection();

// Check connection
if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}

$error = '';
$success = '';
$step = isset($_GET['step']) ? $_GET['step'] : 1;
$user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;

// Handle Step 1: Verify email/username
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_email'])) {
    $username = sanitizeInput($_POST['username']);
    $email = sanitizeInput($_POST['email']);
    
    if (empty($username) && empty($email)) {
        $error = "Please enter your username or email address.";
    } else {
        // Check if user exists and is an admin
        $sql = "SELECT id, username, email, full_name, role FROM users 
                WHERE (username = ? OR email = ?) 
                AND role IN ('admin', 'super_admin')";
        $stmt = $conn->prepare($sql);
        
        if ($stmt === false) {
            $error = "Database error: " . $conn->error;
        } else {
            $search_value = !empty($username) ? $username : $email;
            $stmt->bind_param("ss", $search_value, $search_value);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $user = $result->fetch_assoc();
                
                if (empty($user['email'])) {
                    $error = "No email address found for this account. Please contact the system administrator.";
                } else {
                    // Move to step 2 - show password reset form
                    $step = 2;
                    $user_id = $user['id'];
                    
                    // Log the activity
                    logActivity("Password reset initiated for admin user: " . $user['username']);
                }
            } else {
                $error = "No admin account found with that username or email.";
            }
            $stmt->close();
        }
    }
}

// Handle Step 2: Reset password
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_password'])) {
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    $user_id = intval($_POST['user_id']);
    
    if (empty($new_password) || empty($confirm_password)) {
        $error = "Please enter and confirm your new password.";
    } elseif (strlen($new_password) < 6) {
        $error = "Password must be at least 6 characters long.";
    } elseif ($new_password !== $confirm_password) {
        $error = "Passwords do not match.";
    } else {
        // Hash the new password
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        
        // Update the password
        $sql = "UPDATE users SET password = ?, password_reset_required = 0 WHERE id = ?";
        $stmt = $conn->prepare($sql);
        
        if ($stmt === false) {
            $error = "Database error: " . $conn->error;
        } else {
            $stmt->bind_param("si", $hashed_password, $user_id);
            
            if ($stmt->execute()) {
                $success = "Your password has been reset successfully!";
                $step = 3;
                
                // Log the activity
                logActivity("Password reset completed for user ID: $user_id");
            } else {
                $error = "Failed to reset password. Please try again.";
            }
            $stmt->close();
        }
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Forgot Password - Civicare</title>
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

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-success i {
            color: #28a745;
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

        .back-link {
            text-align: center;
            margin-top: 16px;
        }

        .back-link a {
            color: #666;
            text-decoration: none;
            font-size: 13px;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .back-link a:hover {
            color: #1e3c72;
        }

        .help-text {
            font-size: 12px;
            color: #666;
            margin-top: 8px;
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
            <?php if ($step == 1): ?>
            <div class="auth-header">
                <h1><i class="fas fa-key"></i> Forgot Password</h1>
                <p>Verify your admin account</p>
            </div>
            <?php elseif ($step == 2): ?>
            <div class="auth-header">
                <h1><i class="fas fa-lock"></i> Reset Password</h1>
                <p>Enter your new password</p>
            </div>
            <?php else: ?>
            <div class="auth-header">
                <h1><i class="fas fa-check-circle"></i> Success!</h1>
                <p>Password has been reset</p>
            </div>
            <?php endif; ?>
            
            <div class="auth-body">
                <?php if ($error): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-circle"></i>
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i>
                        <?php echo $success; ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($step == 1): ?>
                <!-- Step 1: Verify Email/Username -->
                <form method="POST" action="">
                    <div class="form-group">
                        <label for="username">Username</label>
                        <input type="text" id="username" name="username" 
                               placeholder="Enter your username">
                        <p class="help-text">Enter your username OR your email address below</p>
                    </div>
                    
                    <div class="form-group">
                        <label for="email">Email Address</label>
                        <input type="email" id="email" name="email" 
                               placeholder="Enter your email address">
                    </div>
                    
                    <button type="submit" name="verify_email" class="btn btn-primary">
                        <i class="fas fa-search"></i> Verify Account
                    </button>
                </form>
                <?php elseif ($step == 2): ?>
                <!-- Step 2: Reset Password -->
                <form method="POST" action="">
                    <input type="hidden" name="user_id" value="<?php echo $user_id; ?>">
                    
                    <div class="form-group">
                        <label for="new_password">New Password</label>
                        <input type="password" id="new_password" name="new_password" required 
                               placeholder="Enter new password (min 6 characters)">
                    </div>
                    
                    <div class="form-group">
                        <label for="confirm_password">Confirm New Password</label>
                        <input type="password" id="confirm_password" name="confirm_password" required 
                               placeholder="Confirm new password">
                    </div>
                    
                    <button type="submit" name="reset_password" class="btn btn-primary">
                        <i fa-save></i> Reset Password
                    </button>
                </form>
                <?php else: ?>
                <!-- Step 3: Success -->
                <div style="text-align: center;">
                    <p style="margin-bottom: 20px;">You can now login with your new password.</p>
                    <a href="login.php" class="btn btn-primary">
                        <i class="fas fa-sign-in-alt"></i> Go to Login
                    </a>
                </div>
                <?php endif; ?>
                
                <div class="auth-links">
                    <a href="login.php"><i class="fas fa-arrow-left"></i> Back to Login</a>
                    <a href="../index.php"><i class="fas fa-home"></i> Homepage</a>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        document.querySelector('form')?.addEventListener('submit', function(e) {
            const username = document.getElementById('username')?.value.trim();
            const email = document.getElementById('email')?.value.trim();
            const newPassword = document.getElementById('new_password')?.value;
            const confirmPassword = document.getElementById('confirm_password')?.value;
            
            if (username !== undefined && email !== undefined) {
                // Step 1 validation
                if (!username && !email) {
                    e.preventDefault();
                    alert('Please enter either your username or email address.');
                    return false;
                }
            }
            
            if (newPassword !== undefined && confirmPassword !== undefined) {
                // Step 2 validation
                if (newPassword.length < 6) {
                    e.preventDefault();
                    alert('Password must be at least 6 characters long.');
                    return false;
                }
                
                if (newPassword !== confirmPassword) {
                    e.preventDefault();
                    alert('Passwords do not match.');
                    return false;
                }
            }
        });
    </script>
</body>
</html>