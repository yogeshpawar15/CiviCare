<?php
// civicare/user/register.php
require_once '../config/database.php';
require_once '../config/functions.php';

$conn = getDatabaseConnection();

$error = '';
$success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize and validate input
    $username = sanitizeInput($_POST['username']);
    $email = sanitizeInput($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $full_name = sanitizeInput($_POST['full_name']);
    $phone = sanitizeInput($_POST['phone']);
    $address = sanitizeInput($_POST['address']);
    
    // Basic validation
    if (empty($username) || empty($email) || empty($password) || empty($full_name)) {
        $error = "Please fill in all required fields.";
    } elseif (!validateEmail($email)) {
        $error = "Please enter a valid email address.";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match.";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters long.";
    } elseif (usernameExists($conn, $username)) {
        $error = "Username already exists. Please choose another.";
    } elseif (emailExists($conn, $email)) {
        $error = "Email already registered. Please use another email.";
    } else {
        // Hash the password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        // Default role is 'citizen'
        $role = 'citizen';
        
        // Insert user into database
        $sql = "INSERT INTO users (username, email, password, full_name, phone, address, role) 
                VALUES (?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssssss", $username, $email, $hashed_password, $full_name, $phone, $address, $role);
        
        if ($stmt->execute()) {
            $success = "Registration successful! You can now login.";
            
            // Clear form fields
            $username = $email = $full_name = $phone = $address = '';
            
            // Create welcome notification for the new user
            $user_id = $stmt->insert_id;
            createNotification($conn, $user_id, 'Welcome to Civicare!', 
                'Thank you for registering with Civicare Municipal Complaint System. You can now file and track complaints.');
                
            // Auto-login after registration (optional)
            $_SESSION['user_id'] = $user_id;
            $_SESSION['user_name'] = $full_name;
            $_SESSION['user_role'] = $role;
            $_SESSION['username'] = $username;
            
            // Redirect to citizen dashboard
            header('Location: ../user/dashboard.php');
            exit();
            
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
    <title>Register - Civicare</title>
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
            max-width: 550px;
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

        input, textarea {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 6px;
            font-size: 15px;
            transition: all 0.3s;
            font-family: inherit;
        }

        input:focus, textarea:focus {
            outline: none;
            border-color: #2a5298;
            box-shadow: 0 0 0 3px rgba(42, 82, 152, 0.1);
        }

        textarea {
            min-height: 80px;
            resize: vertical;
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

        /* ===== LINKS ===== */
        .auth-links {
            text-align: center;
            margin-top: 24px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }

        .auth-links p {
            color: #666;
            font-size: 14px;
        }

        .auth-links a {
            color: #ff6b35;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s;
        }

        .auth-links a:hover {
            color: #1e3c72;
            padding-left: 5px;
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

        /* ===== FORM ROW ===== */
        .form-row {
            display: flex;
            gap: 15px;
        }

        .form-row .form-group {
            flex: 1;
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
                <a href="../index.php" class="logo">
                    <i class="fas fa-city"></i>
                </a>
                <div class="logo-text">
                    <h1>CIVICARE</h1>
                </div>
            </div>
        </div>
    </header>

    <!-- AUTH CARD -->
    <div class="container">
        <div class="auth-card">
            <div class="auth-header">
                <h1><i class="fas fa-user-plus"></i> Create Citizen Account</h1>
                <p>Join Civicare to file and track municipal complaints</p>
            </div>
            
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
                        <?php echo htmlspecialchars($success); ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="">
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
                                   required placeholder="Enter your email">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="password" class="required">Password</label>
                            <input type="password" id="password" name="password" required minlength="6" placeholder="Create a password">
                            <div class="password-info">Minimum 6 characters</div>
                        </div>
                        
                        <div class="form-group">
                            <label for="confirm_password" class="required">Confirm Password</label>
                            <input type="password" id="confirm_password" name="confirm_password" required placeholder="Confirm your password">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="full_name" class="required">Full Name</label>
                        <input type="text" id="full_name" name="full_name" 
                               value="<?php echo isset($full_name) ? htmlspecialchars($full_name) : ''; ?>" 
                               required placeholder="Enter your full name">
                    </div>
                    
                    <div class="form-group">
                        <label for="phone">Phone Number</label>
                        <input type="tel" id="phone" name="phone" 
                               value="<?php echo isset($phone) ? htmlspecialchars($phone) : ''; ?>"
                               placeholder="Enter phone number (optional)">
                    </div>
                    
                    <div class="form-group">
                        <label for="address">Address</label>
                        <textarea id="address" name="address" rows="3" placeholder="Enter your address (optional)"><?php echo isset($address) ? htmlspecialchars($address) : ''; ?></textarea>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-user-plus"></i> Create Account
                    </button>
                </form>
                
                <div class="auth-links">
                    <p>Already have an account? <a href="login.php">Login here</a></p>
                </div>
                
                <div class="back-link">
                    <a href="../index.php"><i class="fas fa-arrow-left"></i> Back to Homepage</a>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Client-side validation for password match
        document.querySelector('form').addEventListener('submit', function(e) {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            if (password !== confirmPassword) {
                e.preventDefault();
                alert('Passwords do not match!');
                return false;
            }
            
            if (password.length < 6) {
                e.preventDefault();
                alert('Password must be at least 6 characters long!');
                return false;
            }
        });
        
        // Real-time password match validation
        const passwordField = document.getElementById('password');
        const confirmField = document.getElementById('confirm_password');
        
        function validatePasswordMatch() {
            if (passwordField.value !== confirmField.value) {
                confirmField.style.borderColor = '#e74c3c';
            } else if (confirmField.value.length > 0) {
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
