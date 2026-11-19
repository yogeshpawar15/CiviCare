<?php
// civicare/staff/reports.php

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../config/database.php';
require_once '../config/functions.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in and is staff
if (!isLoggedIn() || !hasRole('staff')) {
    header('Location: login.php');
    exit();
}

$conn = getDatabaseConnection();

// Check if database connection was successful
if (!$conn) {
    die("Database connection failed");
}

// Define missing functions if they don't exist
if (!function_exists('sanitizeInput')) {
    function sanitizeInput($data) {
        if (empty($data)) return '';
        return htmlspecialchars(stripslashes(trim($data)));
    }
}

// Get filter parameters with default values
$start_date = isset($_GET['start_date']) ? sanitizeInput($_GET['start_date']) : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? sanitizeInput($_GET['end_date']) : date('Y-m-t');
$status_filter = isset($_GET['status']) ? sanitizeInput($_GET['status']) : '';
$priority_filter = isset($_GET['priority']) ? sanitizeInput($_GET['priority']) : '';
$report_type = isset($_GET['report_type']) ? sanitizeInput($_GET['report_type']) : 'overview';

// Validate dates
if (!strtotime($start_date)) $start_date = date('Y-m-01');
if (!strtotime($end_date)) $end_date = date('Y-m-t');

// Ensure end date is not before start date
if (strtotime($end_date) < strtotime($start_date)) {
    $end_date = $start_date;
}

// Get staff's department
$staff_dept_id = $_SESSION['department_id'];
$staff_id = $_SESSION['user_id'];

// Function to get staff complaint statistics
function getStaffComplaintStats($conn, $start_date, $end_date, $staff_id, $status_filter, $priority_filter) {
    if (!$conn) return null;

    $params = [];
    $param_types = "";

    $sql = "SELECT
                COUNT(*) as total_complaints,
                SUM(CASE WHEN status = 'assigned' THEN 1 ELSE 0 END) as assigned,
                SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress,
                SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) as resolved,
                SUM(CASE WHEN status = 'escalated' THEN 1 ELSE 0 END) as escalated,
                AVG(TIMESTAMPDIFF(HOUR, assignment_date, COALESCE(resolution_date, NOW()))) as avg_resolution_hours,
                COUNT(DISTINCT CASE WHEN citizen_rating IS NOT NULL THEN id END) as rated_complaints,
                AVG(citizen_rating) as avg_rating
            FROM complaints
            WHERE assigned_to = ? AND DATE(created_at) BETWEEN ? AND ?";

    $params[] = $staff_id;
    $params[] = $start_date;
    $params[] = $end_date;
    $param_types .= "iss";

    if ($status_filter && $status_filter !== 'all') {
        $sql .= " AND status = ?";
        $params[] = $status_filter;
        $param_types .= "s";
    }

    if ($priority_filter && $priority_filter !== 'all') {
        $sql .= " AND priority = ?";
        $params[] = $priority_filter;
        $param_types .= "s";
    }

    try {
        $stmt = $conn->prepare($sql);
        if (!$stmt) return null;

        if ($params) {
            $stmt->bind_param($param_types, ...$params);
        }

        if (!$stmt->execute()) {
            $stmt->close();
            return null;
        }

        $result = $stmt->get_result();
        $stats = $result->fetch_assoc();
        $stmt->close();

        if ($stats) {
            $stats['total_complaints'] = $stats['total_complaints'] ?? 0;
            $stats['assigned'] = $stats['assigned'] ?? 0;
            $stats['in_progress'] = $stats['in_progress'] ?? 0;
            $stats['resolved'] = $stats['resolved'] ?? 0;
            $stats['escalated'] = $stats['escalated'] ?? 0;
            $stats['avg_resolution_hours'] = $stats['avg_resolution_hours'] ?? 0;
            $stats['rated_complaints'] = $stats['rated_complaints'] ?? 0;
            $stats['avg_rating'] = $stats['avg_rating'] ?? 0;
        }

        return $stats;
    } catch (Exception $e) {
        return null;
    }
}

