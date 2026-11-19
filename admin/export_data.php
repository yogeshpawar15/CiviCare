<?php
// civicare/admin/export_data.php
require_once '../config/database.php';
require_once '../config/functions.php';

// Check if user is logged in 
if (!isLoggedIn() || (!hasRole('admin'))) {
      header('Location: login.php');
      exit();
  }

$conn = getDatabaseConnection();
$message = '';
$message_type = '';

// Handle export request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['export'])) {
    $export_type = sanitizeInput($_POST['export_type']);
    $format = sanitizeInput($_POST['format']);
    $date_from = !empty($_POST['date_from']) ? sanitizeInput($_POST['date_from']) : null;
    $date_to = !empty($_POST['date_to']) ? sanitizeInput($_POST['date_to']) : null;

    try {
        // Build query based on export type
        $sql = "";
        $filename = "";

        switch ($export_type) {
            case 'complaints':
                $sql = "SELECT c.*, u.full_name as user_name, u.email as user_email, u.phone as user_phone,
                               d.name as department_name, staff.full_name as staff_name,
                               admin.full_name as validated_by_name, resolver.full_name as resolved_by_name
                        FROM complaints c
                        JOIN users u ON c.user_id = u.id
                        JOIN departments d ON c.department_id = d.id
                        LEFT JOIN users staff ON c.assigned_to = staff.id
                        LEFT JOIN users admin ON c.validated_by = admin.id
                        LEFT JOIN users resolver ON c.resolved_by = resolver.id
                        WHERE 1=1";

                if ($date_from) $sql .= " AND DATE(c.created_at) >= '$date_from'";
                if ($date_to) $sql .= " AND DATE(c.created_at) <= '$date_to'";
                $sql .= " ORDER BY c.created_at DESC";

                $filename = "complaints_export_" . date('Y-m-d');
                break;

            case 'users':
                $sql = "SELECT u.*, d.name as department_name
                        FROM users u
                        LEFT JOIN departments d ON u.department_id = d.id
                        WHERE 1=1";

                if ($date_from) $sql .= " AND DATE(u.created_at) >= '$date_from'";
                if ($date_to) $sql .= " AND DATE(u.created_at) <= '$date_to'";
                $sql .= " ORDER BY u.created_at DESC";

                $filename = "users_export_" . date('Y-m-d');
                break;

            case 'departments':
                $sql = "SELECT * FROM departments ORDER BY name";
                $filename = "departments_export_" . date('Y-m-d');
                break;

            case 'audit_logs':
                $sql = "SELECT al.*, u.full_name as user_name
                        FROM audit_logs al
                        LEFT JOIN users u ON al.user_id = u.id
                        WHERE 1=1";

                if ($date_from) $sql .= " AND DATE(al.created_at) >= '$date_from'";
                if ($date_to) $sql .= " AND DATE(al.created_at) <= '$date_to'";
                $sql .= " ORDER BY al.created_at DESC";

                $filename = "audit_logs_export_" . date('Y-m-d');
                break;
        }

        $result = $conn->query($sql);

        if ($result && $result->num_rows > 0) {
            if ($format === 'excel') {
                // Export as Excel (CSV format)
                header('Content-Type: text/csv');
                header('Content-Disposition: attachment; filename="' . $filename . '.csv"');

                $output = fopen('php://output', 'w');

                // Get column headers
                $columns = [];
                if ($row = $result->fetch_assoc()) {
                    $columns = array_keys($row);
                    fputcsv($output, $columns);
                    fputcsv($output, $row); // First row
                }

                // Output remaining rows
                while ($row = $result->fetch_assoc()) {
                    fputcsv($output, $row);
                }

                fclose($output);
                exit();

            } elseif ($format === 'pdf') {
                // Export as PDF
                $autoload_path = __DIR__ . '/../vendor/autoload.php';
                if (file_exists($autoload_path)) {
                    require_once $autoload_path; // Load PDF library if installed
                }

                if (!class_exists('TCPDF')) {
                    $message = "PDF export requires TCPDF. Install it or use CSV export.";
                    $message_type = 'error';
                } else {

                $rows = [];
                while ($row = $result->fetch_assoc()) {
                    $rows[] = $row;
                }

                if (empty($rows)) {
                    $message = "No data found for the selected criteria.";
                    $message_type = 'info';
                } else {
                    $columns = array_keys($rows[0]);
                    $column_count = count($columns);
                    $orientation = $column_count > 8 ? 'L' : 'P';
                    $font_size = $column_count > 12 ? 6 : ($column_count > 8 ? 7 : 8);

                    $title = htmlspecialchars(ucfirst($export_type)) . " Export";
                    $generated_at = date('Y-m-d H:i:s');

                    $html = "<style>";
                    $html .= "body{font-family:helvetica;}";
                    $html .= "h1{font-size:14pt;margin:0 0 4px 0;}";
                    $html .= ".meta{font-size:9pt;margin-bottom:6px;color:#555;}";
                    $html .= ".sub{font-size:8pt;margin-bottom:8px;color:#666;}";
                    $html .= "table{border-collapse:collapse;width:100%;table-layout:fixed;}";
                    $html .= "th,td{border:1px solid #888;padding:3px;vertical-align:top;}";
                    $html .= "th{background:#f2f2f2;font-weight:bold;text-align:left;}";
                    $html .= "tr:nth-child(even) td{background:#fafafa;}";
                    $html .= "td{word-break:break-word;overflow-wrap:anywhere;}";
                    $html .= "</style>";
                    $html .= "<h1>" . $title . "</h1>";
                    $html .= "<div class='meta'>Generated on: " . $generated_at . "</div>";
                    $html .= "<div class='sub'>Records: " . count($rows) . " | Columns: " . $column_count . "</div>";
                    $html .= "<table><thead><tr>";

                    foreach ($columns as $col) {
                        $html .= "<th>" . htmlspecialchars($col) . "</th>";
                    }

                    $html .= "</tr></thead><tbody>";

                    foreach ($rows as $row) {
                        $html .= "<tr>";
                        foreach ($columns as $col) {
                            $value = $row[$col];
                            $value = $value === null ? '' : (string)$value;
                            $html .= "<td>" . htmlspecialchars($value) . "</td>";
                        }
                        $html .= "</tr>";
                    }

                    $html .= "</tbody></table>";

                    $pdf = new TCPDF($orientation, 'mm', 'A4', true, 'UTF-8', false);
                    $pdf->SetCreator('Civicare');
                    $pdf->SetAuthor('Civicare Admin');
                    $pdf->SetTitle($export_type . ' Export');
                    $pdf->setPrintHeader(false);
                    $pdf->setPrintFooter(false);
                    $pdf->SetMargins(6, 10, 6);
                    $pdf->SetAutoPageBreak(true, 10);
                    $pdf->SetFont('helvetica', '', $font_size);
                    $pdf->AddPage();
                    $pdf->writeHTML($html, true, false, true, false, '');
                    $pdf->Output($filename . '.pdf', 'D');
                    exit();
                }
                }
            }
        } else {
            $message = "No data found for the selected criteria.";
            $message_type = 'info';
        }

    } catch (Exception $e) {
        $message = "Export error: " . $e->getMessage();
        $message_type = 'error';
    }
}

