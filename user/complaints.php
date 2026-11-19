<?php
// civicare/user/complaints.php
session_start(); // Add session start

require_once '../config/database.php';
require_once '../config/functions.php';

// Check if user is logged in and is citizen
if (!isLoggedIn() || !hasRole('citizen')) {
    header('Location: login.php');
    exit();
}

$conn = getDatabaseConnection();

// Check connection
if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}

$user_id = $_SESSION['user_id'];

// Initialize filter variables - keeping them for functionality but not displaying
$status_filter = isset($_GET['status']) ? sanitizeInput($_GET['status']) : '';
$search_query = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Build SQL query for citizen
$sql = "SELECT c.*, d.name as department_name 
        FROM complaints c 
        JOIN departments d ON c.department_id = d.id 
        WHERE c.user_id = ?";
$count_sql = "SELECT COUNT(*) as total FROM complaints WHERE user_id = ?";
$params = [$user_id];
$param_types = "i";

// Apply filters
if ($status_filter && $status_filter !== 'all') {
    $sql .= " AND c.status = ?";
    $count_sql .= " AND c.status = ?";
    $params[] = $status_filter;
    $param_types .= "s";
}

if ($search_query) {
    $sql .= " AND (c.title LIKE ? OR c.tracking_id LIKE ? OR c.description LIKE ?)";
    $count_sql .= " AND (c.title LIKE ? OR c.tracking_id LIKE ? OR c.description LIKE ?)";
    $search_param = "%$search_query%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $param_types .= "sss";
}

// Store parameters for count query (without pagination params)
$count_params = $params;
$count_param_types = $param_types;

// Add ordering and pagination to main query
$sql .= " ORDER BY c.created_at DESC LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;
$param_types .= "ii";

// Prepare and execute main query
$complaints = [];
if ($stmt = $conn->prepare($sql)) {
    if (!empty($params)) {
        // bind_param requires references; use call_user_func_array with references for compatibility
        $bind_params = [];
        foreach ($params as $key => $value) {
            $bind_params[$key] = &$params[$key];
        }
        array_unshift($bind_params, $param_types);
        call_user_func_array([$stmt, 'bind_param'], $bind_params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $complaints[] = $row;
        }
    } else {
        echo "Error executing query: " . $conn->error;
    }
    $stmt->close();
} else {
    echo "Error preparing query: " . $conn->error;
}

// Get total count for pagination
$total_complaints = 0;
if ($count_stmt = $conn->prepare($count_sql)) {
    if (!empty($count_params)) {
        $bind_params = [];
        foreach ($count_params as $key => $value) {
            $bind_params[$key] = &$count_params[$key];
        }
        array_unshift($bind_params, $count_param_types);
        call_user_func_array([$count_stmt, 'bind_param'], $bind_params);
    }
    $count_stmt->execute();
    $count_result = $count_stmt->get_result();
    
    if ($count_row = $count_result->fetch_assoc()) {
        $total_complaints = $count_row['total'];
    }
    $count_stmt->close();
}

$total_pages = ceil($total_complaints / $limit);

// Get status counts for citizen - keeping for functionality but not displaying
$status_counts = [];
$sql_status = "SELECT status, COUNT(*) as count FROM complaints WHERE user_id = ? GROUP BY status";
if ($stmt_status = $conn->prepare($sql_status)) {
    $stmt_status->bind_param("i", $user_id);
    $stmt_status->execute();
    $result_status = $stmt_status->get_result();
    
    while ($row = $result_status->fetch_assoc()) {
        $status_counts[$row['status']] = $row['count'];
    }
    $stmt_status->close();
}

$conn->close();