function getDepartmentStatsForStaff($conn, $start_date, $end_date, $dept_id) {
    if (!$conn) return [];

    $sql = "SELECT
                COUNT(c.id) as complaint_count,
                SUM(CASE WHEN c.status IN ('resolved', 'closed') THEN 1 ELSE 0 END) as resolved_count,
                SUM(CASE WHEN c.status IN ('pending', 'validated', 'assigned', 'in_progress') THEN 1 ELSE 0 END) as pending_count,
                AVG(CASE WHEN c.resolution_date IS NOT NULL THEN TIMESTAMPDIFF(HOUR, c.created_at, c.resolution_date) ELSE NULL END) as avg_resolution_hours,
                COUNT(DISTINCT c.assigned_to) as staff_assigned,
                MIN(c.created_at) as first_complaint,
                MAX(c.created_at) as last_complaint
            FROM complaints c
            WHERE c.department_id = ? AND DATE(c.created_at) BETWEEN ? AND ?";

    try {
        $stmt = $conn->prepare($sql);
        if (!$stmt) return [];

        $stmt->bind_param("iss", $dept_id, $start_date, $end_date);

        if (!$stmt->execute()) {
            $stmt->close();
            return [];
        }

        $result = $stmt->get_result();
        $dept_stats = $result->fetch_assoc();
        $stmt->close();

        return $dept_stats ?: [];
    } catch (Exception $e) {
        return [];
    }
}

function getStaffPerformanceTrends($conn, $start_date, $end_date, $staff_id, $group_by = 'daily') {
    if (!$conn) return [];

    if ($group_by === 'monthly') {
        $date_format = '%Y-%m';
        $group_format = 'MONTH(created_at), YEAR(created_at)';
    } else {
        $date_format = '%Y-%m-%d';
        $group_format = 'DATE(created_at)';
    }

    $sql = "SELECT
                DATE_FORMAT(created_at, ?) as period,
                COUNT(*) as total_assigned,
                SUM(CASE WHEN status IN ('resolved', 'closed') THEN 1 ELSE 0 END) as resolved_complaints,
                SUM(CASE WHEN status IN ('assigned', 'in_progress') THEN 1 ELSE 0 END) as active_complaints,
                AVG(CASE WHEN citizen_rating IS NOT NULL THEN citizen_rating ELSE NULL END) as avg_rating
            FROM complaints
            WHERE assigned_to = ? AND DATE(created_at) BETWEEN ? AND ?
            GROUP BY $group_format
            ORDER BY period ASC";

    try {
        $stmt = $conn->prepare($sql);
        if (!$stmt) return [];

        $stmt->bind_param("siss", $date_format, $staff_id, $start_date, $end_date);

        if (!$stmt->execute()) {
            $stmt->close();
            return [];
        }

        $result = $stmt->get_result();
        $trends = [];

        while ($row = $result->fetch_assoc()) {
            $trends[] = $row;
        }
        $stmt->close();

        return $trends;
    } catch (Exception $e) {
        return [];
    }
}

function getStaffDetailedComplaints($conn, $start_date, $end_date, $staff_id, $status_filter, $priority_filter, $page = 1, $limit = 50) {
    if (!$conn) return [];

    $offset = ($page - 1) * $limit;
    $params = [];
    $param_types = "";

    $sql = "SELECT
                c.*,
                u.full_name as complainant_name,
                u.email as complainant_email,
                TIMESTAMPDIFF(HOUR, c.created_at, COALESCE(c.resolution_date, NOW())) as hours_open,
                COUNT(cu.id) as update_count
            FROM complaints c
            LEFT JOIN users u ON c.user_id = u.id
            LEFT JOIN complaint_updates cu ON c.id = cu.complaint_id
            WHERE c.assigned_to = ? AND DATE(c.created_at) BETWEEN ? AND ?";

    $params[] = $staff_id;
    $params[] = $start_date;
    $params[] = $end_date;
    $param_types .= "iss";

    if ($status_filter && $status_filter !== 'all') {
        $sql .= " AND c.status = ?";
        $params[] = $status_filter;
        $param_types .= "s";
    }

    if ($priority_filter && $priority_filter !== 'all') {
        $sql .= " AND c.priority = ?";
        $params[] = $priority_filter;
        $param_types .= "s";
    }

    $sql .= " GROUP BY c.id
              ORDER BY c.created_at DESC
              LIMIT ? OFFSET ?";

    $params[] = $limit;
    $params[] = $offset;
    $param_types .= "ii";

    try {
        $stmt = $conn->prepare($sql);
        if (!$stmt) return [];

        if ($params) {
            $stmt->bind_param($param_types, ...$params);
        }

        if (!$stmt->execute()) {
            $stmt->close();
            return [];
        }

        $result = $stmt->get_result();
        $complaints = [];

        while ($row = $result->fetch_assoc()) {
            $complaints[] = $row;
        }
        $stmt->close();

        return $complaints;
    } catch (Exception $e) {
        return [];
    }
}

