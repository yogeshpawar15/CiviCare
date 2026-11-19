<?php
// civicare/admin/users.php
require_once '../config/database.php';
require_once '../config/functions.php';

if (!isLoggedIn() || (!hasRole('admin') && !hasRole('staff') && !hasRole('super_admin'))) {
    header('Location: login.php');
    exit();
}

$conn = getDatabaseConnection();
$error = '';
$success = '';
$action = isset($_GET['action']) ? sanitizeInput($_GET['action']) : '';
$user_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$current_user_role = $_SESSION['user_role'];
$is_super_admin = ($current_user_role === 'super_admin');
$is_admin = ($current_user_role === 'admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $post_action = sanitizeInput($_POST['action']);
    
    switch ($post_action) {
        case 'add_user':
            $full_name = sanitizeInput($_POST['full_name']);
            $username = sanitizeInput($_POST['username']);
            $email = sanitizeInput($_POST['email']);
            $phone = sanitizeInput($_POST['phone']);
            $address = sanitizeInput($_POST['address']);
            $role = sanitizeInput($_POST['role']);
            $department_id = !empty($_POST['department_id']) ? (int)$_POST['department_id'] : null;
            $is_active = isset($_POST['is_active']) ? 1 : 0;
            
            if (empty($full_name) || empty($username) || empty($email) || empty($role)) {
                $error = "Please fill in all required fields.";
            } else {
                $check_sql = "SELECT id FROM users WHERE username = ? OR email = ?";
                $check_stmt = $conn->prepare($check_sql);
                $check_stmt->bind_param("ss", $username, $email);
                $check_stmt->execute();
                $check_result = $check_stmt->get_result();
                
                if ($check_result->num_rows > 0) {
                    $error = "Username or email already exists.";
                } else {
                    $temp_password = bin2hex(random_bytes(4));
                    $hashed_password = password_hash($temp_password, PASSWORD_DEFAULT);
                    
                    $sql = "INSERT INTO users (full_name, username, email, phone, address, password, role, department_id, is_active, created_by) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("sssssssiii", $full_name, $username, $email, $phone, $address, $hashed_password, $role, $department_id, $is_active, $_SESSION['user_id']);
                    
                    if ($stmt->execute()) {
                        $new_user_id = $stmt->insert_id;
                        $success = "User created successfully. Temporary password: $temp_password";
                        
                        // Send email with password (commented out for now)
                        // sendWelcomeEmail($email, $full_name, $username, $temp_password);
                        
                        header("Location: users.php?success=" . urlencode($success));
                        exit();
                    } else {
                        $error = "Failed to create user: " . $conn->error;
                    }
                    $stmt->close();
                }
                $check_stmt->close();
            }
            break;
            
        case 'edit_user':
            $user_id = (int)$_POST['user_id'];
            $full_name = sanitizeInput($_POST['full_name']);
            $username = sanitizeInput($_POST['username']);
            $email = sanitizeInput($_POST['email']);
            $phone = sanitizeInput($_POST['phone']);
            $address = sanitizeInput($_POST['address']);
            $role = sanitizeInput($_POST['role']);
            $department_id = !empty($_POST['department_id']) ? (int)$_POST['department_id'] : null;
            $is_active = isset($_POST['is_active']) ? 1 : 0;
            
            $check_sql = "SELECT id FROM users WHERE (username = ? OR email = ?) AND id != ?";
            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->bind_param("ssi", $username, $email, $user_id);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            
            if ($check_result->num_rows > 0) {
                $error = "Username or email already exists.";
            } else {
                $sql = "UPDATE users SET full_name = ?, username = ?, email = ?, phone = ?, address = ?, role = ?, department_id = ?, is_active = ?, updated_at = NOW() WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("sssssssii", $full_name, $username, $email, $phone, $address, $role, $department_id, $is_active, $user_id);
                
                if ($stmt->execute()) {
                    $success = "User updated successfully.";
                } else {
                    $error = "Failed to update user.";
                }
                $stmt->close();
            }
            $check_stmt->close();
            break;
            
        case 'reset_password':
            $user_id = (int)$_POST['user_id'];
            $new_password = bin2hex(random_bytes(4));
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            
            $sql = "UPDATE users SET password = ?, password_reset_required = 1, updated_at = NOW() WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("si", $hashed_password, $user_id);
            
            if ($stmt->execute()) {
                $success = "Password reset successfully. New password: $new_password";
            } else {
                $error = "Failed to reset password.";
            }
            $stmt->close();
            break;
            
        case 'delete_user':
            $user_id = (int)$_POST['user_id'];
            
            if ($user_id == $_SESSION['user_id']) {
                $error = "You cannot delete your own account.";
            } else {
                $user_sql = "SELECT full_name, username, role FROM users WHERE id = ?";
                $user_stmt = $conn->prepare($user_sql);
                $user_stmt->bind_param("i", $user_id);
                $user_stmt->execute();
                $user_result = $user_stmt->get_result();
                $user_info = $user_result->fetch_assoc();
                $user_stmt->close();
                
                if ($is_admin && $user_info['role'] === 'super_admin') {
                    $error = "You do not have permission to delete a super admin.";
                } else {
                    $complaints_check = "SELECT COUNT(*) as complaint_count FROM complaints WHERE user_id = ?";
                    $complaints_stmt = $conn->prepare($complaints_check);
                    $complaints_stmt->bind_param("i", $user_id);
                    $complaints_stmt->execute();
                    $complaints_result = $complaints_stmt->get_result();
                    $complaints_data = $complaints_result->fetch_assoc();
                    $complaints_stmt->close();
                    
                    if ($complaints_data['complaint_count'] > 0) {
                        $error = "Cannot delete user with existing complaints.";
                    } else {
                        $sql = "UPDATE users SET is_active = 0, deleted_at = NOW() WHERE id = ?";
                        $stmt = $conn->prepare($sql);
                        $stmt->bind_param("i", $user_id);
                        
                        if ($stmt->execute()) {
                            $success = "User deactivated successfully.";
                            header("Location: users.php?success=" . urlencode($success));
                            exit();
                        } else {
                            $error = "Failed to delete user.";
                        }
                        $stmt->close();
                    }
                }
            }
            break;
    }
}

