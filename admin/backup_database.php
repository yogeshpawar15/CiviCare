<?php
// civicare/admin/backup_database.php
require_once '../config/database.php';
require_once '../config/functions.php';

// Check if user is logged in and is admin 
if (!isLoggedIn() || (!hasRole('admin'))) {
    header('Location: login.php');
    exit();
}

$conn = getDatabaseConnection();
$message = '';
$message_type = '';

// Handle backup request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['backup'])) {
    $backup_type = sanitizeInput($_POST['backup_type']);

    // Create backups directory if it doesn't exist
    $backup_dir = '../backups/';
    if (!is_dir($backup_dir)) {
        mkdir($backup_dir, 0755, true);
    }

    $timestamp = date('Y-m-d_H-i-s');
    $filename = "civicare_backup_{$timestamp}.sql";
    $filepath = $backup_dir . $filename;

    try {
        // Get database connection info
        $db_host = DB_HOST;
        $db_name = DB_NAME;
        $db_user = DB_USER;
        $db_pass = DB_PASS;

        // Use mysqldump command
        $command = "mysqldump --host=$db_host --user=$db_user --password=$db_pass $db_name > $filepath";

        // Execute backup
        $output = shell_exec($command . ' 2>&1');

        if (file_exists($filepath) && filesize($filepath) > 0) {
            // Log backup action (skip if audit_logs table is missing)
            $log_sql = "INSERT INTO audit_logs (user_id, action, details, ip_address) VALUES (?, 'database_backup', ?, ?)";
            $stmt = $conn->prepare($log_sql);
            if ($stmt) {
                $stmt->bind_param("iss", $_SESSION['user_id'], "Database backup created: $filename", $_SERVER['REMOTE_ADDR']);
                $stmt->execute();
                $stmt->close();
            } else {
                error_log("Audit log insert failed: " . $conn->error);
            }

            $message = "Database backup created successfully: $filename";
            $message_type = 'success';

            // Optional: Send backup file for download
            if (isset($_POST['download']) && $_POST['download'] === '1') {
                header('Content-Type: application/octet-stream');
                header('Content-Disposition: attachment; filename="' . $filename . '"');
                header('Content-Length: ' . filesize($filepath));
                readfile($filepath);
                exit();
            }
        } else {
            $message = "Backup failed. Please check server permissions.";
            $message_type = 'error';
        }
    } catch (Exception $e) {
        $message = "Backup error: " . $e->getMessage();
        $message_type = 'error';
    }
}

// Get list of existing backups
$backups = [];
if (is_dir('../backups/')) {
    $files = scandir('../backups/');
    foreach ($files as $file) {
        if (pathinfo($file, PATHINFO_EXTENSION) === 'sql') {
            $filepath = '../backups/' . $file;
            $backups[] = [
                'filename' => $file,
                'size' => filesize($filepath),
                'date' => date('Y-m-d H:i:s', filemtime($filepath))
            ];
        }
    }
    // Sort by date descending
    usort($backups, function($a, $b) {
        return strtotime($b['date']) - strtotime($a['date']);
    });
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Backup - Civicare Admin</title>
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

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .backup-form {
            background: white;
            padding: 30px;
            border-radius: 8px;
            margin-bottom: 30px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
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

        .form-group input[type="checkbox"] {
            margin-right: 10px;
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

        .btn-primary {
            background: #3498db;
            color: white;
        }

        .btn-primary:hover {
            background: #2980b9;
        }

        .btn-success {
            background: #27ae60;
            color: white;
        }

        .btn-success:hover {
            background: #219653;
        }

        .backups-list {
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }

        .backups-list h3 {
            background: #3498db;
            color: white;
            padding: 20px;
            margin: 0;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        th {
            background: #f8f9fa;
            font-weight: 600;
            color: #2c3e50;
        }

        tr:hover {
            background: #f9f9f9;
        }

        .file-size {
            color: #666;
            font-size: 0.9rem;
        }

        .download-btn {
            background: #17a2b8;
            color: white;
            padding: 6px 12px;
            border-radius: 4px;
            text-decoration: none;
            font-size: 0.8rem;
        }

        .download-btn:hover {
            background: #138496;
        }

        .no-backups {
            text-align: center;
            padding: 40px;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>💾 Database Backup</h1>
            <p>Create and manage database backups for the Civicare system</p>
            <a href="index.php" style="color: #3498db; text-decoration: none; font-weight: 600;">← Back to Dashboard</a>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo $message_type; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <div class="backup-form">
            <h2 style="color: #2c3e50; margin-bottom: 20px;">Create New Backup</h2>
            <form method="POST">
                <div class="form-group">
                    <label for="backup_type">Backup Type</label>
                    <select id="backup_type" name="backup_type" style="width: 100%; padding: 10px; border: 2px solid #e0e0e0; border-radius: 5px; font-size: 14px;">
                        <option value="full">Full Database Backup</option>
                        <option value="structure">Structure Only</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>
                        <input type="checkbox" name="download" value="1" checked>
                        Download backup file after creation
                    </label>
                </div>

                <button type="submit" name="backup" value="create" class="btn btn-success">
                    🚀 Create Backup Now
                </button>
            </form>
        </div>

        <div class="backups-list">
            <h3>📁 Existing Backups</h3>
            <?php if (!empty($backups)): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Filename</th>
                            <th>Size</th>
                            <th>Created Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($backups as $backup): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($backup['filename']); ?></td>
                                <td class="file-size"><?php echo number_format($backup['size'] / 1024, 1) . ' KB'; ?></td>
                                <td><?php echo $backup['date']; ?></td>
                                <td>
                                    <a href="../backups/<?php echo urlencode($backup['filename']); ?>" class="download-btn" download>
                                        📥 Download
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="no-backups">
                    <p>No backup files found. Create your first backup above.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
