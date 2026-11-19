<?php
// civicare/admin/reports.php

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../config/database.php';
require_once '../config/functions.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in and is admin/super_admin
if (!isLoggedIn() || (!hasRole('admin') && !hasRole('super_admin'))) {
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

if (!function_exists('getAllDepartments')) {
    function getAllDepartments($conn) {
        $departments = [];
        if (!$conn) return $departments;
        
        $sql = "SELECT id, name FROM departments WHERE is_active = 1 ORDER BY name ASC";
        $result = $conn->query($sql);
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $departments[] = $row;
            }
        }
        return $departments;
    }
}

// Get filter parameters with default values
$start_date = isset($_GET['start_date']) ? sanitizeInput($_GET['start_date']) : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? sanitizeInput($_GET['end_date']) : date('Y-m-t');
$department_id = isset($_GET['department_id']) ? (int)$_GET['department_id'] : 0;
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

// Get departments for dropdown
$departments = getAllDepartments($conn);

// Function to get complaint statistics
function getComplaintStats($conn, $start_date, $end_date, $department_id, $status_filter, $priority_filter) {
    if (!$conn) return null;
    
    $params = [];
    $param_types = "";
    
    // Base query for statistics
    $sql = "SELECT 
                COUNT(*) as total_complaints,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = 'validated' THEN 1 ELSE 0 END) as validated,
                SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected,
                SUM(CASE WHEN status = 'assigned' THEN 1 ELSE 0 END) as assigned,
                SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress,
                SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) as resolved,
                SUM(CASE WHEN status = 'escalated' THEN 1 ELSE 0 END) as escalated,
                SUM(CASE WHEN status = 'closed' THEN 1 ELSE 0 END) as closed,
                AVG(TIMESTAMPDIFF(HOUR, created_at, COALESCE(resolution_date, NOW()))) as avg_resolution_hours,
                COUNT(DISTINCT user_id) as unique_complainants,
                COUNT(DISTINCT department_id) as departments_involved
            FROM complaints 
            WHERE DATE(created_at) BETWEEN ? AND ?";
    
    $params[] = $start_date;
    $params[] = $end_date;
    $param_types .= "ss";
    
    // Add department filter
    if ($department_id > 0) {
        $sql .= " AND department_id = ?";
        $params[] = $department_id;
        $param_types .= "i";
    }
    
    // Add status filter
    if ($status_filter && $status_filter !== 'all') {
        $sql .= " AND status = ?";
        $params[] = $status_filter;
        $param_types .= "s";
    }
    
    // Add priority filter
    if ($priority_filter && $priority_filter !== 'all') {
        $sql .= " AND priority = ?";
        $params[] = $priority_filter;
        $param_types .= "s";
    }
    
    try {
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            error_log("Prepare failed: " . $conn->error);
            return null;
        }
        
        if ($params) {
            $stmt->bind_param($param_types, ...$params);
        }
        
        if (!$stmt->execute()) {
            error_log("Execute failed: " . $stmt->error);
            $stmt->close();
            return null;
        }
        
        $result = $stmt->get_result();
        $stats = $result->fetch_assoc();
        $stmt->close();
        
        // Ensure all fields have default values
        if ($stats) {
            $stats['total_complaints'] = $stats['total_complaints'] ?? 0;
            $stats['pending'] = $stats['pending'] ?? 0;
            $stats['validated'] = $stats['validated'] ?? 0;
            $stats['rejected'] = $stats['rejected'] ?? 0;
            $stats['assigned'] = $stats['assigned'] ?? 0;
            $stats['in_progress'] = $stats['in_progress'] ?? 0;
            $stats['resolved'] = $stats['resolved'] ?? 0;
            $stats['escalated'] = $stats['escalated'] ?? 0;
            $stats['closed'] = $stats['closed'] ?? 0;
            $stats['avg_resolution_hours'] = $stats['avg_resolution_hours'] ?? 0;
            $stats['unique_complainants'] = $stats['unique_complainants'] ?? 0;
            $stats['departments_involved'] = $stats['departments_involved'] ?? 0;
        }
        
        return $stats;
    } catch (Exception $e) {
        error_log("Error in getComplaintStats: " . $e->getMessage());
        return null;
    }
}

// Function to get department-wise statistics
function getDepartmentStats($conn, $start_date, $end_date) {
    if (!$conn) return [];
    
    $sql = "SELECT 
                d.id,
                d.name as department_name,
                COUNT(c.id) as complaint_count,
                SUM(CASE WHEN c.status IN ('resolved', 'closed') THEN 1 ELSE 0 END) as resolved_count,
                SUM(CASE WHEN c.status IN ('pending', 'validated', 'assigned', 'in_progress') THEN 1 ELSE 0 END) as pending_count,
                AVG(CASE WHEN c.resolution_date IS NOT NULL THEN TIMESTAMPDIFF(HOUR, c.created_at, c.resolution_date) ELSE NULL END) as avg_resolution_hours,
                COUNT(DISTINCT c.assigned_to) as staff_assigned,
                MIN(c.created_at) as first_complaint,
                MAX(c.created_at) as last_complaint
            FROM departments d
            LEFT JOIN complaints c ON d.id = c.department_id 
                AND DATE(c.created_at) BETWEEN ? AND ?
            WHERE d.is_active = 1
            GROUP BY d.id, d.name
            ORDER BY complaint_count DESC";
    
    try {
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            error_log("Prepare failed: " . $conn->error);
            return [];
        }
        
        $stmt->bind_param("ss", $start_date, $end_date);
        
        if (!$stmt->execute()) {
            error_log("Execute failed: " . $stmt->error);
            $stmt->close();
            return [];
        }
        
        $result = $stmt->get_result();
        $dept_stats = [];
        
        while ($row = $result->fetch_assoc()) {
            $dept_stats[] = $row;
        }
        $stmt->close();
        
        return $dept_stats;
    } catch (Exception $e) {
        error_log("Error in getDepartmentStats: " . $e->getMessage());
        return [];
    }
}

// Function to get staff performance report
function getStaffPerformance($conn, $start_date, $end_date, $department_id) {
    if (!$conn) return [];
    
    $params = [$start_date, $end_date];
    $param_types = "ss";
    
    $sql = "SELECT 
                u.id,
                u.full_name as staff_name,
                u.email,
                d.name as department_name,
                COUNT(c.id) as assigned_complaints,
                SUM(CASE WHEN c.status IN ('resolved', 'closed') THEN 1 ELSE 0 END) as resolved_complaints,
                SUM(CASE WHEN c.status IN ('assigned', 'in_progress') THEN 1 ELSE 0 END) as active_complaints,
                AVG(CASE WHEN c.resolution_date IS NOT NULL THEN TIMESTAMPDIFF(HOUR, c.assignment_date, c.resolution_date) ELSE NULL END) as avg_resolution_hours,
                MAX(c.resolution_date) as last_resolution,
                COUNT(DISTINCT CASE WHEN c.citizen_rating IS NOT NULL THEN c.id END) as rated_complaints,
                AVG(c.citizen_rating) as avg_rating
            FROM users u
            LEFT JOIN departments d ON u.department_id = d.id
            LEFT JOIN complaints c ON u.id = c.assigned_to 
                AND DATE(c.created_at) BETWEEN ? AND ?
            WHERE u.role = 'staff' 
                AND u.is_active = 1";
    
    if ($department_id > 0) {
        $sql .= " AND u.department_id = ?";
        $params[] = $department_id;
        $param_types .= "i";
    }
    
    $sql .= " GROUP BY u.id, u.full_name, u.email, d.name
              HAVING assigned_complaints > 0
              ORDER BY assigned_complaints DESC";
    
    try {
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            error_log("Prepare failed: " . $conn->error);
            return [];
        }
        
        if ($params) {
            $stmt->bind_param($param_types, ...$params);
        }
        
        if (!$stmt->execute()) {
            error_log("Execute failed: " . $stmt->error);
            $stmt->close();
            return [];
        }
        
        $result = $stmt->get_result();
        $staff_performance = [];
        
        while ($row = $result->fetch_assoc()) {
            $staff_performance[] = $row;
        }
        $stmt->close();
        
        return $staff_performance;
    } catch (Exception $e) {
        error_log("Error in getStaffPerformance: " . $e->getMessage());
        return [];
    }
}

// Function to get complaint trends (daily/monthly)
function getComplaintTrends($conn, $start_date, $end_date, $group_by = 'daily') {
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
                COUNT(*) as total_complaints,
                SUM(CASE WHEN status IN ('resolved', 'closed') THEN 1 ELSE 0 END) as resolved_complaints,
                SUM(CASE WHEN status IN ('pending', 'validated', 'assigned', 'in_progress') THEN 1 ELSE 0 END) as pending_complaints,
                AVG(CASE WHEN citizen_rating IS NOT NULL THEN citizen_rating ELSE NULL END) as avg_rating
            FROM complaints
            WHERE DATE(created_at) BETWEEN ? AND ?
            GROUP BY $group_format
            ORDER BY period ASC";
    
    try {
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            error_log("Prepare failed: " . $conn->error);
            return [];
        }
        
        $stmt->bind_param("sss", $date_format, $start_date, $end_date);
        
        if (!$stmt->execute()) {
            error_log("Execute failed: " . $stmt->error);
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
        error_log("Error in getComplaintTrends: " . $e->getMessage());
        return [];
    }
}

