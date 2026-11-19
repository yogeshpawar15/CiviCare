<?php
// civicare/admin/complaint_actions.php
require_once '../config/database.php';
require_once '../config/functions.php';

// Check if user is logged in and is admin/staff
if (!isLoggedIn() || !in_array($_SESSION['user_role'], ['admin', 'staff'])) {
    header('Location: login.php');
    exit();
}

$conn = getDatabaseConnection();

$error = '';
$success = '';
$complaint = null;

function getDepartmentForUser($conn, $user_id) {
    $stmt = $conn->prepare("SELECT department_id FROM users WHERE id = ? LIMIT 1");
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    return $row && $row['department_id'] !== null ? (int)$row['department_id'] : null;
}

// Get complaint ID
$complaint_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$action_type = isset($_GET['action']) ? sanitizeInput($_GET['action']) : '';

// Handle bulk actions
if (isset($_GET['bulk_action']) && isset($_GET['ids'])) {
    $bulk_action = sanitizeInput($_GET['bulk_action']);
    $complaint_ids = array_map('intval', explode(',', $_GET['ids']));
    
    if (!empty($complaint_ids)) {
        switch ($bulk_action) {
            case 'bulk_validate':
                // Bulk validate complaints
                $placeholders = str_repeat('?,', count($complaint_ids) - 1) . '?';
                $sql = "UPDATE complaints SET status = 'validated', validated_by = ?, validation_date = NOW() WHERE id IN ($placeholders) AND status = 'pending'";
                $stmt = $conn->prepare($sql);
                $params = array_merge([$_SESSION['user_id']], $complaint_ids);
                $stmt->bind_param(str_repeat('i', count($params)), ...$params);
                
                if ($stmt->execute()) {
                    foreach ($complaint_ids as $cid) {
                        addComplaintUpdate($conn, $cid, $_SESSION['user_id'], 'validated', "Complaint validated in bulk");
                    }
                    $_SESSION['success'] = count($complaint_ids) . " complaints validated successfully.";
                }
                $stmt->close();
                break;
                
            case 'bulk_reject':
                $rejection_reason = "Rejected in bulk action";
                $placeholders = str_repeat('?,', count($complaint_ids) - 1) . '?';
                $sql = "UPDATE complaints SET status = 'rejected', rejection_reason = ? WHERE id IN ($placeholders) AND status = 'pending'";
                $stmt = $conn->prepare($sql);
                $params = array_merge([$rejection_reason], $complaint_ids);
                $stmt->bind_param(str_repeat('s', count($params)), ...$params);
                
                if ($stmt->execute()) {
                    foreach ($complaint_ids as $cid) {
                        addComplaintUpdate($conn, $cid, $_SESSION['user_id'], 'rejected', "Complaint rejected in bulk");
                    }
                    $_SESSION['success'] = count($complaint_ids) . " complaints rejected successfully.";
                }
                $stmt->close();
                break;
        }
        header('Location: complaints.php');
        exit();
    }
}

if ($complaint_id > 0) {
    // Get complaint details
    $sql = "SELECT c.*, u.full_name as citizen_name, u.email as citizen_email, u.phone as citizen_phone,
                   d.name as department_name, staff.full_name as assigned_staff_name,
                   admin.full_name as validated_by_name, resolver.full_name as resolved_by_name
            FROM complaints c
            JOIN users u ON c.user_id = u.id
            JOIN departments d ON c.department_id = d.id
            LEFT JOIN users staff ON c.assigned_to = staff.id
            LEFT JOIN users admin ON c.validated_by = admin.id
            LEFT JOIN users resolver ON c.resolved_by = resolver.id
            WHERE c.id = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $complaint_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $complaint = $result->fetch_assoc();
        
        // Check if staff user is assigned to this department
        if ($_SESSION['user_role'] === 'staff') {
            $staff_dept = getDepartmentForUser($conn, $_SESSION['user_id']);
            if ($staff_dept != $complaint['department_id'] && $complaint['assigned_to'] != $_SESSION['user_id']) {
                $error = "You are not authorized to manage this complaint.";
                $complaint = null;
            }
        }
    } else {
        $error = "Complaint not found.";
    }
    $stmt->close();
} else {
    $error = "Invalid complaint ID.";
}

