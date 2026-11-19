<?php
// civicare/admin/check_staff.php
require_once '../config/database.php';
require_once '../config/functions.php';

header('Content-Type: application/json');

// Require admin authentication
if (!function_exists('isLoggedIn') || !isLoggedIn() || (!hasRole('admin'))) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo json_encode(['complaint_count' => 0, 'active_complaints' => 0, 'resolved_complaints' => 0]);
    exit();
}

$conn = getDatabaseConnection();
$staff_id = (int)$_GET['id'];

// Verify the user exists and is a staff member
$check_sql = "SELECT id FROM users WHERE id = ? AND role = 'staff'";
$check_stmt = $conn->prepare($check_sql);
$check_stmt->bind_param("i", $staff_id);
$check_stmt->execute();
$check_result = $check_stmt->get_result();

if (!$check_result || $check_result->num_rows === 0) {
    echo json_encode(['complaint_count' => 0, 'active_complaints' => 0, 'resolved_complaints' => 0]);
    $check_stmt->close();
    $conn->close();
    exit();
}
$check_stmt->close();

// Return total, active (assigned/in_progress) and resolved complaint counts
$sql = "SELECT
            COUNT(*) as complaint_count,
            SUM(status IN ('assigned','in_progress')) as active_complaints,
            SUM(status = 'resolved') as resolved_complaints
        FROM complaints WHERE assigned_to = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $staff_id);
$stmt->execute();
$result = $stmt->get_result();
$data = $result->fetch_assoc();

if (!$data) {
    $data = ['complaint_count' => 0, 'active_complaints' => 0, 'resolved_complaints' => 0];
} else {
    $data['complaint_count'] = (int)$data['complaint_count'];
    $data['active_complaints'] = (int)$data['active_complaints'];
    $data['resolved_complaints'] = (int)$data['resolved_complaints'];
}

echo json_encode($data);

$stmt->close();
$conn->close();
?>