// Function to get detailed complaint report
function getDetailedComplaints($conn, $start_date, $end_date, $department_id, $status_filter, $priority_filter, $page = 1, $limit = 50) {
    if (!$conn) return [];
    
    $offset = ($page - 1) * $limit;
    $params = [];
    $param_types = "";
    
    $sql = "SELECT 
                c.*,
                u.full_name as complainant_name,
                u.email as complainant_email,
                u.phone as complainant_phone,
                d.name as department_name,
                s.full_name as assigned_staff_name,
                s.email as staff_email,
                TIMESTAMPDIFF(HOUR, c.created_at, COALESCE(c.resolution_date, NOW())) as hours_open,
                COUNT(cu.id) as update_count
            FROM complaints c
            LEFT JOIN users u ON c.user_id = u.id
            LEFT JOIN departments d ON c.department_id = d.id
            LEFT JOIN users s ON c.assigned_to = s.id
            LEFT JOIN complaint_updates cu ON c.id = cu.complaint_id
            WHERE DATE(c.created_at) BETWEEN ? AND ?";
    
    $params[] = $start_date;
    $params[] = $end_date;
    $param_types .= "ss";
    
    // Add department filter
    if ($department_id > 0) {
        $sql .= " AND c.department_id = ?";
        $params[] = $department_id;
        $param_types .= "i";
    }
    
    // Add status filter
    if ($status_filter && $status_filter !== 'all') {
        $sql .= " AND c.status = ?";
        $params[] = $status_filter;
        $param_types .= "s";
    }
    
    // Add priority filter
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
        if (!$stmt) {
            error_log("Prepare failed: " . $conn->error);
            return [];
        }
        
        if ($params) {
            $stmt->bind_param($param_types, ...$params);
        }
        
        if (!$stmt->execute()) {
            error_log("Execute failed: " . $stmt->error);
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
        error_log("Error in getDetailedComplaints: " . $e->getMessage());
        return [];
    }
}

// Function to get complaint count for pagination
function getTotalComplaintsCount($conn, $start_date, $end_date, $department_id, $status_filter, $priority_filter) {
    if (!$conn) return 0;
    
    $params = [];
    $param_types = "";
    
    $sql = "SELECT COUNT(*) as total FROM complaints 
            WHERE DATE(created_at) BETWEEN ? AND ?";
    
    $params[] = $start_date;
    $params[] = $end_date;
    $param_types .= "ss";
    
    // Add department filter
    if ($department_id > 0) {
        $sql .= " AND department_id = ?";
        $params[] = $department_id;
        $param_types .= "i";
    }
    
    // Add status filter
    if ($status_filter && $status_filter !== 'all') {
        $sql .= " AND status = ?";
        $params[] = $status_filter;
        $param_types .= "s";
    }
    
    // Add priority filter
    if ($priority_filter && $priority_filter !== 'all') {
        $sql .= " AND priority = ?";
        $params[] = $priority_filter;
        $param_types .= "s";
    }
    
    try {
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            error_log("Prepare failed: " . $conn->error);
            return 0;
        }
        
        if ($params) {
            $stmt->bind_param($param_types, ...$params);
        }
        
        if (!$stmt->execute()) {
            error_log("Execute failed: " . $stmt->error);
            $stmt->close();
            return 0;
        }
        
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();
        
        return $row['total'] ?? 0;
    } catch (Exception $e) {
        error_log("Error in getTotalComplaintsCount: " . $e->getMessage());
        return 0;
    }
}

// Get data based on report type
$stats = [];
$dept_stats = [];
$staff_performance = [];
$trends = [];
$detailed_complaints = [];
$total_complaints = 0;
$total_pages = 0;

switch ($report_type) {
    case 'overview':
        $stats = getComplaintStats($conn, $start_date, $end_date, $department_id, $status_filter, $priority_filter);
        $dept_stats = getDepartmentStats($conn, $start_date, $end_date);
        $trends = getComplaintTrends($conn, $start_date, $end_date, 'monthly');
        break;
        
    case 'department':
        $dept_stats = getDepartmentStats($conn, $start_date, $end_date);
        break;
        
    case 'staff':
        $staff_performance = getStaffPerformance($conn, $start_date, $end_date, $department_id);
        break;
        
    case 'trends':
        $trend_group = isset($_GET['trend_group']) ? sanitizeInput($_GET['trend_group']) : 'daily';
        $trends = getComplaintTrends($conn, $start_date, $end_date, $trend_group);
        break;
        
    case 'detailed':
        $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
        $limit = 50;
        $detailed_complaints = getDetailedComplaints($conn, $start_date, $end_date, $department_id, $status_filter, $priority_filter, $page, $limit);
        $total_complaints = getTotalComplaintsCount($conn, $start_date, $end_date, $department_id, $status_filter, $priority_filter);
        $total_pages = ceil($total_complaints / $limit);
        break;
        
    case 'reapplications':
        $reapplication_stats = getReapplicationStats($conn);
        break;
        
case 'export':
        // For export, we'll handle it separately
        break;
}

