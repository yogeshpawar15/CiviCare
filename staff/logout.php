<?php
// civicare/staff/logout.php
session_start();

// Unset all session variables
$_SESSION = [];

// Destroy session
session_destroy();

// Delete session cookie for extra security
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logging Out - Civicare</title>
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
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
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

        /* ===== LOGOUT CARD ===== */
        .logout-card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
            max-width: 450px;
            margin: 0 auto;
            overflow: hidden;
        }

        .logout-header {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            color: white;
            padding: 30px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .logout-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1200 600"><rect fill="%23fff" opacity="0.05" width="1200" height="600"/></svg>');
            opacity: 0.1;
        }

        .logout-header h1 {
            font-size: 26px;
            margin-bottom: 8px;
            position: relative;
            z-index: 1;
        }

        .logout-header p {
            font-size: 14px;
            opacity: 0.95;
            position: relative;
            z-index: 1;
        }

        .logout-body {
            padding: 40px 30px;
            text-align: center;
        }

        .message {
            font-size: 18px;
            color: #1e3c72;
            margin-bottom: 10px;
            font-weight: 600;
        }

        .sub-message {
            font-size: 14px;
            color: #666;
            margin-bottom: 30px;
        }

        .loader {
            width: 50px;
            height: 50px;
            border: 4px solid #e0e0e0;
            border-top: 4px solid #ff6b35;
            border-radius: 50%;
            margin: 0 auto;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .footer-text {
            margin-top: 24px;
            font-size: 13px;
            color: #888;
        }

        .redirect-link {
            display: inline-block;
            margin-top: 20px;
            padding: 12px 24px;
            background: #ff6b35;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 600;
            transition: all 0.3s;
        }

        .redirect-link:hover {
            background: #e55a24;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(255, 107, 53, 0.3);
        }

        @media (max-width: 480px) {
            .logout-body {
                padding: 30px 20px;
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
                    <p>Staff Portal</p>
                </div>
            </div>
        </div>
    </header>

    <!-- LOGOUT CARD -->
    <div class="container">
        <div class="logout-card">
            <div class="logout-header">
                <h1><i class="fas fa-sign-out-alt"></i> Civicare</h1>
                <p>Complaint Management System</p>
            </div>

            <div class="logout-body">
                <div class="message"><i class="fas fa-check-circle" style="color: #28a745;"></i> Logging you out...</div>
                <div class="sub-message">You are being securely signed out. Please wait.</div>
                <div class="loader"></div>
                <div class="footer-text">Redirecting to login page</div>
                <a href="login.php" class="redirect-link">
                    <i class="fas fa-arrow-right"></i> Click here if not redirected
                </a>
            </div>
        </div>
    </div>

    <script>
        // Redirect to staff login after 2 seconds
        setTimeout(function() {
            window.location.href = "login.php";
        }, 2000);
    </script>
</body>
</html>
