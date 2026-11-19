<?php
// civicare/staff/dashboard.php
require_once '../config/database.php';
require_once '../config/functions.php';

// Check if user is logged in and is staff
if (!isLoggedIn() || !hasRole('staff')) {
    header('Location: login.php');
    exit();
}

$conn = getDatabaseConnection();

// Get department info for the staff member
$department_id = $_SESSION['department_id'];
$department_sql = "SELECT name FROM departments WHERE id = ?";
$department_stmt = $conn->prepare($department_sql);
$department_stmt->bind_param("i", $department_id);
$department_stmt->execute();
$department_result = $department_stmt->get_result();
$department_name = $department_result->fetch_assoc()['name'];
$department_stmt->close();

// Get statistics for staff dashboard (department-specific)
$total_complaints = 0;
$pending_complaints = 0;
$assigned_complaints = 0;
$in_progress_complaints = 0;
$resolved_complaints = 0;
$escalated_complaints = 0;

// Total complaints in department
$sql = "SELECT COUNT(*) as total FROM complaints WHERE department_id = ?";
$result = $conn->query($sql);
if ($result) {
    $row = $result->fetch_assoc();
    $total_complaints = $row['total'];
}

// Pending complaints in department
$sql = "SELECT COUNT(*) as pending FROM complaints WHERE department_id = ? AND status = 'pending'";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $department_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result) {
    $row = $result->fetch_assoc();
    $pending_complaints = $row['pending'];
}
$stmt->close();

// Assigned to this staff member
$sql = "SELECT COUNT(*) as assigned FROM complaints WHERE department_id = ? AND assigned_to = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $department_id, $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
if ($result) {
    $row = $result->fetch_assoc();
    $assigned_complaints = $row['assigned'];
}
$stmt->close();

// In progress complaints assigned to this staff
$sql = "SELECT COUNT(*) as in_progress FROM complaints WHERE department_id = ? AND assigned_to = ? AND status = 'in_progress'";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $department_id, $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
if ($result) {
    $row = $result->fetch_assoc();
    $in_progress_complaints = $row['in_progress'];
}
$stmt->close();

// Resolved complaints by this staff
$sql = "SELECT COUNT(*) as resolved FROM complaints WHERE department_id = ? AND resolved_by = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $department_id, $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
if ($result) {
    $row = $result->fetch_assoc();
    $resolved_complaints = $row['resolved'];
}
$stmt->close();

// Escalated complaints by this staff
$sql = "SELECT COUNT(*) as escalated FROM complaints WHERE department_id = ? AND assigned_to = ? AND status = 'escalated'";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $department_id, $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
if ($result) {
    $row = $result->fetch_assoc();
    $escalated_complaints = $row['escalated'];
}
$stmt->close();

// Reapplication statistics for staff's department
$total_reapplications = 0;
$complaints_with_reapplications = 0;

// Total reapplications in department
$sql = "SELECT COUNT(*) as total FROM complaints WHERE department_id = ? AND parent_complaint_id IS NOT NULL";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $department_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result) {
    $row = $result->fetch_assoc();
    $total_reapplications = $row['total'];
}
$stmt->close();

// Complaints with reapplications
$sql = "SELECT COUNT(DISTINCT parent_complaint_id) as total FROM complaints WHERE department_id = ? AND parent_complaint_id IS NOT NULL";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $department_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result) {
    $row = $result->fetch_assoc();
    $complaints_with_reapplications = $row['total'];
}
$stmt->close();

