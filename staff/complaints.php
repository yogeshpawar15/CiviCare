<?php
// civicare/staff/complaints.php
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

// Initialize filter variables
$status_filter = isset($_GET['status']) ? sanitizeInput($_GET['status']) : '';
$search_query = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = 15;
$offset = ($page - 1) * $limit;

// Build SQL query for staff (only their assigned complaints)
$sql = "SELECT c.*, u.full_name as user_name, u.email as user_email
        FROM complaints c
        JOIN users u ON c.user_id = u.id
        WHERE c.department_id = ? AND c.assigned_to = ?";

$count_sql = "SELECT COUNT(*) as total FROM complaints c WHERE c.department_id = ? AND c.assigned_to = ?";
$params = [$department_id, $_SESSION['user_id']];
$param_types = "ii";

// Apply filters
if ($status_filter && $status_filter !== 'all') {
    $sql .= " AND c.status = ?";
    $count_sql .= " AND c.status = ?";
    $params[] = $status_filter;
    $param_types .= "s";
}

if ($search_query) {
    $sql .= " AND (c.title LIKE ? OR c.tracking_id LIKE ? OR c.description LIKE ? OR u.full_name LIKE ? OR u.email LIKE ?)";
    $count_sql .= " AND (c.title LIKE ? OR c.tracking_id LIKE ? OR c.description LIKE ? OR u.full_name LIKE ? OR u.email LIKE ?)";
    $search_param = "%$search_query%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $param_types .= "sssss";
}

// Add ordering and pagination
$sql .= " ORDER BY c.updated_at DESC
          LIMIT ? OFFSET ?";

$params[] = $limit;
$params[] = $offset;
$param_types .= "ii";

// Prepare and execute query
$stmt = $conn->prepare($sql);
if ($params && $param_types) {
    // Validate that the number of type specifiers matches the number of parameters
    if (strlen($param_types) !== count($params)) {
        error_log("Parameter mismatch in staff/complaints.php: param_types length (" . strlen($param_types) . ") != params count (" . count($params) . ")");
        // Fix the param_types to match params count by using 's' for any extra params
        while (strlen($param_types) < count($params)) {
            $param_types .= 's';
        }
    }
    // Properly bind parameters with references
    $bindParams = [$param_types];
    foreach ($params as &$param) {
        $bindParams[] = &$param;
    }
    unset($param); // Break the reference
    call_user_func_array([$stmt, 'bind_param'], $bindParams);
}
$stmt->execute();
$result = $stmt->get_result();
$complaints = [];

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $complaints[] = $row;
    }
}

// Get total count for pagination
$count_stmt = $conn->prepare($count_sql);
$count_param_types = substr($param_types, 0, -2); // Remove last 2 characters
$count_params = array_slice($params, 0, count($params) - 2);
if (!empty($count_param_types) && !empty($count_params)) {
    // Properly bind count parameters with references
    $bindCountParams = [$count_param_types];
    foreach ($count_params as &$param) {
        $bindCountParams[] = &$param;
    }
    unset($param); // Break the reference
    call_user_func_array([$count_stmt, 'bind_param'], $bindCountParams);
}
$count_stmt->execute();
$count_result = $count_stmt->get_result();
$total_complaints = $count_result->fetch_assoc()['total'];
$total_pages = ceil($total_complaints / $limit);

// Get statistics for staff dashboard
$stats = [];
$sql_stats = "SELECT status, COUNT(*) as count
              FROM complaints
              WHERE department_id = ? AND assigned_to = ?
              GROUP BY status";
$stmt_stats = $conn->prepare($sql_stats);
$stmt_stats->bind_param("ii", $department_id, $_SESSION['user_id']);
$stmt_stats->execute();
$result_stats = $stmt_stats->get_result();
if ($result_stats) {
    while ($row = $result_stats->fetch_assoc()) {
        $stats[$row['status']] = $row;
    }
}
$stmt_stats->close();

$conn->close();