function getStaffComplaintsCount($conn, $start_date, $end_date, $staff_id, $status_filter, $priority_filter) {
    if (!$conn) return 0;

    $params = [];
    $param_types = "";

    $sql = "SELECT COUNT(*) as total FROM complaints
            WHERE assigned_to = ? AND DATE(created_at) BETWEEN ? AND ?";

    $params[] = $staff_id;
    $params[] = $start_date;
    $params[] = $end_date;
    $param_types .= "iss";

    if ($status_filter && $status_filter !== 'all') {
        $sql .= " AND status = ?";
        $params[] = $status_filter;
        $param_types .= "s";
    }

    if ($priority_filter && $priority_filter !== 'all') {
        $sql .= " AND priority = ?";
        $params[] = $priority_filter;
        $param_types .= "s";
    }

    try {
        $stmt = $conn->prepare($sql);
        if (!$stmt) return 0;

        if ($params) {
            $stmt->bind_param($param_types, ...$params);
        }

        if (!$stmt->execute()) {
            $stmt->close();
            return 0;
        }

        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();

        return $row['total'] ?? 0;
    } catch (Exception $e) {
        return 0;
    }
}

$stats = [];
$dept_stats = [];
$trends = [];
$detailed_complaints = [];
$total_complaints = 0;
$total_pages = 0;

switch ($report_type) {
    case 'overview':
        $stats = getStaffComplaintStats($conn, $start_date, $end_date, $staff_id, $status_filter, $priority_filter);
        $dept_stats = getDepartmentStatsForStaff($conn, $start_date, $end_date, $staff_dept_id);
        $trends = getStaffPerformanceTrends($conn, $start_date, $end_date, $staff_id, 'monthly');
        break;

    case 'department':
        $dept_stats = getDepartmentStatsForStaff($conn, $start_date, $end_date, $staff_dept_id);
        break;

    case 'performance':
        $stats = getStaffComplaintStats($conn, $start_date, $end_date, $staff_id, $status_filter, $priority_filter);
        $trends = getStaffPerformanceTrends($conn, $start_date, $end_date, $staff_id, 'daily');
        break;

    case 'trends':
        $trend_group = isset($_GET['trend_group']) ? sanitizeInput($_GET['trend_group']) : 'daily';
        $trends = getStaffPerformanceTrends($conn, $start_date, $end_date, $staff_id, $trend_group);
        break;

    case 'detailed':
        $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
        $limit = 50;
        $detailed_complaints = getStaffDetailedComplaints($conn, $start_date, $end_date, $staff_id, $status_filter, $priority_filter, $page, $limit);
        $total_complaints = getStaffComplaintsCount($conn, $start_date, $end_date, $staff_id, $status_filter, $priority_filter);
        $total_pages = ceil($total_complaints / $limit);
        break;
}