$user = null;
if ($user_id > 0 && ($action === 'edit' || $action === 'view')) {
    $sql = "SELECT u.*, d.name as department_name,
                   COUNT(DISTINCT c.id) as complaint_count,
                   COUNT(DISTINCT CASE WHEN c.status IN ('pending', 'validated', 'assigned', 'in_progress') THEN c.id END) as active_complaints,
                   COUNT(DISTINCT CASE WHEN c.status = 'resolved' THEN c.id END) as resolved_complaints,
                   COUNT(DISTINCT a.id) as assigned_complaints_count
            FROM users u
            LEFT JOIN departments d ON u.department_id = d.id
            LEFT JOIN complaints c ON u.id = c.user_id
            LEFT JOIN complaints a ON u.id = a.assigned_to
            WHERE u.id = ?
            GROUP BY u.id";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
    } else {
        $error = "User not found.";
    }
    $stmt->close();
}

$role_filter = isset($_GET['role']) ? sanitizeInput($_GET['role']) : 'citizen';
$status_filter = isset($_GET['status']) ? sanitizeInput($_GET['status']) : '';
$search_query = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

$sql = "SELECT u.*, d.name as department_name,
               COUNT(DISTINCT c.id) as complaint_count,
               COUNT(DISTINCT a.id) as assigned_complaints,
               COUNT(DISTINCT CASE WHEN c.status IN ('pending', 'validated', 'assigned', 'in_progress') THEN c.id END) as active_complaints
        FROM users u
        LEFT JOIN departments d ON u.department_id = d.id
        LEFT JOIN complaints c ON u.id = c.user_id
        LEFT JOIN complaints a ON u.id = a.assigned_to
        WHERE u.role = ?";
        
$count_sql = "SELECT COUNT(*) as total FROM users u WHERE u.role = ?";
$params = [$role_filter];
$param_types = "s";

if ($status_filter && $status_filter !== 'all') {
    if ($status_filter === 'active') {
        $sql .= " AND u.is_active = 1";
        $count_sql .= " AND u.is_active = 1";
    } elseif ($status_filter === 'inactive') {
        $sql .= " AND u.is_active = 0";
        $count_sql .= " AND u.is_active = 0";
    }
}

if ($search_query) {
    $sql .= " AND (u.full_name LIKE ? OR u.username LIKE ? OR u.email LIKE ?)";
    $count_sql .= " AND (u.full_name LIKE ? OR u.username LIKE ? OR u.email LIKE ?)";
    $search_param = "%$search_query%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $param_types .= "sss";
}

$sql .= " GROUP BY u.id ORDER BY u.created_at DESC LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;
$param_types .= "ii";

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
$users = [];

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }
}

$count_stmt = $conn->prepare($count_sql);
$params_count = array_slice($params, 0, count($params) - 2);
$count_param_types = count($params_count) > 0 ? substr($param_types, 0, -2) : '';

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
$total_users = $count_result->fetch_assoc()['total'] ?? 0;
$total_pages = ceil($total_users / $limit);

$departments = getAllDepartments($conn);

$roles_to_show = $is_super_admin ? "('citizen', 'admin', 'super_admin')" : "('citizen', 'admin')";
$stats_sql = "SELECT role, COUNT(*) as total, SUM(is_active) as active, SUM(CASE WHEN is_active = 0 THEN 1 ELSE 0 END) as inactive FROM users WHERE role IN $roles_to_show GROUP BY role";
$stats_result = $conn->query($stats_sql);
$user_stats = [];
while ($row = $stats_result->fetch_assoc()) {
    $user_stats[$row['role']] = $row;
}

$conn->close();

