<?php
// civicare/config/functions.php

/**
 * Check if user is logged in
 * @return bool True if user is logged in, false otherwise
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

/**
 * Check if user has specific role
 * @param string $role Role to check
 * @return bool True if user has the role, false otherwise
 */
function hasRole($role) {
    if (!isLoggedIn()) {
        return false;
    }
    return $_SESSION['user_role'] === $role;
}

// ================= SUPER ADMIN SPECIFIC FUNCTIONS =================

/**
 * Check if user is super admin (now includes admin role - admin is the ultimate boss)
 * @return bool True if user is admin or super admin, false otherwise
 */
function isSuperAdmin() {
    return hasRole('admin') || hasRole('super_admin');
}

/**
 * Check if user is admin
 * @return bool True if user is admin
 */
function isAdmin() {
    return hasRole('admin');
}

/**
 * Check if user can manage all users (admin+super admin)
 * @return bool True if user can manage all users
 */
function canManageAllUsers() {
    return isAdmin(); // Both admin and super admin can manage users
}

/**
 * Check if user can manage other admins (only super admin)
 * @return bool True if user can manage other admins
 */
function canManageAdmins() {
    return isSuperAdmin(); // Only super admin can manage other admins
}

/**
 * Check if user can escalate complaints
 * @return bool True if user can escalate complaints
 */
function canEscalateComplaints() {
    return isAdmin(); // Both admin and super admin can escalate
}

/**
 * Check if user can resolve escalated complaints
 * @return bool True if user can resolve escalated complaints
 */
function canResolveEscalatedComplaints() {
    return isSuperAdmin(); // Only super admin can resolve escalated
}

/**
 * Check if user can access audit logs
 * @return bool True if user can access audit logs
 */
function canAccessAuditLogs() {
    return isSuperAdmin(); // Only super admin
}

/**
 * Check if user can reset system
 * @return bool True if user can reset system
 */
function canResetSystem() {
    return isSuperAdmin(); // Only super admin
}

/**
 * Check if user can create new admins
 * @return bool True if user can create new admins
 */
function canCreateAdmins() {
    return isSuperAdmin(); // Only super admin can create other admins
}

/**
 * Check if user can override complaint status
 * @return bool True if user can override complaint status
 */
function canOverrideStatus() {
    return isSuperAdmin(); // Only super admin can override
}

/**
 * Check if user can access system settings
 * @return bool True if user can access system settings
 */
function canAccessSystemSettings() {
    return isAdmin(); // Both admin and super admin
}

// ================= EXISTING FUNCTIONS (KEPT AS IS) =================

/**
 * Redirect to a specific page
 * @param string $url URL to redirect to
 */
function redirect($url) {
    header("Location: $url");
    exit();
}

/**
 * Generate a random tracking ID for complaints
 * @return string Generated tracking ID
 */
function generateTrackingId() {
    $prefix = 'CIV';
    $year = date('y');
    $month = date('m');
    $random = strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 6));
    return $prefix . $year . $month . $random;
}

/**
 * Get status badge color based on status
 * @param string $status Complaint status
 * @return string CSS class for status badge
 */
function getStatusBadge($status) {
    $status_classes = [
        'pending' => 'badge-warning',
        'validated' => 'badge-info',
        'rejected' => 'badge-danger',
        'assigned' => 'badge-primary',
        'in_progress' => 'badge-info',
        'resolved' => 'badge-success',
        'escalated' => 'badge-danger',
        'closed' => 'badge-secondary'
    ];
    
    return $status_classes[$status] ?? 'badge-secondary';
}

/**
 * Get priority badge color
 * @param string $priority Priority level
 * @return string CSS class for priority badge
 */
function getPriorityBadge($priority) {
    $priority_classes = [
        'low' => 'badge-success',
        'medium' => 'badge-info',
        'high' => 'badge-warning',
        'urgent' => 'badge-danger'
    ];
    
    return $priority_classes[$priority] ?? 'badge-secondary';
}

/**
 * Create notification for user
 * @param mysqli $conn Database connection
 * @param int $user_id User ID to notify
 * @param string $title Notification title
 * @param string $message Notification message
 * @param int|null $complaint_id Related complaint ID (optional)
 * @return bool True if notification created successfully
 */
function createNotification($conn, $user_id, $title, $message, $complaint_id = null) {
    $sql = "INSERT INTO notifications (user_id, title, message, complaint_id) VALUES (?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("issi", $user_id, $title, $message, $complaint_id);
    
    $result = $stmt->execute();
    $stmt->close();
    
    return $result;
}

