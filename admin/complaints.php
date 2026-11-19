<?php
// civicare/admin/complaints.php
require_once '../config/database.php';
require_once '../config/functions.php';

// Check if user is logged in and is admin
if (!isLoggedIn() || (!hasRole('admin') && !hasRole('staff'))) {
    header('Location: login.php');
    exit();
}
$conn = getDatabaseConnection();

$admin_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'];

// Initialize filter variables
$status_filter = isset($_GET['status']) ? sanitizeInput($_GET['status']) : '';
$department_filter = isset($_GET['department']) ? (int)$_GET['department'] : 0;
$search_query = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';
$assigned_to_filter = isset($_GET['assigned_to']) ? (int)$_GET['assigned_to'] : 0;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = 15;
$offset = ($page - 1) * $limit;

// Build SQL query for admin
$sql = "SELECT c.*, 
               u.full_name as user_name, 
               u.email as user_email,
               d.name as department_name,
               staff.full_name as staff_name
        FROM complaints c 
        JOIN users u ON c.user_id = u.id 
        JOIN departments d ON c.department_id = d.id 
        LEFT JOIN users staff ON c.assigned_to = staff.id 
        WHERE 1=1";
        
$count_sql = "SELECT COUNT(*) as total FROM complaints c WHERE 1=1";
$params = [];
$param_types = "";

// Apply filters
if ($status_filter && $status_filter !== 'all') {
    $sql .= " AND c.status = ?";
    $count_sql .= " AND c.status = ?";
    $params[] = $status_filter;
    $param_types .= "s";
}

if ($department_filter > 0) {
    $sql .= " AND c.department_id = ?";
    $count_sql .= " AND c.department_id = ?";
    $params[] = $department_filter;
    $param_types .= "i";
}

if ($assigned_to_filter > 0) {
    $sql .= " AND c.assigned_to = ?";
    $count_sql .= " AND c.assigned_to = ?";
    $params[] = $assigned_to_filter;
    $param_types .= "i";
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
$sql .= " ORDER BY c.created_at DESC LIMIT ? OFFSET ?";
          
$params[] = $limit;
$params[] = $offset;
$param_types .= "ii";

// Prepare and execute query
$stmt = $conn->prepare($sql);
if ($stmt === false) {
    error_log("Prepare failed in complaints.php: " . $conn->error);
    $complaints = [];
} elseif ($params && $param_types) {
    // Validate that the number of type specifiers matches the number of parameters
    if (strlen($param_types) !== count($params)) {
        error_log("Parameter mismatch in complaints.php: param_types length (" . strlen($param_types) . ") != params count (" . count($params) . ")");
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
    $stmt->execute();
    $result = $stmt->get_result();
    $complaints = [];

    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $complaints[] = $row;
        }
    }
} else {
    $result = false;
    $complaints = [];
}

// Get total count for pagination
$total_complaints = 0; // Default value
$count_stmt = $conn->prepare($count_sql);
if ($count_stmt !== false) {
    // Extract params without LIMIT and OFFSET
    $params_count = array_slice($params, 0, count($params) - 2);
    $count_param_types = count($params_count) > 0 ? rtrim($param_types, 'ii') : '';

    if (count($params_count) > 0 && $count_param_types) {
        // Validate that the number of type specifiers matches the number of parameters
        if (strlen($count_param_types) !== count($params_count)) {
            error_log("Parameter mismatch in complaints.php (count query): param_types length (" . strlen($count_param_types) . ") != params count (" . count($params_count) . ")");
            // Fix the count_param_types to match params_count count by using 's' for any extra params
            while (strlen($count_param_types) < count($params_count)) {
                $count_param_types .= 's';
            }
        }
        // Properly bind count parameters with references
        $bindCountParams = [$count_param_types];
        foreach ($params_count as &$param) {
            $bindCountParams[] = &$param;
        }
        unset($param); // Break the reference
        call_user_func_array([$count_stmt, 'bind_param'], $bindCountParams);
        $count_stmt->execute();
        $count_result = $count_stmt->get_result();
        if ($count_result && $count_result->num_rows > 0) {
            $total_complaints = $count_result->fetch_assoc()['total'];
        }
    } else {
        // No params to bind, just execute
        $count_stmt->execute();
        $count_result = $count_stmt->get_result();
        if ($count_result && $count_result->num_rows > 0) {
            $total_complaints = $count_result->fetch_assoc()['total'];
        }
    }
}
$total_pages = ceil($total_complaints / $limit);