// Function to send welcome email (commented out)
function sendWelcomeEmail($email, $name, $username, $password) {
    // Email sending code would go here
    // Uncomment in production
    /*
    $subject = "Welcome to CiviCare - Your Account Details";
    $message = "Dear $name,\n\n";
    $message .= "Your account has been created successfully.\n\n";
    $message .= "Login Details:\n";
    $message .= "Username: $username\n";
    $message .= "Password: $password\n\n";
    $message .= "Please login and change your password immediately.\n\n";
    $message .= "Thank you,\nCiviCare Team";
    mail($email, $subject, $message);
    */
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - <?php echo $is_super_admin ? 'Super Admin' : 'Admin'; ?> - CiviCare</title>
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
        .stat-card.citizen { background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%); border: 1px solid rgba(33, 150, 243, 0.2); }
        .stat-card.citizen .stat-icon { color: #3498db; }
        .stat-card.citizen h3 { color: #2980b9; }
        .stat-card.admin { background: linear-gradient(135deg, #e8f5e9 0%, #c8e6c9 100%); border: 1px solid rgba(76, 175, 80, 0.2); }
        .stat-card.admin .stat-icon { color: #27ae60; }
        .stat-card.admin h3 { color: #1e8449; }
        .stat-card.super_admin { background: linear-gradient(135deg, #fff8e1 0%, #ffe0b2 100%); border: 1px solid rgba(243, 156, 18, 0.2); }
        .stat-card.super_admin .stat-icon { color: #f39c12; }
        .stat-card.super_admin h3 { color: #d68910; }
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
        .users-table-container { background: white; border-radius: 12px; box-shadow: 0 6px 15px rgba(0, 0, 0, 0.1); overflow: hidden; margin-bottom: 30px; }
        .table-header { background: linear-gradient(135deg, #1e3c72, #2a5298); color: white; padding: 25px 30px; display: flex; justify-content: space-between; align-items: center; }
        .table-header h2 { font-size: 20px; margin: 0; font-weight: 600; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #f8f9fa; color: #1e3c72; padding: 18px 20px; text-align: left; font-weight: 600; font-size: 14px; border-bottom: 2px solid #eee; }
        td { padding: 18px 20px; border-bottom: 1px solid #eee; font-size: 14px; }
        tr:hover { background: #f8f9fa; transition: all 0.2s; }
        .status-badge { padding: 6px 14px; border-radius: 20px; font-size: 12px; font-weight: 600; display: inline-block; }
        .status-active { background: #d4edda; color: #155724; border: 1px solid #b8e6b3; }
        .status-inactive { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .role-badge { padding: 4px 10px; border-radius: 4px; font-size: 12px; font-weight: 600; display: inline-block; }
        .role-citizen { background: #3498db; color: white; }
        .role-admin { background: #27ae60; color: white; }
        .role-super_admin { background: #e67e22; color: white; }
        .count-badge { padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: 600; display: inline-block; margin-right: 4px; }
        .complaint-count { background: #cce5ff; color: #004085; }
        .active-complaints { background: #fff3cd; color: #856404; }
        .action-buttons { display: flex; gap: 6px; flex-wrap: wrap; }
        .action-btn { padding: 6px 12px; color: white; text-decoration: none; border-radius: 4px; font-size: 12px; font-weight: 600; display: inline-block; text-align: center; transition: all 0.3s; border: none; cursor: pointer; }
        .action-btn:hover { transform: translateY(-1px); box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2); }
        .action-btn-view { background: #3498db; }
        .action-btn-edit { background: #f39c12; }
        .action-btn-reset { background: #9b59b6; }
        .action-btn-delete { background: #e74c3c; }
        .pagination { display: flex; justify-content: center; gap: 8px; padding: 20px; background: #f8f9fa; }
        .pagination a, .pagination span { padding: 8px 14px; border: 1px solid #ddd; border-radius: 4px; text-decoration: none; color: #333; font-size: 14px; transition: all 0.3s; }
        .pagination a:hover { background: linear-gradient(135deg, #1e3c72, #2a5298); color: white; border-color: #1e3c72; }
        .pagination .active { background: linear-gradient(135deg, #1e3c72, #2a5298); color: white; border-color: #1e3c72; }
        .alert { padding: 1rem; border-radius: 5px; margin-bottom: 1.5rem; font-size: 0.95rem; }
        .alert-error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .no-users { text-align: center; padding: 60px 40px; color: #666; }
        .no-users i { font-size: 48px; color: #ddd; margin-bottom: 20px; }
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
        .user-details { background: white; padding: 25px; border-radius: 12px; margin-bottom: 30px; box-shadow: 0 6px 15px rgba(0, 0, 0, 0.1); }
        .details-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 25px; }
        .detail-item { padding: 15px; background: #f8f9fa; border-radius: 8px; }
        .detail-label { font-weight: 600; color: #666; margin-bottom: 5px; font-size: 13px; text-transform: uppercase; }
        .detail-value { color: #1e3c72; font-size: 15px; font-weight: 500; }
        .complaint-stats { display: flex; gap: 15px; margin-top: 15px; flex-wrap: wrap; }
        .stat-item { text-align: center; padding: 15px; background: white; border-radius: 8px; box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1); flex: 1; min-width: 120px; }
        .stat-item h4 { font-size: 24px; color: #1e3c72; margin-bottom: 5px; }
        .stat-item p { font-size: 12px; color: #666; margin: 0; }
        .role-tabs { display: flex; gap: 8px; margin-bottom: 30px; background: white; padding: 15px; border-radius: 8px; box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1); flex-wrap: wrap; }
        .role-tab { padding: 12px 24px; border: none; background: #f8f9fa; color: #666; border-radius: 6px; cursor: pointer; font-weight: 600; transition: all 0.3s; font-size: 14px; }
        .role-tab:hover { background: #e9ecef; color: #1e3c72; transform: translateY(-2px); }
        .role-tab.active { background: linear-gradient(135deg, #1e3c72, #2a5298); color: white; box-shadow: 0 4px 12px rgba(30, 60, 114, 0.3); }
        .bulk-actions { background: #f8f9fa; padding: 15px 20px; display: flex; gap: 15px; align-items: center; border-bottom: 1px solid #eee; flex-wrap: wrap; }
        .bulk-select { display: flex; align-items: center; gap: 10px; }
        .bulk-select input[type="checkbox"] { width: 20px; height: 20px; cursor: pointer; }
        .bulk-select label { font-weight: 600; color: #333; cursor: pointer; }
        #selected_count { color: #666; font-size: 14px; }
        #bulk_action_select { padding: 10px; border: 2px solid #e0e0e0; border-radius: 6px; font-size: 14px; min-width: 150px; }
        @keyframes fadeInUp { from { opacity: 0; transform: translateY(30px); } to { opacity: 1; transform: translateY(0); } }
        .welcome-panel, .stat-card, .users-table-container, .filters-section, .role-tabs { animation: fadeInUp 0.6s ease-out; }
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
        @media (max-width: 768px) { .header-content { flex-direction: column; gap: 20px; } .nav-links { flex-wrap: wrap; justify-content: center; } .stats-grid { grid-template-columns: repeat(2, 1fr); gap: 12px; } .welcome-info { flex-direction: column; gap: 15px; text-align: center; } table { display: block; overflow-x: auto; } .filters-grid { grid-template-columns: 1fr; } .filter-actions { flex-direction: column; } .table-header { flex-direction: column; gap: 15px; text-align: center; } .action-buttons { flex-direction: column; } .modal-content { width: 95%; padding: 1rem; } .complaint-stats { flex-direction: column; } .stat-item { min-width: 100%; } .role-tabs { justify-content: center; } .bulk-actions { flex-direction: column; align-items: flex-start; } }
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
                <a href="users.php" class="active">Users</a>
                <a href="staff.php">Staff</a>
                <a href="departments.php">Departments</a>
                <a href="../logout.php" class="logout-btn">Logout</a>
            </nav>
        </div>
    </header>

    <div class="container" style="flex: 1;">
        <?php if ($action === 'edit' || $action === 'view'): ?>
            <?php if ($user): ?>
                <div class="user-details">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; padding-bottom: 1.5rem; border-bottom: 2px solid #1e3c72;">
                        <div>
                            <h2 style="color: #2c3e50; margin-bottom: 0.5rem;"><?php echo htmlspecialchars($user['full_name']); ?></h2>
                            <p style="color: #666;">
                                <span class="role-badge role-<?php echo $user['role']; ?>"><?php echo ucfirst(str_replace('_', ' ', $user['role'])); ?></span>
                                <span class="status-badge status-<?php echo $user['is_active'] ? 'active' : 'inactive'; ?>" style="margin-left: 0.5rem;"><?php echo $user['is_active'] ? 'Active' : 'Inactive'; ?></span>
                                <?php if ($user['department_name']): ?>
                                    <span style="margin-left: 1rem; color: #3498db;">• <?php echo htmlspecialchars($user['department_name']); ?></span>
                                <?php endif; ?>
                            </p>
                        </div>
                        <div>
                            <a href="users.php?role=<?php echo $user['role']; ?>" class="btn btn-secondary">Back to Users</a>
                        </div>
                    </div>
                    <div class="details-grid">
                        <div class="detail-item"><div class="detail-label">Username</div><div class="detail-value"><?php echo htmlspecialchars($user['username']); ?></div></div>
                        <div class="detail-item"><div class="detail-label">Email</div><div class="detail-value"><?php echo htmlspecialchars($user['email']); ?></div></div>
                        <div class="detail-item"><div class="detail-label">Phone</div><div class="detail-value"><?php echo htmlspecialchars($user['phone'] ?: 'Not provided'); ?></div></div>
                        <div class="detail-item"><div class="detail-label">Address</div><div class="detail-value"><?php echo htmlspecialchars($user['address'] ?: 'Not provided'); ?></div></div>
                        <div class="detail-item"><div class="detail-label">Account Created</div><div class="detail-value"><?php echo date('F d, Y', strtotime($user['created_at'])); ?></div></div>
                        <div class="detail-item"><div class="detail-label">Last Login</div><div class="detail-value"><?php echo $user['last_login'] ? date('F d, Y h:i A', strtotime($user['last_login'])) : 'Never logged in'; ?></div></div>
                    </div>
                    <?php if ($user['complaint_count'] > 0 || $user['assigned_complaints_count'] > 0): ?>
                    <div class="detail-item" style="grid-column: 1 / -1;">
                        <div class="detail-label">Complaint Statistics</div>
                        <div class="complaint-stats">
                            <?php if ($user['complaint_count'] > 0): ?>
                            <div class="stat-item"><h4><?php echo $user['complaint_count']; ?></h4><p>Total Filed</p></div>
                            <?php endif; ?>
                            <?php if ($user['active_complaints'] > 0): ?>
                            <div class="stat-item"><h4 style="color: #f39c12;"><?php echo $user['active_complaints']; ?></h4><p>Active</p></div>
                            <?php endif; ?>
                            <?php if ($user['resolved_complaints'] > 0): ?>
                            <div class="stat-item"><h4 style="color: #27ae60;"><?php echo $user['resolved_complaints']; ?></h4><p>Resolved</p></div>
                            <?php endif; ?>
                            <?php if ($user['assigned_complaints_count'] > 0): ?>
                            <div class="stat-item"><h4 style="color: #9b59b6;"><?php echo $user['assigned_complaints_count']; ?></h4><p>Assigned</p></div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    <div class="form-actions">
                        <?php if ($action === 'view'): ?>
                            <a href="users.php?action=edit&id=<?php echo $user['id']; ?>" class="btn btn-primary">Edit User</a>
                        <?php endif; ?>
                        <button class="btn btn-warning" onclick="resetPassword(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['full_name']); ?>')">Reset Password</button>
                        <?php if ($user['id'] != $_SESSION['user_id']): ?>
                            <?php if (!($is_admin && $user['role'] === 'super_admin')): ?>
                                <button class="btn btn-danger" onclick="deleteUser(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['full_name']); ?>', '<?php echo $user['role']; ?>')">Delete User</button>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
                
                <?php if ($action === 'edit'): ?>
                <div class="user-details">
                    <h3 style="color: #2c3e50; margin-bottom: 1.5rem; padding-bottom: 0.5rem; border-bottom: 2px solid #3498db;">Edit User</h3>
                    <form method="POST" action="users.php">
                        <input type="hidden" name="action" value="edit_user">
                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                        <div class="details-grid">
                            <div class="form-group"><label for="full_name" class="required">Full Name</label><input type="text" id="full_name" name="full_name" value="<?php echo htmlspecialchars($user['full_name']); ?>" required style="width:100%;padding:12px 15px;border:2px solid #e0e0e0;border-radius:6px;font-size:14px;"></div>
                            <div class="form-group"><label for="username" class="required">Username</label><input type="text" id="username" name="username" value="<?php echo htmlspecialchars($user['username']); ?>" required style="width:100%;padding:12px 15px;border:2px solid #e0e0e0;border-radius:6px;font-size:14px;"></div>
                            <div class="form-group"><label for="email" class="required">Email</label><input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required style="width:100%;padding:12px 15px;border:2px solid #e0e0e0;border-radius:6px;font-size:14px;"></div>
                            <div class="form-group"><label for="phone">Phone</label><input type="tel" id="phone" name="phone" value="<?php echo htmlspecialchars($user['phone']); ?>" style="width:100%;padding:12px 15px;border:2px solid #e0e0e0;border-radius:6px;font-size:14px;"></div>
                            <div class="form-group"><label for="address">Address</label><textarea id="address" name="address" rows="3" style="width:100%;padding:12px 15px;border:2px solid #e0e0e0;border-radius:6px;font-size:14px;"><?php echo htmlspecialchars($user['address']); ?></textarea></div>
                            <div class="form-group"><label for="role" class="required">Role</label><select id="role" name="role" required style="width:100%;padding:12px 15px;border:2px solid #e0e0e0;border-radius:6px;font-size:14px;background:white;" onchange="toggleDepartmentField()"><option value="citizen" <?php echo $user['role'] === 'citizen' ? 'selected' : ''; ?>>Citizen</option><option value="admin" <?php echo $user['role'] === 'admin' ? 'selected' : ''; ?>>Admin</option><?php if ($is_super_admin): ?><option value="super_admin" <?php echo $user['role'] === 'super_admin' ? 'selected' : ''; ?>>Super Admin</option><?php endif; ?></select></div>
                            <div class="form-group" id="department-group" style="display: <?php echo ($user['role'] === 'admin' || $user['role'] === 'super_admin') ? 'block' : 'none'; ?>;">
                                <label for="department_id">Department</label>
                                <select id="department_id" name="department_id" style="width:100%;padding:12px 15px;border:2px solid #e0e0e0;border-radius:6px;font-size:14px;background:white;">
                                    <option value="">-- Select Department --</option>
                                    <?php foreach ($departments as $dept): ?>
                                        <option value="<?php echo $dept['id']; ?>" <?php echo $user['department_id'] == $dept['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($dept['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group"><label class="checkbox-group"><input type="checkbox" id="is_active" name="is_active" value="1" <?php echo $user['is_active'] ? 'checked' : ''; ?>> Active Account</label></div>
                        </div>
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">Update User</button>
                            <a href="users.php?action=view&id=<?php echo $user['id']; ?>" class="btn btn-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
                <?php endif; ?>
                
            <?php else: ?>
                <div class="alert alert-error">User not found.</div>
                <a href="users.php" class="btn btn-primary">Back to Users</a>
            <?php endif; ?>
            
        <?php else: ?>
        
        <div class="role-tabs">
            <button class="role-tab <?php echo $role_filter === 'citizen' ? 'active' : ''; ?>" onclick="window.location.href='users.php?role=citizen'">Citizens (<?php echo $user_stats['citizen']['total'] ?? 0; ?>)</button>
            <button class="role-tab <?php echo $role_filter === 'admin' ? 'active' : ''; ?>" onclick="window.location.href='users.php?role=admin'">Admins (<?php echo $user_stats['admin']['total'] ?? 0; ?>)</button>
            <?php if ($is_super_admin): ?>
            <button class="role-tab <?php echo $role_filter === 'super_admin' ? 'active' : ''; ?>" onclick="window.location.href='users.php?role=super_admin'">Super Admins (<?php echo $user_stats['super_admin']['total'] ?? 0; ?>)</button>
            <?php endif; ?>
        </div>

        <div class="users-table-container">
            <div class="table-header">
                <h2><strong><?php echo $total_users; ?></strong> <?php echo $role_filter; ?>s found</h2>
                <button class="btn btn-success" onclick="openAddUserModal()">+ Add User</button>
            </div>
            
            <?php if (!empty($users)): ?>
                <div class="bulk-actions">
                    <div class="bulk-select">
                        <input type="checkbox" id="select_all" onchange="toggleSelectAll(this)">
                        <label for="select_all">Select All</label>
                    </div>
                    <span id="selected_count">0 users selected</span>
                    <select id="bulk_action_select">
                        <option value="">Bulk Actions</option>
                        <option value="activate">Activate</option>
                        <option value="deactivate">Deactivate</option>
                        <option value="delete">Delete</option>
                    </select>
                    <button type="button" class="btn btn-primary" onclick="submitBulkAction()">Apply</button>
                </div>
                <table>
                    <thead>
                        <tr>
                            <th width="30"><input type="checkbox" id="select_all_table" onchange="toggleAllCheckboxes(this)"></th>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Contact</th>
                            <?php if ($role_filter === 'citizen'): ?>
                                <th>Complaints</th>
                            <?php endif; ?>
                            <th>Status</th>
                            <th>Joined</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user_item): ?>
                            <tr>
                                <td><input type="checkbox" class="user_checkbox" value="<?php echo $user_item['id']; ?>" onchange="updateSelectedCount()"></td>
                                <td><?php echo $user_item['id']; ?></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($user_item['full_name']); ?></strong><br>
                                    <small style="color: #666;">@<?php echo htmlspecialchars($user_item['username']); ?></small>
                                    <?php if ($user_item['id'] == $_SESSION['user_id']): ?>
                                        <span style="color: #3498db; font-size: 0.7rem; margin-left: 5px;">(You)</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div style="font-size:0.85rem;">
                                        <div><?php echo htmlspecialchars($user_item['email']); ?></div>
                                        <?php if ($user_item['phone']): ?>
                                            <div style="color:#666;"><?php echo htmlspecialchars($user_item['phone']); ?></div>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <?php if ($role_filter === 'citizen'): ?>
                                    <td>
                                        <?php if ($user_item['complaint_count'] > 0): ?>
                                            <span class="count-badge complaint-count" title="Total complaints"><?php echo $user_item['complaint_count']; ?></span>
                                            <?php if ($user_item['active_complaints'] > 0): ?>
                                                <span class="count-badge active-complaints" title="Active complaints"><?php echo $user_item['active_complaints']; ?></span>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span style="color:#95a5a6;font-size:0.8rem;">No complaints</span>
                                        <?php endif; ?>
                                    </td>
                                <?php endif; ?>
                                <td><span class="status-badge status-<?php echo $user_item['is_active'] ? 'active' : 'inactive'; ?>"><?php echo $user_item['is_active'] ? 'Active' : 'Inactive'; ?></span></td>
                                <td><?php echo date('M d, Y', strtotime($user_item['created_at'])); ?></td>
                                <td>
                                    <div class="action-buttons">
                                        <a href="users.php?action=view&id=<?php echo $user_item['id']; ?>" class="action-btn action-btn-view">View</a>
                                        <?php if ($user_item['id'] == $_SESSION['user_id'] || ($is_super_admin || ($is_admin && $user_item['role'] !== 'super_admin'))): ?>
                                            <a href="users.php?action=edit&id=<?php echo $user_item['id']; ?>" class="action-btn action-btn-edit">Edit</a>
                                        <?php endif; ?>
                                        <button onclick="resetPassword(<?php echo $user_item['id']; ?>, '<?php echo htmlspecialchars($user_item['full_name']); ?>')" class="action-btn action-btn-reset">Reset</button>
                                        <?php if ($user_item['id'] != $_SESSION['user_id']): ?>
                                            <?php if (!($is_admin && $user_item['role'] === 'super_admin')): ?>
                                                <button onclick="deleteUser(<?php echo $user_item['id']; ?>, '<?php echo htmlspecialchars($user_item['full_name']); ?>', '<?php echo $user_item['role']; ?>')" class="action-btn action-btn-delete">Delete</button>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <?php if ($total_pages > 1): ?>
                    <div class="pagination">
                        <?php if ($page > 1): ?>
                            <a href="?page=<?php echo $page - 1; ?>&role=<?php echo $role_filter; ?>&status=<?php echo $status_filter; ?>&search=<?php echo urlencode($search_query); ?>">Previous</a>
                        <?php endif; ?>
                        
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <?php if ($i == 1 || $i == $total_pages || ($i >= $page - 2 && $i <= $page + 2)): ?>
                                <a href="?page=<?php echo $i; ?>&role=<?php echo $role_filter; ?>&status=<?php echo $status_filter; ?>&search=<?php echo urlencode($search_query); ?>" class="<?php echo $i == $page ? 'active' : ''; ?>"><?php echo $i; ?></a>
                            <?php elseif ($i == $page - 3 || $i == $page + 3): ?>
                                <span>...</span>
                            <?php endif; ?>
                        <?php endfor; ?>
                        
                        <?php if ($page < $total_pages): ?>
                            <a href="?page=<?php echo $page + 1; ?>&role=<?php echo $role_filter; ?>&status=<?php echo $status_filter; ?>&search=<?php echo urlencode($search_query); ?>">Next</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                
            <?php else: ?>
                <div class="no-users">
                    <i class="fas fa-users"></i>
                    <p>No <?php echo $role_filter; ?>s found<?php echo $status_filter || $search_query ? ' with the current filters' : ''; ?>.</p>
                    <button class="btn btn-success" onclick="openAddUserModal()" style="margin-top:1rem;">Add First <?php echo ucfirst($role_filter); ?></button>
                </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- Add User Modal -->
    <div class="modal" id="addUserModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Add New User</h3>
                <button class="close-modal" onclick="closeAddUserModal()">&times;</button>
            </div>
            <form method="POST" action="users.php" id="addUserForm">
                <input type="hidden" name="action" value="add_user">
                
                <div class="form-group">
                    <label for="add_full_name" class="required">Full Name</label>
                    <input type="text" id="add_full_name" name="full_name" required placeholder="e.g., John Smith">
                </div>
                
                <div class="form-group">
                    <label for="add_username" class="required">Username</label>
                    <input type="text" id="add_username" name="username" required placeholder="e.g., john.smith">
                </div>
                
                <div class="form-group">
                    <label for="add_email" class="required">Email</label>
                    <input type="email" id="add_email" name="email" required placeholder="e.g., john@example.com">
                </div>
                
                <div class="form-group">
                    <label for="add_phone">Phone Number</label>
                    <input type="tel" id="add_phone" name="phone" placeholder="e.g., 9876543210">
                </div>
                
                <div class="form-group">
                    <label for="add_address">Address</label>
                    <textarea id="add_address" name="address" rows="3" placeholder="Full address"></textarea>
                </div>
                
                <div class="form-group">
                    <label for="add_role" class="required">Role</label>
                    <select id="add_role" name="role" required onchange="toggleAddDepartmentField()">
                        <option value="citizen" selected>Citizen</option>
                        <option value="admin">Admin</option>
                        <?php if ($is_super_admin): ?>
                            <option value="super_admin">Super Admin</option>
                        <?php endif; ?>
                    </select>
                </div>
                
                <div class="form-group" id="add_department_group" style="display: none;">
                    <label for="add_department_id">Department</label>
                    <select id="add_department_id" name="department_id">
                        <option value="">-- Select Department --</option>
                        <?php foreach ($departments as $dept): ?>
                            <option value="<?php echo $dept['id']; ?>"><?php echo htmlspecialchars($dept['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="checkbox-group">
                        <input type="checkbox" id="add_is_active" name="is_active" value="1" checked>
                        Active Account
                    </label>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Create User</button>
                    <button type="button" class="btn btn-secondary" onclick="closeAddUserModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete User Modal -->
    <div class="modal" id="deleteUserModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Delete User</h3>
                <button class="close-modal" onclick="closeDeleteUserModal()">&times;</button>
            </div>
            <form method="POST" action="users.php">
                <input type="hidden" name="action" value="delete_user">
                <input type="hidden" name="user_id" id="delete_user_id">
                <div class="form-group">
                    <p>Delete user <strong id="delete_user_name"></strong>?</p>
                    <p id="delete_warning" style="color:#e74c3c;font-weight:600;"></p>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn btn-danger">Delete User</button>
                    <button type="button" class="btn btn-secondary" onclick="closeDeleteUserModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Reset Password Modal -->
    <div class="modal" id="resetPasswordModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Reset Password</h3>
                <button class="close-modal" onclick="closeResetPasswordModal()">&times;</button>
            </div>
            <form method="POST" action="users.php">
                <input type="hidden" name="action" value="reset_password">
                <input type="hidden" name="user_id" id="reset_user_id">
                <div class="form-group">
                    <p>Reset password for <strong id="reset_user_name"></strong>?</p>
                    <p>A new random password will be generated.</p>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn btn-warning">Reset Password</button>
                    <button type="button" class="btn btn-secondary" onclick="closeResetPasswordModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Bulk Action Form -->
    <form method="POST" action="users.php" id="bulkActionForm" style="display: none;">
        <input type="hidden" name="action" value="bulk_action">
        <input type="hidden" name="bulk_action_type" id="bulk_action_type">
        <div id="user_ids_container"></div>
    </form>

    <footer class="footer">
        <div class="footer-content">
            <div class="footer-section">
                <h4>CiviCare</h4>
                <p>Municipal Complaint Management System</p>
            </div>
            <div class="footer-section">
                <h4>Quick Links</h4>
                <ul>
                    <li><a href="index.php">Dashboard</a></li>
                    <li><a href="users.php">Users</a></li>
                    <li><a href="departments.php">Departments</a></li>
                </ul>
            </div>
        </div>
        <div class="footer-bottom">
            <p>&copy; <?php echo date('Y'); ?> CiviCare. All rights reserved.</p>
        </div>
    </footer>

    <script>
        function openAddUserModal() { 
            document.getElementById('addUserModal').style.display = 'flex'; 
            document.getElementById('add_full_name').focus(); 
        }
        
        function closeAddUserModal() { 
            document.getElementById('addUserModal').style.display = 'none'; 
            document.getElementById('addUserForm').reset();
            document.getElementById('add_department_group').style.display = 'none';
        }
        
        function deleteUser(userId, userName, userRole) { 
            document.getElementById('delete_user_id').value = userId; 
            document.getElementById('delete_user_name').textContent = userName; 
            
            if (userRole === 'admin' || userRole === 'super_admin') {
                document.getElementById('delete_warning').textContent = 'Warning: This user has administrative privileges.';
            } else {
                document.getElementById('delete_warning').textContent = '';
            }
            
            document.getElementById('deleteUserModal').style.display = 'flex'; 
        }
        
        function closeDeleteUserModal() { 
            document.getElementById('deleteUserModal').style.display = 'none'; 
        }
        
        function resetPassword(userId, userName) { 
            document.getElementById('reset_user_id').value = userId; 
            document.getElementById('reset_user_name').textContent = userName; 
            document.getElementById('resetPasswordModal').style.display = 'flex'; 
        }
        
        function closeResetPasswordModal() { 
            document.getElementById('resetPasswordModal').style.display = 'none'; 
        }
        
        function toggleDepartmentField() {
            var role = document.getElementById('role').value;
            var deptGroup = document.getElementById('department-group');
            deptGroup.style.display = (role === 'admin' || role === 'super_admin') ? 'block' : 'none';
        }
        
        function toggleAddDepartmentField() {
            var role = document.getElementById('add_role').value;
            var deptGroup = document.getElementById('add_department_group');
            deptGroup.style.display = (role === 'admin' || role === 'super_admin') ? 'block' : 'none';
        }
        
        function filterByStatus(status) { 
            document.getElementById('status').value = status; 
            document.querySelector('form').submit(); 
        }
        
        // Bulk actions
        var selectedUsers = [];
        
        function toggleSelectAll(source) {
            var checkboxes = document.querySelectorAll('.user_checkbox');
            for (var i = 0; i < checkboxes.length; i++) {
                checkboxes[i].checked = source.checked;
            }
            updateSelectedCount();
        }
        
        function toggleAllCheckboxes(source) {
            document.getElementById('select_all').checked = source.checked;
            toggleSelectAll(source);
        }
        
        function updateSelectedCount() {
            var checkboxes = document.querySelectorAll('.user_checkbox');
            selectedUsers = [];
            for (var i = 0; i < checkboxes.length; i++) {
                if (checkboxes[i].checked) {
                    selectedUsers.push(checkboxes[i].value);
                }
            }
            document.getElementById('selected_count').textContent = selectedUsers.length + ' users selected';
        }
        
        function submitBulkAction() {
            var action = document.getElementById('bulk_action_select').value;
            if (!action) {
                alert('Please select an action');
                return;
            }
            
            if (selectedUsers.length === 0) {
                alert('Please select users to perform this action');
                return;
            }
            
            var confirmMessage = 'Are you sure you want to ' + action + ' ' + selectedUsers.length + ' user(s)?';
            if (action === 'delete') {
                confirmMessage = 'Warning: This will permanently deactivate ' + selectedUsers.length + ' user(s). Continue?';
            }
            
            if (confirm(confirmMessage)) {
                document.getElementById('bulk_action_type').value = action;
                
                var container = document.getElementById('user_ids_container');
                container.innerHTML = '';
                for (var i = 0; i < selectedUsers.length; i++) {
                    var input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'user_ids[]';
                    input.value = selectedUsers[i];
                    container.appendChild(input);
                }
                
                document.getElementById('bulkActionForm').submit();
            }
        }
        
        function exportUsers() {
            var role = '<?php echo $role_filter; ?>';
            var status = '<?php echo $status_filter; ?>';
            var search = '<?php echo $search_query; ?>';
            
            window.location.href = 'export_users.php?role=' + role + '&status=' + status + '&search=' + encodeURIComponent(search);
        }
        
        window.onclick = function(event) { 
            document.querySelectorAll('.modal').forEach(modal => { 
                if (event.target === modal) modal.style.display = 'none'; 
            }); 
        }
        
        document.getElementById('status').addEventListener('change', function() { 
            if (this.value !== 'all') document.querySelector('form').submit(); 
        });
        
        // Initialize department field visibility on page load for edit form
        window.onload = function() {
            <?php if ($action === 'edit' && $user): ?>
            toggleDepartmentField();
            <?php endif; ?>
        };
    </script>
</body>
</html>