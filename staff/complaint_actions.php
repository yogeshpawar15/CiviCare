<?php
// civicare/staff/complaint_actions.php
require_once '../config/database.php';
require_once '../config/functions.php';

// Check if user is logged in and is staff
if (!isLoggedIn() || !hasRole('staff')) {
    header('Location: login.php');
    exit();
}

$conn = getDatabaseConnection();

$error = '';
$success = '';
$complaint = null;

// Get complaint ID
$complaint_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$action_type = isset($_GET['action']) ? sanitizeInput($_GET['action']) : '';

if ($complaint_id > 0) {
    // Get complaint details (staff can only manage complaints from their department)
    $sql = "SELECT c.*, u.full_name as citizen_name, u.email as citizen_email, u.phone as citizen_phone,
                   d.name as department_name, staff.full_name as assigned_staff_name,
                   admin.full_name as validated_by_name, resolver.full_name as resolved_by_name
            FROM complaints c
            JOIN users u ON c.user_id = u.id
            JOIN departments d ON c.department_id = d.id
            LEFT JOIN users staff ON c.assigned_to = staff.id
            LEFT JOIN users admin ON c.validated_by = admin.id
            LEFT JOIN users resolver ON c.resolved_by = resolver.id
            WHERE c.id = ? AND c.department_id = ?";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $complaint_id, $_SESSION['department_id']);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $complaint = $result->fetch_assoc();

        // Additional check: staff can only manage complaints assigned to them
        if ($complaint['assigned_to'] != $_SESSION['user_id']) {
            $error = "You can only manage complaints assigned to you.";
            $complaint = null;
        }
    } else {
        $error = "Complaint not found or not in your department.";
    }
    $stmt->close();
} else {
    $error = "Invalid complaint ID.";
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $complaint) {
    $action = sanitizeInput($_POST['action']);
    $remarks = isset($_POST['remarks']) ? sanitizeInput($_POST['remarks']) : '';

    switch ($action) {
        case 'in_progress':
            $sql = "UPDATE complaints SET status = 'in_progress' WHERE id = ? AND status = 'assigned' AND assigned_to = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ii", $complaint_id, $_SESSION['user_id']);

            if ($stmt->execute()) {
                addComplaintUpdate($conn, $complaint_id, $_SESSION['user_id'], 'in_progress',
                    "Work started on complaint. " . $remarks);

createNotification($conn, $complaint['user_id'], 'Complaint In Progress',
                    "Work has started on your complaint #{$complaint['tracking_id']}.", $complaint_id);

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
                $sql = "UPDATE complaints SET status = 'escalated', escalation_reason = ?, escalated_by = ?, escalation_date = NOW() WHERE id = ? AND status IN ('assigned', 'in_progress') AND assigned_to = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("siii", $escalation_reason, $_SESSION['user_id'], $complaint_id, $_SESSION['user_id']);

                if ($stmt->execute()) {
                    addComplaintUpdate($conn, $complaint_id, $_SESSION['user_id'], 'escalated',
                        "Complaint escalated. Reason: " . $escalation_reason);

                    // Notify admins about escalation
                    $admin_sql = "SELECT id FROM users WHERE role IN ('admin', 'super_admin') AND is_active = TRUE";
                    $admin_result = $conn->query($admin_sql);

                    while ($admin = $admin_result->fetch_assoc()) {
                        createNotification($conn, $admin['id'], 'Complaint Escalated',
                            "Complaint #{$complaint['tracking_id']} has been escalated by staff. Reason: " . $escalation_reason,
                            $complaint_id);
                    }

createNotification($conn, $complaint['user_id'], 'Complaint Escalated',
                        "Your complaint #{$complaint['tracking_id']} has been escalated to higher authorities for review.",
                        $complaint_id);

                    $success = "Complaint escalated successfully.";
                    header('Location: complaints.php');
                    exit();
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
                $sql = "UPDATE complaints SET status = 'resolved', resolved_by = ?, resolution_date = NOW(), resolution_notes = ? WHERE id = ? AND status IN ('in_progress', 'escalated') AND assigned_to = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("isii", $_SESSION['user_id'], $resolution_notes, $complaint_id, $_SESSION['user_id']);

                if ($stmt->execute()) {
                    addComplaintUpdate($conn, $complaint_id, $_SESSION['user_id'], 'resolved',
                        "Complaint resolved. Notes: " . $resolution_notes);

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

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Complaint - Civicare Staff</title>
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

        .btn-danger {
            background: #e74c3c;
        }

        .btn-danger:hover {
            background: #c0392b;
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

        .btn-warning {
            background: #f39c12;
        }

        .btn-warning:hover {
            background: #d68910;
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

        .page-title p {
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

        .status-pending { background: #f39c12; color: white; }
        .status-validated { background: #3498db; color: white; }
        .status-rejected { background: #e74c3c; color: white; }
        .status-assigned { background: #9b59b6; color: white; }
        .status-in_progress { background: #3498db; color: white; }
        .status-resolved { background: #27ae60; color: white; }
.status-escalated { background: #e67e22; color: white; }
        .status-closed { background: #7f8c8d; color: white; }

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
            padding-bottom: 1rem;
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
            padding-bottom: 1rem;
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

    <style>
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
    <div class="header">
        <div class="container">
            <nav class="navbar">
                <a href="dashboard.php" class="logo">Civicare Staff</a>
                <div class="nav-links">
                    <a href="dashboard.php">Dashboard</a>
                    <a href="complaints.php">My Complaints</a>
                    <a href="track_complaint.php">Track Complaint</a>
                    
                    <a href="logout.php" class="btn btn-danger">Logout</a>
                </div>
            </nav>
        </div>
    </div>

    <div class="container">
        <div class="page-header">
            <h1>Manage Complaint</h1>
            <p>Review and take action on assigned complaints</p>
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
                            <div class="detail-value">
                                <?php echo htmlspecialchars($complaint['assigned_staff_name']); ?>
                                <?php if ($complaint['assigned_to'] == $_SESSION['user_id']): ?>
                                    <span style="color: #27ae60; font-size: 0.8rem;"> (You)</span>
                                <?php endif; ?>
                            </div>
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
            </div>

            <?php
            // Get linked complaints (reapplication chain)
            $conn2 = getDatabaseConnection();
            $linked_complaints = getLinkedComplaints($conn2, $complaint_id);
            ?>

            <?php if (!empty($linked_complaints) && count($linked_complaints) > 1): ?>
            <div style="background: white; padding: 2rem; border-radius: 8px; margin: 2rem 0; box-shadow: 0 4px 6px rgba(0,0,0,0.1); border-top: 4px solid #f39c12;">
                <h3 style="color: #2c3e50; margin-bottom: 1.5rem; padding-bottom: 1rem; border-bottom: 2px solid #f39c12;">🔗 Linked Complaints (Reapplication Chain)</h3>
                
                <div style="overflow-x: auto;">
                    <table style="width: 100%; border-collapse: collapse; font-size: 0.9rem;">
                        <thead>
                            <tr style="background: #f8f9fa;">
                                <th style="padding: 1rem; text-align: left; border-bottom: 2px solid #e0e0e0; font-weight: 600;">Type</th>
                                <th style="padding: 1rem; text-align: left; border-bottom: 2px solid #e0e0e0; font-weight: 600;">Tracking ID</th>
                                <th style="padding: 1rem; text-align: left; border-bottom: 2px solid #e0e0e0; font-weight: 600;">Title</th>
                                <th style="padding: 1rem; text-align: left; border-bottom: 2px solid #e0e0e0; font-weight: 600;">Status</th>
                                <th style="padding: 1rem; text-align: center; border-bottom: 2px solid #e0e0e0; font-weight: 600;">Rating</th>
                                <th style="padding: 1rem; text-align: left; border-bottom: 2px solid #e0e0e0; font-weight: 600;">Filed</th>
                                <th style="padding: 1rem; text-align: center; border-bottom: 2px solid #e0e0e0; font-weight: 600;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($linked_complaints as $idx => $linked): ?>
                            <tr style="border-bottom: 1px solid #eee; <?php echo ($linked['id'] == $complaint_id) ? 'background: #e3f2fd;' : ''; ?>">
                                <td style="padding: 1rem;">
                                    <span style="display: inline-block; padding: 0.4rem 0.75rem; border-radius: 4px; font-size: 0.8rem; font-weight: 600; <?php echo empty($linked['parent_complaint_id']) ? 'background: #d4edda; color: #155724;' : 'background: #fff3cd; color: #856404;'; ?>">
                                        <?php echo empty($linked['parent_complaint_id']) ? 'Original' : 'Reapplication'; ?>
                                    </span>
                                </td>
                                <td style="padding: 1rem;">
                                    <strong><?php echo htmlspecialchars($linked['tracking_id']); ?></strong>
                                </td>
                                <td style="padding: 1rem;">
                                    <?php echo htmlspecialchars(substr($linked['title'], 0, 50)); ?>
                                    <?php if (strlen($linked['title']) > 50): ?>...<?php endif; ?>
                                </td>
                                <td style="padding: 1rem;">
                                    <span class="status-badge status-<?php echo $linked['status']; ?>" style="display: inline-block;">
                                        <?php echo getStatusText($linked['status']); ?>
                                    </span>
                                </td>
                                <td style="padding: 1rem; text-align: center;">
                                    <?php if ($linked['citizen_rating']): ?>
                                        <div style="color: #f39c12; font-weight: bold;">
                                            <?php echo str_repeat('★', $linked['citizen_rating']) . str_repeat('☆', 5 - $linked['citizen_rating']); ?>
                                        </div>
                                        <small style="color: #666;"><?php echo $linked['citizen_rating']; ?>/5</small>
                                    <?php else: ?>
                                        <span style="color: #95a5a6;">Not rated</span>
                                    <?php endif; ?>
                                </td>
                                <td style="padding: 1rem;">
                                    <small><?php echo date('M d, Y', strtotime($linked['created_at'])); ?></small>
                                </td>
                                <td style="padding: 1rem; text-align: center;">
                                    <?php if ($linked['id'] != $complaint_id): ?>
                                        <a href="complaint_actions.php?id=<?php echo $linked['id']; ?>" 
                                           style="color: #3498db; text-decoration: none; font-weight: 600; padding: 0.4rem 0.8rem; background: #e3f2fd; border-radius: 4px; display: inline-block;">
                                            📖 View
                                        </a>
                                    <?php else: ?>
                                        <span style="color: #27ae60; font-weight: 600;">✓ Current</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div style="margin-top: 1.5rem; padding: 1rem; background: #f0f7ff; border-left: 4px solid #3498db; border-radius: 4px;">
                    <strong style="color: #0066cc;">ℹ️ Information:</strong>
                    <p style="margin-top: 0.5rem; color: #666; font-size: 0.9rem;">
                        This complaint is part of a reapplication chain. The table above shows the original complaint and all related reapplication attempts. 
                        You can click "View" to see details of any linked complaint.
                    </p>
                </div>
            </div>
            <?php endif; ?>

            <?php
            // Get complaint timeline/updates
            if (!isset($conn2)) {
                $conn2 = getDatabaseConnection();
            }
            $sql_updates = "SELECT cu.*, u.full_name, u.role
                           FROM complaint_updates cu
                           JOIN users u ON cu.user_id = u.id
                           WHERE cu.complaint_id = ?
                           ORDER BY cu.created_at DESC";
            $stmt_updates = $conn2->prepare($sql_updates);
            $stmt_updates->bind_param("i", $complaint_id);
            $stmt_updates->execute();
            $result_updates = $stmt_updates->get_result();
            $updates = [];
            while ($row = $result_updates->fetch_assoc()) {
                $updates[] = $row;
            }
            $stmt_updates->close();
            if (isset($conn2)) {
                $conn2->close();
            }
            ?>

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
                    <?php if ($complaint['status'] === 'assigned'): ?>
                        <button type="button" class="action-tab active" data-target="inProgressForm">Mark In Progress</button>
                        <button type="button" class="action-tab" data-target="escalateForm">Escalate</button>
                    <?php elseif ($complaint['status'] === 'in_progress'): ?>
                        <button type="button" class="action-tab active" data-target="resolveForm">Mark Resolved</button>
                        <button type="button" class="action-tab" data-target="escalateForm">Escalate</button>
                    <?php endif; ?>
                </div>

                <!-- In Progress Form -->
                <?php if ($complaint['status'] === 'assigned'): ?>
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
                <?php if ($complaint['status'] === 'in_progress'): ?>
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
            </div>

            <div class="back-link">
                <a href="complaints.php">← Back to My Complaints</a>
            </div>
        <?php else: ?>
            <div class="complaint-details" style="text-align: center; padding: 3rem;">
                <p style="color: #666; margin-bottom: 1rem;">Complaint not found or you don't have permission to manage it.</p>
                <a href="complaints.php" class="btn">Go to My Complaints</a>
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