/**
 * Sanitize input data
 * @param string $data Input data to sanitize
 * @return string Sanitized data
 */
function sanitizeInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

/**
 * Get user's full name by ID
 * @param mysqli $conn Database connection
 * @param int $user_id User ID
 * @return string User's full name or empty string
 */
function getUserFullName($conn, $user_id) {
    $sql = "SELECT full_name FROM users WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        return $row['full_name'];
    }
    
    return '';
}

/**
 * Get department name by ID
 * @param mysqli $conn Database connection
 * @param int $department_id Department ID
 * @return string Department name or empty string
 */
function getDepartmentName($conn, $department_id) {
    $sql = "SELECT name FROM departments WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $department_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        return $row['name'];
    }
    
    return '';
}

/**
 * Get all departments for dropdown
 * @param mysqli $conn Database connection
 * @return array List of departments
 */
function getAllDepartments($conn) {
    $departments = [];
    $sql = "SELECT id, name FROM departments ORDER BY name";
    $result = $conn->query($sql);
    
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $departments[] = $row;
        }
    }
    
    return $departments;
}

/**
 * Validate email format
 * @param string $email Email to validate
 * @return bool True if email is valid
 */
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

/**
 * Check if username exists
 * @param mysqli $conn Database connection
 * @param string $username Username to check
 * @return bool True if username exists
 */
function usernameExists($conn, $username) {
    $sql = "SELECT id FROM users WHERE username = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    return $result->num_rows > 0;
}

/**
 * Check if email exists
 * @param mysqli $conn Database connection
 * @param string $email Email to check
 * @return bool True if email exists
 */
function emailExists($conn, $email) {
    $sql = "SELECT id FROM users WHERE email = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    return $result->num_rows > 0;
}

/**
 * Add complaint update history
 * @param mysqli $conn Database connection
 * @param int $complaint_id Complaint ID
 * @param int $user_id User ID who made the update
 * @param string $status New status
 * @param string $remarks Remarks about the update
 * @return bool True if update added successfully
 */
function addComplaintUpdate($conn, $complaint_id, $user_id, $status, $remarks = '') {
    // Insert update using columns defined in the schema (new_status)
    $sql = "INSERT INTO complaint_updates (complaint_id, user_id, new_status, remarks) VALUES (?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iiss", $complaint_id, $user_id, $status, $remarks);
    
    $result = $stmt->execute();
    $stmt->close();
    
    return $result;
}

/**
 * Get complaint status text
 * @param string $status Status key
 * @return string Human-readable status text
 */
function getStatusText($status) {
    $status_texts = [
        'pending' => 'Pending Validation',
        'validated' => 'Validated',
        'rejected' => 'Rejected',
        'assigned' => 'Assigned to Staff',
        'in_progress' => 'In Progress',
        'resolved' => 'Resolved',
        'escalated' => 'Escalated',
        'closed' => 'Closed'
    ];
    
    return $status_texts[$status] ?? ucfirst(str_replace('_', ' ', $status));
}

/**
 * Get unread notifications count for user
 * @param mysqli $conn Database connection
 * @param int $user_id User ID
 * @return int Number of unread notifications
 */
function getUnreadNotificationsCount($conn, $user_id) {
    $sql = "SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = FALSE";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        return $row['count'];
    }
    
    return 0;
}

/**
 * Log an activity message to a simple activity log file.
 * @param string $message Message to log
 * @param int|string|null $userId Optional user id associated with the activity
 * @return void
 */
function logActivity($message, $userId = null) {
    if (session_status() === PHP_SESSION_NONE) {
        @session_start();
    }

    $userId = $userId ?? ($_SESSION['user_id'] ?? 'guest');
    $time = date('Y-m-d H:i:s');
    $entry = "[$time] user:$userId $message" . PHP_EOL;

    $logDir = dirname(__DIR__) . '/logs';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }

    @file_put_contents($logDir . '/activity.log', $entry, FILE_APPEND | LOCK_EX);
}

/**
 * Get user role display text
 * @param string $role Role key
 * @return string Human-readable role text
 */
function getRoleText($role) {
    $role_texts = [
        'admin' => 'Administrator',
        'staff' => 'Staff Member',
        'citizen' => 'Citizen'
    ];
    
    return $role_texts[$role] ?? ucfirst(str_replace('_', ' ', $role));
}

