<?php
// civicare/admin/index.php
require_once '../config/database.php';
require_once '../config/functions.php';

// Check if user is logged in and is admin/super_admin
if (!isLoggedIn() || (!hasRole('admin') && !hasRole('staff') && !hasRole('super_admin'))) {
    header('Location: login.php');
    exit();
}
$conn = getDatabaseConnection();

// Get statistics for admin dashboard
$total_complaints = $conn->query("SELECT COUNT(*) as count FROM complaints")->fetch_assoc()['count'];
$pending_complaints = $conn->query("SELECT COUNT(*) as count FROM complaints WHERE status = 'pending'")->fetch_assoc()['count'];
$validated_complaints = $conn->query("SELECT COUNT(*) as count FROM complaints WHERE status = 'validated'")->fetch_assoc()['count'];
$resolved_complaints = $conn->query("SELECT COUNT(*) as count FROM complaints WHERE status IN ('resolved', 'closed')")->fetch_assoc()['count'];
$total_users = $conn->query("SELECT COUNT(*) as count FROM users")->fetch_assoc()['count'];
$total_staff = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'staff'")->fetch_assoc()['count'];

// Get reapplication statistics
$reapplication_stats = getReapplicationStats($conn);
$total_reapplications = $reapplication_stats['total_reapplications'];
$complaints_with_reapplications = $reapplication_stats['complaints_with_reapplications'];

// SUPER ADMIN SPECIFIC STATISTICS
$escalated_complaints = 0;
$total_admins = 0;
$today_resolved = 0;

if (isSuperAdmin()) {
    $escalated_complaints = $conn->query("SELECT COUNT(*) as count FROM complaints WHERE status = 'escalated'")->fetch_assoc()['count'];
    $total_admins = $conn->query("SELECT COUNT(*) as count FROM users WHERE role IN ('admin', 'super_admin')")->fetch_assoc()['count'];
    $today_resolved = $conn->query("SELECT COUNT(*) as count FROM complaints WHERE DATE(resolution_date) = CURDATE() AND status = 'resolved'")->fetch_assoc()['count'];
}

// Get recent complaints
$recent_complaints = [];
$sql = "SELECT c.*, u.full_name as user_name, d.name as department_name 
        FROM complaints c 
        JOIN users u ON c.user_id = u.id 
        JOIN departments d ON c.department_id = d.id 
        ORDER BY c.created_at DESC LIMIT 10";
$result = $conn->query($sql);
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $recent_complaints[] = $row;
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isSuperAdmin() ? 'Super Admin' : 'Admin'; ?> Dashboard - Municipal Complaint Management System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
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
        
.sidebar {
            width: 250px;
            background: linear-gradient(180deg, #2c3e50 0%, #1a252f 100%);
            color: white;
            height: 100vh;
            position: fixed;
            padding: 20px 0;
            transition: all 0.3s ease;
            z-index: 1000;
            box-shadow: 2px 0 10px rgba(0,0,0,0.2);
        }
        
        .logo {
            text-align: center;
            padding: 20px;
            font-size: 1.5rem;
            font-weight: bold;
            border-bottom: 1px solid #34495e;
        }
        
        .nav-menu {
            padding: 20px 0;
        }
        
        .nav-menu a {
            display: flex;
            align-items: center;
            padding: 12px 20px;
            color: #ecf0f1;
            text-decoration: none;
            transition: all 0.3s ease;
            margin: 5px 10px;
            border-radius: 8px;
            position: relative;
            overflow: hidden;
        }
        
        .nav-menu a::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            height: 100%;
            width: 0;
            background: #3498db;
            transition: width 0.3s ease;
            border-radius: 8px;
            z-index: -1;
        }
        
        .nav-menu a:hover::before {
            width: 100%;
        }
        
        .nav-menu a:hover {
            color: white;
            transform: translateX(5px);
        }
        
        .nav-menu a.active {
            background: #3498db;
            border-left: none;
        }

        .nav-menu a.active::before {
            width: 100%;
        }

        .nav-link { position: relative; display:flex; align-items:center; gap:12px; }
        .nav-text { 
            flex:1; 
            white-space: nowrap;
            transition: opacity 0.3s ease;
        }
        .badge { 
            background:#e74c3c; 
            color:#fff; 
            padding:3px 8px; 
            border-radius:12px; 
            font-size:0.75rem; 
            margin-left:8px;
            animation: pulse 2s infinite;
        }