// Function to get appropriate action buttons for staff
function getStaffActionButtons($complaint) {
    $buttons = [];

    // View button is always available
    $buttons[] = [
        'url' => "track_complaint.php?tracking_id=" . urlencode($complaint['tracking_id']),
        'class' => 'action-btn-view',
        'text' => 'View',
        'target' => '_blank'
    ];

    // Action buttons based on status
    if ($complaint['status'] == 'assigned') {
        $buttons[] = [
            'url' => "complaint_actions.php?action=in_progress&id=" . $complaint['id'],
            'class' => 'action-btn-inprogress',
            'text' => 'Start Work'
        ];
    } elseif ($complaint['status'] == 'in_progress') {
        $buttons[] = [
            'url' => "complaint_actions.php?action=resolve&id=" . $complaint['id'],
            'class' => 'action-btn-resolve',
            'text' => 'Resolve'
        ];
        $buttons[] = [
            'url' => "complaint_actions.php?action=escalate&id=" . $complaint['id'],
            'class' => 'action-btn-escalate',
            'text' => 'Escalate'
        ];
    }

    return $buttons;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Complaints - Civicare Staff</title>
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
            padding: 12px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo-section {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .logo {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, #1e3c72, #2a5298);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 24px;
            font-weight: bold;
            text-decoration: none;
        }

        .logo-text h1 {
            font-size: 20px;
            color: #1e3c72;
            margin: 0;
        }

        .logo-text p {
            font-size: 11px;
            color: #666;
            margin: 2px 0 0 0;
        }

        /* ===== NAVIGATION ===== */
        .nav-links {
            display: flex;
            gap: 8px;
            align-items: center;
        }

        .nav-links a {
            display: inline-block;
            padding: 10px 16px;
            color: #333;
            text-decoration: none;
            font-weight: 500;
            border-radius: 6px;
            transition: all 0.2s;
            font-size: 0.9rem;
        }

        .nav-links a:hover {
            background: #f0f0f0;
            color: #1e3c72;
        }

        .nav-links a.active {
            background: #1e3c72;
            color: white;
        }

        .logout-btn {
            background: #ff6b35;
            color: white;
            padding: 8px 16px;
            border-radius: 6px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.2s;
        }

        .logout-btn:hover {
            background: #e55a24;
        }

        /* ===== CONTAINER ===== */
        .container {
            width: 90%;
            max-width: 1200px;
            margin: 30px auto;
            padding: 0 15px;
        }

        /* ===== TABLE SECTION ===== */
        .table-section {
            background: white;
            border-radius: 8px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            margin-bottom: 30px;
        }

        .table-header {
            background: linear-gradient(135deg, #1e3c72, #2a5298);
            color: white;
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .table-header h2 {
            font-size: 18px;
            margin: 0;
        }

        .table-info {
            font-size: 14px;
            opacity: 0.9;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            background: #f8f9fa;
            color: #1e3c72;
            padding: 12px 15px;
            text-align: left;
            font-weight: 600;
            font-size: 13px;
            border-bottom: 2px solid #eee;
        }

        td {
            padding: 12px 15px;
            border-bottom: 1px solid #eee;
            font-size: 14px;
        }

        tr:hover {
            background: #f8f9fa;
        }

        /* ===== STATUS BADGES ===== */
        .status {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            display: inline-block;
        }

        .status-assigned { background: #d1ecf1; color: #0c5460; }
        .status-in_progress { background: #cce5ff; color: #004085; }
        .status-resolved { background: #d4edda; color: #155724; }
        .status-escalated { background: #f8d7da; color: #721c24; }

        /* ===== ACTION BUTTONS ===== */
        .action-buttons {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .action-btn {
            padding: 6px 12px;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
            text-align: center;
            transition: all 0.2s;
        }

        .action-btn-view { background: #3498db; }
        .action-btn-view:hover { background: #2980b9; }

        .action-btn-inprogress { background: #9b59b6; }
        .action-btn-inprogress:hover { background: #8e44ad; }

        .action-btn-resolve { background: #27ae60; }
        .action-btn-resolve:hover { background: #219653; }

        .action-btn-escalate { background: #e67e22; }
        .action-btn-escalate:hover { background: #d35400; }

        /* ===== PAGINATION ===== */
        .pagination {
            display: flex;
            justify-content: center;
            gap: 8px;
            padding: 15px;
            background: #f8f9fa;
        }

        .pagination a, .pagination span {
            padding: 8px 14px;
            border: 1px solid #ddd;
            border-radius: 4px;
            text-decoration: none;
            color: #333;
            font-size: 14px;
        }

        .pagination a:hover {
            background: #1e3c72;
            color: white;
            border-color: #1e3c72;
        }

        .pagination .active {
            background: #1e3c72;
            color: white;
            border-color: #1e3c72;
        }

        .no-complaints {
            text-align: center;
            padding: 50px;
            color: #666;
        }

        .no-complaints i {
            font-size: 48px;
            color: #ccc;
            margin-bottom: 15px;
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

            .table-header {
                flex-direction: column;
                gap: 10px;
                text-align: center;
            }

            table {
                display: block;
                overflow-x: auto;
            }

            .action-buttons {
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
                    <p>Staff Portal - <?php echo htmlspecialchars($department_name); ?></p>
                </div>
            </div>

            <nav class="nav-links">
                <a href="dashboard.php">Dashboard</a>
                <a href="complaints.php" class="active">Complaints</a>
                
                <a href="logout.php" class="logout-btn">Logout</a>
            </nav>
        </div>
    </header>

    <div class="container">
        <!-- TABLE SECTION (DIRECT, NO HEADER, NO STATS, NO FILTERS) -->
        <div class="table-section">
            <div class="table-header">
                <h2><i class="fas fa-file-alt"></i> My Assigned Complaints</h2>
                <span class="table-info">
                    <strong><?php echo $total_complaints; ?></strong> complaints
                </span>
            </div>

            <?php if (!empty($complaints)): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Tracking ID</th>
                            <th>Title</th>
                            <th>Filed By</th>
                            <th>Status</th>
                            <th>Date Filed</th>
                            <th>Last Updated</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($complaints as $complaint):
                            $action_buttons = getStaffActionButtons($complaint);
                        ?>
                            <tr>
                                <td>
                                    <strong style="color: #ff6b35;"><?php echo htmlspecialchars($complaint['tracking_id']); ?></strong>
                                </td>
                                <td>
                                    <div style="max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                        <?php echo htmlspecialchars($complaint['title']); ?>
                                    </div>
                                </td>
                                <td>
                                    <div style="font-size: 13px;">
                                        <strong><?php echo htmlspecialchars($complaint['user_name']); ?></strong><br>
                                        <small style="color: #666;"><?php echo htmlspecialchars($complaint['user_email']); ?></small>
                                    </div>
                                </td>
                                <td>
                                    <span class="status status-<?php echo $complaint['status']; ?>">
                                        <?php echo getStatusText($complaint['status']); ?>
                                    </span>
                                </td>
                                <td><?php echo date('M d, Y', strtotime($complaint['created_at'])); ?></td>
                                <td><?php echo date('M d, Y', strtotime($complaint['updated_at'])); ?></td>
                                <td>
                                    <div class="action-buttons">
                                        <?php foreach ($action_buttons as $button): ?>
                                            <a href="<?php echo $button['url']; ?>"
                                               class="action-btn <?php echo $button['class']; ?>"
                                               <?php if (isset($button['target'])): ?>target="<?php echo $button['target']; ?>"<?php endif; ?>>
                                                <?php echo $button['text']; ?>
                                            </a>
                                        <?php endforeach; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <?php if ($total_pages > 1): ?>
                    <div class="pagination">
                        <?php if ($page > 1): ?>
                            <a href="?page=<?php echo $page - 1; ?>">Previous</a>
                        <?php endif; ?>

                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <?php if ($i == 1 || $i == $total_pages || ($i >= $page - 2 && $i <= $page + 2)): ?>
                                <a href="?page=<?php echo $i; ?>"
                                   class="<?php echo $i == $page ? 'active' : ''; ?>">
                                    <?php echo $i; ?>
                                </a>
                            <?php elseif ($i == $page - 3 || $i == $page + 3): ?>
                                <span>...</span>
                            <?php endif; ?>
                        <?php endfor; ?>

                        <?php if ($page < $total_pages): ?>
                            <a href="?page=<?php echo $page + 1; ?>">Next</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

            <?php else: ?>
                <div class="no-complaints">
                    <i class="fas fa-inbox"></i>
                    <p>No complaints assigned to you at the moment.</p>
                    <p style="color: #888; margin-top: 10px;">New complaints will appear here when assigned to you.</p>
                </div>
            <?php endif; ?>
        </div>
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