/**
 * Get role badge color
 * @param string $role Role
 * @return string CSS class for role badge
 */
function getRoleBadge($role) {
    $role_classes = [
        'admin' => 'badge-danger',
        'staff' => 'badge-warning',
        'citizen' => 'badge-success'
    ];
    
    return $role_classes[$role] ?? 'badge-secondary';
}

/**
 * Check if user can view all complaints (including escalated)
 * @return bool True if user can view all complaints
 */
function canViewAllComplaints() {
    return isAdmin();
}

/**
 * Check if user can reassign complaints
 * @return bool True if user can reassign complaints
 */
function canReassignComplaints() {
    return isAdmin();
}

/**
 * Get escalated complaints count for super admin
 * @param mysqli $conn Database connection
 * @return int Number of escalated complaints
 */
function getEscalatedComplaintsCount($conn) {
    if (!isSuperAdmin()) {
        return 0;
    }
    
    $sql = "SELECT COUNT(*) as count FROM complaints WHERE status = 'escalated'";
    $result = $conn->query($sql);
    
    if ($row = $result->fetch_assoc()) {
        return $row['count'];
    }
    
    return 0;
}

/**
 * Get admin users count
 * @param mysqli $conn Database connection
 * @return int Number of admin users
 */
function getAdminUsersCount($conn) {
    $sql = "SELECT COUNT(*) as count FROM users WHERE role = 'admin'";
    $result = $conn->query($sql);
    
    if ($row = $result->fetch_assoc()) {
        return $row['count'];
    }
    
    return 0;
}

/**
 * Format date for display
 * @param string $date Date string
 * @param string $format Date format (default: 'M d, Y')
 * @return string Formatted date
 */
function formatDate($date, $format = 'M d, Y') {
    if (empty($date) || $date === '0000-00-00 00:00:00') {
        return 'Never';
    }
    return date($format, strtotime($date));
}

/**
 * Truncate text to specified length
 * @param string $text Text to truncate
 * @param int $length Maximum length
 * @return string Truncated text
 */
function truncateText($text, $length = 100) {
    if (strlen($text) <= $length) {
        return $text;
    }
    
    $truncated = substr($text, 0, $length);
    $truncated = substr($truncated, 0, strrpos($truncated, ' '));
    return $truncated . '...';
}

// ================= REAPPLICATION MANAGEMENT FUNCTIONS =================

/**
 * Configuration for reapplication feature
 */
define('MAX_REAPPLICATIONS_PER_COMPLAINT', 3); // Maximum reapplications allowed for a single complaint

/**
 * Get the count of reapplications for a parent complaint
 * @param object $conn Database connection
 * @param int $parent_complaint_id Parent complaint ID
 * @return int Count of reapplications
 */
function getReapplicationCount($conn, $parent_complaint_id) {
    if (!$conn || $parent_complaint_id <= 0) {
        return 0;
    }
    
    $sql = "SELECT COUNT(*) as reapply_count FROM complaints WHERE parent_complaint_id = ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return 0;
    }
    
    $stmt->bind_param("i", $parent_complaint_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    
    return $row ? (int)$row['reapply_count'] : 0;
}

/**
 * Check if a complaint can be reapplied
 * @param object $conn Database connection
 * @param int $complaint_id Complaint ID to check
 * @return array Array with 'can_reapply' (bool) and 'message' (string)
 */
function canReapplyComplaint($conn, $complaint_id) {
    if (!$conn || $complaint_id <= 0) {
        return ['can_reapply' => false, 'message' => 'Invalid complaint ID'];
    }
    
    // Check if complaint exists and is rated
    $sql = "SELECT status, citizen_rating, parent_complaint_id FROM complaints WHERE id = ? LIMIT 1";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return ['can_reapply' => false, 'message' => 'Database error'];
    }
    
    $stmt->bind_param("i", $complaint_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $complaint = $result->fetch_assoc();
    $stmt->close();
    
    if (!$complaint) {
        return ['can_reapply' => false, 'message' => 'Complaint not found'];
    }
    
    if (!in_array($complaint['status'], ['closed', 'resolved'])) {
        return ['can_reapply' => false, 'message' => 'Complaint must be closed or resolved'];
    }
    
    if (empty($complaint['citizen_rating'])) {
        return ['can_reapply' => false, 'message' => 'Complaint must be rated first'];
    }
    
    // Check reapplication limit
    $reapply_count = getReapplicationCount($conn, $complaint_id);
    if ($reapply_count >= MAX_REAPPLICATIONS_PER_COMPLAINT) {
        return ['can_reapply' => false, 'message' => 'Maximum reapplication limit of ' . MAX_REAPPLICATIONS_PER_COMPLAINT . ' reached'];
    }
    
    return ['can_reapply' => true, 'message' => 'Complaint can be reapplied', 'attempts_used' => $reapply_count, 'attempts_remaining' => MAX_REAPPLICATIONS_PER_COMPLAINT - $reapply_count];
}