// Helper function to get status text (add this if not in functions.php)
if (!function_exists('getStatusText')) {
    function getStatusText($status) {
        $status_map = [
            'pending' => 'Pending',
            'validated' => 'Validated',
            'rejected' => 'Rejected',
            'assigned' => 'Assigned',
            'in_progress' => 'In Progress',
            'resolved' => 'Resolved',
            'escalated' => 'Escalated',
            'closed' => 'Closed'
        ];
        return $status_map[$status] ?? ucfirst($status);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Complaints - Civicare</title>
    <style>
        /* ===== MUNICIPAL GOVERNMENT STYLING ===== */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f8f9fa;
            line-height: 1.6;
            color: #333;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }

        /* ===== HEADER STYLING ===== */
        .header {
            background: white;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .container {
            width: 90%;
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 15px;
            flex: 1;
        }

        .navbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 0;
        }

        .logo {
            font-size: 24px;
            font-weight: bold;
            color: #1e3c72;
            text-decoration: none;
            transition: color 0.3s;
        }

        .logo:hover {
            color: #ff6b35;
        }

        .nav-links {
            display: flex;
            gap: 20px;
            align-items: center;
        }

        .nav-links a {
            color: #333;
            text-decoration: none;
            padding: 8px 12px;
            border-radius: 4px;
            font-weight: 500;
            transition: all 0.3s;
            font-size: 0.95rem;
        }

        .nav-links a:hover {
            background: #f0f0f0;
            color: #1e3c72;
        }

        /* ===== BUTTONS ===== */
        .btn {
            display: inline-block;
            padding: 10px 24px;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            font-weight: 600;
            transition: all 0.3s;
            border: none;
            cursor: pointer;
            font-size: 0.95rem;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(255, 107, 53, 0.3);
        }

        .btn-primary {
            background: #ff6b35;
            color: white;
        }

        .btn-primary:hover {
            background: #e55a24;
        }

        /* ===== MESSAGE ALERTS ===== */
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin: 20px 0;
        }

        .alert-success {
            background: #e8f6ef;
            color: #1e7e34;
            border-left: 4px solid #27ae60;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border-left: 4px solid #e74c3c;
        }

        /* ===== TABLE SECTION ===== */
        .complaints-table {
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.1);
            margin: 30px 0;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            background: linear-gradient(135deg, #1e3c72, #2a5298);
            color: white;
            padding: 15px;
            text-align: left;
            font-weight: 600;
            font-size: 14px;
        }

        td {
            padding: 15px;
            border-bottom: 1px solid #eee;
            font-size: 14px;
        }

        tr:hover {
            background: #f8f9fa;
        }

        .table-header {
            padding: 15px;
            background: #f8f9fa;
            border-bottom: 1px solid #eee;
            font-weight: 600;
            color: #1e3c72;
            font-size: 14px;
        }

        /* ===== STATUS BADGES ===== */
        .status {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status-pending { background: #fff3cd; color: #856404; }
        .status-validated { background: #cce5ff; color: #004085; }
        .status-rejected { background: #f8d7da; color: #721c24; }
        .status-assigned { background: #d1ecf1; color: #0c5460; }
        .status-in_progress { background: #cce5ff; color: #004085; }
        .status-resolved { background: #d4edda; color: #155724; }
        .status-escalated { background: #ffeaa7; color: #d63031; }
        .status-closed { background: #e2e3e5; color: #383d41; }



        /* ===== ACTION BUTTONS ===== */
        .action-btn {
            padding: 8px 16px;
            background: #2a5298;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
            transition: all 0.3s;
            border: none;
            cursor: pointer;
        }

        .action-btn:hover {
            background: #1e3c72;
            transform: translateY(-2px);
            box-shadow: 0 2px 8px rgba(30, 60, 114, 0.3);
        }

        /* ===== PAGINATION ===== */
        .pagination {
            display: flex;
            justify-content: center;
            gap: 5px;
            padding: 20px;
            background: #f8f9fa;
            border-top: 1px solid #eee;
        }

        .pagination a, .pagination span {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            text-decoration: none;
            color: #333;
            font-size: 14px;
            transition: all 0.3s;
        }

        .pagination a:hover {
            background: #ff6b35;
            color: white;
            border-color: #ff6b35;
            transform: translateY(-2px);
        }

        .pagination .active {
            background: #ff6b35;
            color: white;
            border-color: #ff6b35;
        }

        /* ===== NO COMPLAINTS ===== */
        .no-complaints {
            text-align: center;
            padding: 50px;
            color: #666;
        }

        .no-complaints p {
            font-size: 16px;
            margin-bottom: 15px;
        }

        /* ===== FOOTER ===== */
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

        /* ===== RESPONSIVE DESIGN ===== */
        @media (max-width: 768px) {
            .navbar {
                flex-direction: column;
                gap: 15px;
                padding: 10px 0;
            }

            .nav-links {
                flex-wrap: wrap;
                justify-content: center;
                gap: 10px;
            }

            .nav-links a {
                padding: 6px 10px;
                font-size: 0.85rem;
            }

            table {
                display: block;
                overflow-x: auto;
            }

            th, td {
                padding: 12px;
                font-size: 12px;
            }

            .action-btn {
                padding: 6px 12px;
                font-size: 11px;
            }
        }

        @media (max-width: 480px) {
            .navbar {
                padding: 8px 0;
            }

            .nav-links {
                gap: 8px;
            }

            .nav-links a,
            .nav-links .btn {
                padding: 8px 10px;
                font-size: 0.8rem;
            }

            .table-header {
                font-size: 12px;
            }

            th, td {
                padding: 10px;
                font-size: 11px;
            }

            .pagination {
                padding: 15px;
            }

            .pagination a, .pagination span {
                padding: 6px 10px;
                font-size: 12px;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="container">
            <nav class="navbar">
                <a href="../index.php" class="logo">Civicare</a>
                <div class="nav-links">
                    <a href="../index.php">Home</a>
                    <a href="../about.php">About</a>
                    <a href="../user/track_complaint.php">Track Complaint</a>
                    <a href="new_complaint.php" class="btn">File Complaint</a>
                    <a href="dashboard.php" class="btn-primary">Dashboard</a>
                    <a href="../logout.php" class="btn">Logout</a>
                </div>
            </nav>
        </div>
    </div>

    <div class="container">
        <div class="complaints-table">
            <div class="table-header">
                <strong><?php echo $total_complaints; ?></strong> complaints found
            </div>
            
            <?php if (!empty($complaints)): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Tracking ID</th>
                            <th>Title</th>
                            <th>Department</th>
                            <th>Status</th>
                            <th>Date Filed</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($complaints as $complaint): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($complaint['tracking_id']); ?></strong>
                                </td>
                                <td>
                                    <div style="max-width: 250px; overflow: hidden; text-overflow: ellipsis;">
                                        <?php echo htmlspecialchars($complaint['title']); ?>
                                    </div>
                                </td>
                                <td><?php echo htmlspecialchars($complaint['department_name']); ?></td>
                                <td>
                                    <span class="status status-<?php echo $complaint['status']; ?>">
                                        <?php echo getStatusText($complaint['status']); ?>
                                    </span>
                                </td>
                                <td><?php echo date('M d, Y', strtotime($complaint['created_at'])); ?></td>
                                <td>
                                    <a href="../user/track_complaint.php?tracking_id=<?php echo urlencode($complaint['tracking_id']); ?>" 
                                       class="action-btn">View</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <?php if ($total_pages > 1): ?>
                    <div class="pagination">
                        <?php if ($page > 1): ?>
                            <a href="?page=<?php echo $page - 1; ?>&status=<?php echo $status_filter; ?>&search=<?php echo urlencode($search_query); ?>">Previous</a>
                        <?php endif; ?>
                        
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <?php if ($i == 1 || $i == $total_pages || ($i >= $page - 2 && $i <= $page + 2)): ?>
                                <a href="?page=<?php echo $i; ?>&status=<?php echo $status_filter; ?>&search=<?php echo urlencode($search_query); ?>"
                                   class="<?php echo $i == $page ? 'active' : ''; ?>">
                                    <?php echo $i; ?>
                                </a>
                            <?php elseif ($i == $page - 3 || $i == $page + 3): ?>
                                <span>...</span>
                            <?php endif; ?>
                        <?php endfor; ?>
                        
                        <?php if ($page < $total_pages): ?>
                            <a href="?page=<?php echo $page + 1; ?>&status=<?php echo $status_filter; ?>&search=<?php echo urlencode($search_query); ?>">Next</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                
            <?php else: ?>
                <div class="no-complaints">
                    <p>No complaints found<?php echo $status_filter || $search_query ? ' with the current filters' : ''; ?>.</p>
                    <a href="new_complaint.php" class="btn btn-primary" style="margin-top: 1rem;">File Your First Complaint</a>
                </div>
            <?php endif; ?>
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