// Get departments for filter dropdown
$departments = getAllDepartments($conn);

// Get staff members for assigned_to filter
$staff_members = [];
$sql_staff = "SELECT id, full_name, email FROM users WHERE role IN ('staff', 'admin') ORDER BY full_name";
$result_staff = $conn->query($sql_staff);
if ($result_staff && $result_staff->num_rows > 0) {
    while ($row = $result_staff->fetch_assoc()) {
        $staff_members[] = $row;
    }
}

// Get statistics for dashboard
$stats = [];
$sql_stats = "SELECT 
                status, 
                COUNT(*) as count,
                AVG(TIMESTAMPDIFF(DAY, created_at, IFNULL(resolution_date, NOW()))) as avg_days
              FROM complaints 
              GROUP BY status";
$result_stats = $conn->query($sql_stats);
if ($result_stats) {
    while ($row = $result_stats->fetch_assoc()) {
        $stats[$row['status']] = $row;
    }
}

$conn->close();

// Function to get appropriate action buttons based on role and complaint status
function getActionButtons($complaint, $user_role) {
    $buttons = [];
    $is_admin = ($user_role === 'admin');
    $is_staff = ($user_role === 'staff');
    
    // View button is always available
    $buttons[] = [
        'url' => "track_complaint.php?tracking_id=" . urlencode($complaint['tracking_id']),
        'class' => 'action-link',
        'text' => 'View',
        'target' => '_blank'
    ];
    
    $status = $complaint['status'];
    
    // PENDING STATUS - Can validate or reject
    if ($status === 'pending') {
        if ($is_admin) {
            $buttons[] = [
                'url' => "complaint_actions.php?action=validate&id=" . $complaint['id'],
                'class' => 'action-link resolve',
                'text' => 'Validate'
            ];
            $buttons[] = [
                'url' => "complaint_actions.php?action=reject&id=" . $complaint['id'],
                'class' => 'action-link escalate',
                'text' => 'Reject'
            ];
        }
    }
    
    // VALIDATED STATUS - Can assign or escalate
    elseif ($status === 'validated') {
        if ($is_admin) {
            $buttons[] = [
                'url' => "complaint_actions.php?action=assign&id=" . $complaint['id'],
                'class' => 'action-link reassign',
                'text' => 'Assign'
            ];
            // Admin can escalate validated complaints
            $buttons[] = [
                'url' => "complaint_actions.php?action=escalate&id=" . $complaint['id'],
                'class' => 'action-link escalate',
                'text' => 'Escalate'
            ];
        }
    }
    
    // ASSIGNED STATUS - Staff can mark in progress, Admin can escalate
    elseif ($status === 'assigned') {
        if ($is_staff) {
            // Staff assigned to complaint can mark in progress
            if ($complaint['assigned_to'] == $_SESSION['user_id']) {
                $buttons[] = [
                    'url' => "complaint_actions.php?action=in_progress&id=" . $complaint['id'],
                    'class' => 'action-link resolve',
                    'text' => 'In Progress'
                ];
            }
        }
        if ($is_admin) {
            // Admin can escalate assigned complaints
            $buttons[] = [
                'url' => "complaint_actions.php?action=escalate&id=" . $complaint['id'],
                'class' => 'action-link escalate',
                'text' => 'Escalate'
            ];
        }
    }
    
    // IN_PROGRESS STATUS - Staff can resolve, Admin can escalate
    elseif ($status === 'in_progress') {
        if ($is_staff) {
            // Staff can resolve if assigned to them
            if ($complaint['assigned_to'] == $_SESSION['user_id']) {
                $buttons[] = [
                    'url' => "complaint_actions.php?action=resolve&id=" . $complaint['id'],
                    'class' => 'action-link resolve',
                    'text' => 'Resolve'
                ];
            }
        }
        if ($is_admin) {
            // Admin can escalate in-progress complaints
            $buttons[] = [
                'url' => "complaint_actions.php?action=escalate&id=" . $complaint['id'],
                'class' => 'action-link escalate',
                'text' => 'Escalate'
            ];
        }
    }
    
    // ESCALATED STATUS - Only admin can handle
    elseif ($status === 'escalated') {
        if ($is_admin) {
            $buttons[] = [
                'url' => "complaint_actions.php?action=reassign&id=" . $complaint['id'],
                'class' => 'action-link reassign',
                'text' => 'Reassign'
            ];
            $buttons[] = [
                'url' => "complaint_actions.php?action=in_progress&id=" . $complaint['id'],
                'class' => 'action-link resolve',
                'text' => 'In Progress'
            ];
            $buttons[] = [
                'url' => "complaint_actions.php?action=resolve&id=" . $complaint['id'],
                'class' => 'action-link resolve',
                'text' => 'Resolve'
            ];
        }
    }
    
    // RESOLVED STATUS - Admin can close
    elseif ($status === 'resolved') {
        if ($is_admin) {
            $buttons[] = [
                'url' => "complaint_actions.php?action=close&id=" . $complaint['id'],
                'class' => 'action-link',
                'text' => 'Close'
            ];
        }
    }
    
    return $buttons;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Complaints Management - CiviCare</title>
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

        .admin-badge {
            background: rgba(255, 107, 53, 0.1);
            color: #1e3c72;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
            border: 1px solid rgba(255, 107, 53, 0.2);
        }

        .admin-badge.super-admin {
            background: rgba(230, 126, 34, 0.1);
            color: #e67e22;
            border-color: rgba(230, 126, 34, 0.2);
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
            position: relative;
            overflow: hidden;
        }

        .nav-links a::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 107, 53, 0.1), transparent);
            transition: left 0.5s;
        }

        .nav-links a:hover::before {
            left: 100%;
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
            border: none;
            cursor: pointer;
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

        /* ===== WELCOME SECTION ===== */
        .welcome-panel {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            color: white;
            padding: 20px 25px;
            border-radius: 8px;
            margin-bottom: 30px;
            box-shadow: 0 4px 15px rgba(30, 60, 114, 0.3);
            position: relative;
            overflow: hidden;
        }

        .welcome-panel::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 150px;
            height: 150px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            transform: translate(50%, -50%);
        }

        .welcome-info {
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: relative;
            z-index: 1;
        }

        .welcome-text h2 {
            font-size: 24px;
            margin-bottom: 5px;
            font-weight: 700;
        }

        .welcome-text p {
            font-size: 14px;
            opacity: 0.95;
            margin: 0;
        }

        /* ===== STATS GRID ===== */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            padding: 15px;
            border-radius: 8px;
            text-align: center;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            transition: all 0.3s;
            border: 1px solid rgba(255, 255, 255, 0.2);
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(42, 82, 152, 0.1) 0%, rgba(255, 107, 53, 0.1) 100%);
            opacity: 0;
            transition: opacity 0.3s;
        }

        .stat-card:hover::before {
            opacity: 1;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
            background: linear-gradient(135deg, #ffffff 0%, #f1f3f4 100%);
        }

        .stat-icon {
            font-size: 20px;
            margin-bottom: 8px;
            opacity: 0.8;
            position: relative;
            z-index: 1;
        }

        .stat-card h3 {
            font-size: 20px;
            color: #1e3c72;
            margin-bottom: 5px;
            font-weight: 700;
            position: relative;
            z-index: 1;
        }

        .stat-card p {
            color: #666;
            font-size: 12px;
            font-weight: 500;
            position: relative;
            z-index: 1;
        }

        .stat-card.escalated {
            background: linear-gradient(135deg, #ffeaea 0%, #ffcccc 100%);
            border: 1px solid rgba(231, 76, 60, 0.2);
        }

        .stat-card.escalated .stat-icon {
            color: #e74c3c;
        }

        .stat-card.escalated h3 {
            color: #e74c3c;
        }

        /* ===== FILTERS SECTION ===== */
        .filters-section {
            background: white;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        .filters-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .filter-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
            font-size: 14px;
        }

        .filter-group select, 
        .filter-group input {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 6px;
            font-size: 14px;
            transition: all 0.3s;
            background: white;
        }

        .filter-group select:focus, 
        .filter-group input:focus {
            outline: none;
            border-color: #2a5298;
            box-shadow: 0 0 0 3px rgba(42, 82, 152, 0.1);
        }

        .filter-actions {
            display: flex;
            gap: 12px;
            justify-content: flex-end;
        }

        .btn {
            display: inline-block;
            padding: 12px 24px;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 600;
            transition: all 0.3s;
            border: none;
            cursor: pointer;
            font-size: 14px;
        }

        .btn-primary {
            background: linear-gradient(135deg, #1e3c72, #2a5298);
            color: white;
            box-shadow: 0 4px 12px rgba(30, 60, 114, 0.3);
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, #2a5298, #1e3c72);
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(30, 60, 114, 0.4);
        }

        .btn-secondary {
            background: linear-gradient(135deg, #6c757d, #5a6268);
            color: white;
            box-shadow: 0 4px 12px rgba(108, 117, 125, 0.3);
        }

        .btn-secondary:hover {
            background: linear-gradient(135deg, #5a6268, #495057);
            transform: translateY(-2px);
        }

        /* ===== COMPLAINTS TABLE ===== */
        .complaints-section {
            background: white;
            border-radius: 12px;
            box-shadow: 0 6px 15px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            margin-bottom: 30px;
        }

        .section-header {
            background: linear-gradient(135deg, #1e3c72, #2a5298);
            color: white;
            padding: 25px 30px;
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
            padding: 18px 15px;
            text-align: left;
            font-weight: 600;
            font-size: 14px;
            border-bottom: 2px solid #eee;
            white-space: nowrap;
        }

        td {
            padding: 15px;
            border-bottom: 1px solid #eee;
            font-size: 14px;
        }

        .actions-cell {
            white-space: nowrap;
            min-width: 180px;
        }

        tr:hover {
            background: #f8f9fa;
            transition: all 0.2s;
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
        .status-rejected { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .status-assigned { background: #d1ecf1; color: #0c5460; border: 1px solid #a3d9e6; }
        .status-in_progress { background: #cce5ff; color: #004085; border: 1px solid #99d6ff; }
        .status-resolved { background: #d4edda; color: #155724; border: 1px solid #b8e6b3; }
        .status-escalated { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .status-closed { background: #e2e3e5; color: #383d41; border: 1px solid #d6d8db; }

        /* ===== ACTION LINKS ===== */
        .action-link {
            color: #1e3c72;
            text-decoration: none;
            font-weight: 600;
            margin-bottom: 4px;
            transition: all 0.3s;
            padding: 6px 10px;
            border-radius: 4px;
            border: none;
            background: none;
            cursor: pointer;
            font-size: 12px;
            display: block;
            width: 100%;
            text-align: left;
        }

        .actions-cell {
            white-space: nowrap;
            min-width: 120px;
            padding: 12px !important;
        }

        .action-link:hover {
            color: #ff6b35;
            background: rgba(255, 107, 53, 0.1);
            transform: translateY(-1px);
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

        .action-link.reassign {
            color: #3498db;
        }

        .action-link.reassign:hover {
            background: rgba(52, 152, 219, 0.1);
        }

        /* ===== BULK ACTIONS ===== */
        .bulk-actions {
            display: flex;
            gap: 12px;
            align-items: center;
        }

        .bulk-select {
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }

        .export-btn {
            padding: 12px 24px;
            background: linear-gradient(135deg, #27ae60, #219653);
            color: white;
            border: none;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(39, 174, 96, 0.3);
        }

        .export-btn:hover {
            background: linear-gradient(135deg, #219653, #1e8449);
            transform: translateY(-2px);
        }

        /* ===== PAGINATION ===== */
        .pagination {
            display: flex;
            justify-content: center;
            gap: 8px;
            padding: 20px;
            background: #f8f9fa;
        }

        .pagination a, .pagination span {
            padding: 8px 14px;
            border: 1px solid #ddd;
            border-radius: 4px;
            text-decoration: none;
            color: #333;
            font-size: 14px;
            transition: all 0.3s;
        }

        .pagination a:hover {
            background: linear-gradient(135deg, #1e3c72, #2a5298);
            color: white;
            border-color: #1e3c72;
        }

        .pagination .active {
            background: linear-gradient(135deg, #1e3c72, #2a5298);
            color: white;
            border-color: #1e3c72;
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

        /* ===== ANIMATIONS ===== */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .welcome-panel, .stat-card, .complaints-section, .filters-section {
            animation: fadeInUp 0.6s ease-out;
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

        .footer-bottom {
            border-top: 1px solid #333;
            padding-top: 10px;
            text-align: center;
            color: #aaa;
            font-size: 11px;
            max-width: 1200px;
            margin: 0 auto;
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

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 12px;
            }

            .welcome-info {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }

            .filters-grid {
                grid-template-columns: 1fr;
            }

            .filter-actions {
                flex-direction: column;
            }

            table {
                display: block;
                overflow-x: auto;
            }

            .section-header {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }

            .bulk-actions {
                flex-direction: column;
                align-items: stretch;
            }
        }

        @media (max-width: 480px) {
            .stats-grid {
                grid-template-columns: 1fr;
                gap: 10px;
            }

            .welcome-text h2 {
                font-size: 18px;
            }

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

            .admin-badge {
                font-size: 12px;
                padding: 6px 12px;
            }
        }
    </style>
</head>
<body>
    <!-- MAIN HEADER -->
    <header class="main-header">
        <div class="header-content">
            <div class="logo-section">
                <a href="index.php" class="logo">
                    <i class="fas fa-city"></i>
                </a>
                <div class="logo-text">
                    <h1>CIVICARE</h1>
                    <p>Admin Portal</p>
                </div>
            </div>

            <nav class="nav-links">
                <a href="index.php">Dashboard</a>
                <a href="complaints.php" class="active">Complaints</a>
                <a href="users.php">Users</a>
                <a href="staff.php">Staff</a>
                <a href="departments.php">Departments</a>
              
                <a href="../logout.php" class="logout-btn">Logout</a>
            </nav>
        </div>
    </header>

    <div class="container">
        <!-- COMPLAINTS TABLE -->
        <div class="complaints-section">
            <div class="section-header">
                <div>
                    <h2>All Complaints</h2>
                    <span style="font-size: 14px; opacity: 0.9;"><?php echo $total_complaints; ?> complaints found</span>
                </div>
                <div class="bulk-actions">
                    <select class="bulk-select" id="bulkAction">
                        <option value="">Bulk Actions</option>
                        <?php if (isSuperAdmin()): ?>
                            <option value="reassign">Reassign Selected</option>
                            <option value="resolve">Resolve Selected</option>
                        <?php endif; ?>
                        <option value="validate">Validate Selected</option>
                        <option value="assign">Assign to Staff</option>
                        <option value="reject">Reject Selected</option>
                        <?php if ($user_role === 'admin'): ?>
                            <option value="escalate">Escalate Selected</option>
                        <?php endif; ?>
                    </select>
                    <button class="btn btn-primary" onclick="applyBulkAction()">Apply</button>
                    <button class="export-btn" onclick="exportToCSV()">Export CSV</button>
                </div>
            </div>

            <?php if (!empty($complaints)): ?>
                <table>
                    <thead>
                        <tr>
                            <th width="30"><input type="checkbox" id="selectAll"></th>
                            <th>Tracking ID</th>
                            <th>Title</th>
                            <th>Filed By</th>
                            <th>Department</th>
                            <th>Status</th>
                            <th>Assigned To</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($complaints as $complaint): 
                            $action_buttons = getActionButtons($complaint, $user_role);
                        ?>
                            <tr>
                                <td><input type="checkbox" class="complaint-checkbox" value="<?php echo $complaint['id']; ?>"></td>
                                <td>
                                    <span class="tracking-id"><?php echo htmlspecialchars($complaint['tracking_id']); ?></span>
                                </td>
                                <td>
                                    <span class="complaint-title"><?php echo htmlspecialchars($complaint['title']); ?></span>
                                </td>
                                <td>
                                    <div style="font-size: 13px;">
                                        <strong><?php echo htmlspecialchars($complaint['user_name']); ?></strong><br>
                                        <small style="color: #666;"><?php echo htmlspecialchars($complaint['user_email']); ?></small>
                                    </div>
                                </td>
                                <td><?php echo htmlspecialchars($complaint['department_name']); ?></td>
                                <td>
                                    <span class="status status-<?php echo $complaint['status']; ?>">
                                        <?php echo getStatusText($complaint['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($complaint['staff_name']): ?>
                                        <?php echo htmlspecialchars($complaint['staff_name']); ?>
                                    <?php else: ?>
                                        <span style="color: #95a5a6; font-style: italic;">Not Assigned</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo date('M d, Y', strtotime($complaint['created_at'])); ?></td>
                                <td class="actions-cell">
                                    <?php foreach ($action_buttons as $button): ?>
                                        <a href="<?php echo $button['url']; ?>" 
                                           class="<?php echo $button['class']; ?>"
                                           <?php if (isset($button['target'])): ?>target="<?php echo $button['target']; ?>"<?php endif; ?>>
                                            <?php echo $button['text']; ?>
                                        </a>
                                    <?php endforeach; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <?php if ($total_pages > 1): ?>
                    <div class="pagination">
                        <?php if ($page > 1): ?>
                            <a href="?page=<?php echo $page - 1; ?>&status=<?php echo $status_filter; ?>&department=<?php echo $department_filter; ?>&assigned_to=<?php echo $assigned_to_filter; ?>&search=<?php echo urlencode($search_query); ?>">Previous</a>
                        <?php endif; ?>
                        
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <?php if ($i == 1 || $i == $total_pages || ($i >= $page - 2 && $i <= $page + 2)): ?>
                                <a href="?page=<?php echo $i; ?>&status=<?php echo $status_filter; ?>&department=<?php echo $department_filter; ?>&assigned_to=<?php echo $assigned_to_filter; ?>&search=<?php echo urlencode($search_query); ?>"
                                   class="<?php echo $i == $page ? 'active' : ''; ?>">
                                    <?php echo $i; ?>
                                </a>
                            <?php elseif ($i == $page - 3 || $i == $page + 3): ?>
                                <span>...</span>
                            <?php endif; ?>
                        <?php endfor; ?>
                        
                        <?php if ($page < $total_pages): ?>
                            <a href="?page=<?php echo $page + 1; ?>&status=<?php echo $status_filter; ?>&department=<?php echo $department_filter; ?>&assigned_to=<?php echo $assigned_to_filter; ?>&search=<?php echo urlencode($search_query); ?>">Next</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                
            <?php else: ?>
                <div class="no-complaints">
                    <i class="fas fa-inbox"></i>
                    <p>No complaints found<?php echo $status_filter || $department_filter || $search_query ? ' with the current filters' : ''; ?>.</p>
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
            </div>
            <div class="footer-section">
                <h4>Quick Links</h4>
                <ul>
                    <li><a href="../index.php">Home</a></li>
                    <li><a href="../about.php">About Us</a></li>
                    <li><a href="login.php">Admin Login</a></li>
                    <li><a href="../user/login.php">Citizen Login</a></li>
                </ul>
            </div>
            <div class="footer-section">
                <h4>Contact Us</h4>
                <p>Municipal Corp Building, City Center</p>
                <p>+(91) 9876543210</p>
                <p>info@civicare.gov.in</p>
            </div>
        </div>
        <div class="footer-bottom">
            <p>&copy; <?php echo date('Y'); ?> CiviCare - Municipal Complaint Management System. All rights reserved.</p>
        </div>
    </footer>

    <script>
        // Select all checkbox
        document.getElementById('selectAll').addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('.complaint-checkbox');
            checkboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
        });

        // Bulk action handler
        function applyBulkAction() {
            const action = document.getElementById('bulkAction').value;
            if (!action) {
                alert('Please select an action');
                return;
            }

            const selectedIds = [];
            document.querySelectorAll('.complaint-checkbox:checked').forEach(checkbox => {
                selectedIds.push(checkbox.value);
            });

            if (selectedIds.length === 0) {
                alert('Please select at least one complaint');
                return;
            }

            if (confirm(`Are you sure you want to ${action} ${selectedIds.length} complaint(s)?`)) {
                window.location.href = `complaint_actions.php?action=bulk_${action}&ids=${selectedIds.join(',')}`;
            }
        }

        // Export to CSV
        function exportToCSV() {
            const params = new URLSearchParams(window.location.search);
            window.location.href = 'export_data.php?' + params.toString();
        }

        // Auto-submit on filter change
        document.getElementById('status').addEventListener('change', function() {
            if (this.value !== 'all') {
                document.querySelector('form').submit();
            }
        });

        // Search with debounce
        let searchTimer;
        document.getElementById('search').addEventListener('input', function() {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(() => {
                if (this.value.length === 0 || this.value.length >= 3) {
                    document.querySelector('form').submit();
                }
            }, 500);
        });

        // Highlight active nav-link based on URL
        document.querySelectorAll('.nav-links a').forEach(a => {
            if (location.pathname.endsWith(a.getAttribute('href'))) {
                document.querySelectorAll('.nav-links a').forEach(n => n.classList.remove('active'));
                a.classList.add('active');
            }
        });
    </script>
</body>
</html>