// Handle export request
if (isset($_GET['export']) && in_array($_GET['export'], ['pdf', 'excel'])) {
    $export_format = $_GET['export'];
    
    // Get all complaints data for export (without pagination limits)
    $export_complaints = getDetailedComplaints($conn, $start_date, $end_date, $department_id, $status_filter, $priority_filter, 1, 100000);
    
    // Also get stats for summary
    $export_stats = getComplaintStats($conn, $start_date, $end_date, $department_id, $status_filter, $priority_filter);
    $export_dept_stats = getDepartmentStats($conn, $start_date, $end_date);
    $export_staff_perf = getStaffPerformance($conn, $start_date, $end_date, $department_id);
    
    if ($export_format === 'excel') {
        // Export as Excel (CSV)
        $filename = "complaints_report_" . date('Y-m-d');
        
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        $output = fopen('php://output', 'w');
        
        // Add UTF-8 BOM for Excel to properly display Unicode characters
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        // Summary section
        fputcsv($output, ['CIVICARE COMPLAINTS REPORT']);
        fputcsv($output, ['Report Date: ' . date('Y-m-d H:i:s')]);
        fputcsv($output, ['Period: ' . $start_date . ' to ' . $end_date]);
        fputcsv($output, []);
        
        // Summary statistics
        fputcsv($output, ['SUMMARY STATISTICS']);
        fputcsv($output, ['Total Complaints', $export_stats['total_complaints'] ?? 0]);
        fputcsv($output, ['Pending', ($export_stats['pending'] ?? 0) + ($export_stats['validated'] ?? 0) + ($export_stats['assigned'] ?? 0) + ($export_stats['in_progress'] ?? 0)]);
        fputcsv($output, ['Resolved', ($export_stats['resolved'] ?? 0) + ($export_stats['closed'] ?? 0)]);
        fputcsv($output, ['Escalated', $export_stats['escalated'] ?? 0]);
        fputcsv($output, ['Average Resolution Hours', number_format($export_stats['avg_resolution_hours'] ?? 0, 2)]);
        fputcsv($output, []);
        
        // Complaints detail header
        fputcsv($output, ['COMPLAINTS DETAIL']);
        fputcsv($output, ['Tracking ID', 'Title', 'Description', 'Complainant Name','Department', 'Status', 'Created Date', 'Resolution Date']);
        
        // Complaints data
        foreach ($export_complaints as $complaint) {
            fputcsv($output, [
                $complaint['tracking_id'] ?? '',
                $complaint['title'] ?? '',
                substr($complaint['description'] ?? '', 0, 500),
                $complaint['complainant_name'] ?? '',
                $complaint['department_name'] ?? '',
                $complaint['status'] ?? '',
                $complaint['created_at'] ?? '',
                $complaint['resolution_date'] ?? ''
            ]);
        }
        
        fputcsv($output, []);
        
        // Department summary
        if (!empty($export_dept_stats)) {
            fputcsv($output, ['DEPARTMENT WISE SUMMARY']);
            fputcsv($output, ['Department', 'Total Complaints', 'Resolved', 'Pending', 'Resolution Rate %']);
            
            foreach ($export_dept_stats as $dept) {
                $resolution_rate = ($dept['complaint_count'] ?? 0) > 0 ? 
                    round((($dept['resolved_count'] ?? 0) / ($dept['complaint_count'] ?? 0)) * 100, 1) : 0;
                fputcsv($output, [
                    $dept['department_name'] ?? '',
                    $dept['complaint_count'] ?? 0,
                    $dept['resolved_count'] ?? 0,
                    $dept['pending_count'] ?? 0,
                    $resolution_rate
                ]);
            }
        }
        
        fputcsv($output, []);
        
        // Staff performance summary
        if (!empty($export_staff_perf)) {
            fputcsv($output, ['STAFF PERFORMANCE SUMMARY']);
            fputcsv($output, ['Staff Name', 'Department', 'Assigned', 'Resolved', 'Active', 'Resolution Rate %']);
            
            foreach ($export_staff_perf as $staff) {
                $resolution_rate = ($staff['assigned_complaints'] ?? 0) > 0 ? 
                    round((($staff['resolved_complaints'] ?? 0) / ($staff['assigned_complaints'] ?? 0)) * 100, 1) : 0;
                fputcsv($output, [
                    $staff['staff_name'] ?? '',
                    $staff['department_name'] ?? '',
                    $staff['assigned_complaints'] ?? 0,
                    $staff['resolved_complaints'] ?? 0,
                    $staff['active_complaints'] ?? 0,
                    $resolution_rate,
                    number_format($staff['avg_rating'] ?? 0, 1)
                ]);
            }
        }
        
        fclose($output);
        exit();
        
    } elseif ($export_format === 'pdf') {
        // Check if TCPDF is available
        $autoload_path = __DIR__ . '/../vendor/autoload.php';
        $tcpdf_available = false;
        
        if (file_exists($autoload_path)) {
            require_once $autoload_path;
            if (class_exists('TCPDF')) {
                $tcpdf_available = true;
            }
        }
        
        if (!$tcpdf_available) {
            // Fallback to CSV if TCPDF not available
            $filename = "complaints_report_" . date('Y-m-d');
            
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
            header('Pragma: no-cache');
            header('Expires: 0');
            
            $output = fopen('php://output', 'w');
            fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
            
            fputcsv($output, ['CIVICARE COMPLAINTS REPORT (PDF not available - CSV export)']);
            fputcsv($output, ['Report Date: ' . date('Y-m-d H:i:s')]);
            fputcsv($output, ['Period: ' . $start_date . ' to ' . $end_date]);
            fputcsv($output, []);
            fputcsv($output, ['Total Complaints', $export_stats['total_complaints'] ?? 0]);
            fputcsv($output, ['Pending', ($export_stats['pending'] ?? 0) + ($export_stats['validated'] ?? 0) + ($export_stats['assigned'] ?? 0) + ($export_stats['in_progress'] ?? 0)]);
            fputcsv($output, ['Resolved', ($export_stats['resolved'] ?? 0) + ($export_stats['closed'] ?? 0)]);
            fputcsv($output, []);
            fputcsv($output, ['Tracking ID', 'Title', 'Department', 'Status', 'Created Date']);
            
            foreach ($export_complaints as $complaint) {
                fputcsv($output, [
                    $complaint['tracking_id'] ?? '',
                    $complaint['title'] ?? '',
                    $complaint['department_name'] ?? '',
                    $complaint['status'] ?? '',
                    //$complaint['priority'] ?? '',
                    $complaint['created_at'] ?? ''
                ]);
            }
            
            fclose($output);
            exit();
        }
        
        // PDF export using TCPDF
        $filename = "complaints_report_" . date('Y-m-d');
        
        $html = "<style>
            body { font-family: helvetica; font-size: 10px; }
            h1 { font-size: 16pt; color: #2c3e50; margin-bottom: 5px; }
            h2 { font-size: 12pt; color: #34495e; margin-top: 15px; margin-bottom: 8px; border-bottom: 1px solid #ccc; }
            .meta { font-size: 9pt; color: #666; margin-bottom: 15px; }
            table { width: 100%; border-collapse: collapse; font-size: 8pt; }
            th, td { border: 1px solid #aaa; padding: 4px; text-align: left; }
            th { background: #3498db; color: white; }
            .summary-row td { font-weight: bold; background: #f8f9fa; }
            .status-pending { color: #f39c12; }
            .status-resolved { color: #27ae60; }
            .status-rejected { color: #e74c3c; }
        </style>";
        
        $html .= "<h1>Civicare Complaints Report</h1>";
        $html .= "<div class='meta'>Generated: " . date('Y-m-d H:i:s') . "<br>Period: " . $start_date . " to " . $end_date . "</div>";
        
        // Summary section
        $html .= "<h2>Summary Statistics</h2>";
        $html .= "<table>";
        $html .= "<tr><td>Total Complaints</td><td><strong>" . ($export_stats['total_complaints'] ?? 0) . "</strong></td></tr>";
        $html .= "<tr><td>Pending</td><td>" . (($export_stats['pending'] ?? 0) + ($export_stats['validated'] ?? 0) + ($export_stats['assigned'] ?? 0) + ($export_stats['in_progress'] ?? 0)) . "</td></tr>";
        $html .= "<tr><td>Resolved</td><td>" . (($export_stats['resolved'] ?? 0) + ($export_stats['closed'] ?? 0)) . "</td></tr>";
        $html .= "<tr><td>Escalated</td><td>" . ($export_stats['escalated'] ?? 0) . "</td></tr>";
        $html .= "<tr><td>Avg Resolution Time</td><td>" . number_format($export_stats['avg_resolution_hours'] ?? 0, 2) . " hours</td></tr>";
        $html .= "</table>";
        
        // Complaints table
        $html .= "<h2>Complaints Detail</h2>";
        $html .= "<table>";
        $html .= "<tr><th>Tracking ID</th><th>Title</th><th>Complainant</th><th>Department</th><th>Status</th><th>Created</th></tr>";
        
        foreach ($export_complaints as $complaint) {
            $status_class = '';
            if (in_array($complaint['status'], ['pending', 'validated', 'assigned', 'in_progress'])) {
                $status_class = 'status-pending';
            } elseif (in_array($complaint['status'], ['resolved', 'closed'])) {
                $status_class = 'status-resolved';
            } elseif ($complaint['status'] === 'rejected') {
                $status_class = 'status-rejected';
            }
            
            $html .= "<tr>";
            $html .= "<td>" . ($complaint['tracking_id'] ?? '') . "</td>";
            $html .= "<td>" . substr(($complaint['title'] ?? ''), 0, 40) . "</td>";
            $html .= "<td>" . ($complaint['complainant_name'] ?? '') . "</td>";
            $html .= "<td>" . ($complaint['department_name'] ?? '') . "</td>";
            $html .= "<td class='" . $status_class . "'>" . ($complaint['status'] ?? '') . "</td>";
            //$html .= "<td>" . ($complaint['priority'] ?? '') . "</td>";
            $html .= "<td>" . ($complaint['created_at'] ?? '') . "</td>";
            $html .= "</tr>";
        }
        $html .= "</table>";
        
        // Department summary
        if (!empty($export_dept_stats)) {
            $html .= "<h2>Department Summary</h2>";
            $html .= "<table>";
            $html .= "<tr><th>Department</th><th>Total</th><th>Resolved</th><th>Pending</th><th>Rate %</th></tr>";
            
            foreach ($export_dept_stats as $dept) {
                $resolution_rate = ($dept['complaint_count'] ?? 0) > 0 ? 
                    round((($dept['resolved_count'] ?? 0) / ($dept['complaint_count'] ?? 0)) * 100, 1) : 0;
                $html .= "<tr>";
                $html .= "<td>" . ($dept['department_name'] ?? '') . "</td>";
                $html .= "<td>" . ($dept['complaint_count'] ?? 0) . "</td>";
                $html .= "<td>" . ($dept['resolved_count'] ?? 0) . "</td>";
                $html .= "<td>" . ($dept['pending_count'] ?? 0) . "</td>";
                $html .= "<td>" . $resolution_rate . "</td>";
                $html .= "</tr>";
            }
            $html .= "</table>";
        }
        
        // Staff performance
        if (!empty($export_staff_perf)) {
            $html .= "<h2>Staff Performance</h2>";
            $html .= "<table>";
            $html .= "<tr><th>Staff</th><th>Department</th><th>Assigned</th><th>Resolved</th><th>Active</th><th>Rate %</th></tr>";
            
            foreach ($export_staff_perf as $staff) {
                $resolution_rate = ($staff['assigned_complaints'] ?? 0) > 0 ? 
                    round((($staff['resolved_complaints'] ?? 0) / ($staff['assigned_complaints'] ?? 0)) * 100, 1) : 0;
                $html .= "<tr>";
                $html .= "<td>" . ($staff['staff_name'] ?? '') . "</td>";
                $html .= "<td>" . ($staff['department_name'] ?? '') . "</td>";
                $html .= "<td>" . ($staff['assigned_complaints'] ?? 0) . "</td>";
                $html .= "<td>" . ($staff['resolved_complaints'] ?? 0) . "</td>";
                $html .= "<td>" . ($staff['active_complaints'] ?? 0) . "</td>";
                $html .= "<td>" . $resolution_rate . "</td>";
                $html .= "</tr>";
            }
            $html .= "</table>";
        }
        
        $pdf = new TCPDF('L', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->SetCreator('Civicare');
        $pdf->SetAuthor('Civicare Admin');
        $pdf->SetTitle('Complaints Report');
        $pdf->setPrintHeader(true);
        $pdf->setPrintFooter(true);
        $pdf->SetMargins(10, 20, 10);
        $pdf->SetAutoPageBreak(true, 15);
        $pdf->SetFont('helvetica', '', 8);
        $pdf->AddPage();
        $pdf->writeHTML($html, true, false, true, false, '');
        $pdf->Output($filename . '.pdf', 'D');
        exit();
    }
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
    <title>Reports & Analytics - Civicare Admin</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f5f5;
        }
        
        .header {
            background: linear-gradient(135deg, #2c3e50, #34495e);
            color: white;
            padding: 1rem 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .container {
            width: 90%;
            max-width: 1600px;
            margin: 0 auto;
            padding: 0 15px;
        }
        
        .navbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 0;
        }
        
        .logo {
            font-size: 1.8rem;
            font-weight: bold;
            color: white;
            text-decoration: none;
        }
        
        .nav-links {
            display: flex;
            gap: 1rem;
            align-items: center;
        }
        
        .nav-links a {
            color: white;
            text-decoration: none;
            padding: 0.5rem 1rem;
            border-radius: 4px;
            transition: background 0.3s;
            font-size: 0.9rem;
        }
        
        .nav-links a:hover {
            background: rgba(255,255,255,0.1);
        }
        
        .btn {
            display: inline-block;
            padding: 0.5rem 1.5rem;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            font-weight: 600;
            transition: background 0.3s;
            border: none;
            cursor: pointer;
            font-size: 0.9rem;
        }
        
        .btn-primary {
            background: #3498db;
        }
        
        .btn-primary:hover {
            background: #2980b9;
        }
        
        .btn-success {
            background: #27ae60;
        }
        
        .btn-success:hover {
            background: #219653;
        }
        
        .btn-danger {
            background: #e74c3c;
        }
        
        .btn-danger:hover {
            background: #c0392b;
        }
        
        .btn-warning {
            background: #f39c12;
        }
        
        .btn-warning:hover {
            background: #d68910;
        }
        
        .btn-info {
            background: #17a2b8;
        }
        
        .btn-info:hover {
            background: #138496;
        }
        
        .page-header {
            background: white;
            padding: 2rem;
            border-radius: 8px;
            margin: 2rem 0;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .page-title h1 {
            color: #2c3e50;
            margin-bottom: 0.5rem;
        }
        
        .page-title p {
            color: #666;
        }
        
        .report-tabs {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 2rem;
            background: white;
            padding: 1rem;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            flex-wrap: wrap;
        }
        
        .report-tab {
            padding: 0.75rem 1.5rem;
            border: none;
            background: #f8f9fa;
            color: #666;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .report-tab:hover {
            background: #e9ecef;
        }
        
        .report-tab.active {
            background: #3498db;
            color: white;
        }
        
        .filters-section {
            background: white;
            padding: 2rem;
            border-radius: 8px;
            margin-bottom: 2rem;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        
        .filters-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .filter-group {
            margin-bottom: 1rem;
        }
        
        .filter-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #333;
            font-size: 0.9rem;
        }
        
        select, input {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #e0e0e0;
            border-radius: 5px;
            font-size: 0.9rem;
            transition: border-color 0.3s;
        }
        
        select:focus, input:focus {
            outline: none;
            border-color: #3498db;
        }
        
        .filter-actions {
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            text-align: center;
            transition: transform 0.3s;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-card h3 {
            font-size: 2rem;
            color: #2c3e50;
            margin-bottom: 0.5rem;
        }
        
        .stat-card.total { border-left: 4px solid #3498db; }
        .stat-card.pending { border-left: 4px solid #f39c12; }
        .stat-card.resolved { border-left: 4px solid #27ae60; }
        .stat-card.escalated { border-left: 4px solid #e74c3c; }
        .stat-card.rating { border-left: 4px solid #9b59b6; }
        .stat-card.time { border-left: 4px solid #1abc9c; }
        
        .report-section {
            background: white;
            padding: 2rem;
            border-radius: 8px;
            margin-bottom: 2rem;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #3498db;
        }
        
        .section-header h3 {
            color: #2c3e50;
        }
        
        .table-container {
            overflow-x: auto;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 800px;
        }
        
        th {
            background: #3498db;
            color: white;
            padding: 1rem;
            text-align: left;
            font-weight: 600;
            font-size: 0.9rem;
        }
        
        td {
            padding: 1rem;
            border-bottom: 1px solid #eee;
            font-size: 0.9rem;
        }
        
        tr:hover {
            background: #f9f9f9;
        }
        
        .status-badge {
            padding: 0.4rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            display: inline-block;
        }
        
        .status-pending { background: #f39c12; color: white; }
        .status-validated { background: #3498db; color: white; }
        .status-rejected { background: #e74c3c; color: white; }
        .status-assigned { background: #9b59b6; color: white; }
        .status-in_progress { background: #1abc9c; color: white; }
        .status-resolved { background: #27ae60; color: white; }
        .status-escalated { background: #e67e22; color: white; }
        .status-closed { background: #7f8c8d; color: white; }
        
        .priority-badge {
            padding: 0.4rem 0.75rem;
            border-radius: 4px;
            font-size: 0.8rem;
            font-weight: 600;
            display: inline-block;
        }
        
        .priority-low { background: #27ae60; color: white; }
        .priority-medium { background: #f39c12; color: white; }
        .priority-high { background: #e74c3c; color: white; }
        .priority-urgent { background: #c0392b; color: white; }
        
        .rating-stars {
            color: #f39c12;
            font-size: 0.9rem;
        }
        
        .progress-bar {
            width: 100%;
            height: 10px;
            background: #ecf0f1;
            border-radius: 5px;
            overflow: hidden;
            margin-top: 0.5rem;
        }
        
        .progress-fill {
            height: 100%;
            background: #3498db;
            transition: width 0.3s;
        }
        
        .chart-container {
            height: 300px;
            margin-top: 1.5rem;
        }
        
        .trend-chart {
            display: flex;
            align-items: flex-end;
            height: 250px;
            gap: 1rem;
            padding: 1rem;
            border-bottom: 2px solid #eee;
            margin-bottom: 1rem;
        }
        
        .trend-bar {
            flex: 1;
            background: #3498db;
            border-radius: 4px 4px 0 0;
            position: relative;
            min-height: 10px;
        }
        
        .trend-bar:hover {
            background: #2980b9;
        }
        
        .trend-value {
            position: absolute;
            top: -25px;
            left: 50%;
            transform: translateX(-50%);
            background: #2c3e50;
            color: white;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.75rem;
            display: none;
        }
        
        .trend-bar:hover .trend-value {
            display: block;
        }

        /* Chart Styles */
        .chart-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .chart-card {
            background: white;
            padding: 1.5rem;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }

        .chart-card h4 {
            color: #2c3e50;
            margin-bottom: 1rem;
            font-size: 1.1rem;
            text-align: center;
        }

        .chart-wrapper {
            position: relative;
            height: 300px;
        }

        .chart-full {
            grid-column: 1 / -1;
        }

        .chart-full .chart-wrapper {
            height: 400px;
        }

        /* Donut Chart Stats */
        .donut-stats {
            display: flex;
            justify-content: center;
            gap: 2rem;
            margin-top: 1rem;
            flex-wrap: wrap;
        }

        .donut-stat {
            text-align: center;
        }

        .donut-stat-value {
            font-size: 1.5rem;
            font-weight: bold;
            color: #2c3e50;
        }

        .donut-stat-label {
            font-size: 0.85rem;
            color: #666;
        }

        /* Animated counters */
        .counter {
            display: inline-block;
        }

        @keyframes countUp {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .counter.animate {
            animation: countUp 0.5s ease-out forwards;
        }

        /* Gauge Chart */
        .gauge-container {
            display: flex;
            justify-content: center;
            align-items: center;
            height: 200px;
        }

        /* Heatmap Table */
        .heatmap-table {
            width: 100%;
            border-collapse: collapse;
        }

        .heatmap-cell {
            padding: 0.75rem;
            text-align: center;
            border: 1px solid #eee;
            transition: all 0.3s;
        }

        .heatmap-cell:hover {
            transform: scale(1.05);
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .heat-low { background-color: #d4edda; }
        .heat-medium { background-color: #fff3cd; }
        .heat-high { background-color: #f8d7da; }

        /* Interactive comparison */
        .compare-toggle {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 1rem;
            justify-content: flex-end;
        }

        .compare-btn {
            padding: 0.5rem 1rem;
            border: 1px solid #ddd;
            background: white;
            cursor: pointer;
            border-radius: 4px;
            font-size: 0.85rem;
            transition: all 0.3s;
        }

        .compare-btn.active {
            background: #3498db;
            color: white;
            border-color: #3498db;
        }

        .export-options {
            display: flex;
            gap: 1rem;
            margin-top: 1rem;
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            gap: 0.5rem;
            padding: 1.5rem;
            background: #f8f9fa;
            border-radius: 0 0 8px 8px;
        }
        
        .pagination a, .pagination span {
            padding: 0.5rem 1rem;
            border: 1px solid #ddd;
            border-radius: 4px;
            text-decoration: none;
            color: #333;
            font-size: 0.9rem;
        }
        
        .pagination a:hover {
            background: #3498db;
            color: white;
            border-color: #3498db;
        }
        
        .pagination .active {
            background: #3498db;
            color: white;
            border-color: #3498db;
        }
        
        .no-data {
            text-align: center;
            padding: 3rem;
            color: #666;
        }
        
        .loading {
            text-align: center;
            padding: 2rem;
            color: #666;
        }
        
        .alert {
            padding: 1rem;
            border-radius: 5px;
            margin-bottom: 1.5rem;
            font-size: 0.95rem;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        @media (max-width: 768px) {
            .navbar {
                flex-direction: column;
                gap: 1rem;
            }
            
            .nav-links {
                flex-wrap: wrap;
                justify-content: center;
            }
            
            .page-header {
                flex-direction: column;
                gap: 1rem;
                text-align: center;
            }
            
            .report-tabs {
                flex-wrap: wrap;
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
            
            .export-options {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="container">
            <nav class="navbar">
                <a href="index.php" class="logo">Civicare Admin</a>
                <div class="nav-links">
                    <a href="index.php">Dashboard</a>
                    <a href="complaints.php">Complaints</a>
                    <a href="users.php">Users</a>
                    <a href="departments.php">Departments</a>
                    <a href="staff.php">Staff</a>
                    <a href="reports.php" style="background: rgba(255,255,255,0.1);">Reports</a>
                    <a href="../logout.php" class="btn btn-danger">Logout</a>
                </div>
            </nav>
        </div>
    </div>

    <div class="container">
        <div class="page-header">
            <div class="page-title">
                <h1>Reports & Analytics</h1>
                <p>Comprehensive complaint analysis and performance metrics</p>
            </div>
            <div class="export-options">
                <button class="btn btn-info" onclick="exportReport('pdf')">Export PDF</button>
                <button class="btn btn-success" onclick="exportReport('excel')">Export Excel</button>
            </div>
        </div>

        <?php if (isset($_GET['error'])): ?>
            <div class="alert alert-error">
                <?php echo htmlspecialchars($_GET['error']); ?>
            </div>
        <?php endif; ?>

        <div class="report-tabs">
            <button class="report-tab <?php echo $report_type === 'overview' ? 'active' : ''; ?>" 
                    onclick="changeReportType('overview')">
                Overview
            </button>
            <button class="report-tab <?php echo $report_type === 'department' ? 'active' : ''; ?>" 
                    onclick="changeReportType('department')">
                Department Report
            </button>
            <!--<button class="report-tab <?php echo $report_type === 'staff' ? 'active' : ''; ?>" 
                    onclick="changeReportType('staff')">
                Staff Performance
            </button>-->
            <!--<button class="report-tab <?php echo $report_type === 'trends' ? 'active' : ''; ?>" 
                    onclick="changeReportType('trends')">
                Trends Analysis
            </button>-->
            <button class="report-tab <?php echo $report_type === 'detailed' ? 'active' : ''; ?>" 
                    onclick="changeReportType('detailed')">
                Detailed Report
            </button>
            <!--<button class="report-tab <?php echo $report_type === 'reapplications' ? 'active' : ''; ?>" 
                    onclick="changeReportType('reapplications')">
                Reapplications Analytics
            </button>-->
        </div>

        <div class="filters-section">
            <form method="GET" action="" id="reportForm">
                <input type="hidden" name="report_type" id="report_type" value="<?php echo $report_type; ?>">
                
                <div class="filters-grid">
                    <div class="filter-group">
                        <label for="start_date">Start Date</label>
                        <input type="date" id="start_date" name="start_date" 
                               value="<?php echo $start_date; ?>" required>
                    </div>
                    
                    <div class="filter-group">
                        <label for="end_date">End Date</label>
                        <input type="date" id="end_date" name="end_date" 
                               value="<?php echo $end_date; ?>" required>
                    </div>
                    
                    <div class="filter-group">
                        <label for="department_id">Department</label>
                        <select id="department_id" name="department_id">
                            <option value="0">All Departments</option>
                            <?php foreach ($departments as $dept): ?>
                                <option value="<?php echo $dept['id']; ?>" 
                                    <?php echo $department_id == $dept['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($dept['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label for="status">Status</label>
                        <select id="status" name="status">
                            <option value="all">All Status</option>
                            <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="validated" <?php echo $status_filter === 'validated' ? 'selected' : ''; ?>>Validated</option>
                            <option value="rejected" <?php echo $status_filter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                            <option value="assigned" <?php echo $status_filter === 'assigned' ? 'selected' : ''; ?>>Assigned</option>
                            <option value="in_progress" <?php echo $status_filter === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                            <option value="resolved" <?php echo $status_filter === 'resolved' ? 'selected' : ''; ?>>Resolved</option>
                            <option value="escalated" <?php echo $status_filter === 'escalated' ? 'selected' : ''; ?>>Escalated</option>
                            <option value="closed" <?php echo $status_filter === 'closed' ? 'selected' : ''; ?>>Closed</option>
                        </select>
                    </div>
                    
                   <!-- <div class="filter-group">
                        <label for="priority">Priority</label>
                        <select id="priority" name="priority">
                            <option value="all">All Priorities</option>
                            <option value="low" <?php echo $priority_filter === 'low' ? 'selected' : ''; ?>>Low</option>
                            <option value="medium" <?php echo $priority_filter === 'medium' ? 'selected' : ''; ?>>Medium</option>
                            <option value="high" <?php echo $priority_filter === 'high' ? 'selected' : ''; ?>>High</option>
                            <option value="urgent" <?php echo $priority_filter === 'urgent' ? 'selected' : ''; ?>>Urgent</option>
                        </select>
                    </div>-->
                    
                    <?php if ($report_type === 'trends'): ?>
                    <div class="filter-group">
                        <label for="trend_group">Group By</label>
                        <select id="trend_group" name="trend_group">
                            <option value="daily" <?php echo (!isset($_GET['trend_group']) || $_GET['trend_group'] === 'daily') ? 'selected' : ''; ?>>Daily</option>
                            <option value="monthly" <?php echo (isset($_GET['trend_group']) && $_GET['trend_group'] === 'monthly') ? 'selected' : ''; ?>>Monthly</option>
                        </select>
                    </div>
                    <?php endif; ?>
                </div>
                
                <div class="filter-actions">
                    <button type="submit" class="btn btn-primary">Generate Report</button>
                    <button type="button" class="btn" onclick="resetFilters()">Reset Filters</button>
                </div>
            </form>
        </div>

        <?php if ($report_type === 'overview'): ?>
            <?php if ($stats && (!empty($stats['total_complaints']) || $stats['total_complaints'] == 0)): ?>
                <div class="stats-grid">
                    <div class="stat-card total">
                        <h3><?php echo $stats['total_complaints'] ?? 0; ?></h3>
                        <p>Total Complaints</p>
                    </div>
                    
                    <div class="stat-card pending">
                        <h3><?php echo ($stats['pending'] ?? 0) + ($stats['validated'] ?? 0) + ($stats['assigned'] ?? 0) + ($stats['in_progress'] ?? 0); ?></h3>
                        <p>Pending Resolution</p>
                    </div>
                    
                    <div class="stat-card resolved">
                        <h3><?php echo ($stats['resolved'] ?? 0) + ($stats['closed'] ?? 0); ?></h3>
                        <p>Resolved Complaints</p>
                    </div>
                    
                    <!--<div class="stat-card escalated">
                        <h3><?php echo $stats['escalated'] ?? 0; ?></h3>
                        <p>Escalated Cases</p>
                    </div>-->
                    
                    <!--<div class="stat-card rating">
                        <h3><?php echo number_format($stats['unique_complainants'] ?? 0); ?></h3>
                        <p>Unique Complainants</p>
                    </div>

                    <div class="stat-card rating">
                        <h3>
                            <?php echo ($stats['rated_complaints'] ?? 0) > 0
                                ? number_format($stats['avg_rating'] ?? 0, 1)
                                : 'N/A'; ?>
                        </h3>
                        <p>Avg Rating</p>
                        <small style="color: #666;">
                            <?php echo number_format($stats['rated_complaints'] ?? 0); ?> rated
                        </small>
                    </div>
                    
                    <div class="stat-card time">
                        <h3><?php echo number_format($stats['avg_resolution_hours'] ?? 0, 1); ?></h3>
                        <p>Avg. Resolution Time (hrs)</p>
                    </div>-->
                </div>

                <!-- Chart Visualizations -->
                <div class="chart-row">
                    <div class="chart-card">
                        <h4>Complaint Status Distribution</h4>
                        <div class="chart-wrapper">
                            <canvas id="statusPieChart"></canvas>
                        </div>
                        <div class="donut-stats">
                            <div class="donut-stat">
                                <div class="donut-stat-value"><?php echo ($stats['pending'] ?? 0) + ($stats['validated'] ?? 0) + ($stats['assigned'] ?? 0) + ($stats['in_progress'] ?? 0); ?></div>
                                <div class="donut-stat-label">Pending</div>
                            </div>
                            <div class="donut-stat">
                                <div class="donut-stat-value"><?php echo ($stats['resolved'] ?? 0) + ($stats['closed'] ?? 0); ?></div>
                                <div class="donut-stat-label">Resolved</div>
                            </div>
                            <div class="donut-stat">
                                <div class="donut-stat-value"><?php echo $stats['escalated'] ?? 0; ?></div>
                                <div class="donut-stat-label">Escalated</div>
                            </div>
                        </div>
                    </div>
                    <!--<div class="chart-card">
                        <h4>Complaint Trends Over Time</h4>
                        <div class="chart-wrapper">
                            <canvas id="trendsLineChart"></canvas>
                        </div>
                    </div>
                </div>

                <div class="chart-row">
                    <div class="chart-card">
                        <h4>Staff Performance Comparison</h4>
                        <div class="chart-wrapper">
                            <canvas id="staffBarChart"></canvas>
                        </div>
                    </div>-->
                    <div class="chart-card">
                        <h4>Monthly vs Previous Month</h4>
                        <div class="chart-wrapper">
                            <canvas id="monthlyComparisonChart"></canvas>
                        </div>
                    </div>
                </div>

            <?php else: ?>
                <div class="no-data">
                    <p>No complaint data available for the selected period and filters.</p>
                </div>
            <?php endif; ?>

        <?php elseif ($report_type === 'department'): ?>
            <div class="report-section">
                <div class="section-header">
                    <h3>Department-wise Complaint Report</h3>
                    <span>Period: <?php echo date('d M Y', strtotime($start_date)); ?> to <?php echo date('d M Y', strtotime($end_date)); ?></span>
                </div>
                
                <?php if (!empty($dept_stats)): ?>
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>Department</th>
                                    <th>Total Complaints</th>
                                    <th>Resolved</th>
                                    <th>Pending</th>
                                    <th>Resolution Rate</th>
                                    <th>Avg. Resolution Time</th>
                                    <th>Staff Assigned</th>
                                    <th>First Complaint</th>
                                    <th>Last Complaint</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($dept_stats as $dept): 
                                    $resolution_rate = ($dept['complaint_count'] ?? 0) > 0 ? 
                                        round((($dept['resolved_count'] ?? 0) / ($dept['complaint_count'] ?? 0)) * 100, 1) : 0;
                                ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($dept['department_name'] ?? 'N/A'); ?></strong></td>
                                        <td><?php echo $dept['complaint_count'] ?? 0; ?></td>
                                        <td><?php echo $dept['resolved_count'] ?? 0; ?></td>
                                        <td><?php echo $dept['pending_count'] ?? 0; ?></td>
                                        <td>
                                            <div style="display: flex; align-items: center; gap: 0.5rem;">
                                                <span><?php echo $resolution_rate; ?>%</span>
                                                <div class="progress-bar">
                                                    <div class="progress-fill" style="width: <?php echo $resolution_rate; ?>%"></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td><?php echo number_format($dept['avg_resolution_hours'] ?? 0, 1); ?> hrs</td>
                                        <td><?php echo $dept['staff_assigned'] ?? 0; ?></td>
                                        <td><?php echo $dept['first_complaint'] ? date('d M Y', strtotime($dept['first_complaint'])) : 'N/A'; ?></td>
                                        <td><?php echo $dept['last_complaint'] ? date('d M Y', strtotime($dept['last_complaint'])) : 'N/A'; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="no-data">
                        <p>No department data available for the selected period.</p>
                    </div>
                <?php endif; ?>
            </div>

        <?php elseif ($report_type === 'staff'): ?>
            <div class="report-section">
                <div class="section-header">
                    <h3>Staff Performance Report</h3>
                    <span>Period: <?php echo date('d M Y', strtotime($start_date)); ?> to <?php echo date('d M Y', strtotime($end_date)); ?></span>
                </div>
                
                <?php if (!empty($staff_performance)): ?>
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>Staff Name</th>
                                    <th>Department</th>
                                    <th>Assigned Complaints</th>
                                    <th>Resolved</th>
                                    <th>Active</th>
                                    <th>Resolution Rate</th>
                                    <th>Avg. Resolution Time</th>
                                    <th>Avg. Rating</th>
                                    <th>Last Resolution</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($staff_performance as $staff): 
                                    $resolution_rate = ($staff['assigned_complaints'] ?? 0) > 0 ? 
                                        round((($staff['resolved_complaints'] ?? 0) / ($staff['assigned_complaints'] ?? 0)) * 100, 1) : 0;
                                ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($staff['staff_name'] ?? 'N/A'); ?></strong><br>
                                            <small><?php echo htmlspecialchars($staff['email'] ?? ''); ?></small>
                                        </td>
                                        <td><?php echo htmlspecialchars($staff['department_name'] ?? 'N/A'); ?></td>
                                        <td><?php echo $staff['assigned_complaints'] ?? 0; ?></td>
                                        <td><?php echo $staff['resolved_complaints'] ?? 0; ?></td>
                                        <td><?php echo $staff['active_complaints'] ?? 0; ?></td>
                                        <td>
                                            <div style="display: flex; align-items: center; gap: 0.5rem;">
                                                <span><?php echo $resolution_rate; ?>%</span>
                                                <div class="progress-bar">
                                                    <div class="progress-fill" style="width: <?php echo $resolution_rate; ?>%"></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td><?php echo number_format($staff['avg_resolution_hours'] ?? 0, 1); ?> hrs</td>
                                        <td>
                                            <?php if ($staff['avg_rating']): ?>
                                                <div class="rating-stars">
                                                    <?php 
                                                    $rating = round($staff['avg_rating']);
                                                    echo str_repeat('★', $rating) . str_repeat('☆', 5 - $rating);
                                                    ?>
                                                    <span style="color: #666; margin-left: 0.5rem;">(<?php echo number_format($staff['avg_rating'], 1); ?>)</span>
                                                </div>
                                            <?php else: ?>
                                                <span style="color: #95a5a6;">Not rated</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo $staff['last_resolution'] ? date('d M Y', strtotime($staff['last_resolution'])) : 'N/A'; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="no-data">
                        <p>No staff performance data available for the selected period.</p>
                    </div>
                <?php endif; ?>
            </div>

        <?php elseif ($report_type === 'trends'): ?>
            <div class="report-section">
                <div class="section-header">
                    <h3>Complaint Trends Analysis</h3>
                    <span>Period: <?php echo date('d M Y', strtotime($start_date)); ?> to <?php echo date('d M Y', strtotime($end_date)); ?></span>
                </div>
                
                <?php if (!empty($trends)): ?>
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>Period</th>
                                    <th>Total Complaints</th>
                                    <th>Resolved</th>
                                    <th>Pending</th>
                                    <th>Resolution Rate</th>
                                    <th>Avg. Rating</th>
                                    <th>Trend</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $previous_total = null;
                                foreach ($trends as $index => $trend): 
                                    $resolution_rate = ($trend['total_complaints'] ?? 0) > 0 ? 
                                        round((($trend['resolved_complaints'] ?? 0) / ($trend['total_complaints'] ?? 0)) * 100, 1) : 0;
                                    
                                    $trend_icon = '';
                                    $trend_color = '';
                                    if ($previous_total !== null) {
                                        if ($trend['total_complaints'] > $previous_total) {
                                            $trend_icon = '↑';
                                            $trend_color = '#e74c3c';
                                        } elseif ($trend['total_complaints'] < $previous_total) {
                                            $trend_icon = '↓';
                                            $trend_color = '#27ae60';
                                        } else {
                                            $trend_icon = '→';
                                            $trend_color = '#f39c12';
                                        }
                                    }
                                    $previous_total = $trend['total_complaints'] ?? 0;
                                ?>
                                    <tr>
                                        <td><strong><?php echo $trend['period'] ?? 'N/A'; ?></strong></td>
                                        <td><?php echo $trend['total_complaints'] ?? 0; ?></td>
                                        <td><?php echo $trend['resolved_complaints'] ?? 0; ?></td>
                                        <td><?php echo $trend['pending_complaints'] ?? 0; ?></td>
                                        <td><?php echo $resolution_rate; ?>%</td>
                                        <td>
                                            <?php if ($trend['avg_rating']): ?>
                                                <div class="rating-stars">
                                                    <?php 
                                                    $rating = round($trend['avg_rating']);
                                                    echo str_repeat('★', $rating) . str_repeat('☆', 5 - $rating);
                                                    ?>
                                                </div>
                                            <?php else: ?>
                                                <span style="color: #95a5a6;">N/A</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($trend_icon): ?>
                                                <span style="color: <?php echo $trend_color; ?>; font-weight: bold;"><?php echo $trend_icon; ?></span>
                                            <?php endif; ?>
                                        </td>
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
                                    <th>Complaint Details</th>
                                    <th>Complainant</th>
                                    <th>Department/Staff</th>
                                    <th>Status & Priority</th>
    
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($detailed_complaints as $complaint): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($complaint['tracking_id'] ?? 'N/A'); ?></strong><br>
                                            <small><?php echo date('d M Y', strtotime($complaint['created_at'] ?? '')); ?></small>
                                        </td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($complaint['title'] ?? 'No Title'); ?></strong><br>
                                            <small style="color: #666;"><?php echo substr(htmlspecialchars($complaint['description'] ?? ''), 0, 100); ?>...</small><br>
                                            <small><strong>Location:</strong> <?php echo htmlspecialchars($complaint['location'] ?? 'N/A'); ?></small>
                                        </td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($complaint['complainant_name'] ?? 'N/A'); ?></strong><br>
                                        </td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($complaint['department_name'] ?? 'N/A'); ?></strong><br>
                                            
                                        </td>
                                        <td>
                                            <?php if ($complaint['status']): ?>
                                                <span class="status-badge status-<?php echo $complaint['status']; ?>">
                                                    <?php echo ucfirst(str_replace('_', ' ', $complaint['status'])); ?>
                                                </span><br>
                                            <?php endif; ?>
                                            <small style="color: #666; margin-top: 0.25rem; display: block;">
                                                Updates: <?php echo $complaint['update_count'] ?? 0; ?>
                                            </small>
                                        </td>
                                        
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
            
        <?php elseif ($report_type === 'reapplications'): ?>
            <div class="report-section">
                <div class="section-header">
                    <h3>Reapplication Analytics Report</h3>
                    <span>Analysis of complaint reapplications and satisfaction trends</span>
                </div>
                
                <?php if (!empty($reapplication_stats)): ?>
                    <div class="stats-grid">
                        <div class="stat-card total">
                            <h3><?php echo $reapplication_stats['total_reapplications'] ?? 0; ?></h3>
                            <p>Total Reapplications</p>
                        </div>
                        
                        <div class="stat-card resolved">
                            <h3><?php echo $reapplication_stats['complaints_with_reapplications'] ?? 0; ?></h3>
                            <p>Complaints with Reapplications</p>
                        </div>
                        
                       <!-- <div class="stat-card rating">
                            <h3><?php echo number_format($reapplication_stats['original_avg_rating'] ?? 0, 2); ?></h3>
                            <p>Original Avg Rating</p>
                            <small style="color: #666;">out of 5 stars</small>
                        </div>-->
                        
                       <!-- <div class="stat-card" style="background: linear-gradient(135deg, #fff, #fff8f8); color: black;">
                            <h3><?php echo number_format($reapplication_stats['reapplication_avg_rating'] ?? 0, 2); ?></h3>
                            <p>Reapplication Avg Rating</p>
                            <small style="opacity: 0.9;">out of 5 stars</small>
                        </div>-->
                    </div>
                    
                    <div class="chart-row">
                        <div class="chart-card">
                            <h4>Reapplications by Status</h4>
                            <div class="chart-wrapper">
                                <canvas id="reapplicationStatusChart"></canvas>
                            </div>
                        </div>
                        <div class="chart-card">
                            <h4>Rating Comparison</h4>
                            <table style="width: 100%;">
                                <thead>
                                    <tr style="background: #f5f5f5; border-bottom: 2px solid #ddd;">
                                        <th style="padding: 10px; text-align: left;">Metric</th>
                                        <th style="padding: 10px; text-align: right;">Original</th>
                                        <th style="padding: 10px; text-align: right;">Reapplication</th>
                                        <th style="padding: 10px; text-align: right;">Change</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr style="border-bottom: 1px solid #eee;">
                                        <!--<td style="padding: 10px;"><strong>Average Rating</strong></td>-->
                                        <td style="padding: 10px; text-align: right;"><?php echo number_format($reapplication_stats['original_avg_rating'] ?? 0, 2); ?>/5</td>
                                        <td style="padding: 10px; text-align: right;"><?php echo number_format($reapplication_stats['reapplication_avg_rating'] ?? 0, 2); ?>/5</td>
                                        <td style="padding: 10px; text-align: right; font-weight: bold; color: <?php echo ($reapplication_stats['reapplication_avg_rating'] ?? 0) > ($reapplication_stats['original_avg_rating'] ?? 0) ? '#27ae60' : '#e74c3c'; ?>;">
                                            <?php $change = ($reapplication_stats['reapplication_avg_rating'] ?? 0) - ($reapplication_stats['original_avg_rating'] ?? 0); echo ($change > 0 ? '+' : '') . number_format($change, 2); ?>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    
                    <?php if (!empty($reapplication_stats['reapplications_by_status'])): ?>
                    <div class="table-container" style="margin-top: 30px;">
                        <h4 style="margin-bottom: 15px;">Reapplications by Status</h4>
                        <table>
                            <thead>
                                <tr>
                                    <th>Status</th>
                                    <th>Count</th>
                                    <th>Percentage</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $total = array_sum($reapplication_stats['reapplications_by_status'] ?? []);
                                foreach ($reapplication_stats['reapplications_by_status'] as $status => $count): 
                                    $percentage = $total > 0 ? round(($count / $total) * 100, 1) : 0;
                                ?>
                                <tr>
                                    <td>
                                        <span class="status-badge status-<?php echo $status; ?>" style="padding: 5px 10px; border-radius: 3px; display: inline-block;">
                                            <?php echo ucfirst(str_replace('_', ' ', $status)); ?>
                                        </span>
                                    </td>
                                    <td><?php echo $count; ?></td>
                                    <td>
                                        <div style="display: flex; align-items: center; gap: 10px;">
                                            <div style="background: #e0e0e0; border-radius: 5px; height: 20px; width: 100px; overflow: hidden;">
                                                <div style="background: #3498db; height: 100%; width: percentage; width: <?php echo $percentage; ?>%;"></div>
                                            </div>
                                            <span><?php echo $percentage; ?>%</span>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                    
                    <div class="info-box" style="background: #e8f4f8; border-left: 4px solid #3498db; padding: 15px; margin-top: 20px; border-radius: 5px;">
                        <h4 style="color: #2c3e50; margin-top: 0;">Key Insights:</h4>
                        <ul style="margin: 10px 0; padding-left: 20px;">
                            <li>Total reapplications tracked: <strong><?php echo $reapplication_stats['total_reapplications'] ?? 0; ?></strong></li>
                            <li>Complaints with at least one reapplication: <strong><?php echo $reapplication_stats['complaints_with_reapplications'] ?? 0; ?></strong></li>
                            <li>Reapplication rate: <strong><?php echo $reapplication_stats['complaints_with_reapplications'] > 0 && $reapplication_stats['total_reapplications'] > 0 ? number_format(($reapplication_stats['total_reapplications'] / ($reapplication_stats['complaints_with_reapplications'] * 3)) * 100, 1) : 'N/A'; ?>%</strong></li>
                            <li>Overall satisfaction trend: <?php 
                                $rating_change = ($reapplication_stats['reapplication_avg_rating'] ?? 0) - ($reapplication_stats['original_avg_rating'] ?? 0);
                                if ($rating_change > 0) {
                                    echo '<span style="color: #27ae60;">📈 Improving</span>';
                                } elseif ($rating_change < 0) {
                                    echo '<span style="color: #e74c3c;">📉 Declining</span>';
                                } else {
                                    echo '<span style="color: #f39c12;">➡️ Stable</span>';
                                }
                            ?></li>
                        </ul>
                    </div>
                    
                <?php else: ?>
                    <div class="no-data">
                        <p>No reapplication data available yet.</p>
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
            document.getElementById('department_id').value = '0';
            document.getElementById('status').value = 'all';
            document.getElementById('priority').value = 'all';
            
            if (document.getElementById('trend_group')) {
                document.getElementById('trend_group').value = 'daily';
            }
        }
        
function exportReport(format) {
            const params = new URLSearchParams(window.location.search);
            params.set('export', format);
            
            // Redirect to the same page with export parameter
            window.location.href = 'reports.php?' + params.toString();
        }
        
        // Auto-submit when changing trend group
        document.addEventListener('DOMContentLoaded', function() {
            const trendGroup = document.getElementById('trend_group');
            if (trendGroup) {
                trendGroup.addEventListener('change', function() {
                    document.getElementById('reportForm').submit();
                });
            }
        });
        
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
        
        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Ctrl + E to export
            if (e.ctrlKey && e.key === 'e') {
                e.preventDefault();
                exportReport('excel');
            }
            // Ctrl + P to print/export PDF
            if (e.ctrlKey && e.key === 'p') {
                e.preventDefault();
                exportReport('pdf');
            }
        });

        // Chart.js visualizations
        document.addEventListener('DOMContentLoaded', function() {
            // Status Distribution Pie Chart
            const statusCtx = document.getElementById('statusPieChart');
            if (statusCtx) {
                new Chart(statusCtx, {
                    type: 'doughnut',
                    data: {
                        labels: ['Pending', 'Validated', 'Rejected', 'Assigned', 'In Progress', 'Resolved', 'Escalated', 'Closed'],
                        datasets: [{
                            data: [
                                <?php echo ($stats['pending'] ?? 0) + ($stats['validated'] ?? 0) + ($stats['assigned'] ?? 0) + ($stats['in_progress'] ?? 0); ?>,
                                <?php echo $stats['validated'] ?? 0; ?>,
                                <?php echo $stats['rejected'] ?? 0; ?>,
                                <?php echo $stats['assigned'] ?? 0; ?>,
                                <?php echo $stats['in_progress'] ?? 0; ?>,
                                <?php echo ($stats['resolved'] ?? 0) + ($stats['closed'] ?? 0); ?>,
                                <?php echo $stats['escalated'] ?? 0; ?>,
                                <?php echo $stats['closed'] ?? 0; ?>
                            ],
                            backgroundColor: [
                                '#f39c12', '#3498db', '#e74c3c', '#9b59b6',
                                '#1abc9c', '#27ae60', '#e67e22', '#7f8c8d'
                            ],
                            borderWidth: 2,
                            borderColor: '#fff'
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'bottom',
                                labels: {
                                    padding: 20,
                                    usePointStyle: true
                                }
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                        const percentage = total > 0 ? ((context.parsed / total) * 100).toFixed(1) : 0;
                                        return context.label + ': ' + context.parsed + ' (' + percentage + '%)';
                                    }
                                }
                            }
                        },
                        cutout: '60%'
                    }
                });
            }


            // Complaint Trends Line Chart
            const trendsLineCtx = document.getElementById('trendsLineChart');
            if (trendsLineCtx && <?php echo !empty($trends) ? 'true' : 'false'; ?>) {
                const trendsLabels = <?php echo json_encode(array_column($trends, 'period')); ?>;
                const trendsTotalData = <?php echo json_encode(array_column($trends, 'total_complaints')); ?>;
                const trendsResolvedData = <?php echo json_encode(array_column($trends, 'resolved_complaints')); ?>;
                const trendsPendingData = <?php echo json_encode(array_column($trends, 'pending_complaints')); ?>;

                new Chart(trendsLineCtx, {
                    type: 'line',
                    data: {
                        labels: trendsLabels,
                        datasets: [{
                            label: 'Total Complaints',
                            data: trendsTotalData,
                            borderColor: '#3498db',
                            backgroundColor: 'rgba(52, 152, 219, 0.1)',
                            tension: 0.4,
                            fill: true
                        }, {
                            label: 'Resolved',
                            data: trendsResolvedData,
                            borderColor: '#27ae60',
                            backgroundColor: 'rgba(39, 174, 96, 0.1)',
                            tension: 0.4,
                            fill: true
                        }, {
                            label: 'Pending',
                            data: trendsPendingData,
                            borderColor: '#f39c12',
                            backgroundColor: 'rgba(243, 156, 18, 0.1)',
                            tension: 0.4,
                            fill: true
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        interaction: {
                            mode: 'index',
                            intersect: false,
                        },
                        plugins: {
                            legend: {
                                position: 'top'
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        return context.dataset.label + ': ' + context.parsed.y;
                                    }
                                }
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    stepSize: 1
                                }
                            }
                        }
                    }
                });
            }

            // Staff Performance Bar Chart
            const staffBarCtx = document.getElementById('staffBarChart');
            if (staffBarCtx && <?php echo !empty($staff_performance) ? 'true' : 'false'; ?>) {
                const staffLabels = <?php echo json_encode(array_map(function($staff) {
                    return substr($staff['staff_name'] ?? 'Unknown', 0, 15) . (strlen($staff['staff_name'] ?? '') > 15 ? '...' : '');
                }, $staff_performance)); ?>;
                const staffResolvedData = <?php echo json_encode(array_column($staff_performance, 'resolved_complaints')); ?>;
                const staffActiveData = <?php echo json_encode(array_column($staff_performance, 'active_complaints')); ?>;

                new Chart(staffBarCtx, {
                    type: 'bar',
                    data: {
                        labels: staffLabels,
                        datasets: [{
                            label: 'Resolved Complaints',
                            data: staffResolvedData,
                            backgroundColor: '#27ae60',
                            borderColor: '#219653',
                            borderWidth: 1
                        }, {
                            label: 'Active Complaints',
                            data: staffActiveData,
                            backgroundColor: '#f39c12',
                            borderColor: '#d68910',
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    stepSize: 1
                                }
                            }
                        },
                        plugins: {
                            legend: {
                                position: 'top'
                            }
                        }
                    }
                });
            }

            // Monthly vs Previous Month Comparison Chart
            const monthlyComparisonCtx = document.getElementById('monthlyComparisonChart');
            if (monthlyComparisonCtx && <?php echo !empty($trends) ? 'true' : 'false'; ?>) {
                const trendsData = <?php echo json_encode($trends); ?>;
                
                // Get current month and previous month data
                const currentMonth = trendsData.length > 0 ? trendsData[trendsData.length - 1] : null;
                const previousMonth = trendsData.length > 1 ? trendsData[trendsData.length - 2] : null;
                
                new Chart(monthlyComparisonCtx, {
                    type: 'bar',
                    data: {
                        labels: previousMonth ? ['Previous Month', 'Current Month'] : ['Current Month'],
                        datasets: [{
                            label: 'Total Complaints',
                            data: previousMonth 
                                ? [previousMonth.total_complaints, currentMonth.total_complaints]
                                : [currentMonth.total_complaints],
                            backgroundColor: ['#95a5a6', '#3498db'],
                            borderColor: ['#7f8c8d', '#2980b9'],
                            borderWidth: 1
                        }, {
                            label: 'Resolved',
                            data: previousMonth
                                ? [previousMonth.resolved_complaints, currentMonth.resolved_complaints]
                                : [currentMonth.resolved_complaints],
                            backgroundColor: ['#a3d9a5', '#27ae60'],
                            borderColor: ['#82c785', '#219653'],
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    stepSize: 1
                                }
                            }
                        },
                        plugins: {
                            legend: {
                                position: 'top'
                            }
                        }
                    }
                });
            }

            // Animate counters
            const counters = document.querySelectorAll('.stat-card h3');
            counters.forEach(counter => {
                const target = parseInt(counter.textContent.replace(/,/g, ''));
                if (!isNaN(target)) {
                    animateCounter(counter, target);
                }
            });

            function animateCounter(element, target) {
                let current = 0;
                const increment = target / 50;
                const timer = setInterval(() => {
                    current += increment;
                    if (current >= target) {
                        element.textContent = target.toLocaleString();
                        clearInterval(timer);
                    } else {
                        element.textContent = Math.floor(current).toLocaleString();
                    }
                }, 30);
            }

            // Add interactive features
            // Chart click handlers
            document.querySelectorAll('canvas').forEach(canvas => {
                canvas.addEventListener('click', function(event) {
                    const activePoints = this.chart.getElementsAtEventForMode(event, 'nearest', { intersect: true }, false);
                    if (activePoints.length > 0) {
                        const dataIndex = activePoints[0].index;
                        const datasetIndex = activePoints[0].datasetIndex;
                        const chart = this.chart;
            // Reapplication Status Chart
            const reapplicationStatusCtx = document.getElementById('reapplicationStatusChart');
            if (reapplicationStatusCtx && <?php echo !empty($reapplication_stats['reapplications_by_status']) ? 'true' : 'false'; ?>) {
                const reapplicationLabels = <?php echo json_encode(array_map(function($status) {
                    return ucfirst(str_replace('_', ' ', $status));
                }, array_keys($reapplication_stats['reapplications_by_status'] ?? []))); ?>;
                const reapplicationData = <?php echo json_encode(array_values($reapplication_stats['reapplications_by_status'] ?? [])); ?>;

                new Chart(reapplicationStatusCtx, {
                    type: 'doughnut',
                    data: {
                        labels: reapplicationLabels,
                        datasets: [{
                            data: reapplicationData,
                            backgroundColor: [
                                '#f39c12', '#3498db', '#e74c3c', '#9b59b6',
                                '#1abc9c', '#27ae60', '#e67e22', '#7f8c8d'
                            ],
                            borderWidth: 2,
                            borderColor: '#fff'
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'bottom',
                                labels: {
                                    padding: 15,
                                    font: {
                                        size: 12
                                    }
                                }
                            }
                        }
                    }
                });
            }

                        // Add visual feedback
                        const meta = chart.getDatasetMeta(datasetIndex);
                        const element = meta.data[dataIndex];
                        element.options.backgroundColor = '#ff6b6b';

                        setTimeout(() => {
                            element.options.backgroundColor = chart.data.datasets[datasetIndex].backgroundColor[dataIndex] || chart.data.datasets[datasetIndex].backgroundColor;
                            chart.update();
                        }, 200);
                    }
                });
            });
        });
    </script>
</body>
</html>