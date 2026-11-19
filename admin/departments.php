<?php
// civicare/admin/departments.php
require_once '../config/database.php';
require_once '../config/functions.php';

// Check if user is logged in and is admin
if (!isLoggedIn() || (!hasRole('admin') && !hasRole('staff'))) {
    header('Location: login.php');
    exit();
}

$conn = getDatabaseConnection();

$error = '';
$success = '';
$action = isset($_GET['action']) ? sanitizeInput($_GET['action']) : '';
$department_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $post_action = sanitizeInput($_POST['action']);
    
    switch ($post_action) {
        case 'add_department':
            $name = sanitizeInput($_POST['name']);
            $description = sanitizeInput($_POST['description']);
            $is_active = isset($_POST['is_active']) ? 1 : 0;
            
            if (empty($name)) {
                $error = "Department name is required.";
            } else {
                // Check if department already exists
                $check_sql = "SELECT id FROM departments WHERE name = ?";
                $check_stmt = $conn->prepare($check_sql);
                $check_stmt->bind_param("s", $name);
                $check_stmt->execute();
                $check_result = $check_stmt->get_result();
                
                if ($check_result->num_rows > 0) {
                    $error = "Department with this name already exists.";
                } else {
                    $sql = "INSERT INTO departments (name, description, is_active, created_at) 
                            VALUES (?, ?, ?, NOW())";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("ssi", $name, $description, $is_active);
                    
                    if ($stmt->execute()) {
                        $new_dept_id = $stmt->insert_id;
                        $success = "Department '$name' added successfully.";
                        header("Location: departments.php?action=view&id=$new_dept_id&success=" . urlencode($success));
                        exit();
                    } else {
                        $error = "Failed to add department: " . $conn->error;
                    }
                    $stmt->close();
                }
                $check_stmt->close();
            }
            break;
            
        case 'edit_department':
            $department_id = (int)$_POST['department_id'];
            $name = sanitizeInput($_POST['name']);
            $description = sanitizeInput($_POST['description']);
            $is_active = isset($_POST['is_active']) ? 1 : 0;
            
            if (empty($name)) {
                $error = "Department name is required.";
            } else {
                // Check if department name already exists (excluding current department)
                $check_sql = "SELECT id FROM departments WHERE name = ? AND id != ?";
                $check_stmt = $conn->prepare($check_sql);
                $check_stmt->bind_param("si", $name, $department_id);
                $check_stmt->execute();
                $check_result = $check_stmt->get_result();
                
                if ($check_result->num_rows > 0) {
                    $error = "Another department with this name already exists.";
                } else {
                    $sql = "UPDATE departments SET name = ?, description = ?, is_active = ?, updated_at = NOW() WHERE id = ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("ssii", $name, $description, $is_active, $department_id);
                    
                    if ($stmt->execute()) {
                        $success = "Department updated successfully.";
                    } else {
                        $error = "Failed to update department: " . $conn->error;
                    }
                    $stmt->close();
                }
                $check_stmt->close();
            }
            break;
            
        case 'delete_department':
            $department_id = (int)$_POST['department_id'];
            
            // Check if department has staff assigned
            $staff_check = "SELECT COUNT(*) as staff_count FROM users WHERE department_id = ? AND role = 'staff' AND deleted_at IS NULL";
            $staff_stmt = $conn->prepare($staff_check);
            $staff_stmt->bind_param("i", $department_id);
            $staff_stmt->execute();
            $staff_result = $staff_stmt->get_result();
            $staff_data = $staff_result->fetch_assoc();
            $staff_stmt->close();
            
            // Check if department has complaints
            $complaints_check = "SELECT COUNT(*) as complaints_count FROM complaints WHERE department_id = ?";
            $complaints_stmt = $conn->prepare($complaints_check);
            $complaints_stmt->bind_param("i", $department_id);
            $complaints_stmt->execute();
            $complaints_result = $complaints_stmt->get_result();
            $complaints_data = $complaints_result->fetch_assoc();
            $complaints_stmt->close();
            
            if ($staff_data['staff_count'] > 0) {
                $error = "Cannot delete department. There are " . $staff_data['staff_count'] . " staff members assigned to this department.";
            } elseif ($complaints_data['complaints_count'] > 0) {
                $error = "Cannot delete department. There are " . $complaints_data['complaints_count'] . " complaints associated with this department.";
            } else {
                $sql = "DELETE FROM departments WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("i", $department_id);
                
                if ($stmt->execute()) {
                    $success = "Department deleted successfully.";
                    header("Location: departments.php?success=" . urlencode($success));
                    exit();
                } else {
                    $error = "Failed to delete department: " . $conn->error;
                }
                $stmt->close();
            }
            break;
    }
}

