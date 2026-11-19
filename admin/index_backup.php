<?php
// civicare/admin/index.php
require_once '../config/database.php';
require_once '../config/functions.php';

// Check if user is logged in and is admin
if (!isLoggedIn() || (!hasRole('admin') && !hasRole('staff'))) {
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
            margin-bottom: 40px;
        }

        .stat-card {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            padding: 10px;
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
            font-size: 18px;
            margin-bottom: 6px;
            opacity: 0.8;
        }

        .stat-card h3 {
            font-size: 16px;
            color: #1e3c72;
            margin-bottom: 2px;
            font-weight: 700;
        }

        .stat-card p {
            color: #666;
            font-size: 12px;
            font-weight: 500;
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

        .stat-card.admin-card {
            background: linear-gradient(135deg, #e8f4f8 0%, #d4e9f2 100%);
            border: 1px solid rgba(52, 152, 219, 0.2);
        }

        .stat-card.admin-card .stat-icon {
            color: #3498db;
        }

        .stat-card.admin-card h3 {
            color: #3498db;
        }

        .stat-card.reapplications {
            background: linear-gradient(135deg, #fff8e1 0%, #ffeaa7 100%);
            border: 1px solid rgba(243, 156, 18, 0.2);
        }

        .stat-card.reapplications .stat-icon {
            color: #f39c12;
        }

        .stat-card.reapplications h3 {
            color: #f39c12;
        }

        /* ===== COMPLAINTS TABLE ===== */
        .complaints-section {
            background: white;
            border-radius: 12px;
            box-shadow: 0 6px 15px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            margin-bottom: 40px;
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
            padding: 18px 20px;
            text-align: left;
            font-weight: 600;
            font-size: 14px;
            border-bottom: 2px solid #eee;
        }

        td {
            padding: 18px 20px;
            border-bottom: 1px solid #eee;
            font-size: 14px;
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

        .escalated-icon {
            color: #e74c3c;
            margin-left: 5px;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.5; }
            100% { opacity: 1; }
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

        .priority {
            font-weight: 600;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
        }

        .priority-urgent { color: #e74c3c; background: rgba(231, 76, 60, 0.1); }
        .priority-high { color: #f39c12; background: rgba(243, 156, 18, 0.1); }
        .priority-medium { color: #3498db; background: rgba(52, 152, 219, 0.1); }
        .priority-low { color: #27ae60; background: rgba(39, 174, 96, 0.1); }

        /* ===== ACTION LINKS ===== */
        .action-link {
            color: #1e3c72;
            text-decoration: none;
            font-weight: 600;
            margin-right: 12px;
            transition: all 0.3s;
            padding: 4px 8px;
            border-radius: 4px;
            border: none;
            background: none;
            cursor: pointer;
            font-size: 12px;
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

        /* ===== QUICK ACTIONS ===== */
        .quick-actions {
            background: white;
            border-radius: 12px;
            box-shadow: 0 6px 15px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        .quick-actions-header {
            background: linear-gradient(135deg, #1e3c72, #2a5298);
            color: white;
            padding: 25px 30px;
        }

        .quick-actions-header h3 {
            font-size: 18px;
            margin: 0;
            font-weight: 600;
        }

        .quick-actions-grid {
            padding: 30px;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
        }

        .action-btn {
            display: inline-block;
            padding: 15px 25px;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s;
            text-align: center;
            font-size: 15px;
            position: relative;
            overflow: hidden;
            border: none;
            cursor: pointer;
            color: white;
        }

        .action-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.5s;
        }

        .action-btn:hover::before {
            left: 100%;
        }

        .btn-primary {
            background: linear-gradient(135deg, #1e3c72, #2a5298);
            box-shadow: 0 4px 12px rgba(30, 60, 114, 0.3);
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, #2a5298, #1e3c72);
            transform: translateY(-3px);
            box-shadow: 0 6px 16px rgba(30, 60, 114, 0.4);
        }

        .btn-success {
            background: linear-gradient(135deg, #27ae60, #219653);
            box-shadow: 0 4px 12px rgba(39, 174, 96, 0.3);
        }

        .btn-success:hover {
            background: linear-gradient(135deg, #219653, #1e8449);
            transform: translateY(-3px);
            box-shadow: 0 6px 16px rgba(39, 174, 96, 0.4);
        }

        .btn-warning {
            background: linear-gradient(135deg, #f39c12, #d68910);
            box-shadow: 0 4px 12px rgba(243, 156, 18, 0.3);
        }

        .btn-warning:hover {
            background: linear-gradient(135deg, #d68910, #b9770e);
            transform: translateY(-3px);
            box-shadow: 0 6px 16px rgba(243, 156, 18, 0.4);
        }

        .btn-danger {
            background: linear-gradient(135deg, #e74c3c, #c0392b);
            box-shadow: 0 4px 12px rgba(231, 76, 60, 0.3);
        }

        .btn-danger:hover {
            background: linear-gradient(135deg, #c0392b, #a93226);
            transform: translateY(-3px);
            box-shadow: 0 6px 16px rgba(231, 76, 60, 0.4);
        }

        .btn-info {
            background: linear-gradient(135deg, #3498db, #2980b9);
            box-shadow: 0 4px 12px rgba(52, 152, 219, 0.3);
        }

        .btn-info:hover {
            background: linear-gradient(135deg, #2980b9, #1f5f99);
            transform: translateY(-3px);
            box-shadow: 0 6px 16px rgba(52, 152, 219, 0.4);
        }

        .btn-secondary {
            background: linear-gradient(135deg, #6c757d, #5a6268);
            box-shadow: 0 4px 12px rgba(108, 117, 125, 0.3);
        }

        .btn-secondary:hover {
            background: linear-gradient(135deg, #5a6268, #495057);
            transform: translateY(-3px);
            box-shadow: 0 6px 16px rgba(108, 117, 125, 0.4);
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

        /* ===== COPY BUTTON ===== */
        .copy-id {
            padding: 6px 12px;
            border-radius: 6px;
            border: none;
            background: linear-gradient(135deg, #2a5298, #1e3c72);
            color: white;
            cursor: pointer;
            font-size: 12px;
            transition: all 0.3s;
            font-weight: 600;
            margin-left: 8px;
        }

        .copy-id:hover {
            background: linear-gradient(135deg, #1e3c72, #152a4a);
            transform: translateY(-1px);
        }

        /* ===== MODAL ===== */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            background: white;
            padding: 30px;
            border-radius: 10px;
            max-width: 500px;
            width: 90%;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
        }

        .modal-content h3 {
            color: #e74c3c;
            margin-bottom: 20px;
            font-size: 18px;
        }

        .modal-content p {
            margin-bottom: 20px;
            color: #666;
        }

        .modal-options {
            margin-bottom: 20px;
        }

        .modal-options label {
            display: block;
            margin-bottom: 10px;
        }

        .modal-options input[type="checkbox"] {
            margin-right: 8px;
        }

        .modal-buttons {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
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

        .welcome-panel, .stat-card, .complaints-section, .quick-actions {
            animation: fadeInUp 0.6s ease-out;
        }

        .stat-card:nth-child(1) { animation-delay: 0.1s; }
        .stat-card:nth-child(2) { animation-delay: 0.2s; }
        .stat-card:nth-child(3) { animation-delay: 0.3s; }
        .stat-card:nth-child(4) { animation-delay: 0.4s; }
        .stat-card:nth-child(5) { animation-delay: 0.5s; }
        .stat-card:nth-child(6) { animation-delay: 0.6s; }
        .stat-card:nth-child(7) { animation-delay: 0.7s; }
        .stat-card:nth-child(8) { animation-delay: 0.8s; }
        .stat-card:nth-child(9) { animation-delay: 0.9s; }

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

            .welcome-text h2 {
                font-size: 20px;
            }

            table {
                display: block;
                overflow-x: auto;
            }

            .quick-actions-grid {
                grid-template-columns: 1fr;
                gap: 15px;
            }

            .section-header {
                flex-direction: column;
                gap: 15px;
                text-align: center;
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
                    <p>Municipal Complaint Management</p>
                </div>
            </div>

            <nav class="nav-links">
                <a href="index.php" class="active">Dashboard</a>
                <a href="complaints.php">Complaints</a>
                <a href="users.php">Users</a>
                <a href="staff.php">Staff</a>
                <a href="departments.php">Departments</a>
                <a href="logout.php" class="logout-btn">Logout</a>
            </nav>
        </div>
    </header>

    <div class="container">
        <!-- WELCOME PANEL -->
        <div class="welcome-panel">
            <div class="welcome-info">
                <div class="welcome-text">
                    <h2>Welcome back, <?php echo htmlspecialchars($_SESSION['user_name']); ?>!</h2>
                    <p><?php echo isSuperAdmin() ? 'Manage system-wide complaints and administrators' : 'Manage complaints and system settings'; ?></p>
                </div>
                <div class="admin-badge <?php echo isSuperAdmin() ? 'super-admin' : ''; ?>">
                    <i class="fas fa-shield-alt"></i>
                    <span><?php echo isSuperAdmin() ? 'Super Admin' : 'Admin'; ?></span>
                </div>
            </div>
        </div>

        <!-- STATS GRID -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-clipboard-list"></i></div>
                <h3><?php echo $total_complaints; ?></h3>
                <p>Total Complaints</p>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-clock"></i></div>
                <h3><?php echo $pending_complaints; ?></h3>
                <p>Pending Validation</p>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-check"></i></div>
                <h3><?php echo $validated_complaints; ?></h3>
                <p>Validated</p>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
                <h3><?php echo $resolved_complaints; ?></h3>
                <p>Resolved</p>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-users"></i></div>
                <h3><?php echo $total_users; ?></h3>
                <p>Total Users</p>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-user-tie"></i></div>
                <h3><?php echo $total_staff; ?></h3>
                <p>Total Staff</p>
            </div>
            <?php if ($total_reapplications > 0): ?>
            <div class="stat-card reapplications">
                <div class="stat-icon"><i class="fas fa-redo"></i></div>
                <h3><?php echo $total_reapplications; ?></h3>
                <p>Reapplications</p>
            </div>
            <?php endif; ?>
            <?php if (isSuperAdmin()): ?>
            <?php if ($escalated_complaints > 0): ?>
            <div class="stat-card escalated">
                <div class="stat-icon"><i class="fas fa-exclamation-triangle"></i></div>
                <h3><?php echo $escalated_complaints; ?></h3>
                <p>Escalated</p>
            </div>
            <?php endif; ?>
            <div class="stat-card admin-card">
                <div class="stat-icon"><i class="fas fa-crown"></i></div>
                <h3><?php echo $total_admins; ?></h3>
                <p>Total Admins</p>
            </div>
            <?php if ($today_resolved > 0): ?>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-star"></i></div>
                <h3><?php echo $today_resolved; ?></h3>
                <p>Resolved Today</p>
            </div>
            <?php endif; ?>
            <?php endif; ?>
        </div>

        <!-- COMPLAINTS TABLE -->
        <div class="complaints-section">
            <div class="section-header">
                <h2>Recent Complaints</h2>
                <?php if (isSuperAdmin() && $escalated_complaints > 0): ?>
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
                        <th>Priority</th>
                        <th>Date</th>
                        <?php if (isSuperAdmin()): ?><th>Actions</th><?php endif; ?>
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
                                <td>
                                    <span class="priority priority-<?php echo $complaint['priority']; ?>">
                                        <?php echo ucfirst($complaint['priority']); ?>
                                    </span>
                                </td>
                                <td><?php echo date('M d, Y', strtotime($complaint['created_at'])); ?></td>
                                <?php if (isSuperAdmin()): ?>
                                <td>
                                    <?php if ($complaint['status'] === 'escalated'): ?>
                                        <button class="action-link resolve" onclick="resolveEscalated(<?php echo $complaint['id']; ?>)">Resolve</button>
                                        <button class="action-link reassign" onclick="reassignEscalated(<?php echo $complaint['id']; ?>)">Reassign</button>
                                    <?php elseif (in_array($complaint['status'], ['pending', 'validated', 'assigned', 'in_progress'])): ?>
                                        <button class="action-link escalate" onclick="escalateComplaint(<?php echo $complaint['id']; ?>)">Escalate</button>
                                    <?php else: ?>
                                        <span style="color: #95a5a6; font-size: 0.8rem;">—</span>
                                    <?php endif; ?>
                                </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="<?php echo isSuperAdmin() ? '8' : '7'; ?>" class="no-complaints">
                                <i class="fas fa-inbox"></i>
                                <p>No complaints found</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- QUICK ACTIONS -->
        <div class="quick-actions">
            <div class="quick-actions-header">
                <h3>System Quick Actions</h3>
            </div>
            <div class="quick-actions-grid">
                <a href="complaints.php" class="action-btn btn-primary"><i class="fas fa-file-alt"></i> View All Complaints</a>
                <a href="users.php" class="action-btn btn-info"><i class="fas fa-users"></i> Manage Users</a>
                <a href="departments.php" class="action-btn btn-success"><i class="fas fa-building"></i> Departments</a>
                <a href="backup_database.php" class="action-btn btn-warning"><i class="fas fa-database"></i> Backup Database</a>
                <a href="export_data.php" class="action-btn btn-primary"><i class="fas fa-download"></i> Export Data</a>
                <a href="system_status.php" class="action-btn btn-secondary"><i class="fas fa-heartbeat"></i> System Status</a>
                <?php if (isSuperAdmin()): ?>
                <button class="action-btn btn-danger" onclick="showSystemResetModal()"><i class="fas fa-sync"></i> System Reset</button>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Super Admin System Reset Modal -->
    <?php if (isSuperAdmin()): ?>
    <div id="systemResetModal" class="modal">
        <div class="modal-content">
            <h3><i class="fas fa-exclamation-triangle"></i> System Reset Confirmation</h3>
            <p>This action will reset certain system settings. Please select what you want to reset:</p>
            
            <div class="modal-options">
                <label>
                    <input type="checkbox" id="resetCache" checked> Clear System Cache
                </label>
                <label>
                    <input type="checkbox" id="resetLogs"> Clear Old Logs (older than 30 days)
                </label>
                <label>
                    <input type="checkbox" id="resetTemp"> Clear Temporary Files
                </label>
            </div>
            
            <div class="modal-buttons">
                <button class="action-btn btn-danger" onclick="performSystemReset()">Proceed with Reset</button>
                <button class="action-btn btn-secondary" onclick="hideSystemResetModal()">Cancel</button>
            </div>
        </div>
    </div>
    <?php endif; ?>

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
                    <li><a href="login.php">Admin Login</a></li>
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
                        e.target.style.background = 'linear-gradient(135deg, #2a5298, #1e3c72)';
                    }, 1000);
                });
            }
        });

        // Modal functions
        function showSystemResetModal() {
            document.getElementById('systemResetModal').style.display = 'flex';
        }

        function hideSystemResetModal() {
            document.getElementById('systemResetModal').style.display = 'none';
        }

        function performSystemReset() {
            // Implement system reset functionality
            alert('System reset initiated. This would perform the selected operations.');
            hideSystemResetModal();
        }

        function resolveEscalated(complaintId) {
            alert('Resolve escalated complaint #' + complaintId);
        }

        function reassignEscalated(complaintId) {
            alert('Reassign escalated complaint #' + complaintId);
        }

        function escalateComplaint(complaintId) {
            alert('Escalate complaint #' + complaintId);
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('systemResetModal');
            if (event.target == modal) {
                modal.style.display = 'none';
            }
        }

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