/**
 * Get linked complaints (parent and children reapplications)
 * @param object $conn Database connection
 * @param int $complaint_id Complaint ID (can be parent or child)
 * @return array Array of linked complaint details
 */
function getLinkedComplaints($conn, $complaint_id) {
    if (!$conn || $complaint_id <= 0) {
        return [];
    }
    
    $linked_complaints = [];
    
    // First, find if this is a reapplication (has parent)
    $sql = "SELECT parent_complaint_id FROM complaints WHERE id = ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return [];
    }
    
    $stmt->bind_param("i", $complaint_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    
    $main_complaint_id = ($row && $row['parent_complaint_id']) ? (int)$row['parent_complaint_id'] : $complaint_id;
    
    // Get the main complaint and all its reapplications
    $sql = "SELECT c.id, c.tracking_id, c.title, c.status, c.citizen_rating, c.parent_complaint_id, c.created_at 
            FROM complaints c 
            WHERE c.id = ? OR c.parent_complaint_id = ? 
            ORDER BY c.created_at ASC";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return [];
    }
    
    $stmt->bind_param("ii", $main_complaint_id, $main_complaint_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($c = $result->fetch_assoc()) {
        $linked_complaints[] = $c;
    }
    $stmt->close();
    
    return $linked_complaints;
}

/**
 * Get reapplication statistics
 * @param object $conn Database connection
 * @return array Statistics array
 */
function getReapplicationStats($conn) {
    if (!$conn) {
        return [];
    }
    
    $stats = [];
    
    // Total reapplications
    $result = $conn->query("SELECT COUNT(*) as total FROM complaints WHERE parent_complaint_id IS NOT NULL");
    $stats['total_reapplications'] = $result ? (int)$result->fetch_assoc()['total'] : 0;
    
    // Total original complaints with reapplications
    $result = $conn->query("SELECT COUNT(DISTINCT parent_complaint_id) as total FROM complaints WHERE parent_complaint_id IS NOT NULL");
    $stats['complaints_with_reapplications'] = $result ? (int)$result->fetch_assoc()['total'] : 0;
    
    // Reapplications by status
    $result = $conn->query("SELECT status, COUNT(*) as count FROM complaints WHERE parent_complaint_id IS NOT NULL GROUP BY status");
    $stats['reapplications_by_status'] = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $stats['reapplications_by_status'][$row['status']] = (int)$row['count'];
        }
    }
    
    // Average rating for original vs reapplication
    $result = $conn->query("SELECT 
        (SELECT AVG(citizen_rating) FROM complaints WHERE parent_complaint_id IS NULL AND citizen_rating IS NOT NULL) as original_avg_rating,
        (SELECT AVG(citizen_rating) FROM complaints WHERE parent_complaint_id IS NOT NULL AND citizen_rating IS NOT NULL) as reapplication_avg_rating");
    $row = $result ? $result->fetch_assoc() : [];
    $stats['original_avg_rating'] = $row ? (float)($row['original_avg_rating'] ?? 0) : 0;
    $stats['reapplication_avg_rating'] = $row ? (float)($row['reapplication_avg_rating'] ?? 0) : 0;
    
    return $stats;
}

// ================= EMAIL NOTIFICATION FUNCTIONS =================

/**
 * Send complaint status email notification to user
 * @param mysqli $conn Database connection
 * @param int $user_id User ID to send email to
 * @param int $complaint_id Complaint ID
 * @param string $status New status of the complaint
 * @return bool True if email sent successfully (or no email configured), false on failure
 */