// Get department details for edit/view
$department = null;
if ($department_id > 0 && ($action === 'edit' || $action === 'view')) {
    $sql = "SELECT d.*, 
                   COUNT(DISTINCT u.id) as staff_count,
                   COUNT(DISTINCT c.id) as complaint_count,
                   COUNT(DISTINCT CASE WHEN c.status IN ('assigned', 'in_progress') THEN c.id END) as active_complaints,
                   COUNT(DISTINCT CASE WHEN c.status = 'resolved' THEN c.id END) as resolved_complaints
            FROM departments d
            LEFT JOIN users u ON d.id = u.department_id AND u.role = 'staff' AND u.deleted_at IS NULL
            LEFT JOIN complaints c ON d.id = c.department_id
            WHERE d.id = ?
            GROUP BY d.id";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $department_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $department = $result->fetch_assoc();
    } else {
        $error = "Department not found.";
    }
    $stmt->close();
}

// Get all departments for listing
$status_filter = isset($_GET['status']) ? sanitizeInput($_GET['status']) : '';
$search_query = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// Build query for departments list
$sql = "SELECT d.*, 
               COUNT(DISTINCT u.id) as staff_count,
               COUNT(DISTINCT c.id) as complaint_count,
               COUNT(DISTINCT CASE WHEN c.status IN ('assigned', 'in_progress') THEN c.id END) as active_complaints,
               COUNT(DISTINCT CASE WHEN c.status = 'resolved' THEN c.id END) as resolved_complaints
        FROM departments d
        LEFT JOIN users u ON d.id = u.department_id AND u.role = 'staff' AND u.deleted_at IS NULL
        LEFT JOIN complaints c ON d.id = c.department_id
        WHERE 1=1";
        
$count_sql = "SELECT COUNT(*) as total FROM departments d WHERE 1=1";
$params = [];
$param_types = "";

// Apply filters
if ($status_filter && $status_filter !== 'all') {
    if ($status_filter === 'active') {
        $sql .= " AND d.is_active = 1";
        $count_sql .= " AND d.is_active = 1";
    } elseif ($status_filter === 'inactive') {
        $sql .= " AND d.is_active = 0";
        $count_sql .= " AND d.is_active = 0";
    }
}

if ($search_query) {
    $sql .= " AND (d.name LIKE ? OR d.description LIKE ?)";
    $count_sql .= " AND (d.name LIKE ? OR d.description LIKE ?)";
    $search_param = "%$search_query%";
    $params[] = $search_param;
    $params[] = $search_param;
    $param_types .= "ss";
}

// Add grouping and ordering
$sql .= " GROUP BY d.id ORDER BY d.name ASC LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;
$param_types .= "ii";

// Prepare and execute query
$stmt = $conn->prepare($sql);
if ($params && $param_types) {
    $bindParams = [$param_types];
    foreach ($params as &$param) {
        $bindParams[] = &$param;
    }
    unset($param);
    call_user_func_array([$stmt, 'bind_param'], $bindParams);
}
$stmt->execute();
$result = $stmt->get_result();
$departments = [];

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $departments[] = $row;
    }
}

// Get total count for pagination
$count_stmt = $conn->prepare($count_sql);
$params_count = array_slice($params, 0, count($params) - 2);
$count_param_types = count($params_count) > 0 ? rtrim($param_types, 'ii') : '';

if (count($params_count) > 0 && $count_param_types) {
    $bindCountParams = [$count_param_types];
    foreach ($params_count as &$param) {
        $bindCountParams[] = &$param;
    }
    unset($param);
    call_user_func_array([$count_stmt, 'bind_param'], $bindCountParams);
}
$count_stmt->execute();
$count_result = $count_stmt->get_result();
$total_departments = $count_result->fetch_assoc()['total'] ?? 0;
$total_pages = ceil($total_departments / $limit);

// Get department statistics
$stats_sql = "SELECT 
                COUNT(*) as total_departments,
                SUM(is_active) as active_departments,
                SUM(CASE WHEN is_active = 0 THEN 1 ELSE 0 END) as inactive_departments
              FROM departments";