// Get department staff for assignment
$staff_members = [];
if ($complaint) {
    $sql = "SELECT id, full_name, username FROM users 
            WHERE role = 'staff' AND department_id = ? AND is_active = TRUE";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $complaint['department_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $staff_members[] = $row;
    }
    $stmt->close();
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $complaint) {
    $action = sanitizeInput($_POST['action']);
    $remarks = isset($_POST['remarks']) ? sanitizeInput($_POST['remarks']) : '';
    
    switch ($action) {
        case 'validate':
            if (true) {
                // Update complaint - no priority needed
                $sql = "UPDATE complaints SET status = 'validated', validated_by = ?, validation_date = NOW() WHERE id = ? AND status = 'pending'";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ii", $_SESSION['user_id'], $complaint_id);
                
                if ($stmt->execute()) {
                    // Add update history
                    addComplaintUpdate($conn, $complaint_id, $_SESSION['user_id'], 'validated', 
                        "Complaint validated. " . $remarks);
                    
                    // Notify citizen
                    createNotification($conn, $complaint['user_id'], 'Complaint Validated', 
                        "Your complaint #{$complaint['tracking_id']} has been validated.", 
                        $complaint_id);
                    
$success = "Complaint validated successfully.";
                } else {
                    $error = "Failed to validate complaint. It may already be processed.";
                }
                $stmt->close();
            }
            break;
            
        case 'reject':
            $rejection_reason = sanitizeInput($_POST['rejection_reason']);
            
            if (empty($rejection_reason)) {
                $error = "Please provide rejection reason.";
            } else {
                $sql = "UPDATE complaints SET status = 'rejected', rejection_reason = ?, rejection_date = NOW() WHERE id = ? AND status = 'pending'";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("si", $rejection_reason, $complaint_id);
                
                if ($stmt->execute()) {
                    addComplaintUpdate($conn, $complaint_id, $_SESSION['user_id'], 'rejected', 
                        "Complaint rejected. Reason: " . $rejection_reason);
                    
                    createNotification($conn, $complaint['user_id'], 'Complaint Rejected', 
                        "Your complaint #{$complaint['tracking_id']} has been rejected. Reason: " . $rejection_reason, 
                        $complaint_id);
                    
                    sendComplaintStatusEmail($conn, $complaint['user_id'], $complaint_id, 'rejected');
                    
                    $success = "Complaint rejected successfully.";
                } else {
                    $error = "Failed to reject complaint.";
                }
                $stmt->close();
            }
            break;
            
        case 'assign':
            $assigned_to = (int)$_POST['assigned_to'];
            
            if (empty($assigned_to)) {
                $error = "Please select staff member.";
            } else {
                $sql = "UPDATE complaints SET status = 'assigned', assigned_to = ?, assignment_date = NOW(), assigned_by = ? WHERE id = ? AND status = 'validated'";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("iii", $assigned_to, $_SESSION['user_id'], $complaint_id);
                
                if ($stmt->execute()) {
                    addComplaintUpdate($conn, $complaint_id, $_SESSION['user_id'], 'assigned', 
                        "Complaint assigned to staff. " . $remarks);
                    
                    // Notify citizen
                    createNotification($conn, $complaint['user_id'], 'Complaint Assigned', 
                        "Your complaint #{$complaint['tracking_id']} has been assigned to department staff.", 
                        $complaint_id);
                    
                    // Notify assigned staff
                    createNotification($conn, $assigned_to, 'New Complaint Assigned', 
                        "A new complaint #{$complaint['tracking_id']} has been assigned to you.", 
                        $complaint_id);
                    
                    sendComplaintStatusEmail($conn, $complaint['user_id'], $complaint_id, 'assigned');
                    
                    $success = "Complaint assigned successfully.";
                } else {
                    $error = "Failed to assign complaint.";
                }
                $stmt->close();
            }
            break;
            
        case 'in_progress':
            $sql = "UPDATE complaints SET status = 'in_progress' WHERE id = ? AND status IN ('assigned', 'escalated')";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $complaint_id);
            
            if ($stmt->execute()) {
                addComplaintUpdate($conn, $complaint_id, $_SESSION['user_id'], 'in_progress', 
                    "Work started on complaint. " . $remarks);
                
                createNotification($conn, $complaint['user_id'], 'Complaint In Progress', 
                    "Work has started on your complaint #{$complaint['tracking_id']}.", 
                    $complaint_id);
                
                sendComplaintStatusEmail($conn, $complaint['user_id'], $complaint_id, 'in_progress');
                
                $success = "Complaint marked as in progress.";
                header('Location: complaints.php');
                exit();
            } else {
                $error = "Failed to update complaint status.";
            }
            $stmt->close();
            break;
            
        case 'escalate':
            $escalation_reason = sanitizeInput($_POST['escalation_reason']);
            
            if (empty($escalation_reason)) {
                $error = "Please provide escalation reason.";
            } else {
                $sql = "UPDATE complaints SET status = 'escalated', escalation_reason = ?, escalated_by = ?, escalation_date = NOW() WHERE id = ? AND status IN ('assigned', 'in_progress')";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("sii", $escalation_reason, $_SESSION['user_id'], $complaint_id);
                
                if ($stmt->execute()) {
                    addComplaintUpdate($conn, $complaint_id, $_SESSION['user_id'], 'escalated', 
                        "Complaint escalated. Reason: " . $escalation_reason);
                    
                    // Notify higher authorities (admins)
                    $admin_sql = "SELECT id FROM users WHERE role = 'admin' AND is_active = TRUE";
                    $admin_result = $conn->query($admin_sql);
                    
                    while ($admin = $admin_result->fetch_assoc()) {
                        createNotification($conn, $admin['id'], 'Complaint Escalated', 
                            "Complaint #{$complaint['tracking_id']} has been escalated. Reason: " . $escalation_reason, 
                            $complaint_id);
                    }
                    
                    createNotification($conn, $complaint['user_id'], 'Complaint Escalated', 
                        "Your complaint #{$complaint['tracking_id']} has been escalated to higher authorities for review.", 
                        $complaint_id);
                    
                    sendComplaintStatusEmail($conn, $complaint['user_id'], $complaint_id, 'escalated');
                    
                    $success = "Complaint escalated successfully.";
                } else {
                    $error = "Failed to escalate complaint.";
                }
                $stmt->close();
            }
            break;
            
        case 'resolve':
            $resolution_notes = sanitizeInput($_POST['resolution_notes']);

            if (empty($resolution_notes)) {
                $error = "Please provide resolution notes.";
            } else {
                $sql = "UPDATE complaints SET status = 'resolved', resolved_by = ?, resolution_date = NOW(), resolution_notes = ? WHERE id = ? AND status IN ('in_progress', 'escalated')";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("isi", $_SESSION['user_id'], $resolution_notes, $complaint_id);

                if ($stmt->execute()) {
                    $status_text = $complaint['status'] === 'escalated' ? 'Escalated complaint resolved' : 'Complaint resolved';
                    addComplaintUpdate($conn, $complaint_id, $_SESSION['user_id'], 'resolved',
                        "$status_text. Notes: " . $resolution_notes);

                    createNotification($conn, $complaint['user_id'], 'Complaint Resolved',
                        "Your complaint #{$complaint['tracking_id']} has been resolved. Please provide your feedback.",
                        $complaint_id);

                    sendComplaintStatusEmail($conn, $complaint['user_id'], $complaint_id, 'resolved');

                    $success = "Complaint marked as resolved.";
                    header('Location: complaints.php');
                    exit();
                } else {
                    $error = "Failed to mark complaint as resolved.";
                }
                $stmt->close();
            }
            break;
            
        case 'reassign':
            $assigned_to = (int)$_POST['assigned_to'];
            $reassign_reason = sanitizeInput($_POST['reassign_reason']);

            if (empty($assigned_to)) {
                $error = "Please select staff member to reassign to.";
            } else {
                $sql = "UPDATE complaints SET assigned_to = ?, status = 'assigned', assignment_date = NOW(), assigned_by = ? WHERE id = ? AND status = 'escalated'";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("iii", $assigned_to, $_SESSION['user_id'], $complaint_id);

                if ($stmt->execute()) {
                    addComplaintUpdate($conn, $complaint_id, $_SESSION['user_id'], 'assigned',
                        "Complaint reassigned to staff. Reason: " . $reassign_reason);

                    // Notify citizen
                    createNotification($conn, $complaint['user_id'], 'Complaint Reassigned',
                        "Your complaint #{$complaint['tracking_id']} has been reassigned to department staff.",
                        $complaint_id);

                    // Notify assigned staff
                    createNotification($conn, $assigned_to, 'New Complaint Assigned',
                        "A new complaint #{$complaint['tracking_id']} has been assigned to you.",
                        $complaint_id);

                    sendComplaintStatusEmail($conn, $complaint['user_id'], $complaint_id, 'assigned');

                    $success = "Complaint reassigned successfully.";
                    header('Location: complaints.php');
                    exit();
                } else {
                    $error = "Failed to reassign complaint.";
                }
                $stmt->close();
            }
            break;

        case 'close':
            $closing_remarks = sanitizeInput($_POST['closing_remarks']);
            $sql = "UPDATE complaints SET status = 'closed', closing_remarks = ? WHERE id = ? AND status = 'resolved'";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("si", $closing_remarks, $complaint_id);

            if ($stmt->execute()) {
                addComplaintUpdate($conn, $complaint_id, $_SESSION['user_id'], 'closed',
                    "Complaint closed. " . $closing_remarks);

                createNotification($conn, $complaint['user_id'], 'Complaint Closed',
                    "Your complaint #{$complaint['tracking_id']} has been closed.",
                    $complaint_id);

                sendComplaintStatusEmail($conn, $complaint['user_id'], $complaint_id, 'closed');

                $success = "Complaint closed successfully.";
            } else {
                $error = "Failed to close complaint.";
            }
            $stmt->close();
            break;
    }
    
    // Refresh complaint data
    if ($success && $complaint_id > 0) {
        $sql = "SELECT c.*, u.full_name as citizen_name, u.email as citizen_email, u.phone as citizen_phone,
                       d.name as department_name, staff.full_name as assigned_staff_name,
                       admin.full_name as validated_by_name, resolver.full_name as resolved_by_name
                FROM complaints c
                JOIN users u ON c.user_id = u.id
                JOIN departments d ON c.department_id = d.id
                LEFT JOIN users staff ON c.assigned_to = staff.id
                LEFT JOIN users admin ON c.validated_by = admin.id
                LEFT JOIN users resolver ON c.resolved_by = resolver.id
                WHERE c.id = ?";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $complaint_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $complaint = $result->fetch_assoc();
        }
        $stmt->close();
    }
}