// Get recent complaints assigned to this staff member (with department info)
$recent_complaints = [];
$sql = "SELECT c.*, u.full_name as user_name, d.name as department_name
        FROM complaints c
        JOIN users u ON c.user_id = u.id
        JOIN departments d ON c.department_id = d.id
        WHERE c.department_id = ? AND c.assigned_to = ?
        ORDER BY c.updated_at DESC LIMIT 10";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $department_id, $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $recent_complaints[] = $row;
    }
}
$stmt->close();

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Dashboard - Municipal Complaint Management System</title>
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
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            position: relative;
        }

        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-image:
                radial-gradient(circle at 25% 25%, rgba(255, 255, 255, 0.1) 0%, transparent 50%),
                radial-gradient(circle at 75% 75%, rgba(255, 255, 255, 0.05) 0%, transparent 50%);
            background-size: 400px 400px;
            background-position: 0 0, 200px 200px;
            pointer-events: none;
            z-index: -1;
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
            box-shadow: 0 4px 12px rgba(30, 60, 114, 0.3);
            transition: all 0.3s;
        }

        .logo:hover {
            transform: scale(1.05);
            box-shadow: 0 6px 16px rgba(30, 60, 114, 0.4);
        }

        .logo-text h1 {
            font-size: 24px;
            color: #1e3c72;
            margin: 0;
            font-weight: 700;
        }

        .logo-text p {
            font-size: 12px;
            color: #666;
            margin: 3px 0 0 0;
            font-weight: 500;
        }

        /* ===== NAVIGATION ===== */
        .nav-links {
            display: flex;
            gap: 8px;
            align-items: center;
        }

        .nav-links a {
            display: inline-block;
            padding: 12px 20px;
            color: #333;
            text-decoration: none;
            font-weight: 500;
            border-radius: 6px;
            transition: all 0.3s;
            font-size: 0.95rem;
        }

        .nav-links a:hover {
            background: #f0f0f0;
            color: #1e3c72;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .nav-links a.active {
            background: linear-gradient(135deg, #1e3c72, #2a5298);
            color: white;
            box-shadow: 0 4px 12px rgba(30, 60, 114, 0.3);
        }

        .logout-btn {
            background: linear-gradient(135deg, #ff6b35, #e55a24);
            color: white;
            padding: 10px 20px;
            border-radius: 6px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s;
            box-shadow: 0 4px 12px rgba(255, 107, 53, 0.3);
        }

        .logout-btn:hover {
            background: linear-gradient(135deg, #e55a24, #cc4a1f);
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(255, 107, 53, 0.4);
        }

        /* ===== CONTAINER ===== */
        .container {
            width: 90%;
            max-width: 1200px;
            margin: 30px auto;
            padding: 0 15px;
        }

        /* ===== MAIN CONTENT WRAPPER ===== */
        .main-content {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 12px;
            padding: 30px;
            margin-top: 20px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        /* ===== COMPLAINTS TABLE ===== */
        .complaints-section {
            background: white;
            border-radius: 12px;
            box-shadow: 0 6px 15px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            transition: all 0.2s ease;
        }

        .complaints-section:hover {
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
        }

        .section-header {
            background: linear-gradient(135deg, #1e3c72, #2a5298);
            color: white;
            padding: 20px 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .section-header h2 {
            font-size: 20px;
            margin: 0;
            font-weight: 600;
        }

        .section-header .warning-text {
            color: #ff6b35;
            font-size: 14px;
            background: rgba(255, 107, 53, 0.1);
            padding: 6px 12px;
            border-radius: 20px;
            border: 1px solid rgba(255, 107, 53, 0.3);
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            background: #f8f9fa;
            color: #1e3c72;
            padding: 15px 20px;
            text-align: left;
            font-weight: 600;
            font-size: 14px;
            border-bottom: 2px solid #eee;
        }

        td {
            padding: 15px 20px;
            border-bottom: 1px solid #eee;
            font-size: 14px;
        }

        tr:hover {
            background: #f8f9fa;
        }

        .tracking-id {
            color: #ff6b35;
            font-weight: 700;
            font-size: 15px;
        }

        .complaint-title {
            color: #1e3c72;
            font-weight: 600;
            max-width: 200px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .escalated-icon {
            color: #e74c3c;
            margin-left: 5px;
        }

        /* ===== STATUS BADGES ===== */
        .status {
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            display: inline-block;
            min-width: 80px;
            text-align: center;
        }

        .status-pending { background: #fff3cd; color: #856404; border: 1px solid #ffeaa7; }
        .status-validated { background: #cce5ff; color: #004085; border: 1px solid #99d6ff; }
        .status-assigned { background: #d1ecf1; color: #0c5460; border: 1px solid #a3d9e6; }
        .status-in_progress { background: #cce5ff; color: #004085; border: 1px solid #99d6ff; }
        .status-resolved { background: #d4edda; color: #155724; border: 1px solid #b8e6b3; }
        .status-escalated { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }

        /* ===== ACTION LINKS ===== */
        .action-link {
            color: #1e3c72;
            text-decoration: none;
            font-weight: 600;
            margin-right: 12px;
            transition: all 0.2s;
            padding: 4px 8px;
            border-radius: 4px;
        }

        .action-link:hover {
            color: #ff6b35;
            background: rgba(255, 107, 53, 0.1);
        }

        .action-link.start {
            color: #27ae60;
        }

        .action-link.start:hover {
            background: rgba(39, 174, 96, 0.1);
        }

        .action-link.resolve {
            color: #27ae60;
        }

        .action-link.resolve:hover {
            background: rgba(39, 174, 96, 0.1);
        }

        .action-link.escalate {
            color: #e67e22;
        }

        .action-link.escalate:hover {
            background: rgba(230, 126, 34, 0.1);
        }

        /* ===== COPY BUTTON ===== */
        .copy-id {
            padding: 6px 12px;
            border-radius: 6px;
            border: none;
            background: linear-gradient(135deg, #2a5298, #1e3c72);
            color: white;
            cursor: pointer;
            font-size: 12px;
            transition: all 0.2s;
            font-weight: 600;
            margin-left: 5px;
        }

        .copy-id:hover {
            background: linear-gradient(135deg, #1e3c72, #152a4a);
        }

        .no-complaints {
            text-align: center;
            padding: 60px 40px;
            color: #666;
        }

        .no-complaints i {
            font-size: 48px;
            color: #ddd;
            margin-bottom: 20px;
        }

        /* ===== RESPONSIVE ===== */
        @media (max-width: 768px) {
            .header-content {
                flex-direction: column;
                gap: 20px;
            }

            .nav-links {
                flex-wrap: wrap;
                justify-content: center;
            }

            table {
                display: block;
                overflow-x: auto;
            }

            .section-header {
                flex-direction: column;
                gap: 15px;
                text-align: center;
                padding: 20px;
            }
        }

        @media (max-width: 480px) {
            .logo-section {
                gap: 15px;
            }

            .logo {
                width: 50px;
                height: 50px;
                font-size: 24px;
            }

            .logo-text h1 {
                font-size: 20px;
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
            transition: color 0.2s;
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
            transition: all 0.2s;
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
    </style>
</head>
<body>
    <!-- MAIN HEADER -->
    <header class="main-header">
        <div class="header-content">
            <div class="logo-section">
                <a href="dashboard.php" class="logo">
                    <i class="fas fa-city"></i>
                </a>
                <div class="logo-text">
                    <h1>CIVICARE</h1>
                    <p>Municipal Complaint Management</p>
                </div>
            </div>

            <nav class="nav-links">
                <a href="dashboard.php" class="active">Dashboard</a>
                <a href="complaints.php">Complaints</a>
                <a href="logout.php" class="logout-btn">Logout</a>
            </nav>
        </div>
    </header>

    <div class="container">
        <!-- COMPLAINTS TABLE -->
        <div class="complaints-section">
            <div class="section-header">
                <h2>My Assigned Complaints</h2>
                <?php if ($escalated_complaints > 0): ?>
                <span class="warning-text"><i class="fas fa-exclamation-triangle"></i> <?php echo $escalated_complaints; ?> escalated need attention</span>
                <?php endif; ?>
            </div>

            <table>
                <thead>
                    <tr>
                        <th>Tracking ID</th>
                        <th>Title</th>
                        <th>Citizen</th>
                        <th>Department</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($recent_complaints)): ?>
                        <?php foreach ($recent_complaints as $complaint): ?>
                            <tr>
                                <td>
                                    <span class="tracking-id"><?php echo htmlspecialchars($complaint['tracking_id']); ?></span>
                                    <button class="copy-id" data-id="<?php echo htmlspecialchars($complaint['tracking_id']); ?>">Copy</button>
                                </td>
                                <td>
                                    <span class="complaint-title"><?php echo htmlspecialchars($complaint['title']); ?></span>
                                    <?php if ($complaint['status'] === 'escalated'): ?>
                                        <span class="escalated-icon"><i class="fas fa-exclamation-triangle"></i></span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($complaint['user_name']); ?></td>
                                <td><?php echo htmlspecialchars($complaint['department_name']); ?></td>
                                <td>
                                    <span class="status status-<?php echo $complaint['status']; ?>">
                                        <?php echo getStatusText($complaint['status']); ?>
                                    </span>
                                </td>
                                <td><?php echo date('M d, Y', strtotime($complaint['created_at'])); ?></td>
                                <td>
                                    <a href="track_complaint.php?tracking_id=<?php echo urlencode($complaint['tracking_id']); ?>" class="action-link">View</a>
                                    <?php if ($complaint['status'] === 'assigned'): ?>
                                        <a href="complaint_actions.php?action=in_progress&id=<?php echo $complaint['id']; ?>" class="action-link start">Start</a>
                                    <?php elseif ($complaint['status'] === 'in_progress'): ?>
                                        <a href="complaint_actions.php?action=resolve&id=<?php echo $complaint['id']; ?>" class="action-link resolve">Resolve</a>
                                        <a href="complaint_actions.php?action=escalate&id=<?php echo $complaint['id']; ?>" class="action-link escalate">Escalate</a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" class="no-complaints">
                                <i class="fas fa-inbox"></i>
                                <p>No complaints assigned to you at the moment</p>
                                <small>Check back later or view all complaints in your department</small>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
        // Copy tracking ID functionality
        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('copy-id')) {
                const trackingId = e.target.getAttribute('data-id');
                navigator.clipboard.writeText(trackingId).then(function() {
                    const originalText = e.target.textContent;
                    e.target.textContent = 'Copied!';
                    e.target.style.background = '#27ae60';
                    setTimeout(function() {
                        e.target.textContent = originalText;
                        e.target.style.background = '#2a5298';
                    }, 1000);
                });
            }
        });

        // Highlight active nav-link based on URL
        document.querySelectorAll('.nav-links a').forEach(a => {
            if (location.pathname.endsWith(a.getAttribute('href'))) {
                document.querySelectorAll('.nav-links a').forEach(n => n.classList.remove('active'));
                a.classList.add('active');
            }
        });
    </script>

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