/* Toggle Button - Fixed Position */
        .sidebar-toggle {
            position: fixed;
            left: 250px;
            top: 15px;
            z-index: 1001;
            background: #2c3e50;
            color: white;
            border: none;
            width: 36px;
            height: 36px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 1.2rem;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
            box-shadow: 0 2px 8px rgba(0,0,0,0.2);
        }

        .sidebar-toggle:hover {
            background: #34495e;
            transform: scale(1.05);
        }

        .sidebar-toggle.collapsed {
            left: 70px;
        }

        .sidebar-toggle-icon {
            transition: transform 0.3s ease;
        }

        .sidebar-toggle.collapsed .sidebar-toggle-icon {
            transform: rotate(180deg);
        }

        .sidebar.collapsed { width:70px; }
        .sidebar.collapsed .nav-text, .sidebar.collapsed .badge, .nav-sub { display:none; }

        /* Collapsible Reports Section - Hide when collapsed */
        .sidebar.collapsed .nav-collapsible .collapsible-text {
            opacity: 0;
            width: 0;
        }
        
        .sidebar.collapsed .nav-collapsible .content {
            display: none !important;
        }
        
        /* Collapsible button styling to match nav-link */
        .nav-collapsible {
            position: relative;
        }
        
        .nav-collapsible .collapsible {
            display: flex;
            align-items: center;
            gap: 12px;
            width: 100%;
            text-align: left;
            padding: 12px 20px;
            margin: 5px 10px;
            border-radius: 8px;
            background: transparent;
            border: none;
            color: #ecf0f1;
            cursor: pointer;
            font-size: 1rem;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .nav-collapsible .collapsible::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            height: 100%;
            width: 0;
            background: #3498db;
            transition: width 0.3s ease;
            border-radius: 8px;
            z-index: -1;
        }

        .nav-collapsible .collapsible:hover::before {
            width: 100%;
        }

        .nav-collapsible .collapsible:hover {
            color: white;
            transform: translateX(5px);
        }

        .collapsible-text {
            flex: 1;
            white-space: nowrap;
            transition: opacity 0.3s ease;
        }

        .nav-collapsible .collapsible-arrow {
            transition: transform 0.3s ease;
            font-size: 0.8rem;
        }

        .nav-collapsible .collapsible.open .collapsible-arrow {
            transform: rotate(180deg);
        }

        .nav-collapsible .content {
            display: none;
            padding-left: 20px;
            transition: all 0.3s ease;
        }

        .nav-collapsible .content.show {
            display: block;
        }

        .nav-collapsible .content .nav-sub {
            display: block;
            padding: 10px 20px;
            color: #bdc3c7;
            text-decoration: none;
            transition: all 0.3s ease;
            border-radius: 6px;
            margin: 3px 10px;
            font-size: 0.9rem;
        }

        .nav-collapsible .content .nav-sub:hover {
            background: rgba(52, 152, 219, 0.2);
            color: white;
            transform: translateX(5px);
        }

        /* Logo transition for collapse */
        .logo {
            font-size: 1.4rem;
            font-weight: bold;
            white-space: nowrap;
            overflow: hidden;
            transition: opacity 0.3s ease;
        }

        .sidebar.collapsed .logo {
            opacity: 0;
            width: 0;
        }

        /* Nav-text transition for collapse */
        .nav-text { 
            flex:1; 
            white-space: nowrap;
            transition: opacity 0.3s ease;
        }

        .sidebar.collapsed .nav-text {
            opacity: 0;
            width: 0;
        }

        /* Badge positioning for collapsed state */
        .badge { 
            background: #e74c3c; 
            color: #fff; 
            padding: 3px 8px; 
            border-radius: 12px; 
            font-size: 0.75rem; 
            margin-left: 8px;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% { box-shadow: 0 0 0 0 rgba(231, 76, 60, 0.7); }
            70% { box-shadow: 0 0 0 6px rgba(231, 76, 60, 0); }
            100% { box-shadow: 0 0 0 0 rgba(231, 76, 60, 0); }
        }

        .sidebar.collapsed .badge {
            position: absolute;
            top: 5px;
            right: 5px;
            padding: 2px 5px;
            font-size: 0.6rem;
            margin-left: 0;
        }

        .main-content {
            margin-left: 250px;
            padding: 20px;
            transition: margin-left 0.3s ease;
        }

        .main-content.expanded {
            margin-left: 70px;
        }
        
        .header {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .header h1 {
            color: #2c3e50;
            margin-bottom: 10px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            text-align: center;
            transition: transform 0.3s;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-card.super-admin {
            border: 2px solid #e74c3c;
            background: linear-gradient(135deg, #fff, #fff8f8);
        }
        
        .stat-card.escalated {
            border-left: 4px solid #e74c3c;
        }
        
        .stat-card.admin-stats {
            border-left: 4px solid #9b59b6;
        }
        
        .stat-card h3 {
            font-size: 2rem;
            color: #3498db;
            margin-bottom: 10px;
        }
        
        .stat-card.escalated h3 {
            color: #e74c3c;
        }
        
        .stat-card.admin-stats h3 {
            color: #9b59b6;
        }
        
        .complaints-table {
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th {
            background: #3498db;
            color: white;
            padding: 15px;
            text-align: left;
        }
        
        td {
            padding: 15px;
            border-bottom: 1px solid #eee;
        }
        
        tr:hover {
            background: #f9f9f9;
        }
        
        .status {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: bold;
        }
        
        .status-pending { background: #f39c12; color: white; }
        .status-validated { background: #3498db; color: white; }
        .status-assigned { background: #9b59b6; color: white; }
        .status-in_progress { background: #3498db; color: white; }
        .status-resolved { background: #27ae60; color: white; }
        .status-escalated { 
            background: linear-gradient(45deg, #e74c3c, #e67e22);
            color: white;
            font-weight: bold;
        }
        
        .user-info {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: white;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .user-role-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: bold;
            margin-left: 10px;
        }
        
        .role-super_admin { background: #e74c3c; color: white; }
        .role-admin { background: #3498db; color: white; }
        .role-staff { background: #f39c12; color: white; }
        
        .logout-btn {
            padding: 8px 20px;
            background: #e74c3c;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            font-weight: 600;
            cursor: pointer;
            border: none;
        }
        
        .logout-btn:hover {
            background: #c0392b;
        }
        
        .super-admin-btn {
            background: #9b59b6;
        }
        
        .super-admin-btn:hover {
            background: #8e44ad;
        }
        
        .admin-btn {
            background: #3498db;
        }
        
        .admin-btn:hover {
            background: #2980b9;
        }
        
        .btn-group {
            display: flex;
            gap: 8px;
            align-items: center;
        }
    </style>
</head>
<body>
    <div class="sidebar" id="sidebar">
        <div style="display:flex;align-items:center;justify-content:space-between;padding:16px 20px;">
            <div class="logo"><?php echo isSuperAdmin() ? 'Civicare Super Admin' : 'Civicare Admin'; ?></div>
            <button id="sidebarToggle" title="Collapse sidebar" style="background:none;border:none;color:#ecf0f1;font-size:18px;cursor:pointer">☰</button>
        </div>
        <div class="nav-menu" id="navMenu">
            <a href="index.php" class="nav-link active">
                <span>🏠</span> <span class="nav-text">Dashboard</span>
            </a>
            <a href="complaints.php" class="nav-link">
                <span>📄</span> <span class="nav-text">Complaints</span>
                <span class="badge" title="Total complaints"><?php echo $total_complaints; ?></span>
            </a>
            
            <?php if (isSuperAdmin()): ?>
            <a href="complaints.php?status=escalated" class="nav-link">
                <span>🚨</span> <span class="nav-text">Escalated</span>
                <?php if ($escalated_complaints > 0): ?>
                <span class="badge" title="Escalated complaints" style="background: #e74c3c;"><?php echo $escalated_complaints; ?></span>
                <?php endif; ?>
            </a>
            <?php endif; ?>
            
            <a href="users.php?role=citizen" class="nav-link">
                <span>👥</span> <span class="nav-text">Citizens</span>
                <span class="badge" title="Total citizens"><?php echo $total_users - $total_staff - $total_admins; ?></span>
            </a>
            
            <?php if (isSuperAdmin()): ?>
            <!-- Only show Admins tab for Super Admin -->
            <a href="users.php?role=admin" class="nav-link">
                <span>👑</span> <span class="nav-text">Admins</span>
                <?php if ($total_admins > 0): ?>
                <span class="badge" title="Total admins" style="background: #9b59b6;"><?php echo $total_admins; ?></span>
                <?php endif; ?>
            </a>
            <?php endif; ?>
            
            <a href="staff.php" class="nav-link">
                <span>🧑‍🔧</span> <span class="nav-text">Staff</span>
                <span class="badge" title="Total staff"><?php echo $total_staff; ?></span>
            </a>
            
            <a href="departments.php" class="nav-link">
                <span>🏛️</span> <span class="nav-text">Departments</span>
            </a>
            
            <a href="reports.php" class="nav-link">
                <span>📊</span> <span class="nav-text">Reports</span>
            </a>
            
            <a href="settings.php" class="nav-link">
                <span>⚙️</span> <span class="nav-text">Settings</span>
            </a>
        </div>
    </div>
    
    <div class="main-content">
        <div class="user-info">
            <div>
                <h2>Welcome, <?php echo htmlspecialchars($_SESSION['user_name']); ?>!</h2>
                <p>
                    <?php echo isSuperAdmin() ?>
                    <span class="user-role-badge role-<?php echo $_SESSION['user_role']; ?>">
                        
                    </span>
                </p>
            </div>
            <div class="btn-group">
                <?php if (isSuperAdmin()): ?>
                
                <?php endif; ?>
                <a href="logout.php" class="logout-btn">Logout</a>
            </div>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <h3><?php echo $total_complaints; ?></h3>
                <p>Total Complaints</p>
            </div>
            <div class="stat-card">
                <h3><?php echo $pending_complaints; ?></h3>
                <p>Pending Validation</p>
            </div>
            <div class="stat-card">
                <h3><?php echo $validated_complaints; ?></h3>
                <p>Validated Complaints</p>
            </div>
            <div class="stat-card">
                <h3><?php echo $resolved_complaints; ?></h3>
                <p>Resolved Complaints</p>
            </div>
            <div class="stat-card">
                <h3><?php echo $total_users; ?></h3>
                <p>Total Users</p>
            </div>
            <div class="stat-card">
                <h3><?php echo $total_staff; ?></h3>
                <p>Total Staff</p>
            </div>
            
            <div class="stat-card" style="background: linear-gradient(135deg, #fff, #fff8f8); color: black;">
                <h3><?php echo $total_reapplications; ?></h3>
                <p>Total Reapplications</p>
                <small style="font-size: 0.8rem; opacity: 0.9;">From <?php echo $complaints_with_reapplications; ?> complaints</small>
            </div>
            
            <?php if (isSuperAdmin()): ?>
            <div class="stat-card escalated">
                <h3><?php echo $escalated_complaints; ?></h3>
                <p>Escalated Complaints</p>
                <?php if ($escalated_complaints > 0): ?>
                <small style="color: #e74c3c; font-weight: bold;">Requires Attention</small>
                <?php endif; ?>
            </div>
            
            <div class="stat-card admin-stats">
                <h3><?php echo $total_admins; ?></h3>
                <p>Total Admins</p>
            </div>
            
            <?php if ($today_resolved > 0): ?>
            <div class="stat-card" style="border-left: 4px solid #27ae60;">
                <h3 style="color: #27ae60;"><?php echo $today_resolved; ?></h3>
                <p>Resolved Today</p>
            </div>
            <?php endif; ?>
            <?php endif; ?>
        </div>
        
        <div class="complaints-table">
            <h3 style="padding: 20px; margin: 0;">Recent Complaints 
                <?php if (isSuperAdmin() && $escalated_complaints > 0): ?>
                <span style="color: #e74c3c; font-size: 0.9rem; margin-left: 10px;">
                    ⚠️ <?php echo $escalated_complaints; ?> escalated complaints need your attention
                </span>
                <?php endif; ?>
            </h3>
            <table>
                <thead>
                    <tr>
                        <th>Tracking ID</th>
                        <th>Title</th>
                        <th>User</th>
                        <th>Department</th>
                        <th>Status</th>
                        <th>Priority</th>
                        <th>Date</th>
                        <?php if (isSuperAdmin()): ?>
                        <th>Super Admin Actions</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($recent_complaints)): ?>
                        <?php foreach ($recent_complaints as $complaint): ?>
                            <tr>
                                <td>
                                    <span class="tracking-id"><?php echo htmlspecialchars($complaint['tracking_id']); ?></span>
                                    <button class="copy-id" data-id="<?php echo htmlspecialchars($complaint['tracking_id']); ?>" style="margin-left:8px;padding:4px 8px;border-radius:4px;border:none;background:#2980b9;color:#fff;cursor:pointer;font-size:0.8rem;">Copy</button>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($complaint['title']); ?>
                                    <?php if ($complaint['status'] === 'escalated'): ?>
                                        <span style="color: #e74c3c; font-size: 0.8rem; margin-left: 5px;">🚨</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($complaint['user_name']); ?></td>
                                <td><?php echo htmlspecialchars($complaint['department_name']); ?></td>
                                <td>
                                    <span class="status status-<?php echo $complaint['status']; ?>">
                                        <?php echo getStatusText($complaint['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <span style="font-weight: bold; color: 
                                        <?php echo $complaint['priority'] === 'urgent' ? '#e74c3c' : 
                                               ($complaint['priority'] === 'high' ? '#f39c12' : 
                                               ($complaint['priority'] === 'medium' ? '#3498db' : '#27ae60')); ?>">
                                        <?php echo ucfirst($complaint['priority']); ?>
                                    </span>
                                </td>
                                <td><?php echo date('M d, Y', strtotime($complaint['created_at'])); ?></td>
                                <?php if (isSuperAdmin()): ?>
                                <td>
                                    <?php if ($complaint['status'] === 'escalated'): ?>
                                        <button class="resolve-escalated" data-id="<?php echo $complaint['id']; ?>" style="padding:4px 8px;border-radius:4px;border:none;background:#27ae60;color:#fff;cursor:pointer;font-size:0.8rem;">Resolve</button>
                                        <button class="reassign-escalated" data-id="<?php echo $complaint['id']; ?>" style="padding:4px 8px;border-radius:4px;border:none;background:#3498db;color:#fff;cursor:pointer;font-size:0.8rem;margin-left:5px;">Reassign</button>
                                    <?php elseif (in_array($complaint['status'], ['pending', 'validated', 'assigned', 'in_progress'])): ?>
                                        <button class="super-escalate" data-id="<?php echo $complaint['id']; ?>" style="padding:4px 8px;border-radius:4px;border:none;background:#e67e22;color:#fff;cursor:pointer;font-size:0.8rem;">Escalate</button>
                                    <?php else: ?>
                                        <span style="color: #95a5a6; font-size: 0.8rem;">No action</span>
                                    <?php endif; ?>
                                </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="<?php echo isSuperAdmin() ? '8' : '7'; ?>" style="text-align: center; padding: 30px;">No complaints found</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <div class="complaints-table" style="margin-top: 30px;">
            <h3 style="padding: 20px; margin: 0;">System Quick Actions</h3>
            <div style="padding: 20px; display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                <?php if (isSuperAdmin()): ?>
                <button class="logout-btn admin-btn" onclick="window.location.href='users.php?action=add&role=admin'">➕ Add New Admin</button>
                <button class="logout-btn" style="background: #17a2b8;" onclick="window.location.href='reports.php?type=audit'">📋 View Audit Logs</button>
                <?php endif; ?>
                <button class="logout-btn" style="background: #f39c12;" onclick="window.location.href='backup_database.php'">💾 Backup Database</button>
                <button class="logout-btn" style="background: #e74c3c;" onclick="window.location.href='clear_cache.php'">🗑️ Clear Cache</button>
                <button class="logout-btn" style="background: #27ae60;" onclick="window.location.href='export_data.php'">📄 Export Data (PDF/Excel)</button>
                <button class="logout-btn" style="background: #6c757d;" onclick="window.location.href='system_status.php'">📊 System Status</button>
                <?php if (isSuperAdmin()): ?>
                <button class="logout-btn super-admin-btn" onclick="showSystemResetModal()">🔄 System Reset</button>
                <?php endif; ?>
    </div>
    
    <!-- Super Admin System Reset Modal -->
    <?php if (isSuperAdmin()): ?>
    <div id="systemResetModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:1000; align-items:center; justify-content:center;">
        <div style="background:white; padding:30px; border-radius:10px; max-width:500px; width:90%;">
            <h3 style="color: #e74c3c; margin-bottom: 20px;">⚠️ System Reset Confirmation</h3>
            <p style="margin-bottom: 20px; color: #666;">This action will reset certain system settings. Please select what you want to reset:</p>
            
            <div style="margin-bottom: 20px;">
                <label style="display:block; margin-bottom:10px;">
                    <input type="checkbox" id="resetCache" checked> Clear System Cache
                </label>
                <label style="display:block; margin-bottom:10px;">
                    <input type="checkbox" id="resetLogs"> Clear Old Logs (older than 30 days)
                </label>
                <label style="display:block; margin-bottom:10px;">
                    <input type="checkbox" id="resetTemp"> Clear Temporary Files
                </label>
            </div>
            
            <div style="display:flex; gap:10px; justify-content:flex-end;">
                <button onclick="performSystemReset()" class="logout-btn" style="background:#e74c3c;">Proceed with Reset</button>
                <button onclick="hideSystemResetModal()" class="logout-btn" style="background:#95a5a6;">Cancel</button>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <script>
        // Sidebar toggle and collapsible menu behavior
        document.getElementById('sidebarToggle').addEventListener('click', function() {
            const sidebar = document.getElementById('sidebar');
            const mainContent = document.querySelector('.main-content');
            const toggleButton = document.getElementById('sidebarToggle');
            sidebar.classList.toggle('collapsed');
            mainContent.classList.toggle('expanded');
            toggleButton.classList.toggle('collapsed');
        });

        document.querySelectorAll('.nav-collapsible .collapsible').forEach(btn => {
            btn.addEventListener('click', function() {
                const content = this.nextElementSibling;
                if (content.style.display === 'block') {
                    content.style.display = 'none';
                } else {
                    content.style.display = 'block';
                }
            });
        });

        // Highlight active nav-link based on URL
        document.querySelectorAll('.nav-link').forEach(a => {
            if (location.pathname.endsWith(a.getAttribute('href'))) {
                document.querySelectorAll('.nav-link').forEach(n => n.classList.remove('active'));
                a.classList.add('active');
            }
        });
    </script>
</body>
</html>