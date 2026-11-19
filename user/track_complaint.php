<?php
// civicare/user/track_complaint.php
session_start();
require_once '../config/database.php';
require_once '../config/functions.php';

$conn = getDatabaseConnection();

// Check connection
if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}

$error = '';
$success = '';
$complaint = null;
$updates = [];
$comments = [];

// Handle tracking ID from GET or POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tracking_id'])) {
    $tracking_id = sanitizeInput($_POST['tracking_id']);
    header("Location: track_complaint.php?tracking_id=" . urlencode($tracking_id));
    exit();
}

if (isset($_GET['tracking_id'])) {
    $tracking_id = sanitizeInput($_GET['tracking_id']);
    
    try {
        // Get complaint details - using correct column names
        $sql = "SELECT c.*, d.name as department_name, 
                       u.full_name as user_name, u.email as user_email
                FROM complaints c 
                JOIN departments d ON c.department_id = d.id 
                JOIN users u ON c.user_id = u.id 
                WHERE c.tracking_id = ?";
        
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception("Database error: " . $conn->error);
        }
        
        $stmt->bind_param("s", $tracking_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result && $result->num_rows > 0) {
            $complaint = $result->fetch_assoc();
            
            // Check if user is logged in and owns this complaint
            $is_owner = false;
            if (isset($_SESSION['user_id'])) {
                $is_owner = ($complaint['user_id'] == $_SESSION['user_id']);
            }
            
            // If not owner and not admin/staff, show limited info
            if (!$is_owner && (!isset($_SESSION['role']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'staff' && $_SESSION['role'] !== 'super_admin'))) {
                // Public can still view but with limited info
                $complaint['user_name'] = 'Citizen';
                $complaint['user_email'] = '';
                $complaint['location'] = 'Location hidden for privacy';
                $complaint['landmark'] = '';
            }
            
            // Get complaint updates/history - check if assigned_to column exists
            $updates = [];
            try {
                // Check if complaint_updates table exists
                $check_table = $conn->query("SHOW TABLES LIKE 'complaint_updates'");
                if ($check_table && $check_table->num_rows > 0) {
                    $sql_updates = "SELECT cu.*, u.full_name as updated_by_name 
                                    FROM complaint_updates cu 
                                    LEFT JOIN users u ON cu.user_id = u.id 
                                    WHERE cu.complaint_id = ? 
                                    ORDER BY cu.created_at DESC";
                    $stmt_updates = $conn->prepare($sql_updates);
                    if ($stmt_updates) {
                        $stmt_updates->bind_param("i", $complaint['id']);
                        $stmt_updates->execute();
                        $result_updates = $stmt_updates->get_result();
                        
                        while ($update = $result_updates->fetch_assoc()) {
                            $updates[] = $update;
                        }
                        $stmt_updates->close();
                    }
                }
            } catch (Exception $e) {
                // Table doesn't exist, continue without updates
                error_log("complaint_updates table error: " . $e->getMessage());
            }
            

            
            // Get comments if any (only if table exists)
            $comments = [];
            try {
                // Check if complaint_comments table exists
                $check_table = $conn->query("SHOW TABLES LIKE 'complaint_comments'");
                if ($check_table && $check_table->num_rows > 0) {
                    $sql_comments = "SELECT cc.*, u.full_name as commenter_name, u.role as commenter_role
                                     FROM complaint_comments cc 
                                     JOIN users u ON cc.user_id = u.id 
                                     WHERE cc.complaint_id = ? 
                                     ORDER BY cc.created_at ASC";
                    $stmt_comments = $conn->prepare($sql_comments);
                    if ($stmt_comments) {
                        $stmt_comments->bind_param("i", $complaint['id']);
                        $stmt_comments->execute();
                        $result_comments = $stmt_comments->get_result();
                        
                        while ($comment = $result_comments->fetch_assoc()) {
                            $comments[] = $comment;
                        }
                        $stmt_comments->close();
                    }
                }
            } catch (Exception $e) {
                // Table doesn't exist, continue without comments
                error_log("complaint_comments table error: " . $e->getMessage());
            }
            
        } else {
            $error = "No complaint found with Tracking ID: <strong>" . htmlspecialchars($tracking_id) . "</strong>";
        }
        $stmt->close();
        
    } catch (Exception $e) {
        $error = "Error retrieving complaint details: " . $e->getMessage();
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Track Complaint - Civicare</title>
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
        .nav-container {
            display: flex;
            gap: 0;
        }

        nav {
            width: 100%;
        }

        nav ul {
            list-style: none;
            display: flex;
            gap: 0;
        }

        nav > ul > li {
            position: relative;
        }

        nav > ul > li > a {
            display: block;
            padding: 20px 18px;
            color: #333;
            text-decoration: none;
            font-weight: 500;
            border-bottom: 3px solid transparent;
            transition: all 0.3s;
            font-size: 0.95rem;
        }

        nav > ul > li > a:hover {
            background: #f0f0f0;
            color: #1e3c72;
            border-bottom-color: #ff6b35;
        }

        /* ===== DROPDOWN MENUS ===== */
        .dropdown {
            position: relative;
        }

        .dropdown-menu {
            display: none;
            position: absolute;
            top: 100%;
            left: 0;
            background: white;
            min-width: 250px;
            list-style: none;
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.1);
            border-radius: 0 0 4px 4px;
        }

        .dropdown:hover .dropdown-menu {
            display: block;
            animation: slideDown 0.3s ease-out;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .dropdown-menu li a {
            display: block;
            padding: 12px 20px;
            color: #333;
            text-decoration: none;
            transition: all 0.3s;
            border-left: 3px solid transparent;
        }

        .dropdown-menu li a i {
            color: #ff6b35;
            font-size: 0.75rem;
            margin-right: 8px;
            transition: all 0.3s;
        }

        .dropdown-menu li a:hover {
            background: #f0f0f0;
            color: #1e3c72;
            border-left-color: #ff6b35;
            padding-left: 26px;
        }

        .dropdown > a::after {
            content: ' ▼';
            font-size: 0.7rem;
            margin-left: 5px;
        }

        /* ===== CONTAINER ===== */
        .container {
            width: 90%;
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 15px;
        }

        /* ===== BUTTONS ===== */
        .btn {
            display: inline-block;
            padding: 10px 24px;
            text-decoration: none;
            border-radius: 4px;
            font-weight: 600;
            transition: all 0.3s;
            border: none;
            cursor: pointer;
            font-size: 0.95rem;
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
            background: transparent;
            color: #1e3c72;
            border: 2px solid #1e3c72;
        }

        .btn-secondary:hover {
            background: #1e3c72;
            color: white;
        }

        /* ===== PAGE TITLE SECTION ===== */
        .page-title {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            color: white;
            padding: 40px 20px;
            text-align: center;
            margin-bottom: 40px;
        }

        .page-title h2 {
            font-size: 32px;
            margin-bottom: 10px;
            font-weight: 700;
        }

        .page-title p {
            font-size: 16px;
            opacity: 0.95;
        }

        /* ===== TRACK FORM SECTION ===== */
        .track-form-section {
            background: white;
            padding: 40px;
            border-radius: 8px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
            margin-bottom: 40px;
            text-align: center;
        }

        .track-form-section h3 {
            color: #1e3c72;
            margin-bottom: 10px;
            font-size: 24px;
        }

        .track-form-section > p {
            color: #666;
            margin-bottom: 25px;
        }

        .track-form {
            display: flex;
            gap: 15px;
            max-width: 600px;
            margin: 0 auto;
        }

        .track-input {
            flex: 1;
            padding: 12px 20px;
            border: 2px solid #ddd;
            border-radius: 4px;
            font-size: 1rem;
            transition: all 0.3s;
        }

        .track-input:focus {
            outline: none;
            border-color: #1e3c72;
            box-shadow: 0 0 0 3px rgba(30, 60, 114, 0.1);
        }

        .btn-track {
            padding: 12px 30px;
            background: #ff6b35;
            color: white;
            border: none;
            border-radius: 4px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            font-size: 1rem;
        }

        .btn-track:hover {
            background: #e55a24;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(255, 107, 53, 0.3);
        }

        /* ===== ALERT BOXES ===== */
        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 25px;
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

        /* ===== COMPLAINT DETAILS ===== */
        .complaint-details {
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
            margin-bottom: 40px;
        }

        .complaint-header {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            color: white;
            padding: 30px;
            position: relative;
        }

        .tracking-id-display {
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .tracking-id-display .copy-btn {
            background: rgba(255,255,255,0.2);
            border: 2px solid rgba(255,255,255,0.3);
            color: white;
            padding: 6px 12px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.8rem;
            transition: all 0.3s;
        }

        .tracking-id-display .copy-btn:hover {
            background: rgba(255,255,255,0.3);
        }

        .complaint-title {
            font-size: 20px;
            margin-bottom: 15px;
            font-weight: 600;
        }

        .status-badge {
            display: inline-block;
            padding: 6px 16px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.85rem;
            margin-right: 10px;
        }

        .status-pending { background: #fff3cd; color: #856404; }
        .status-validated { background: #cce5ff; color: #004085; }
        .status-rejected { background: #f8d7da; color: #721c24; }
        .status-assigned { background: #d1ecf1; color: #0c5460; }
        .status-in_progress { background: #cce5ff; color: #004085; }
        .status-resolved { background: #d4edda; color: #155724; }
        .status-closed { background: #e2e3e5; color: #383d41; }

        .print-btn {
            position: absolute;
            top: 30px;
            right: 30px;
            background: rgba(255,255,255,0.2);
            border: 2px solid rgba(255,255,255,0.3);
            color: white;
            padding: 8px 15px;
            border-radius: 4px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 6px;
            transition: all 0.3s;
        }

        .print-btn:hover {
            background: rgba(255,255,255,0.3);
        }

        /* ===== COMPLAINT CONTENT ===== */
        .complaint-content {
            padding: 30px;
        }

        .section-title {
            color: #1e3c72;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #ff6b35;
            font-size: 18px;
            font-weight: 600;
        }

        .details-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .detail-card {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            border-left: 4px solid #2a5298;
        }

        .detail-card:hover {
            background: #f0f0f0;
        }

        .detail-label {
            font-size: 0.8rem;
            color: #666;
            margin-bottom: 5px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .detail-value {
            font-weight: 600;
            color: #1e3c72;
            font-size: 1rem;
        }

        .description-box {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
            line-height: 1.8;
        }

        /* ===== RATING BOX ===== */
        .rating-box {
            background: #fff9e6;
            padding: 20px;
            border-radius: 8px;
            border-left: 4px solid #f39c12;
            margin-bottom: 30px;
        }

        .rating-stars {
            color: #f39c12;
            font-size: 1.5rem;
            letter-spacing: 3px;
        }

        .rating-note {
            margin-top: 8px;
            color: #666;
            font-size: 0.9rem;
        }

        /* ===== TIMELINE ===== */
        .timeline {
            position: relative;
            padding-left: 30px;
            margin: 30px 0;
        }

        .timeline::before {
            content: '';
            position: absolute;
            left: 8px;
            top: 0;
            bottom: 0;
            width: 3px;
            background: #2a5298;
        }

        .timeline-item {
            position: relative;
            margin-bottom: 25px;
        }

        .timeline-item::before {
            content: '';
            position: absolute;
            left: -25px;
            top: 3px;
            width: 14px;
            height: 14px;
            border-radius: 50%;
            background: #2a5298;
            border: 3px solid white;
            box-shadow: 0 0 0 2px #2a5298;
        }

        .timeline-date {
            font-size: 0.85rem;
            color: #888;
            margin-bottom: 5px;
        }

        .timeline-status {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 15px;
            font-size: 0.75rem;
            font-weight: 600;
            margin-left: 8px;
        }

        .timeline-content {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-top: 10px;
        }

        /* ===== ATTACHMENTS ===== */
        .attachments-container {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-top: 15px;
        }

        .attachment-item {
            background: #e8f4fc;
            padding: 12px 18px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            color: #1e3c72;
            transition: all 0.3s;
        }

        .attachment-item:hover {
            background: #d4eaf7;
            transform: translateY(-2px);
        }

        /* ===== COMMENTS SECTION ===== */
        .comments-section {
            background: white;
            border-radius: 8px;
            padding: 25px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
            margin-top: 30px;
        }

        .comment-item {
            padding: 15px;
            border-bottom: 1px solid #eee;
            margin-bottom: 15px;
            border-radius: 8px;
        }

        .comment-item:hover {
            background: #f8f9fa;
        }

        .comment-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
        }

        .comment-author {
            font-weight: 600;
            color: #1e3c72;
        }

        .comment-role {
            font-size: 0.75rem;
            color: #666;
            margin-left: 8px;
            background: #e0e0e0;
            padding: 2px 8px;
            border-radius: 10px;
        }

        .comment-time {
            font-size: 0.8rem;
            color: #999;
        }

        .comment-content {
            color: #555;
            line-height: 1.6;
        }

        .no-comments {
            text-align: center;
            padding: 30px;
            color: #999;
        }

        /* ===== SHARE BUTTONS ===== */
        .share-buttons {
            display: flex;
            gap: 10px;
            margin-top: 25px;
        }

        .share-btn {
            background: #f0f0f0;
            border: none;
            padding: 10px 18px;
            border-radius: 4px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 6px;
            transition: all 0.3s;
            font-weight: 500;
            color: #333;
        }

        .share-btn:hover {
            background: #1e3c72;
            color: white;
            transform: translateY(-2px);
        }

        /* ===== FOOTER ===== */
        .footer {
            background: #1a1a1a;
            color: #fff;
            padding: 30px 20px 15px;
            margin-top: 60px;
        }

        .footer-content {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            gap: 25px;
            margin-bottom: 20px;
            max-width: 1200px;
            margin-left: auto;
            margin-right: auto;
        }

        .footer-section {
            flex: 1;
            min-width: 200px;
        }

        .footer-section:first-child {
            flex: 2;
        }

        .footer-section h4 {
            color: #ff6b35;
            margin-bottom: 12px;
            font-size: 14px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .footer-section ul {
            list-style: none;
        }

        .footer-section ul li {
            margin-bottom: 6px;
        }

        .footer-section ul li a {
            color: #aaa;
            text-decoration: none;
            transition: color 0.3s;
            font-size: 13px;
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
            font-size: 12px;
            margin-top: 3px;
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
            margin-top: 12px;
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
            transform: translateY(-2px);
        }

        .footer-bottom {
            border-top: 1px solid #333;
            padding-top: 12px;
            text-align: center;
            color: #777;
            font-size: 11px;
            max-width: 1200px;
            margin: 0 auto;
        }

        .footer-bottom p {
            margin-bottom: 4px;
        }

        /* ===== RESPONSIVE ===== */
        @media (max-width: 768px) {
            .header-content {
                flex-direction: column;
                gap: 15px;
            }

            nav ul {
                flex-wrap: wrap;
            }

            .track-form {
                flex-direction: column;
            }

            .page-title h2 {
                font-size: 24px;
            }

            .details-grid {
                grid-template-columns: 1fr;
            }

            .print-btn {
                position: relative;
                top: 0;
                right: 0;
                margin-top: 15px;
            }

            .tracking-id-display {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }

            .share-buttons {
                flex-wrap: wrap;
            }

            .footer-content {
                flex-direction: column;
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

            <!-- NAVIGATION -->
            <nav class="nav-container">
                <ul>
                    <li><a href="../index.php">Home</a></li>

                    <li class="dropdown">
                        <a href="#">Services</a>
                        <ul class="dropdown-menu">
                            <li><a href="new_complaint.php"><i class="fas fa-chevron-right"></i> File Complaint</a></li>
                            <li><a href="track_complaint.php"><i class="fas fa-chevron-right"></i> Track Complaint</a></li>
                            <li><a href="complaints.php"><i class="fas fa-chevron-right"></i> View History</a></li>
                        </ul>
                    </li>

                    <li><a href="../contact.php">Contact</a></li>

                    <li class="dropdown">
                        <a href="#">Login</a>
                        <ul class="dropdown-menu">
                            <li><a href="login.php"><i class="fas fa-chevron-right"></i> Citizen Login</a></li>
                        </ul>
                    </li>
                </ul>
            </nav>
        </div>
    </header>

    <!-- PAGE TITLE -->
    <section class="page-title">
        <div class="container">
            <h2>Track Your Complaint</h2>
            <p>Monitor the status and progress of your submitted complaints</p>
        </div>
    </section>

    <div class="container">
        <!-- Tracking Form -->
        <div class="track-form-section">
            <h3>Enter Tracking ID</h3>
            <p>Enter your complaint tracking ID to view status and updates</p>
            
            <form method="POST" action="" class="track-form">
                <input type="text" 
                       name="tracking_id" 
                       class="track-input" 
                       placeholder="Enter Tracking ID (e.g., CIV-2024-00123)"
                       value="<?php echo isset($tracking_id) ? htmlspecialchars($tracking_id) : ''; ?>"
                       required>
                <button type="submit" class="btn-track">
                    <i class="fas fa-search"></i> Track
                </button>
            </form>
            
            <?php if (isset($_SESSION['user_id'])): ?>
                <p style="margin-top: 15px; color: #666;">
                    Or view all your complaints from <a href="dashboard.php" style="color: #ff6b35; text-decoration: none; font-weight: 600;">Dashboard</a>
                </p>
            <?php else: ?>
                <p style="margin-top: 15px; color: #666;">
                    <a href="login.php" style="color: #ff6b35; text-decoration: none; font-weight: 600;">Login</a> to view your all complaints.
                </p>
            <?php endif; ?>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <?php if ($complaint): ?>
            <!-- Complaint Details -->
            <div class="complaint-details">
                <div class="complaint-header">
                    <div class="tracking-id-display">
                        <span>#<?php echo htmlspecialchars($complaint['tracking_id']); ?></span>
                        <button class="copy-btn" onclick="copyTrackingId()" title="Copy Tracking ID">
                            <i class="fas fa-copy"></i> Copy
                        </button>
                    </div>
                    <h1 class="complaint-title"><?php echo htmlspecialchars($complaint['title']); ?></h1>
                    
                    <span class="status-badge status-<?php echo $complaint['status']; ?>">
                        <?php echo getStatusText($complaint['status']); ?>
                    </span>
                    
                    <button class="print-btn" onclick="window.print()">
                        <i class="fas fa-print"></i> Print
                    </button>
                </div>
                
                <div class="complaint-content">
                    <!-- Basic Details -->
                    <div class="details-grid">
                        <div class="detail-card">
                            <div class="detail-label">Filed By</div>
                            <div class="detail-value"><?php echo htmlspecialchars($complaint['user_name']); ?></div>
                        </div>
                        
                        <div class="detail-card">
                            <div class="detail-label">Department</div>
                            <div class="detail-value"><?php echo htmlspecialchars($complaint['department_name']); ?></div>
                        </div>
                        
                        <div class="detail-card">
                            <div class="detail-label">Date Filed</div>
                            <div class="detail-value">
                                <?php echo date('F d, Y h:i A', strtotime($complaint['created_at'])); ?>
                            </div>
                        </div>
                        
                        <div class="detail-card">
                            <div class="detail-label">Last Updated</div>
                            <div class="detail-value">
                                <?php echo date('F d, Y h:i A', strtotime($complaint['updated_at'])); ?>
                            </div>
                        </div>
                        
                        <div class="detail-card">
                            <div class="detail-label">Location</div>
                            <div class="detail-value"><?php echo htmlspecialchars($complaint['location']); ?></div>
                        </div>
                        
                        <?php if (!empty($complaint['landmark'])): ?>
                        <div class="detail-card">
                            <div class="detail-label">Landmark</div>
                            <div class="detail-value"><?php echo htmlspecialchars($complaint['landmark']); ?></div>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Description -->
                    <h3 class="section-title">Description</h3>
                    <div class="description-box">
                        <?php echo nl2br(htmlspecialchars($complaint['description'])); ?>
                    </div>

                    <!-- Resolution Feedback -->
                    <h3 class="section-title">Resolution Feedback</h3>
                    <div class="rating-box">
                        <?php if (in_array($complaint['status'], ['resolved', 'closed'], true)): ?>
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
                        <?php else: ?>
                            <div class="rating-note">Feedback will be available once the complaint is resolved.</div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Share Buttons -->
                    <div class="share-buttons">
                        <button class="share-btn" onclick="shareComplaint('whatsapp')">
                            <i class="fab fa-whatsapp"></i> WhatsApp
                        </button>
                        <button class="share-btn" onclick="shareComplaint('email')">
                            <i class="fas fa-envelope"></i> Email
                        </button>
                        <button class="share-btn" onclick="copyShareLink()">
                            <i class="fas fa-link"></i> Copy Link
                        </button>
                    </div>
                    
                    <!-- Status Timeline -->
                    <h3 class="section-title">Status History</h3>
                    <div class="timeline">
                        <?php if (!empty($updates)): ?>
                            <?php foreach ($updates as $update): ?>
                                <div class="timeline-item">
                                    <div class="timeline-date">
                                        <?php echo date('M d, Y h:i A', strtotime($update['created_at'])); ?>
                                    </div>
                                    <div>
                                        <strong><?php echo htmlspecialchars($update['updated_by_name'] ?? 'System'); ?></strong>
                                        <span class="timeline-status status-<?php echo $update['new_status']; ?>">
                                            <?php echo getStatusText($update['new_status']); ?>
                                        </span>
                                    </div>
                                    <?php if (!empty($update['remarks'])): ?>
                                        <div class="timeline-content">
                                            <?php echo nl2br(htmlspecialchars($update['remarks'])); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="timeline-item">
                                <div class="timeline-date">
                                    <?php echo date('M d, Y h:i A', strtotime($complaint['created_at'])); ?>
                                </div>
                                <div>
                                    <strong>System</strong>
                                    <span class="timeline-status status-pending">
                                        Complaint Filed
                                    </span>
                                </div>
                                <div class="timeline-content">
                                    Complaint was filed successfully. Awaiting validation by department.
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Comments Section -->
                    <?php if (!empty($comments)): ?>
                        <h3 class="section-title">Comments & Updates</h3>
                        <div class="comments-section">
                            <?php foreach ($comments as $comment): ?>
                                <div class="comment-item">
                                    <div class="comment-header">
                                        <div>
                                            <span class="comment-author"><?php echo htmlspecialchars($comment['commenter_name']); ?></span>
                                            <span class="comment-role">(<?php echo ucfirst($comment['commenter_role']); ?>)</span>
                                        </div>
                                        <div class="comment-time">
                                            <?php echo date('M d, h:i A', strtotime($comment['created_at'])); ?>
                                        </div>
                                    </div>
                                    <div class="comment-content">
                                        <?php echo nl2br(htmlspecialchars($comment['comment'])); ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="no-comments">
                            <p>No comments yet. Department will update here.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php elseif (isset($tracking_id) && !$error): ?>
            <!-- Loading state -->
            <div style="text-align: center; padding: 3rem; background: white; border-radius: 8px; box-shadow: 0 4px 10px rgba(0,0,0,0.1);">
                <div style="font-size: 3rem; margin-bottom: 1rem;">🔍</div>
                <h3 style="color: #1e3c72; margin-bottom: 0.5rem;">Searching for complaint...</h3>
                <p style="color: #666;">Please wait while we fetch the complaint details.</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- FOOTER -->
    <footer class="footer">
        <div class="footer-content">
            <!-- ABOUT -->
            <div class="footer-section">
                <h4>About CiviCare</h4>
                <p>CiviCare is the official municipal complaint management system. We bridge the gap between citizens and government for efficient service delivery.</p>
                <div class="footer-social">
                    <a href="#"><i class="fab fa-facebook-f"></i></a>
                    <a href="#"><i class="fab fa-twitter"></i></a>
                    <a href="#"><i class="fab fa-youtube"></i></a>
                    <a href="#"><i class="fab fa-instagram"></i></a>
                </div>
            </div>

            <!-- QUICK LINKS -->
            <div class="footer-section">
                <h4>Quick Links</h4>
                <ul>
                    <li><a href="../index.php">Home</a></li>
                    <li><a href="new_complaint.php">File Complaint</a></li>
                    <li><a href="track_complaint.php">Track Status</a></li>
                    <li><a href="../contact.php">Contact Us</a></li>
                    <li><a href="../faqs.php">FAQs</a></li>
                    <li><a href="#">Privacy Policy</a></li>
                </ul>
            </div>

            <!-- DEPARTMENTS -->
            <div class="footer-section">
                <h4>Departments</h4>
                <ul>
                    <li><a href="../department_info.php?id=1">Water Supply</a></li>
                    <li><a href="../department_info.php?id=2">Electricity</a></li>
                    <li><a href="../department_info.php?id=3">Roads</a></li>
                    <li><a href="../department_info.php?id=4">Sanitation</a></li>
                    <li><a href="../department_info.php?id=5">Public Works</a></li>
                    <li><a href="../department_info.php?id=6">Parks & Gardens</a></li>
                </ul>
            </div>

            <!-- CONTACT -->
            <div class="footer-section">
                <h4>Contact Us</h4>
                <div class="footer-contact">
                    <i class="fas fa-map-marker-alt"></i>
                    <span>
                        <strong>Address:</strong><br>
                        Three Archives Building<br>
                        Pandit Colony, Nashik - 422002
                    </span>
                </div>
                <div class="footer-contact">
                    <i class="fas fa-phone"></i>
                    <span><a href="tel:+919876543210">+(91) 9876543210</a></span>
                </div>
                <div class="footer-contact">
                    <i class="fas fa-envelope"></i>
                    <span><a href="mailto:info@civicare.gov.in">info@civicare.gov.in</a></span>
                </div>
                <div class="footer-contact">
                    <i class="fas fa-clock"></i>
                    <span>
                        <strong>Helpline:</strong><br>
                        Monday - Saturday<br>
                        10:00 AM - 6:00 PM
                    </span>
                </div>
            </div>
        </div>

        <!-- FOOTER BOTTOM -->
        <div class="footer-bottom">
            <p>&copy; 2026 CiviCare - Municipal Complaint Management System. All rights reserved.</p>
            <p><a href="#" style="color: #ff6b35;">Privacy Policy</a> | <a href="#" style="color: #ff6b35;">Terms & Conditions</a> | <a href="#" style="color: #ff6b35;">Sitemap</a></p>
        </div>
    </footer>

    <script>
        // Copy Tracking ID to clipboard
        function copyTrackingId() {
            const trackingId = "<?php echo isset($complaint['tracking_id']) ? $complaint['tracking_id'] : ''; ?>";
            navigator.clipboard.writeText(trackingId).then(() => {
                alert('Tracking ID copied to clipboard: ' + trackingId);
            });
        }
        
        // Copy shareable link
        function copyShareLink() {
            const trackingId = "<?php echo isset($complaint['tracking_id']) ? $complaint['tracking_id'] : ''; ?>";
            const shareLink = window.location.origin + window.location.pathname + '?tracking_id=' + trackingId;
            navigator.clipboard.writeText(shareLink).then(() => {
                alert('Shareable link copied to clipboard!');
            });
        }
        
        // Share via WhatsApp
        function shareComplaint(platform) {
            const trackingId = "<?php echo isset($complaint['tracking_id']) ? $complaint['tracking_id'] : ''; ?>";
            const title = "<?php echo isset($complaint['title']) ? addslashes($complaint['title']) : ''; ?>";
            const status = "<?php echo isset($complaint['status']) ? getStatusText($complaint['status']) : ''; ?>";
            const shareLink = window.location.origin + window.location.pathname + '?tracking_id=' + trackingId;
            
            let message = `Check my complaint status on Civicare:\n\n`;
            message += `Complaint: ${title}\n`;
            message += `Tracking ID: ${trackingId}\n`;
            message += `Status: ${status}\n`;
            message += `Track here: ${shareLink}`;
            
            if (platform === 'whatsapp') {
                window.open(`https://wa.me/?text=${encodeURIComponent(message)}`, '_blank');
            } else if (platform === 'email') {
                const subject = `My Complaint Status: ${trackingId}`;
                const body = message;
                window.location.href = `mailto:?subject=${encodeURIComponent(subject)}&body=${encodeURIComponent(body)}`;
            }
        }
        
        // Auto-focus on tracking input
        document.addEventListener('DOMContentLoaded', function() {
            const trackingInput = document.querySelector('.track-input');
            if (trackingInput && !trackingInput.value) {
                trackingInput.focus();
            }
            
            // Add enter key support
            if (trackingInput) {
                trackingInput.addEventListener('keypress', function(e) {
                    if (e.key === 'Enter') {
                        this.closest('form').submit();
                    }
                });
            }
        });
        
        // Auto-refresh for in-progress complaints
        const complaintStatus = "<?php echo isset($complaint['status']) ? $complaint['status'] : ''; ?>";
        if (['in_progress', 'assigned', 'validated'].includes(complaintStatus)) {
            setTimeout(() => {
                window.location.reload();
            }, 30000); // Refresh every 30 seconds
        }
    </script>
    
    <?php
    // Helper function for file size formatting
    function formatFileSize($bytes) {
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        } elseif ($bytes > 1) {
            return $bytes . ' bytes';
        } elseif ($bytes == 1) {
            return '1 byte';
        } else {
            return '0 bytes';
        }
    }
    ?>
</body>
</html>