if ($conn) {
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Reports & Analytics - Civicare Staff</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
            transition: all 0.3s;
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
            transition: all 0.3s;
        }

        .logout-btn:hover {
            background: #e55a24;
            transform: translateY(-2px);
        }

        /* ===== CONTAINER ===== */
        .container {
            width: 90%;
            max-width: 1200px;
            margin: 30px auto;
            padding: 0 15px;
        }

        /* ===== PAGE HEADER ===== */
        .page-header {
            background: white;
            padding: 24px;
            border-radius: 8px;
            margin-bottom: 30px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
            border-left: 5px solid #ff6b35;
        }

        .page-header h1 {
            color: #1e3c72;
            margin-bottom: 8px;
            font-size: 24px;
        }

        .page-header p {
            color: #666;
            margin-bottom: 0;
        }

        .role-badge {
            display: inline-block;
            padding: 4px 12px;
            background: #f39c12;
            color: white;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            margin-left: 10px;
            vertical-align: middle;
        }

        /* ===== REPORT TABS ===== */
        .report-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 25px;
            background: white;
            padding: 15px;
            border-radius: 8px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
            flex-wrap: wrap;
        }

        .report-tab {
            padding: 10px 20px;
            border: none;
            background: #f8f9fa;
            color: #666;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
            font-size: 14px;
        }

        .report-tab:hover {
            background: #e9ecef;
            color: #1e3c72;
        }

        .report-tab.active {
            background: #1e3c72;
            color: white;
        }

        /* ===== FILTERS ===== */
        .filters-section {
            background: white;
            padding: 24px;
            border-radius: 8px;
            margin-bottom: 30px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
        }

        .filters-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .filter-group label {
            display: block;
            margin-bottom: 6px;
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
            background: #1e3c72;
            color: white;
        }

        .btn-primary:hover {
            background: #2a5298;
            transform: translateY(-2px);
        }

        .btn-secondary {
            background: #95a5a6;
            color: white;
        }

        .btn-secondary:hover {
            background: #7f8c8d;
        }

        /* ===== STATS GRID ===== */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
            border-left: 4px solid #2a5298;
            transition: all 0.3s;
        }

        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 15px rgba(0, 0, 0, 0.15);
        }

        .stat-card h3 {
            font-size: 32px;
            color: #1e3c72;
            margin-bottom: 5px;
        }

        .stat-card p {
            color: #666;
            font-size: 13px;
            font-weight: 600;
        }

        .stat-card.assigned { border-left-color: #9b59b6; }
        .stat-card.in_progress { border-left-color: #3498db; }
        .stat-card.resolved { border-left-color: #27ae60; }
        .stat-card.escalated { border-left-color: #e67e22; }
        .stat-card.rating { border-left-color: #f39c12; }
        .stat-card.time { border-left-color: #1abc9c; }

        /* ===== REPORT SECTIONS ===== */
        .report-section {
            background: white;
            padding: 24px;
            border-radius: 8px;
            margin-bottom: 30px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #1e3c72;
        }

        .section-header h3 {
            color: #1e3c72;
            font-size: 18px;
        }

        /* ===== TABLE ===== */
        .table-container {
            overflow-x: auto;
            border-radius: 8px;
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
            font-size: 13px;
        }

        td {
            padding: 15px;
            border-bottom: 1px solid #eee;
            font-size: 14px;
        }

        tr:hover {
            background: #f8f9fa;
        }

        /* ===== BADGES ===== */
        .status-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
        }

        .status-assigned { background: #d1ecf1; color: #0c5460; }
        .status-in_progress { background: #cce5ff; color: #004085; }
        .status-resolved { background: #d4edda; color: #155724; }
        .status-escalated { background: #f8d7da; color: #721c24; }

        .priority-badge {
            padding: 4px 10px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
        }

        .priority-low { background: #d4edda; color: #155724; }
        .priority-medium { background: #cce5ff; color: #004085; }
        .priority-high { background: #fff3cd; color: #856404; }
        .priority-urgent { background: #f8d7da; color: #721c24; }

        /* ===== TREND CHART ===== */
        .trend-chart {
            display: flex;
            align-items: flex-end;
            height: 250px;
            gap: 10px;
            padding: 20px;
            border-bottom: 2px solid #eee;
            margin-bottom: 15px;
        }

        .trend-bar {
            flex: 1;
            background: linear-gradient(180deg, #2a5298, #1e3c72);
            border-radius: 4px 4px 0 0;
            position: relative;
            min-height: 10px;
            transition: all 0.3s;
        }

        .trend-bar:hover {
            background: linear-gradient(180deg, #ff6b35, #e55a24);
        }

        .trend-value {
            position: absolute;
            top: -30px;
            left: 50%;
            transform: translateX(-50%);
            background: #1e3c72;
            color: white;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            display: none;
            white-space: nowrap;
        }

        .trend-bar:hover .trend-value {
            display: block;
        }

        .trend-labels {
            display: flex;
            justify-content: space-around;
            padding: 0 20px;
            font-size: 12px;
            color: #666;
        }

        /* ===== CHART ===== */
        .chart-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .chart-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
        }

        .chart-card h4 {
            color: #1e3c72;
            margin-bottom: 15px;
            font-size: 16px;
            text-align: center;
        }

        .chart-wrapper {
            position: relative;
            height: 300px;
        }

        /* ===== NO DATA ===== */
        .no-data {
            text-align: center;
            padding: 50px;
            color: #666;
        }

        .no-data i {
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

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .filters-grid {
                grid-template-columns: 1fr;
            }

            .filter-actions {
                flex-direction: column;
            }

            .report-tabs {
                flex-wrap: wrap;
            }

            .table-container {
                overflow-x: auto;
            }

            .chart-row {
                grid-template-columns: 1fr;
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
                    <p>Staff Portal</p>
                </div>
            </div>

            <nav class="nav-links">
                <a href="dashboard.php">Dashboard</a>
                <a href="complaints.php">Complaints</a>
                <a href="reports.php" class="active">Reports</a>
                <a href="profile.php">Profile</a>
                <a href="settings.php">Settings</a>
                <a href="logout.php" class="logout-btn">Logout</a>
            </nav>
        </div>
    </header>

    <div class="container">
        <!-- PAGE HEADER -->
        <div class="page-header">
            <h1>My Reports & Analytics <span class="role-badge">Staff Member</span></h1>
            <p>Track your performance and department statistics</p>
        </div>

        <!-- REPORT TABS -->
        <div class="report-tabs">
            <button class="report-tab <?php echo $report_type === 'overview' ? 'active' : ''; ?>" onclick="changeReportType('overview')">
                <i class="fas fa-chart-pie"></i> Overview
            </button>
            <button class="report-tab <?php echo $report_type === 'department' ? 'active' : ''; ?>" onclick="changeReportType('department')">
                <i class="fas fa-building"></i> Department
            </button>
            <button class="report-tab <?php echo $report_type === 'performance' ? 'active' : ''; ?>" onclick="changeReportType('performance')">
                <i class="fas fa-user-check"></i> My Performance
            </button>
            <button class="report-tab <?php echo $report_type === 'trends' ? 'active' : ''; ?>" onclick="changeReportType('trends')">
                <i class="fas fa-chart-line"></i> Trends
            </button>
            <button class="report-tab <?php echo $report_type === 'detailed' ? 'active' : ''; ?>" onclick="changeReportType('detailed')">
                <i class="fas fa-list"></i> Detailed
            </button>
        </div>

        <!-- FILTERS SECTION -->
        <div class="filters-section">
            <form method="GET" action="" id="reportForm">
                <input type="hidden" name="report_type" id="report_type" value="<?php echo $report_type; ?>">

                <div class="filters-grid">
                    <div class="filter-group">
                        <label for="start_date">Start Date</label>
                        <input type="date" id="start_date" name="start_date" value="<?php echo $start_date; ?>" required>
                    </div>

                    <div class="filter-group">
                        <label for="end_date">End Date</label>
                        <input type="date" id="end_date" name="end_date" value="<?php echo $end_date; ?>" required>
                    </div>

                    <div class="filter-group">
                        <label for="status">Status</label>
                        <select id="status" name="status">
                            <option value="all">All Status</option>
                            <option value="assigned" <?php echo $status_filter === 'assigned' ? 'selected' : ''; ?>>Assigned</option>
                            <option value="in_progress" <?php echo $status_filter === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                            <option value="resolved" <?php echo $status_filter === 'resolved' ? 'selected' : ''; ?>>Resolved</option>
                            <option value="escalated" <?php echo $status_filter === 'escalated' ? 'selected' : ''; ?>>Escalated</option>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label for="priority">Priority</label>
                        <select id="priority" name="priority">
                            <option value="all">All Priorities</option>
                            <option value="low" <?php echo $priority_filter === 'low' ? 'selected' : ''; ?>>Low</option>
                            <option value="medium" <?php echo $priority_filter === 'medium' ? 'selected' : ''; ?>>Medium</option>
                            <option value="high" <?php echo $priority_filter === 'high' ? 'selected' : ''; ?>>High</option>
                            <option value="urgent" <?php echo $priority_filter === 'urgent' ? 'selected' : ''; ?>>Urgent</option>
                        </select>
                    </div>
                </div>

                <div class="filter-actions">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Generate Report</button>
                    <button type="button" class="btn btn-secondary" onclick="resetFilters()"><i class="fas fa-redo"></i> Reset</button>
                </div>
            </form>
        </div>

        <?php if ($report_type === 'overview'): ?>
            <?php if ($stats || $dept_stats): ?>
                <div class="stats-grid">
                    <div class="stat-card">
                        <h3><?php echo $stats['total_complaints'] ?? 0; ?></h3>
                        <p>Total Assigned</p>
                    </div>
                    <div class="stat-card assigned">
                        <h3><?php echo $stats['assigned'] ?? 0; ?></h3>
                        <p>Assigned</p>
                    </div>
                    <div class="stat-card in_progress">
                        <h3><?php echo $stats['in_progress'] ?? 0; ?></h3>
                        <p>In Progress</p>
                    </div>
                    <div class="stat-card resolved">
                        <h3><?php echo $stats['resolved'] ?? 0; ?></h3>
                        <p>Resolved</p>
                    </div>
                    <div class="stat-card escalated">
                        <h3><?php echo $stats['escalated'] ?? 0; ?></h3>
                        <p>Escalated</p>
                    </div>
                    <div class="stat-card rating">
                        <h3><?php echo ($stats['rated_complaints'] ?? 0) > 0 ? number_format($stats['avg_rating'] ?? 0, 1) : 'N/A'; ?></h3>
                        <p>Avg Rating</p>
                    </div>
                    <div class="stat-card time">
                        <h3><?php echo number_format($stats['avg_resolution_hours'] ?? 0, 1); ?></h3>
                        <p>Avg Hours</p>
                    </div>
                </div>

                <?php if ($trends): ?>
                <div class="report-section">
                    <div class="section-header">
                    <h3><i class="fas fa-chart-line"></i> Monthly Performance Trends</h3>
                </div>

                <div class="trend-chart">
                    <?php
                    $max_complaints = max(array_column($trends, 'total_assigned'));
                    foreach ($trends as $trend):
                        $height = $max_complaints > 0 ? ($trend['total_assigned'] / $max_complaints) * 100 : 10;
                    ?>
                    <div class="trend-bar" style="height: <?php echo $height; ?>%">
                        <div class="trend-value"><?php echo $trend['total_assigned']; ?> complaints</div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div class="trend-labels">
                    <?php foreach ($trends as $trend): ?>
                        <span><?php echo $trend['period']; ?></span>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                    <div class="no-data">
                        <p>No trend data available for the selected period.</p>
                    </div>
                <?php endif; ?>

                <div class="report-section">
                    <div class="section-header">
                        <h3>Department Performance</h3>
                        <span>Period: <?php echo date('d M Y', strtotime($start_date)); ?> to <?php echo date('d M Y', strtotime($end_date)); ?></span>
                    </div>

                    <?php if (!empty($dept_stats) && ($dept_stats['complaint_count'] ?? 0) > 0): ?>
                        <div class="stats-grid">
                            <div class="stat-card" style="border-left: 4px solid #3498db;">
                                <h3><?php echo $dept_stats['complaint_count'] ?? 0; ?></h3>
                                <p>Dept Total Complaints</p>
                            </div>
                            <div class="stat-card" style="border-left: 4px solid #27ae60;">
                                <h3><?php echo $dept_stats['resolved_count'] ?? 0; ?></h3>
                                <p>Dept Resolved</p>
                            </div>
                            <div class="stat-card" style="border-left: 4px solid #f39c12;">
                                <h3><?php echo $dept_stats['pending_count'] ?? 0; ?></h3>
                                <p>Dept Pending</p>
                            </div>
                            <div class="stat-card" style="border-left: 4px solid #1abc9c;">
                                <h3><?php echo number_format($dept_stats['avg_resolution_hours'] ?? 0, 1); ?></h3>
                                <p>Avg Resolution (hrs)</p>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="no-data">
                            <p>No department data available for the selected period.</p>
                        </div>
                    <?php endif; ?>
                </div>

            <?php else: ?>
                <div class="no-data">
                    <p>No data available for the selected period and filters.</p>
                </div>
            <?php endif; ?>

        <?php elseif ($report_type === 'department'): ?>
            <div class="report-section">
                <div class="section-header">
                    <h3>Department Complaint Report</h3>
                    <span>Period: <?php echo date('d M Y', strtotime($start_date)); ?> to <?php echo date('d M Y', strtotime($end_date)); ?></span>
                </div>

                <?php if (!empty($dept_stats) && ($dept_stats['complaint_count'] ?? 0) > 0): ?>
                    <div class="stats-grid">
                        <div class="stat-card" style="border-left: 4px solid #3498db;">
                            <h3><?php echo $dept_stats['complaint_count'] ?? 0; ?></h3>
                            <p>Total Complaints</p>
                        </div>
                        <div class="stat-card" style="border-left: 4px solid #27ae60;">
                            <h3><?php echo $dept_stats['resolved_count'] ?? 0; ?></h3>
                            <p>Resolved</p>
                        </div>
                        <div class="stat-card" style="border-left: 4px solid #f39c12;">
                            <h3><?php echo $dept_stats['pending_count'] ?? 0; ?></h3>
                            <p>Pending</p>
                        </div>
                        <div class="stat-card" style="border-left: 4px solid #1abc9c;">
                            <h3><?php echo number_format($dept_stats['avg_resolution_hours'] ?? 0, 1); ?></h3>
                            <p>Avg Resolution (hrs)</p>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="no-data">
                        <p>No department data available for the selected period.</p>
                    </div>
                <?php endif; ?>
            </div>

        <?php elseif ($report_type === 'performance'): ?>
            <?php if ($stats): ?>
                <div class="stats-grid">
                    <div class="stat-card" style="border-left: 4px solid #3498db;">
                        <h3><?php echo $stats['total_complaints'] ?? 0; ?></h3>
                        <p>Total Assigned</p>
                    </div>
                    <div class="stat-card assigned">
                        <h3><?php echo $stats['assigned'] ?? 0; ?></h3>
                        <p>Assigned</p>
                    </div>
                    <div class="stat-card in_progress">
                        <h3><?php echo $stats['in_progress'] ?? 0; ?></h3>
                        <p>In Progress</p>
                    </div>
                    <div class="stat-card resolved">
                        <h3><?php echo $stats['resolved'] ?? 0; ?></h3>
                        <p>Resolved</p>
                    </div>
                    <div class="stat-card escalated">
                        <h3><?php echo $stats['escalated'] ?? 0; ?></h3>
                        <p>Escalated</p>
                    </div>
                    <div class="stat-card time">
                        <h3><?php echo number_format($stats['avg_resolution_hours'] ?? 0, 1); ?></h3>
                        <p>Avg Resolution (hrs)</p>
                    </div>
                </div>

                <div class="report-section">
                    <div class="section-header">
                        <h3>Performance Metrics</h3>
                    </div>

                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>Metric</th>
                                    <th>Value</th>
                                    <th>Percentage</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><strong>Total Assigned</strong></td>
                                    <td><?php echo $stats['total_complaints'] ?? 0; ?></td>
                                    <td>100%</td>
                                </tr>
                                <tr>
                                    <td><strong>Resolved</strong></td>
                                    <td><?php echo $stats['resolved'] ?? 0; ?></td>
                                    <td><?php echo ($stats['total_complaints'] ?? 0) > 0 ? round(($stats['resolved'] ?? 0) / ($stats['total_complaints'] ?? 1) * 100, 1) : 0; ?>%</td>
                                </tr>
                                <tr>
                                    <td><strong>Escalated</strong></td>
                                    <td><?php echo $stats['escalated'] ?? 0; ?></td>
                                    <td><?php echo ($stats['total_complaints'] ?? 0) > 0 ? round(($stats['escalated'] ?? 0) / ($stats['total_complaints'] ?? 1) * 100, 1) : 0; ?>%</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

            <?php else: ?>
                <div class="no-data">
                    <p>No performance data available for the selected period.</p>
                </div>
            <?php endif; ?>

        <?php elseif ($report_type === 'trends'): ?>
            <div class="report-section">
                <div class="section-header">
                    <h3>Performance Trends Analysis</h3>
                    <span>Period: <?php echo date('d M Y', strtotime($start_date)); ?> to <?php echo date('d M Y', strtotime($end_date)); ?></span>
                </div>

                <?php if (!empty($trends)): ?>
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>Period</th>
                                    <th>Total Assigned</th>
                                    <th>Resolved</th>
                                    <th>Active</th>
                                    <th>Resolution Rate</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($trends as $trend): ?>
                                    <tr>
                                        <td><strong><?php echo $trend['period'] ?? 'N/A'; ?></strong></td>
                                        <td><?php echo $trend['total_assigned'] ?? 0; ?></td>
                                        <td><?php echo $trend['resolved_complaints'] ?? 0; ?></td>
                                        <td><?php echo $trend['active_complaints'] ?? 0; ?></td>
                                        <td><?php echo ($trend['total_assigned'] ?? 0) > 0 ? round(($trend['resolved_complaints'] ?? 0) / ($trend['total_assigned'] ?? 0) * 100, 1) : 0; ?>%</td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="no-data">
                        <p>No trend data available for the selected period.</p>
                    </div>
                <?php endif; ?>
            </div>

        <?php elseif ($report_type === 'detailed'): ?>
            <div class="report-section">
                <div class="section-header">
                    <h3>Detailed Complaint Report</h3>
                    <span>Showing <?php echo count($detailed_complaints); ?> of <?php echo $total_complaints; ?> complaints</span>
                </div>

                <?php if (!empty($detailed_complaints)): ?>
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>Tracking ID</th>
                                    <th>Title</th>
                                    <th>Complainant</th>
                                    <th>Status</th>
                                    <th>Priority</th>
                                    <th>Date</th>
                                    <th>Hours Open</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($detailed_complaints as $complaint): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($complaint['tracking_id'] ?? 'N/A'); ?></strong></td>
                                        <td><?php echo htmlspecialchars($complaint['title'] ?? 'No Title'); ?></td>
                                        <td><?php echo htmlspecialchars($complaint['complainant_name'] ?? 'N/A'); ?></td>
                                        <td>
                                            <span class="status-badge status-<?php echo $complaint['status']; ?>">
                                                <?php echo ucfirst(str_replace('_', ' ', $complaint['status'])); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="priority-badge priority-<?php echo $complaint['priority']; ?>">
                                                <?php echo ucfirst($complaint['priority']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo date('M d, Y', strtotime($complaint['created_at'] ?? '')); ?></td>
                                        <td><?php echo number_format($complaint['hours_open'] ?? 0); ?> hrs</td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <?php if ($total_pages > 1): ?>
                        <div class="pagination">
                            <?php if ($page > 1): ?>
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">Previous</a>
                            <?php endif; ?>

                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <?php if ($i == 1 || $i == $total_pages || ($i >= $page - 2 && $i <= $page + 2)): ?>
                                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>"
                                       class="<?php echo $i == $page ? 'active' : ''; ?>">
                                        <?php echo $i; ?>
                                    </a>
                                <?php elseif ($i == $page - 3 || $i == $page + 3): ?>
                                    <span>...</span>
                                <?php endif; ?>
                            <?php endfor; ?>

                            <?php if ($page < $total_pages): ?>
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">Next</a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                <?php else: ?>
                    <div class="no-data">
                        <p>No complaint data available for the selected filters.</p>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <script>
        function changeReportType(type) {
            document.getElementById('report_type').value = type;
            document.getElementById('reportForm').submit();
        }

        function resetFilters() {
            const today = new Date();
            const firstDay = new Date(today.getFullYear(), today.getMonth(), 1);

            document.getElementById('start_date').value = firstDay.toISOString().split('T')[0];
            document.getElementById('end_date').value = today.toISOString().split('T')[0];
            document.getElementById('status').value = 'all';
            document.getElementById('priority').value = 'all';

            if (document.getElementById('trend_group')) {
                document.getElementById('trend_group').value = 'daily';
            }
        }

        // Validate date range
        document.getElementById('start_date').addEventListener('change', function() {
            const endDate = document.getElementById('end_date');
            if (this.value > endDate.value) {
                endDate.value = this.value;
            }
        });

        document.getElementById('end_date').addEventListener('change', function() {
            const startDate = document.getElementById('start_date');
            if (this.value < startDate.value) {
                startDate.value = this.value;
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