// Get data counts for preview
$stats = [
    'complaints' => $conn->query("SELECT COUNT(*) as count FROM complaints")->fetch_assoc()['count'],
    'users' => $conn->query("SELECT COUNT(*) as count FROM users")->fetch_assoc()['count'],
    'departments' => $conn->query("SELECT COUNT(*) as count FROM departments")->fetch_assoc()['count'],
    'audit_logs' => $conn->query("SELECT COUNT(*) as count FROM audit_logs")->fetch_assoc()['count']
];

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Export Data - Civicare Admin</title>
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

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
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

        .alert {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            font-weight: 600;
        }

        .alert-info {
            background: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .export-form {
            background: white;
            padding: 30px;
            border-radius: 8px;
            margin-bottom: 30px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }

        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
        }

        select, input[type="date"] {
            width: 100%;
            padding: 10px;
            border: 2px solid #e0e0e0;
            border-radius: 5px;
            font-size: 14px;
        }

        .btn {
            padding: 12px 25px;
            border: none;
            border-radius: 5px;
            font-weight: 600;
            cursor: pointer;
            font-size: 14px;
            transition: background 0.3s;
        }

        .btn-success {
            background: #27ae60;
            color: white;
        }

        .btn-success:hover {
            background: #219653;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            text-align: center;
        }

        .stat-card h3 {
            font-size: 2rem;
            color: #3498db;
            margin-bottom: 10px;
        }

        .stat-card p {
            color: #666;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📄 Export Data</h1>
            <p>Export system data in PDF or Excel format</p>
            <a href="index.php" style="color: #3498db; text-decoration: none; font-weight: 600;">← Back to Dashboard</a>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo $message_type; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <div class="stats-grid">
            <div class="stat-card">
                <h3><?php echo number_format($stats['complaints']); ?></h3>
                <p>Total Complaints</p>
            </div>
            <div class="stat-card">
                <h3><?php echo number_format($stats['users']); ?></h3>
                <p>Total Users</p>
            </div>
            <div class="stat-card">
                <h3><?php echo number_format($stats['departments']); ?></h3>
                <p>Departments</p>
            </div>
            <div class="stat-card">
                <h3><?php echo number_format($stats['audit_logs']); ?></h3>
                <p>Audit Log Entries</p>
            </div>
        </div>

        <div class="export-form">
            <h2 style="color: #2c3e50; margin-bottom: 20px;">Export Options</h2>
            <form method="POST">
                <div class="form-row">
                    <div class="form-group">
                        <label for="export_type">Data Type</label>
                        <select id="export_type" name="export_type" required>
                            <option value="">Select Data Type</option>
                            <option value="complaints">Complaints</option>
                            <option value="users">Users</option>
                            <option value="departments">Departments</option>
                            <option value="audit_logs">Audit Logs</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="format">Export Format</label>
                        <select id="format" name="format" required>
                            <option value="excel">Excel (CSV)</option>
                            <option value="pdf">PDF</option>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="date_from">Date From (Optional)</label>
                        <input type="date" id="date_from" name="date_from">
                    </div>

                    <div class="form-group">
                        <label for="date_to">Date To (Optional)</label>
                        <input type="date" id="date_to" name="date_to">
                    </div>
                </div>

                <button type="submit" name="export" value="1" class="btn btn-success">
                    📄 Export Data Now
                </button>
            </form>
        </div>
    </div>
</body>
</html>