// Get linked complaints for reapplication chain - must be done before closing connection
$linked_complaints = [];
if ($complaint) {
    $linked_complaints = getLinkedComplaints($conn, $complaint_id);
}

// Get complaint timeline/updates - must be done before closing connection
$updates = [];
$conn2 = getDatabaseConnection();
$sql_updates = "SELECT cu.*, u.full_name, u.role 
               FROM complaint_updates cu 
               JOIN users u ON cu.user_id = u.id 
               WHERE cu.complaint_id = ? 
               ORDER BY cu.created_at DESC";
$stmt_updates = $conn2->prepare($sql_updates);
$stmt_updates->bind_param("i", $complaint_id);
$stmt_updates->execute();
$result_updates = $stmt_updates->get_result();
while ($row = $result_updates->fetch_assoc()) {
    $updates[] = $row;
}
$stmt_updates->close();
$conn2->close();

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Complaint - Civicare Admin</title>
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
        }
        
        .page-header h1 {
            color: #2c3e50;
            margin-bottom: 0.5rem;
        }
        
        .page-header p {
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
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-info {
            background: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }
        
        select, input, textarea {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #e0e0e0;
            border-radius: 5px;
            font-size: 0.9rem;
            transition: border-color 0.3s;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        select:focus, input:focus, textarea:focus {
            outline: none;
            border-color: #3498db;
        }
        
        textarea {
            resize: vertical;
            min-height: 100px;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #333;
            font-size: 0.9rem;
        }
        
        .form-group label.required::after {
            content: " *";
            color: #e74c3c;
        }
        
        .btn-submit {
            background: #3498db;
            color: white;
            padding: 0.75rem 2rem;
            border: none;
            border-radius: 5px;
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.3s;
        }
        
        .btn-submit:hover {
            background: #2980b9;
        }
        
        .btn-submit.btn-primary {
            background: #27ae60;
        }
        
        .btn-submit.btn-primary:hover {
            background: #219653;
        }
        
        .btn-submit.btn-danger {
            background: #e74c3c;
        }
        
        .btn-submit.btn-danger:hover {
            background: #c0392b;
        }
        
        .btn-submit.btn-warning {
            background: #f39c12;
        }
        
        .btn-submit.btn-warning:hover {
            background: #d68910;
        }
        
        .btn-submit:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
        
        .complaint-details {
            background: white;
            padding: 2rem;
            border-radius: 8px;
            margin-bottom: 2rem;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        
        .details-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .detail-item {
            padding: 1rem;
            background: #f8f9fa;
            border-radius: 5px;
        }
        
        .detail-label {
            font-weight: 600;
            color: #2c3e50;
            font-size: 0.85rem;
            text-transform: uppercase;
            margin-bottom: 0.5rem;
        }
        
        .detail-value {
            color: #333;
            font-size: 0.95rem;
        }
        
        .status-badge {
            display: inline-block;
            padding: 0.5rem 1rem;
            border-radius: 5px;
            font-weight: 600;
            font-size: 0.85rem;
        }
        
        .status-pending { background: #ffc107; color: #333; }
        .status-validated { background: #17a2b8; color: white; }
        .status-rejected { background: #e74c3c; color: white; }
        .status-assigned { background: #3498db; color: white; }
        .status-in_progress { background: #f39c12; color: white; }
        .status-resolved { background: #27ae60; color: white; }
        .status-escalated { background: #9b59b6; color: white; }
        .status-closed { background: #95a5a6; color: white; }
        

        
        .timeline {
            background: white;
            padding: 2rem;
            border-radius: 8px;
            margin-bottom: 2rem;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        
        .timeline h3 {
            color: #2c3e50;
            margin-bottom: 1.5rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid #3498db;
        }
        
        .timeline-item {
            padding: 1rem;
            border-left: 3px solid #3498db;
            margin-bottom: 1rem;
            background: #f8f9fa;
        }
        
        .timeline-date {
            font-size: 0.85rem;
            color: #666;
            margin-bottom: 0.25rem;
        }
        
        .timeline-action {
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 0.25rem;
        }
        
        .timeline-remarks {
            color: #666;
            font-size: 0.9rem;
        }
        
        .action-section {
            background: white;
            padding: 2rem;
            border-radius: 8px;
            margin-bottom: 2rem;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        
        .action-section h3 {
            color: #2c3e50;
            margin-bottom: 1.5rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid #3498db;
        }
        
        .action-tabs {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 2rem;
            flex-wrap: wrap;
        }
        
        .action-tab {
            padding: 0.75rem 1.5rem;
            border: 2px solid #e0e0e0;
            background: white;
            color: #333;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .action-tab:hover {
            border-color: #3498db;
            color: #3498db;
        }
        
        .action-tab.active {
            background: #3498db;
            color: white;
            border-color: #3498db;
        }
        
        .action-form {
            display: none;
            padding: 1.5rem;
            background: #f8f9fa;
            border-radius: 5px;
            border-left: 4px solid #3498db;
        }
        
        .action-form.active {
            display: block;
        }
        
        .action-form .required::after {
            content: " *";
            color: #e74c3c;
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
                    <a href="complaints.php">All Complaints</a>
                    <a href="register.php">Register Staff</a>
                    <a href="users.php">Manage Users</a>
                    <a href="../logout.php" class="btn">Logout</a>
                </div>
            </nav>
        </div>
    </div>

    <div class="container">
        <div class="page-header">
            <h1>Manage Complaint</h1>
            <p>Review and take action on citizen complaints</p>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-error">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success">
                <?php echo htmlspecialchars($success); ?>
                <br><a href="complaint_actions.php?id=<?php echo $complaint_id; ?>" style="color: #155724; text-decoration: underline;">Refresh page to see updates</a>
            </div>
        <?php endif; ?>
        
        <?php if ($complaint): ?>
            <div class="complaint-details">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; padding-bottom: 1rem; border-bottom: 2px solid #3498db;">
                    <div>
                        <h2 style="color: #2c3e50;">Complaint #<?php echo htmlspecialchars($complaint['tracking_id']); ?></h2>
                        <p style="color: #666;">Filed by <?php echo htmlspecialchars($complaint['citizen_name']); ?></p>
                    </div>
                    <div style="text-align: right;">
                        <span class="status-badge status-<?php echo $complaint['status']; ?>">
                            <?php echo getStatusText($complaint['status']); ?>
                        </span>
                    </div>
                </div>
                
                <div class="details-grid">
                    <div class="detail-item">
                        <div class="detail-label">Title</div>
                        <div class="detail-value"><?php echo htmlspecialchars($complaint['title']); ?></div>
                    </div>
                    
                    <div class="detail-item">
                        <div class="detail-label">Department</div>
                        <div class="detail-value"><?php echo htmlspecialchars($complaint['department_name']); ?></div>
                    </div>
                    
                    <div class="detail-item">
                        <div class="detail-label">Filed By</div>
                        <div class="detail-value"><?php echo htmlspecialchars($complaint['citizen_name']); ?></div>
                        <div class="detail-label" style="margin-top: 0.5rem;">Email</div>
                        <div class="detail-value"><?php echo htmlspecialchars($complaint['citizen_email']); ?></div>
                        <?php if ($complaint['citizen_phone']): ?>
                            <div class="detail-label" style="margin-top: 0.5rem;">Phone</div>
                            <div class="detail-value"><?php echo htmlspecialchars($complaint['citizen_phone']); ?></div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="detail-item">
                        <div class="detail-label">Date Filed</div>
                        <div class="detail-value"><?php echo date('F d, Y h:i A', strtotime($complaint['created_at'])); ?></div>
                    </div>
                    
                    <div class="detail-item">
                        <div class="detail-label">Location</div>
                        <div class="detail-value"><?php echo htmlspecialchars($complaint['location']); ?></div>
                        <?php if ($complaint['landmark']): ?>
                            <div class="detail-label" style="margin-top: 0.5rem;">Landmark</div>
                            <div class="detail-value"><?php echo htmlspecialchars($complaint['landmark']); ?></div>
                        <?php endif; ?>
                    </div>
                    
                    <?php if ($complaint['assigned_staff_name']): ?>
                        <div class="detail-item">
                            <div class="detail-label">Assigned To</div>
                            <div class="detail-value"><?php echo htmlspecialchars($complaint['assigned_staff_name']); ?></div>
                            <?php if ($complaint['assignment_date']): ?>
                                <div class="detail-label" style="margin-top: 0.5rem;">Assigned Date</div>
                                <div class="detail-value"><?php echo date('F d, Y', strtotime($complaint['assignment_date'])); ?></div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($complaint['validated_by_name']): ?>
                        <div class="detail-item">
                            <div class="detail-label">Validated By</div>
                            <div class="detail-value"><?php echo htmlspecialchars($complaint['validated_by_name']); ?></div>
                            <?php if ($complaint['validation_date']): ?>
                                <div class="detail-label" style="margin-top: 0.5rem;">Validation Date</div>
                                <div class="detail-value"><?php echo date('F d, Y', strtotime($complaint['validation_date'])); ?></div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($complaint['resolved_by_name']): ?>
                        <div class="detail-item">
                            <div class="detail-label">Resolved By</div>
                            <div class="detail-value"><?php echo htmlspecialchars($complaint['resolved_by_name']); ?></div>
                            <?php if ($complaint['resolution_date']): ?>
                                <div class="detail-label" style="margin-top: 0.5rem;">Resolution Date</div>
                                <div class="detail-value"><?php echo date('F d, Y', strtotime($complaint['resolution_date'])); ?></div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="detail-item" style="grid-column: 1 / -1;">
                    <div class="detail-label">Description</div>
                    <div class="detail-value"><?php echo nl2br(htmlspecialchars($complaint['description'])); ?></div>
                </div>
                
                <?php if ($complaint['rejection_reason']): ?>
                    <div class="detail-item" style="grid-column: 1 / -1; background: #f8d7da; border-left: 4px solid #e74c3c;">
                        <div class="detail-label">Rejection Reason</div>
                        <div class="detail-value"><?php echo nl2br(htmlspecialchars($complaint['rejection_reason'])); ?></div>
                    </div>
                <?php endif; ?>
                
                <?php if ($complaint['escalation_reason']): ?>
                    <div class="detail-item" style="grid-column: 1 / -1; background: #fff3cd; border-left: 4px solid #ffc107;">
                        <div class="detail-label">Escalation Reason</div>
                        <div class="detail-value"><?php echo nl2br(htmlspecialchars($complaint['escalation_reason'])); ?></div>
                    </div>
                <?php endif; ?>
                
                <?php if ($complaint['resolution_notes']): ?>
                    <div class="detail-item" style="grid-column: 1 / -1; background: #d4edda; border-left: 4px solid #28a745;">
                        <div class="detail-label">Resolution Notes</div>
                        <div class="detail-value"><?php echo nl2br(htmlspecialchars($complaint['resolution_notes'])); ?></div>
                    </div>
                <?php endif; ?>
                
                <?php 
                // Display linked complaints for reapplication chain
                if (count($linked_complaints) > 1): 
                ?>
                <div class="detail-item" style="grid-column: 1 / -1; background: #cfe2ff; border-left: 4px solid #0d6efd;">
                    <div class="detail-label">Reapplication Chain</div>
                    <div class="detail-value">
                        <table style="width: 100%; border-collapse: collapse; font-size: 0.9rem;">
                            <thead style="background: #0d6efd; color: white;">
                                <tr>
                                    <th style="padding: 0.5rem; text-align: left; border: 1px solid #0d6efd;">Type</th>
                                    <th style="padding: 0.5rem; text-align: left; border: 1px solid #0d6efd;">Tracking ID</th>
                                    <th style="padding: 0.5rem; text-align: left; border: 1px solid #0d6efd;">Title</th>
                                    <th style="padding: 0.5rem; text-align: left; border: 1px solid #0d6efd;">Status</th>
                                    <th style="padding: 0.5rem; text-align: left; border: 1px solid #0d6efd;">Rating</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($linked_complaints as $idx => $linked): ?>
                                <tr style="background: <?php echo $linked['id'] == $complaint_id ? '#e7f3ff' : 'white'; ?>; border-bottom: 1px solid #0d6efd;">
                                    <td style="padding: 0.5rem; border-right: 1px solid #0d6efd;">
                                        <span style="background: <?php echo $linked['parent_complaint_id'] ? '#ff9800' : '#2196F3'; ?>; color: white; padding: 0.25rem 0.5rem; border-radius: 3px; font-size: 0.85rem;">
                                            <?php echo $linked['parent_complaint_id'] ? 'Reapplication' : 'Original'; ?>
                                        </span>
                                    </td>
                                    <td style="padding: 0.5rem; border-right: 1px solid #0d6efd;">
                                        <?php if ($linked['id'] == $complaint_id): ?>
                                            <strong><?php echo htmlspecialchars($linked['tracking_id']); ?></strong>
                                        <?php else: ?>
                                            <a href="complaint_actions.php?id=<?php echo (int)$linked['id']; ?>" style="color: #0d6efd; text-decoration: none;">
                                                <?php echo htmlspecialchars($linked['tracking_id']); ?>
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                    <td style="padding: 0.5rem; border-right: 1px solid #0d6efd; max-width: 200px; overflow: hidden; text-overflow: ellipsis;">
                                        <?php echo htmlspecialchars($linked['title']); ?>
                                    </td>
                                    <td style="padding: 0.5rem; border-right: 1px solid #0d6efd;">
                                        <span style="background: <?php 
                                            echo $linked['status'] === 'closed' ? '#6c757d' : 
                                                 ($linked['status'] === 'resolved' ? '#28a745' :
                                                 ($linked['status'] === 'in_progress' ? '#007bff' : '#ffc107'));
                                        ?>; color: white; padding: 0.25rem 0.5rem; border-radius: 3px; font-size: 0.85rem;">
                                            <?php echo ucfirst(str_replace('_', ' ', $linked['status'])); ?>
                                        </span>
                                    </td>
                                    <td style="padding: 0.5rem;">
                                        <?php if ($linked['citizen_rating']): ?>
                                            <?php echo str_repeat('★', (int)$linked['citizen_rating']) . str_repeat('☆', 5 - (int)$linked['citizen_rating']); ?>
                                            <span style="color: #666;">(<?php echo (int)$linked['citizen_rating']; ?>/5)</span>
                                        <?php else: ?>
                                            <span style="color: #999;">Not rated</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            
            <?php if (!empty($updates)): ?>
            <div class="timeline">
                <h3>Complaint Timeline</h3>
                <?php foreach ($updates as $update): ?>
                    <div class="timeline-item">
                        <div class="timeline-date">
                            <?php echo date('M d, Y h:i A', strtotime($update['created_at'])); ?>
                        </div>
                        <div class="timeline-action">
                            <?php echo getStatusText($update['new_status']); ?> 
                            <span style="color: #666; font-weight: normal;">by <?php echo htmlspecialchars($update['full_name']); ?> (<?php echo ucfirst($update['role']); ?>)</span>
                        </div>
                        <?php if ($update['remarks']): ?>
                            <div class="timeline-remarks">
                                <?php echo nl2br(htmlspecialchars($update['remarks'])); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            
            <div class="action-section">
                <h3 style="color: #2c3e50; margin-bottom: 1rem;">Available Actions</h3>
                
                <div class="action-tabs">
                    <?php if ($complaint['status'] === 'pending'): ?>
                        <?php if (in_array($_SESSION['user_role'], ['admin'])): ?>
                            <button type="button" class="action-tab <?php echo $action_type !== 'reject' ? 'active' : ''; ?>" data-target="validateForm">Validate</button>
                            <button type="button" class="action-tab <?php echo $action_type === 'reject' ? 'active' : ''; ?>" data-target="rejectForm">Reject</button>
                        <?php endif; ?>
                    <?php elseif ($complaint['status'] === 'validated'): ?>
                        <?php if (in_array($_SESSION['user_role'], ['admin'])): ?>
                            <button type="button" class="action-tab active" data-target="assignForm">Assign to Staff</button>
                            <button type="button" class="action-tab" data-target="escalateForm">Escalate</button>
                        <?php endif; ?>
                    <?php elseif ($complaint['status'] === 'assigned'): ?>
                        <?php if ($_SESSION['user_role'] === 'staff' && $complaint['assigned_to'] == $_SESSION['user_id']): ?>
                            <button type="button" class="action-tab active" data-target="inProgressForm">Mark In Progress</button>
                        <?php endif; ?>
                        <?php if (in_array($_SESSION['user_role'], ['admin'])): ?>
                            <button type="button" class="action-tab <?php echo $_SESSION['user_role'] === 'staff' ? 'active' : ''; ?>" data-target="escalateForm">Escalate</button>
                            <?php if ($_SESSION['user_role'] === 'admin'): ?>
                                <button type="button" class="action-tab" data-target="reassignForm">Reassign</button>
                            <?php endif; ?>
                        <?php endif; ?>
                    <?php elseif ($complaint['status'] === 'in_progress'): ?>
                        <button ... data-target="resolveForm">Resolve</button>
                        <?php if ($_SESSION['user_role'] === 'staff' && $complaint['assigned_to'] == $_SESSION['user_id']): ?>
                            <button type="button" class="action-tab active" data-target="resolveForm">Mark Resolved</button>
                        <?php endif; ?>
                        <?php if (in_array($_SESSION['user_role'], ['admin'])): ?>
                            <button type="button" class="action-tab <?php echo $_SESSION['user_role'] === 'staff' ? 'active' : ''; ?>" data-target="escalateForm">Escalate</button>
                            <?php if ($_SESSION['user_role'] === 'admin'): ?>
                                <button type="button" class="action-tab" data-target="resolveForm">Resolve</button>
                            <?php endif; ?>
                        <?php endif; ?>
                    <?php elseif ($complaint['status'] === 'escalated'): ?>
                        <?php if ($_SESSION['user_role'] === 'admin'): ?>
                            <button type="button" class="action-tab active" data-target="reassignForm">Reassign</button>
                            <button type="button" class="action-tab" data-target="inProgressForm">Mark In Progress</button>
                            <button type="button" class="action-tab" data-target="resolveForm">Mark Resolved</button>
                        <?php endif; ?>
                    <?php elseif ($complaint['status'] === 'resolved'): ?>
                        <?php if (in_array($_SESSION['user_role'], ['admin'])): ?>
                            <button type="button" class="action-tab active" data-target="closeForm">Close Complaint</button>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
                
                <!-- Validate Form -->
                <?php if ($complaint['status'] === 'pending'): ?>
                    <form method="POST" class="action-form <?php echo $action_type !== 'reject' ? 'active' : ''; ?>" id="validateForm">
                        <input type="hidden" name="action" value="validate">
                        <div class="form-group">
                            <label for="remarks">Remarks (Optional)</label>
                            <textarea id="remarks" name="remarks" placeholder="Add any additional notes..."></textarea>
                        </div>
                        <button type="submit" class="btn-submit">Validate Complaint</button>
                    </form>
                    
                    <!-- Reject Form -->
                    <form method="POST" class="action-form <?php echo $action_type === 'reject' ? 'active' : ''; ?>" id="rejectForm">
                        <input type="hidden" name="action" value="reject">
                        <div class="form-group">
                            <label for="rejection_reason" class="required">Rejection Reason</label>
                            <textarea id="rejection_reason" name="rejection_reason" required placeholder="Explain why this complaint is being rejected..."></textarea>
                        </div>
                        <button type="submit" class="btn-submit btn-danger">Reject Complaint</button>
                    </form>
                <?php endif; ?>
                
                <!-- Assign Form -->
                <?php if ($complaint['status'] === 'validated' && !empty($staff_members)): ?>
                    <form method="POST" class="action-form active" id="assignForm">
                        <input type="hidden" name="action" value="assign">
                        <div class="form-group">
                            <label for="assigned_to" class="required">Assign to Staff</label>
                            <select id="assigned_to" name="assigned_to" required>
                                <option value="">-- Select Staff Member --</option>
                                <?php foreach ($staff_members as $staff): ?>
                                    <option value="<?php echo $staff['id']; ?>">
                                        <?php echo htmlspecialchars($staff['full_name']); ?> (<?php echo htmlspecialchars($staff['username']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="remarks">Remarks (Optional)</label>
                            <textarea id="remarks" name="remarks" placeholder="Add instructions for the staff member..."></textarea>
                        </div>
                        <button type="submit" class="btn-submit">Assign to Staff</button>
                    </form>
                <?php endif; ?>
                
                <!-- In Progress Form -->
                <?php if ($complaint['status'] === 'assigned' && $_SESSION['user_role'] === 'staff'): ?>
                    <form method="POST" class="action-form active" id="inProgressForm">
                        <input type="hidden" name="action" value="in_progress">
                        <div class="form-group">
                            <label for="remarks">Update Remarks</label>
                            <textarea id="remarks" name="remarks" placeholder="Add progress update..."></textarea>
                        </div>
                        <button type="submit" class="btn-submit">Mark In Progress</button>
                    </form>
                <?php endif; ?>
                
                <!-- Resolve Form -->
                <?php if ($complaint['status'] === 'in_progress' && in_array($_SESSION['user_role'], ['admin', 'staff'])): ?>
                    <form method="POST" class="action-form active" id="resolveForm">
                        <input type="hidden" name="action" value="resolve">
                        <div class="form-group">
                            <label for="resolution_notes" class="required">Resolution Notes</label>
                            <textarea id="resolution_notes" name="resolution_notes" required placeholder="Describe how the issue was resolved..."></textarea>
                        </div>
                        <button type="submit" class="btn-submit btn-primary">Mark as Resolved</button>
                    </form>
                <?php endif; ?>
                
                <!-- Escalate Form -->
                <form method="POST" class="action-form" id="escalateForm">
                    <input type="hidden" name="action" value="escalate">
                    <div class="form-group">
                        <label for="escalation_reason" class="required">Escalation Reason</label>
                        <textarea id="escalation_reason" name="escalation_reason" required placeholder="Explain why this needs to be escalated to higher authorities..."></textarea>
                    </div>
                    <button type="submit" class="btn-submit btn-warning">Escalate Complaint</button>
                </form>

                <!-- Reassign Form -->
                <?php if ($complaint['status'] === 'escalated' && $_SESSION['user_role'] === 'admin' && !empty($staff_members)): ?>
                    <form method="POST" class="action-form" id="reassignForm">
                        <input type="hidden" name="action" value="reassign">
                        <div class="form-group">
                            <label for="assigned_to" class="required">Reassign to Staff</label>
                            <select id="assigned_to" name="assigned_to" required>
                                <option value="">-- Select Staff Member --</option>
                                <?php foreach ($staff_members as $staff): ?>
                                    <option value="<?php echo $staff['id']; ?>">
                                        <?php echo htmlspecialchars($staff['full_name']); ?> (<?php echo htmlspecialchars($staff['username']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="reassign_reason">Reassignment Reason</label>
                            <textarea id="reassign_reason" name="reassign_reason" placeholder="Explain why this complaint is being reassigned..."></textarea>
                        </div>
                        <button type="submit" class="btn-submit">Reassign Complaint</button>
                    </form>
                <?php endif; ?>

                <!-- In Progress Form for Escalated Complaints -->
                <?php if ($complaint['status'] === 'escalated' && $_SESSION['user_role'] === 'admin'): ?>
                    <form method="POST" class="action-form" id="inProgressForm">
                        <input type="hidden" name="action" value="in_progress">
                        <div class="form-group">
                            <label for="remarks">Update Remarks</label>
                            <textarea id="remarks" name="remarks" placeholder="Add progress update..."></textarea>
                        </div>
                        <button type="submit" class="btn-submit">Mark In Progress</button>
                    </form>
                <?php endif; ?>

                <!-- Resolve Form for Escalated Complaints -->
                <?php if ($complaint['status'] === 'escalated' && $_SESSION['user_role'] === 'admin'): ?>
                    <form method="POST" class="action-form" id="resolveForm">
                        <input type="hidden" name="action" value="resolve">
                        <div class="form-group">
                            <label for="resolution_notes" class="required">Resolution Notes</label>
                            <textarea id="resolution_notes" name="resolution_notes" required placeholder="Describe how the issue was resolved..."></textarea>
                        </div>
                        <button type="submit" class="btn-submit btn-primary">Mark as Resolved</button>
                    </form>
                <?php endif; ?>

                <!-- Close Form -->
                <?php if ($complaint['status'] === 'resolved' && in_array($_SESSION['user_role'], ['admin'])): ?>
                    <form method="POST" class="action-form active" id="closeForm">
                        <input type="hidden" name="action" value="close">
                        <div class="form-group">
                            <label for="closing_remarks">Closing Remarks</label>
                            <textarea id="closing_remarks" name="closing_remarks" placeholder="Add final remarks...">Complaint resolved successfully.</textarea>
                        </div>
                        <button type="submit" class="btn-submit">Close Complaint</button>
                    </form>
                <?php endif; ?>
            </div>
            
            <div class="back-link">
                <a href="complaints.php">← Back to All Complaints</a>
            </div>
        <?php else: ?>
            <div class="complaint-details" style="text-align: center; padding: 3rem;">
                <p style="color: #666; margin-bottom: 1rem;">No complaint found or you don't have permission to access it.</p>
                <a href="complaints.php" class="btn">Go to Complaints List</a>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // Tab switching
        const tabs = document.querySelectorAll('.action-tab');
        const forms = document.querySelectorAll('.action-form');
        
        tabs.forEach(tab => {
            tab.addEventListener('click', function() {
                const targetId = this.getAttribute('data-target');
                
                // Remove active class from all tabs and forms
                tabs.forEach(t => t.classList.remove('active'));
                forms.forEach(f => f.classList.remove('active'));
                
                // Add active class to clicked tab and target form
                this.classList.add('active');
                const targetForm = document.getElementById(targetId);
                if (targetForm) {
                    targetForm.classList.add('active');
                }
            });
        });
        
        // Form validation
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', function(e) {
                const requiredFields = this.querySelectorAll('[required]');
                let isValid = true;
                
                requiredFields.forEach(field => {
                    if (!field.value.trim()) {
                        isValid = false;
                        field.style.borderColor = '#e74c3c';
                        field.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    } else {
                        field.style.borderColor = '#e0e0e0';
                    }
                });
                
                if (!isValid) {
                    e.preventDefault();
                    alert('Please fill in all required fields marked with *.');
                    return false;
                }
                
                // Show loading state
                const submitBtn = this.querySelector('button[type="submit"]');
                if (submitBtn) {
                    const originalText = submitBtn.textContent;
                    submitBtn.innerHTML = '<span style="display: inline-block; animation: spin 1s linear infinite;">↻</span> Processing...';
                    submitBtn.disabled = true;
                    
                    // Re-enable button after 5 seconds in case submission fails
                    setTimeout(() => {
                        submitBtn.textContent = originalText;
                        submitBtn.disabled = false;
                    }, 5000);
                }
                
                return true;
            });
        });
        
        // Auto-resize textareas
        document.querySelectorAll('textarea').forEach(textarea => {
            textarea.addEventListener('input', function() {
                this.style.height = 'auto';
                this.style.height = (this.scrollHeight) + 'px';
            });
            // Trigger initial resize
            textarea.dispatchEvent(new window.Event('input'));
        });
        
        // Focus on first input in active form
        window.addEventListener('load', function() {
            const activeForm = document.querySelector('.action-form.active');
            if (activeForm) {
                const firstInput = activeForm.querySelector('input, select, textarea');
                if (firstInput) {
                    firstInput.focus();
                }
            }
        });
        
        // Add spin animation
        const style = document.createElement('style');
        style.textContent = `
            @keyframes spin {
                0% { transform: rotate(0deg); }
                100% { transform: rotate(360deg); }
            }
        `;
        document.head.appendChild(style);
    </script>
</body>
</html> 
