<?php
// civicare/citizen/dashboard.php
require_once '../config/database.php';
require_once '../config/functions.php';

// Check if user is logged in and is citizen
if (!isLoggedIn() || !hasRole('citizen')) {
    header('Location: ../login.php');
    exit();
}

$conn = getDatabaseConnection();

// Get user's complaints
$user_id = $_SESSION['user_id'];
$user_complaints = [];
$sql = "SELECT c.*, d.name as department_name 
        FROM complaints c 
        JOIN departments d ON c.department_id = d.id 
        WHERE c.user_id = ? 
        ORDER BY c.created_at DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $user_complaints[] = $row;
    }
}

// Get user's notifications
$notifications = [];
$sql = "SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 5";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $notifications[] = $row;
    }
}

$unread_count = getUnreadNotificationsCount($conn, $user_id);

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Civicare</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            line-height: 1.6; 
            color: #333; 
            background: #f5f5f5;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }

        /* Header / Navbar (aligned with index.php styles) */
        .navbar { background: white; box-shadow: 0 2px 10px rgba(0,0,0,0.08); position: sticky; top: 0; z-index: 100; }
        .nav-container { max-width: 1200px; margin: 0 auto; padding: 12px 20px; display: flex; justify-content: space-between; align-items: center; }
        .logo { width: 60px; height: 60px; background: linear-gradient(135deg,#1e3c72,#2a5298); border-radius: 50%; display:flex; align-items:center; justify-content:center; color:white; font-size:24px; font-weight:700; text-decoration:none; }
        .logo + .logo-text, .brand { margin-left:12px; display:flex; align-items:center; gap:12px; }
        .logo-text h1 { font-size:20px; color:#1e3c72; margin:0; text-transform:uppercase; }
        .logo-text p { font-size:12px; color:#666; margin-top:3px; }

        .nav-links { display:flex; gap:8px; align-items:center; }
        .nav-links a { display:inline-block; padding:10px 14px; color:#333; text-decoration:none; border-radius:4px; font-weight:600; transition: all 0.18s; font-size:0.95rem; }
        .nav-links a:hover { background:#f0f0f0; color:#1e3c72; }
        .logout-btn { background: #ff6b35; color: white; padding:8px 14px; border-radius:6px; font-weight:700; text-decoration:none; }
        .logout-btn:hover { background:#e55a24; }

        /* Page container */
        .container { 
            width:90%; 
            max-width:1200px; 
            margin:20px auto; 
            padding:0 15px;
            flex: 1;
        }

        /* Welcome card */
        .welcome-section { background:white; padding:24px; border-radius:8px; margin-bottom:20px; box-shadow:0 4px 8px rgba(0,0,0,0.06); }
        .welcome-section h1 { color:#1e3c72; margin-bottom:8px; }
        .welcome-section p { color:#666; margin-bottom:14px; }
        .action-buttons { display:flex; gap:12px; flex-wrap:wrap; }

        /* Buttons (re-usable from index) */
        .btn { display:inline-block; padding:10px 20px; border-radius:6px; font-weight:700; text-decoration:none; border:none; cursor:pointer; transition: all 0.2s; font-size:0.95rem; }
        .btn-primary { background:#ff6b35; color:white; }
        .btn-primary:hover { background:#e55a24; transform:translateY(-2px); box-shadow:0 6px 14px rgba(229,90,36,0.12); }
        .btn-secondary { background:transparent; color:#1e3c72; border:2px solid #1e3c72; }
        .btn-secondary:hover { background:#1e3c72; color:#fff; }

        /* Main grid - now with single column for notifications */
        .dashboard-grid {
            max-width: 800px;
            margin: 0 auto;
        }

        .notifications-section { 
            background:white; 
            padding:20px; 
            border-radius:8px; 
            box-shadow:0 6px 18px rgba(0,0,0,0.06);
            margin-top: 20px;
        }

        .section-header { 
            display:flex; 
            justify-content:space-between; 
            align-items:center; 
            margin-bottom:12px; 
            padding-bottom:8px; 
            border-bottom:4px solid #ff6b35; 
        }
        .section-header h2 { color:#1e3c72; font-size:18px; }

        .no-notifications { text-align:center; padding:36px 12px; color:#666; }

        .notification-item { padding:12px 0; border-bottom:1px solid #eee; }
        .notification-item:last-child { border-bottom:none; }
        .notification-title { color:#1e3c72; font-weight:700; margin-bottom:6px; }
        .notification-message { color:#666; font-size:14px; margin-bottom:6px; }
        .notification-time { color:#999; font-size:12px; }
        .notification-unread { background:#fff7f0; border-left:4px solid #ff6b35; padding-left:12px; }

        /* Footer */
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

        /* Responsive adjustments (from index) */
        @media (max-width: 768px) {
            .nav-container { padding:10px 14px; }
            .logo { width:50px; height:50px; font-size:20px; }
            .section-header { border-bottom-width:3px; }
        }

        @media (max-width: 600px) {
            .logo-text h1 { font-size:16px; }
            .nav-links { display:flex; flex-wrap:wrap; gap:6px; }
            .action-buttons { flex-direction: column; }
            .btn { width: 100%; text-align: center; }
        }
    </style>
</head>
<body>
    <div class="navbar">
        <div class="nav-container">
                <div class="brand">
                    <a href="dashboard.php" class="logo"><i class="fas fa-city"></i></a>
                    <div class="logo-text"><h1>CIVICARE</h1></div>
                </div>
            <div class="nav-links">
                <a href="dashboard.php">Dashboard</a>
                <a href="complaints.php">My Complaints</a>
                <a href="new_complaint.php">File Complaint</a>
                
                <a href="notifications.php">
                    Notifications <?php if ($unread_count > 0): ?>
                        <span style="background: #e74c3c; padding: 2px 6px; border-radius: 10px; font-size: 0.8rem;">
                            <?php echo $unread_count; ?>
                        </span>
                    <?php endif; ?>
                </a>
                <a href="../logout.php" class="logout-btn">Logout</a>
            </div>
        </div>
    </div>
    
    <div class="container">
        <div class="welcome-section">
            <h1>Welcome, <?php echo htmlspecialchars($_SESSION['user_name']); ?>!</h1>
            <p>Track your complaints, file new issues, and stay updated on municipal services.</p>
            <div class="action-buttons">
                <a href="new_complaint.php" class="btn btn-primary">File New Complaint</a>
                <a href="complaints.php" class="btn btn-secondary">View All Complaints</a>
            </div>
        </div>
        
        <div class="dashboard-grid">
            <div class="notifications-section">
                <div class="section-header">
                    <h2>Recent Notifications</h2>
                    <a href="notifications.php" style="font-size: 0.9rem; color: #3498db;">View All</a>
                </div>
                
                <?php if (!empty($notifications)): ?>
                    <?php foreach ($notifications as $notification): ?>
                        <div class="notification-item <?php echo !$notification['is_read'] ? 'notification-unread' : ''; ?>">
                            <div class="notification-title">
                                <?php echo htmlspecialchars($notification['title']); ?>
                            </div>
                            <div class="notification-message">
                                <?php echo htmlspecialchars($notification['message']); ?>
                            </div>
                            <div class="notification-time">
                                <?php echo date('M d, h:i A', strtotime($notification['created_at'])); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="no-notifications">
                        <p>No notifications yet.</p>
                    </div>
                <?php endif; ?>
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
</body>
</html>