function sendComplaintStatusEmail($conn, $user_id, $complaint_id, $status) {
    // Get user email
    $sql = "SELECT email, full_name FROM users WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $stmt->close();
        return false;
    }
    
    $user = $result->fetch_assoc();
    $stmt->close();
    
    if (empty($user['email'])) {
        return false;
    }
    
    // Get complaint details
    $sql = "SELECT tracking_id, title, department_id FROM complaints WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $complaint_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $stmt->close();
        return false;
    }
    
    $complaint = $result->fetch_assoc();
    $stmt->close();
    
    // Get department name
    $dept_name = getDepartmentName($conn, $complaint['department_id']);
    
    // Email subject and message based on status
    $subject = "CiviCare - Complaint Status Update";
    $status_messages = [
        'pending' => [
            'subject' => "Complaint Filed - Pending Validation - #{$complaint['tracking_id']}",
            'message' => "Your complaint has been filed successfully and is currently pending validation."
        ],
        'validated' => [
            'subject' => "Complaint Validated - #{$complaint['tracking_id']}",
            'message' => "Your complaint has been validated and will be assigned to the concerned department."
        ],
        'rejected' => [
            'subject' => "Complaint Rejected - #{$complaint['tracking_id']}",
            'message' => "Your complaint has been rejected. Please check your dashboard for more details."
        ],
        'assigned' => [
            'subject' => "Complaint Assigned - #{$complaint['tracking_id']}",
            'message' => "Your complaint has been assigned to a staff member for processing."
        ],
        'in_progress' => [
            'subject' => "Complaint In Progress - #{$complaint['tracking_id']}",
            'message' => "Work has started on your complaint. We will keep you updated."
        ],
        'resolved' => [
            'subject' => "Complaint Resolved - #{$complaint['tracking_id']}",
            'message' => "Your complaint has been resolved. Please provide your feedback."
        ],
        'escalated' => [
            'subject' => "Complaint Escalated - #{$complaint['tracking_id']}",
            'message' => "Your complaint has been escalated to higher authorities for further action."
        ],
        'closed' => [
            'subject' => "Complaint Closed - #{$complaint['tracking_id']}",
            'message' => "Your complaint has been closed. Thank you for using CiviCare."
        ]
    ];
    
    $status_info = $status_messages[$status] ?? [
        'subject' => "Complaint Status Update - #{$complaint['tracking_id']}",
        'message' => "Your complaint status has been updated to: " . ucfirst(str_replace('_', ' ', $status))
    ];
    
    // Build email
    $email_subject = $status_info['subject'];
    $email_body = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: #1e3c72; color: white; padding: 20px; text-align: center; }
            .content { padding: 20px; background: #f9f9f9; }
            .footer { padding: 20px; text-align: center; font-size: 12px; color: #666; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>CiviCare</h1>
                <p>Municipal Complaint Management System</p>
            </div>
            <div class='content'>
                <h2>Dear {$user['full_name']},</h2>
                <p>{$status_info['message']}</p>
                
                <h3>Complaint Details:</h3>
                <table style='width: 100%; border-collapse: collapse;'>
                    <tr>
                        <td style='padding: 8px; border: 1px solid #ddd;'><strong>Tracking ID:</strong></td>
                        <td style='padding: 8px; border: 1px solid #ddd;'>{$complaint['tracking_id']}</td>
                    </tr>
                    <tr>
                        <td style='padding: 8px; border: 1px solid #ddd;'><strong>Title:</strong></td>
                        <td style='padding: 8px; border: 1px solid #ddd;'>{$complaint['title']}</td>
                    </tr>
                    <tr>
                        <td style='padding: 8px; border: 1px solid #ddd;'><strong>Department:</strong></td>
                        <td style='padding: 8px; border: 1px solid #ddd;'>{$dept_name}</td>
                    </tr>
                    <tr>
                        <td style='padding: 8px; border: 1px solid #ddd;'><strong>Status:</strong></td>
                        <td style='padding: 8px; border: 1px solid #ddd;'>" . ucfirst(str_replace('_', ' ', $status)) . "</td>
                    </tr>
                </table>
                
                <p style='margin-top: 20px;'>
                    You can track the status of your complaint by visiting our website and using the tracking ID: 
                    <strong>{$complaint['tracking_id']}</strong>
                </p>
            </div>
            <div class='footer'>
                <p>&copy; " . date('Y') . " CiviCare - Municipal Complaint Management System. All rights reserved.</p>
                <p>This is an automated message. Please do not reply to this email.</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    // Set email headers
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: CiviCare <noreply@civicare.gov.in>" . "\r\n";
    
    // Send email
    $mail_sent = @mail($user['email'], $email_subject, $email_body, $headers);
    
    return $mail_sent;
}

?>