$stats_result = $conn->query($stats_sql);
$stats = $stats_result->fetch_assoc();

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Departments - <?php echo isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'super_admin' ? 'Super Admin' : 'Admin'; ?> - CiviCare</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; line-height: 1.6; color: #333; background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%); min-height: 100vh; position: relative; }
        body::before { content: ''; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background-image: radial-gradient(circle at 25% 25%, rgba(255, 255, 255, 0.1) 0%, transparent 50%), radial-gradient(circle at 75% 75%, rgba(255, 255, 255, 0.05) 0%, transparent 50%); background-size: 400px 400px; pointer-events: none; z-index: -1; }
        .main-header { background: white; box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1); position: sticky; top: 0; z-index: 100; }
        .header-content { max-width: 1200px; margin: 0 auto; padding: 15px 20px; display: flex; justify-content: space-between; align-items: center; }
        .logo-section { display: flex; align-items: center; gap: 20px; }
        .logo { width: 60px; height: 60px; background: linear-gradient(135deg, #1e3c72, #2a5298); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-size: 28px; font-weight: bold; text-decoration: none; box-shadow: 0 4px 12px rgba(30, 60, 114, 0.3); transition: all 0.3s; }
        .logo:hover { transform: scale(1.05); box-shadow: 0 6px 16px rgba(30, 60, 114, 0.4); }
        .logo-text h1 { font-size: 24px; color: #1e3c72; margin: 0; font-weight: 700; }
        .logo-text p { font-size: 12px; color: #666; margin: 3px 0 0 0; font-weight: 500; }
        .admin-badge { background: rgba(255, 107, 53, 0.1); color: #1e3c72; padding: 8px 16px; border-radius: 20px; font-size: 14px; font-weight: 600; border: 1px solid rgba(255, 107, 53, 0.2); }
        .nav-links { display: flex; gap: 8px; align-items: center; }
        .nav-links a { display: inline-block; padding: 12px 20px; color: #333; text-decoration: none; font-weight: 500; border-radius: 6px; transition: all 0.3s; font-size: 0.95rem; position: relative; overflow: hidden; }
        .nav-links a::before { content: ''; position: absolute; top: 0; left: -100%; width: 100%; height: 100%; background: linear-gradient(90deg, transparent, rgba(255, 107, 53, 0.1), transparent); transition: left 0.5s; }
        .nav-links a:hover::before { left: 100%; }
        .nav-links a:hover { background: #f0f0f0; color: #1e3c72; transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1); }
        .nav-links a.active { background: linear-gradient(135deg, #1e3c72, #2a5298); color: white; box-shadow: 0 4px 12px rgba(30, 60, 114, 0.3); }
        .logout-btn { background: linear-gradient(135deg, #ff6b35, #e55a24); color: white; padding: 10px 20px; border-radius: 6px; font-weight: 600; text-decoration: none; transition: all 0.3s; box-shadow: 0 4px 12px rgba(255, 107, 53, 0.3); border: none; cursor: pointer; }
        .logout-btn:hover { background: linear-gradient(135deg, #e55a24, #cc4a1f); transform: translateY(-2px); box-shadow: 0 6px 16px rgba(255, 107, 53, 0.4); }
        .container { width: 90%; max-width: 1200px; margin: 30px auto; padding: 0 15px; }
        .welcome-panel { background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%); color: white; padding: 20px 25px; border-radius: 8px; margin-bottom: 30px; box-shadow: 0 4px 15px rgba(30, 60, 114, 0.3); position: relative; overflow: hidden; }
        .welcome-panel::before { content: ''; position: absolute; top: 0; right: 0; width: 150px; height: 150px; background: rgba(255, 255, 255, 0.1); border-radius: 50%; transform: translate(50%, -50%); }
        .welcome-info { display: flex; justify-content: space-between; align-items: center; position: relative; z-index: 1; }
        .welcome-text h2 { font-size: 24px; margin-bottom: 5px; font-weight: 700; }
        .welcome-text p { font-size: 14px; opacity: 0.95; margin: 0; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px; margin-bottom: 40px; }
        .stat-card { background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); padding: 15px; border-radius: 8px; text-align: center; box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1); transition: all 0.3s; border: 1px solid rgba(255, 255, 255, 0.2); position: relative; overflow: hidden; cursor: pointer; }
        .stat-card::before { content: ''; position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: linear-gradient(135deg, rgba(42, 82, 152, 0.1) 0%, rgba(255, 107, 53, 0.1) 100%); opacity: 0; transition: opacity 0.3s; }
        .stat-card:hover::before { opacity: 1; }
        .stat-card:hover { transform: translateY(-5px); box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15); background: linear-gradient(135deg, #ffffff 0%, #f1f3f4 100%); }
        .stat-icon { font-size: 24px; margin-bottom: 8px; color: #1e3c72; }
        .stat-card h3 { font-size: 28px; color: #1e3c72; margin-bottom: 5px; font-weight: 700; position: relative; z-index: 1; }
        .stat-card p { color: #666; font-size: 13px; font-weight: 500; position: relative; z-index: 1; }
        .stat-card.total { background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%); border: 1px solid rgba(33, 150, 243, 0.2); }
        .stat-card.total .stat-icon { color: #2196f3; }
        .stat-card.total h3 { color: #1976d2; }
        .stat-card.active { background: linear-gradient(135deg, #e8f5e9 0%, #c8e6c9 100%); border: 1px solid rgba(76, 175, 80, 0.2); }
        .stat-card.active .stat-icon { color: #4caf50; }
        .stat-card.active h3 { color: #388e3c; }
        .stat-card.inactive { background: linear-gradient(135deg, #fce4ec 0%, #f8bbd0 100%); border: 1px solid rgba(244, 67, 54, 0.2); }
        .stat-card.inactive .stat-icon { color: #f44336; }
        .stat-card.inactive h3 { color: #d32f2f; }
        .filters-section { background: white; border-radius: 12px; padding: 25px; margin-bottom: 30px; box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1); }
        .filters-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 20px; }
        .filter-group label { display: block; margin-bottom: 8px; font-weight: 600; color: #333; font-size: 14px; }
        .filter-group select, .filter-group input { width: 100%; padding: 12px 15px; border: 2px solid #e0e0e0; border-radius: 6px; font-size: 14px; transition: all 0.3s; background: white; }
        .filter-group select:focus, .filter-group input:focus { outline: none; border-color: #2a5298; box-shadow: 0 0 0 3px rgba(42, 82, 152, 0.1); }
        .filter-actions { display: flex; gap: 12px; justify-content: flex-end; }
        .btn { display: inline-block; padding: 12px 24px; text-decoration: none; border-radius: 6px; font-weight: 600; transition: all 0.3s; border: none; cursor: pointer; font-size: 14px; }
        .btn-primary { background: linear-gradient(135deg, #1e3c72, #2a5298); color: white; box-shadow: 0 4px 12px rgba(30, 60, 114, 0.3); }
        .btn-primary:hover { background: linear-gradient(135deg, #2a5298, #1e3c72); transform: translateY(-2px); }
        .btn-secondary { background: linear-gradient(135deg, #6c757d, #5a6268); color: white; box-shadow: 0 4px 12px rgba(108, 117, 125, 0.3); }
        .btn-secondary:hover { background: linear-gradient(135deg, #5a6268, #495057); transform: translateY(-2px); }
        .btn-success { background: linear-gradient(135deg, #27ae60, #219653); color: white; box-shadow: 0 4px 12px rgba(39, 174, 96, 0.3); }
        .btn-success:hover { background: linear-gradient(135deg, #219653, #1e8449); transform: translateY(-2px); }
        .btn-danger { background: linear-gradient(135deg, #e74c3c, #c0392b); color: white; box-shadow: 0 4px 12px rgba(231, 76, 60, 0.3); }
        .btn-danger:hover { background: linear-gradient(135deg, #c0392b, #a93226); transform: translateY(-2px); }
        .btn-warning { background: linear-gradient(135deg, #f39c12, #d68910); color: white; box-shadow: 0 4px 12px rgba(243, 156, 18, 0.3); }
        .btn-warning:hover { background: linear-gradient(135deg, #d68910, #b0780d); transform: translateY(-2px); }
        .btn-info { background: linear-gradient(135deg, #3498db, #2980b9); color: white; box-shadow: 0 4px 12px rgba(52, 152, 219, 0.3); }
        .btn-info:hover { background: linear-gradient(135deg, #2980b9, #1f618d); transform: translateY(-2px); }
        .departments-table-container { background: white; border-radius: 12px; box-shadow: 0 6px 15px rgba(0, 0, 0, 0.1); overflow: hidden; margin-bottom: 30px; }
        .table-header { background: linear-gradient(135deg, #1e3c72, #2a5298); color: white; padding: 25px 30px; display: flex; justify-content: space-between; align-items: center; }
        .table-header h2 { font-size: 20px; margin: 0; font-weight: 600; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #f8f9fa; color: #1e3c72; padding: 18px 20px; text-align: left; font-weight: 600; font-size: 14px; border-bottom: 2px solid #eee; }
        td { padding: 18px 20px; border-bottom: 1px solid #eee; font-size: 14px; }
        tr:hover { background: #f8f9fa; transition: all 0.2s; }
        .status-badge { padding: 6px 14px; border-radius: 20px; font-size: 12px; font-weight: 600; display: inline-block; }
        .status-active { background: #d4edda; color: #155724; border: 1px solid #b8e6b3; }
        .status-inactive { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .count-badge { padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: 600; display: inline-block; margin-right: 4px; }
        .staff-count { background: #9b59b6; color: white; }
        .complaint-count { background: #3498db; color: white; }
        .active-complaints { background: #f39c12; color: white; }
        .resolved-complaints { background: #27ae60; color: white; }
        .action-buttons { display: flex; gap: 6px; flex-wrap: wrap; }
        .action-btn { padding: 6px 12px; color: white; text-decoration: none; border-radius: 4px; font-size: 12px; font-weight: 600; display: inline-block; text-align: center; transition: all 0.3s; border: none; cursor: pointer; }
        .action-btn:hover { transform: translateY(-1px); box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2); }
        .action-btn-view { background: #3498db; }
        .action-btn-edit { background: #f39c12; }
        .action-btn-delete { background: #e74c3c; }
        .action-btn-staff { background: #9b59b6; }
        .pagination { display: flex; justify-content: center; gap: 8px; padding: 20px; background: #f8f9fa; }
        .pagination a, .pagination span { padding: 8px 14px; border: 1px solid #ddd; border-radius: 4px; text-decoration: none; color: #333; font-size: 14px; transition: all 0.3s; }
        .pagination a:hover { background: linear-gradient(135deg, #1e3c72, #2a5298); color: white; border-color: #1e3c72; }
        .pagination .active { background: linear-gradient(135deg, #1e3c72, #2a5298); color: white; border-color: #1e3c72; }
        .alert { padding: 1rem; border-radius: 5px; margin-bottom: 1.5rem; font-size: 0.95rem; }
        .alert-error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .no-departments { text-align: center; padding: 60px 40px; color: #666; }
        .no-departments i { font-size: 48px; color: #ddd; margin-bottom: 20px; }
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.5); z-index: 1000; align-items: center; justify-content: center; }
        .modal-content { background: white; padding: 30px; border-radius: 12px; max-width: 500px; width: 90%; max-height: 90vh; overflow-y: auto; box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3); }
        .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 2px solid #1e3c72; }
        .modal-header h3 { color: #1e3c72; font-size: 20px; }
        .close-modal { background: none; border: none; font-size: 24px; cursor: pointer; color: #666; transition: color 0.3s; }
        .close-modal:hover { color: #e74c3c; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 600; color: #333; font-size: 14px; }
        .form-group label.required::after { content: " *"; color: #e74c3c; }
        textarea { width: 100%; padding: 12px 15px; border: 2px solid #e0e0e0; border-radius: 6px; font-size: 14px; transition: border-color 0.3s; font-family: inherit; resize: vertical; min-height: 100px; }
        textarea:focus { outline: none; border-color: #2a5298; box-shadow: 0 0 0 3px rgba(42, 82, 152, 0.1); }
        .checkbox-group { display: flex; align-items: center; gap: 8px; }
        .checkbox-group input[type="checkbox"] { width: auto; width: 20px; height: 20px; }
        .form-actions { display: flex; gap: 12px; justify-content: flex-end; margin-top: 25px; padding-top: 20px; border-top: 1px solid #eee; }
        .department-details { background: white; padding: 25px; border-radius: 12px; margin-bottom: 30px; box-shadow: 0 6px 15px rgba(0, 0, 0, 0.1); }
        .details-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 25px; }
        .detail-item { padding: 15px; background: #f8f9fa; border-radius: 8px; }
        .detail-label { font-weight: 600; color: #666; margin-bottom: 5px; font-size: 13px; text-transform: uppercase; }
        .detail-value { color: #1e3c72; font-size: 15px; font-weight: 500; }
        .complaint-stats { display: flex; gap: 15px; margin-top: 15px; flex-wrap: wrap; }
        .stat-item { text-align: center; padding: 15px; background: white; border-radius: 8px; box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1); flex: 1; min-width: 120px; }
        .stat-item h4 { font-size: 24px; color: #1e3c72; margin-bottom: 5px; }
        .stat-item p { font-size: 12px; color: #666; margin: 0; }
        @keyframes fadeInUp { from { opacity: 0; transform: translateY(30px); } to { opacity: 1; transform: translateY(0); } }
        .welcome-panel, .stat-card, .departments-table-container, .filters-section { animation: fadeInUp 0.6s ease-out; }
        .stat-card:nth-child(1) { animation-delay: 0.1s; }
        .stat-card:nth-child(2) { animation-delay: 0.2s; }
        .stat-card:nth-child(3) { animation-delay: 0.3s; }
        .footer { background: #1a1a1a; color: #fff; padding: 25px 20px 10px; margin-top: 40px; }
        .footer-content { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 15px; max-width: 1200px; margin-left: auto; margin-right: auto; }
        .footer-section h4 { color: #ff6b35; margin-bottom: 10px; font-size: 14px; font-weight: 700; }
        .footer-section ul { list-style: none; }
        .footer-section ul li { margin-bottom: 5px; }
        .footer-section ul li a { color: #aaa; text-decoration: none; transition: color 0.3s; font-size: 14px; }
        .footer-section ul li a:hover { color: #ff6b35; padding-left: 5px; }
        .footer-section p { color: #aaa; font-size: 13px; line-height: 1.5; }
        .footer-bottom { border-top: 1px solid #333; padding-top: 10px; text-align: center; color: #aaa; font-size: 11px; max-width: 1200px; margin: 0 auto; }
        @media (max-width: 768px) { .header-content { flex-direction: column; gap: 20px; } .nav-links { flex-wrap: wrap; justify-content: center; } .stats-grid { grid-template-columns: repeat(2, 1fr); gap: 12px; } .welcome-info { flex-direction: column; gap: 15px; text-align: center; } table { display: block; overflow-x: auto; } .filters-grid { grid-template-columns: 1fr; } .filter-actions { flex-direction: column; } .table-header { flex-direction: column; gap: 15px; text-align: center; } .action-buttons { flex-direction: column; } .modal-content { width: 95%; padding: 1rem; } .complaint-stats { flex-direction: column; } .stat-item { min-width: 100%; } }
        @media (max-width: 480px) { .stats-grid { grid-template-columns: 1fr; gap: 10px; } .welcome-text h2 { font-size: 18px; } .logo-section { gap: 15px; } .logo { width: 50px; height: 50px; font-size: 24px; } .logo-text h1 { font-size: 20px; } .admin-badge { font-size: 12px; padding: 6px 12px; } }
    </style>
</head>
<body>
    <header class="main-header">
        <div class="header-content">
            <div class="logo-section">
                <a href="index.php" class="logo"><i class="fas fa-city"></i></a>
                <div class="logo-text">
                    <h1>CIVICARE</h1>
                    <p>Admin Portal</p>
                </div>
            </div>
            <nav class="nav-links">
                <a href="index.php">Dashboard</a>
                <a href="complaints.php">Complaints</a>
                <a href="users.php">Users</a>
                <a href="staff.php">Staff</a>
                <a href="departments.php" class="active">Departments</a>
                <a href="../logout.php" class="logout-btn">Logout</a>
            </nav>
        </div>
    </header>

    <div class="container">
        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($_GET['success']); ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        
        <?php if ($action === 'edit' || $action === 'view'): ?>
            <?php if ($department): ?>
                <div class="department-details">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; padding-bottom: 1.5rem; border-bottom: 2px solid #1e3c72;">
                        <div>
                            <h2 style="color: #2c3e50; margin-bottom: 0.5rem;"><?php echo htmlspecialchars($department['name']); ?></h2>
                            <p style="color: #666;">
                                <span class="status-badge status-<?php echo $department['is_active'] ? 'active' : 'inactive'; ?>"><?php echo $department['is_active'] ? 'Active' : 'Inactive'; ?></span>
                            </p>
                        </div>
                        <div>
                            <a href="departments.php" class="btn btn-secondary">Back to Departments</a>
                        </div>
                    </div>
                    <div class="details-grid">
                        <div class="detail-item"><div class="detail-label">Department Name</div><div class="detail-value"><?php echo htmlspecialchars($department['name']); ?></div></div>
                        <div class="detail-item"><div class="detail-label">Staff Members</div><div class="detail-value"><span class="count-badge staff-count"><?php echo $department['staff_count']; ?> Staff</span></div></div>
                        <div class="detail-item"><div class="detail-label">Total Complaints</div><div class="detail-value"><span class="count-badge complaint-count"><?php echo $department['complaint_count']; ?> Total</span></div></div>
                        <div class="detail-item"><div class="detail-label">Created</div><div class="detail-value"><?php echo date('F d, Y', strtotime($department['created_at'])); ?></div></div>
                        <div class="detail-item"><div class="detail-label">Last Updated</div><div class="detail-value"><?php echo $department['updated_at'] ? date('F d, Y', strtotime($department['updated_at'])) : 'Never'; ?></div></div>
                    </div>
                    
                    <div class="detail-item" style="grid-column: 1 / -1; margin-bottom: 20px;">
                        <div class="detail-label">Description</div>
                        <div class="detail-value"><?php echo nl2br(htmlspecialchars($department['description'] ?: 'No description provided.')); ?></div>
                    </div>
                    
                    <?php if ($department['complaint_count'] > 0): ?>
                    <div class="detail-item" style="grid-column: 1 / -1;">
                        <div class="detail-label">Complaint Statistics</div>
                        <div class="complaint-stats">
                            <div class="stat-item"><h4><?php echo $department['complaint_count']; ?></h4><p>Total Complaints</p></div>
                            <?php if ($department['active_complaints'] > 0): ?>
                            <div class="stat-item"><h4 style="color: #f39c12;"><?php echo $department['active_complaints']; ?></h4><p>Active</p></div>
                            <?php endif; ?>
                            <?php if ($department['resolved_complaints'] > 0): ?>
                            <div class="stat-item"><h4 style="color: #27ae60;"><?php echo $department['resolved_complaints']; ?></h4><p>Resolved</p></div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <div class="form-actions">
                        <a href="departments.php?action=edit&id=<?php echo $department['id']; ?>" class="btn btn-primary">Edit Department</a>
                        <a href="staff.php?department=<?php echo $department['id']; ?>" class="btn btn-info">View Staff</a>
                        <button class="btn btn-danger" onclick="deleteDepartment(<?php echo $department['id']; ?>, '<?php echo htmlspecialchars($department['name']); ?>')">Delete Department</button>
                    </div>
                </div>
                
                <?php if ($action === 'edit'): ?>
                <div class="department-details">
                    <h3 style="color: #2c3e50; margin-bottom: 1.5rem; padding-bottom: 0.5rem; border-bottom: 2px solid #3498db;">Edit Department</h3>
                    <form method="POST" action="departments.php">
                        <input type="hidden" name="action" value="edit_department">
                        <input type="hidden" name="department_id" value="<?php echo $department['id']; ?>">
                        
                        <div class="details-grid">
                            <div class="form-group"><label for="name" class="required">Department Name</label><input type="text" id="name" name="name" value="<?php echo htmlspecialchars($department['name']); ?>" required style="width:100%;padding:12px 15px;border:2px solid #e0e0e0;border-radius:6px;font-size:14px;"></div>
                            
                            <div class="form-group" style="grid-column: 1 / -1;"><label for="description">Description</label><textarea id="description" name="description" rows="4" style="width:100%;padding:12px 15px;border:2px solid #e0e0e0;border-radius:6px;font-size:14px;"><?php echo htmlspecialchars($department['description']); ?></textarea></div>
                            
                            <div class="form-group"><label class="checkbox-group"><input type="checkbox" id="is_active" name="is_active" value="1" <?php echo $department['is_active'] ? 'checked' : ''; ?>> Active Department</label><small style="color: #666; display: block; margin-top: 0.25rem;">Inactive departments won't appear in dropdowns</small></div>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">Update Department</button>
                            <a href="departments.php?action=view&id=<?php echo $department['id']; ?>" class="btn btn-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
                <?php endif; ?>
                
            <?php else: ?>
                <div class="alert alert-error">Department not found.</div>
                <a href="departments.php" class="btn btn-primary">Back to Departments</a>
            <?php endif; ?>
            
        <?php else: ?>
        
        <div class="departments-table-container">
            <div class="table-header">
                <h2><strong><?php echo $total_departments; ?></strong> Departments</h2>
                <button class="btn btn-success" onclick="openAddDepartmentModal()">+ Add Department</button>
            </div>
            
            <?php if (!empty($departments)): ?>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Department Name</th>
                            <th>Description</th>
                            <th>Staff</th>
                            <th>Complaints</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($departments as $dept): ?>
                            <tr>
                                <td><?php echo $dept['id']; ?></td>
                                <td><strong><?php echo htmlspecialchars($dept['name']); ?></strong></td>
                                <td>
                                    <div style="max-width: 250px; overflow: hidden; text-overflow: ellipsis; font-size: 0.85rem; color: #666;">
                                        <?php echo htmlspecialchars($dept['description'] ?: 'No description'); ?>
                                    </div>
                                </td>
                                <td><span class="count-badge staff-count"><?php echo $dept['staff_count']; ?></span></td>
                                <td>
                                    <div style="display: flex; flex-wrap: wrap; gap: 0.25rem;">
                                        <span class="count-badge complaint-count" title="Total complaints"><?php echo $dept['complaint_count']; ?></span>
                                        <?php if ($dept['active_complaints'] > 0): ?>
                                            <span class="count-badge active-complaints" title="Active complaints"><?php echo $dept['active_complaints']; ?></span>
                                        <?php endif; ?>
                                        <?php if ($dept['resolved_complaints'] > 0): ?>
                                            <span class="count-badge resolved-complaints" title="Resolved complaints"><?php echo $dept['resolved_complaints']; ?></span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td><span class="status-badge status-<?php echo $dept['is_active'] ? 'active' : 'inactive'; ?>"><?php echo $dept['is_active'] ? 'Active' : 'Inactive'; ?></span></td>
                                <td><?php echo date('M d, Y', strtotime($dept['created_at'])); ?></td>
                                <td>
                                    <div class="action-buttons">
                                        <a href="departments.php?action=view&id=<?php echo $dept['id']; ?>" class="action-btn action-btn-view">View</a>
                                        <a href="departments.php?action=edit&id=<?php echo $dept['id']; ?>" class="action-btn action-btn-edit">Edit</a>
                                        <a href="staff.php?department=<?php echo $dept['id']; ?>" class="action-btn action-btn-staff">Staff</a>
                                        <button onclick="deleteDepartment(<?php echo $dept['id']; ?>, '<?php echo htmlspecialchars($dept['name']); ?>')" class="action-btn action-btn-delete">Delete</button>
                                    </div>
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
                                <a href="?page=<?php echo $i; ?>&status=<?php echo $status_filter; ?>&search=<?php echo urlencode($search_query); ?>" class="<?php echo $i == $page ? 'active' : ''; ?>"><?php echo $i; ?></a>
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
                <div class="no-departments">
                    <i class="fas fa-building"></i>
                    <p>No departments found<?php echo $status_filter || $search_query ? ' with the current filters' : ''; ?>.</p>
                    <button class="btn btn-success" onclick="openAddDepartmentModal()" style="margin-top:1rem;">Add First Department</button>
                </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- Add Department Modal -->
    <div class="modal" id="addDepartmentModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Add New Department</h3>
                <button class="close-modal" onclick="closeAddDepartmentModal()">&times;</button>
            </div>
            <form method="POST" action="departments.php" id="addDepartmentForm">
                <input type="hidden" name="action" value="add_department">
                
                <div class="form-group">
                    <label for="add_name" class="required">Department Name</label>
                    <input type="text" id="add_name" name="name" required placeholder="e.g., Water Department, Electricity Department">
                </div>
                
                <div class="form-group">
                    <label for="add_description">Description</label>
                    <textarea id="add_description" name="description" rows="4" placeholder="Describe the department's responsibilities..."></textarea>
                </div>
                
                <div class="form-group">
                    <label class="checkbox-group">
                        <input type="checkbox" id="add_is_active" name="is_active" value="1" checked>
                        Active Department
                    </label>
                    <small style="color: #666; display: block; margin-top: 0.25rem;">Active departments will appear in dropdowns</small>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Create Department</button>
                    <button type="button" class="btn btn-secondary" onclick="closeAddDepartmentModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete Department Modal -->
    <div class="modal" id="deleteDepartmentModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Delete Department</h3>
                <button class="close-modal" onclick="closeDeleteDepartmentModal()">&times;</button>
            </div>
            <form method="POST" action="departments.php">
                <input type="hidden" name="action" value="delete_department">
                <input type="hidden" name="department_id" id="delete_department_id">
                
                <div class="form-group">
                    <p>Delete department <strong id="delete_department_name"></strong>?</p>
                    <p id="delete_warning" style="color:#e74c3c;font-weight:600;"></p>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-danger">Delete Department</button>
                    <button type="button" class="btn btn-secondary" onclick="closeDeleteDepartmentModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <footer class="footer">
        <div class="footer-content">
            <div class="footer-section"><h4>CiviCare</h4><p>Municipal Complaint Management System</p></div>
            <div class="footer-section"><h4>Quick Links</h4><ul><li><a href="index.php">Dashboard</a></li><li><a href="users.php">Users</a></li><li><a href="departments.php">Departments</a></li></ul></div>
        </div>
        <div class="footer-bottom"><p>&copy; <?php echo date('Y'); ?> CiviCare. All rights reserved.</p></div>
    </footer>

    <script>
        function openAddDepartmentModal() { 
            document.getElementById('addDepartmentModal').style.display = 'flex'; 
            document.getElementById('add_name').focus(); 
        }
        
        function closeAddDepartmentModal() { 
            document.getElementById('addDepartmentModal').style.display = 'none'; 
            document.getElementById('addDepartmentForm').reset(); 
        }
        
        function deleteDepartment(departmentId, departmentName) { 
            document.getElementById('delete_department_id').value = departmentId; 
            document.getElementById('delete_department_name').textContent = departmentName; 
            
            // Check if department has staff or complaints (using data from PHP)
            <?php
            // We need to pass the counts to JavaScript
            if (!empty($departments)) {
                foreach ($departments as $dept) {
                    if ($dept['id'] == $department_id) {
                        echo "var staffCount = " . $dept['staff_count'] . ";";
                        echo "var complaintCount = " . $dept['complaint_count'] . ";";
                    }
                }
            }
            ?>
            
            // Use the counts we have
            if (typeof staffCount !== 'undefined' && staffCount > 0) {
                document.getElementById('delete_warning').textContent = '⚠️ This department has ' + staffCount + ' staff member(s). Cannot delete.';
                document.getElementById('deleteDepartmentModal').querySelector('button[type="submit"]').disabled = true;
            } else if (typeof complaintCount !== 'undefined' && complaintCount > 0) {
                document.getElementById('delete_warning').textContent = '⚠️ This department has ' + complaintCount + ' complaint(s). Cannot delete.';
                document.getElementById('deleteDepartmentModal').querySelector('button[type="submit"]').disabled = true;
            } else {
                document.getElementById('delete_warning').textContent = 'This action cannot be undone.';
                document.getElementById('deleteDepartmentModal').querySelector('button[type="submit"]').disabled = false;
            }
            
            document.getElementById('deleteDepartmentModal').style.display = 'flex'; 
        }
        
        function closeDeleteDepartmentModal() { 
            document.getElementById('deleteDepartmentModal').style.display = 'none'; 
            document.getElementById('deleteDepartmentForm').reset();
            document.getElementById('deleteDepartmentModal').querySelector('button[type="submit"]').disabled = false;
        }
        
        window.onclick = function(event) { 
            document.querySelectorAll('.modal').forEach(modal => { 
                if (event.target === modal) modal.style.display = 'none'; 
            }); 
        }
        
        function filterByStatus(status) { 
            document.getElementById('status').value = status; 
            document.querySelector('form').submit(); 
        }
        
        document.getElementById('status').addEventListener('change', function() { 
            if (this.value !== 'all') document.querySelector('form').submit(); 
        });
        
        // Auto-suggest description based on department name
        const descriptionSuggestions = {
            'water': 'Handles water supply, pipeline maintenance, drainage systems, and water quality issues.',
            'electric': 'Manages power supply, electrical faults, street lighting, and electrical infrastructure.',
            'road': 'Responsible for road maintenance, pothole repairs, construction projects, and traffic management.',
            'sanitation': 'Deals with garbage collection, waste management, cleanliness, and public hygiene.',
            'public works': 'Handles public building maintenance, infrastructure projects, and municipal construction.',
            'parks': 'Manages parks, gardens, green spaces, playgrounds, and recreational facilities.',
            'health': 'Oversees public health initiatives, sanitation, disease control, and health awareness programs.',
            'education': 'Manages educational institutions, scholarship programs, and educational infrastructure.',
            'transport': 'Handles public transportation, vehicle permits, road safety, and traffic management.',
            'revenue': 'Manages tax collection, revenue generation, financial records, and billing systems.',
            'planning': 'Oversees urban planning, building permits, land use, and development projects.',
            'fire': 'Manages fire services, emergency response, fire safety inspections, and rescue operations.'
        };
        
        document.getElementById('add_name')?.addEventListener('input', function() {
            const name = this.value.toLowerCase();
            const descriptionField = document.getElementById('add_description');
            
            if (!descriptionField.value) {
                for (const [keyword, suggestion] of Object.entries(descriptionSuggestions)) {
                    if (name.includes(keyword)) {
                        descriptionField.value = suggestion;
                        break;
                    }
                }
            }
        });
    </script>
</body>
</html>