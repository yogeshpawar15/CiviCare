<?php
// civicare/admin/clear_cache.php
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

// Handle cache clearing request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['clear_cache'])) {
    $cache_types = isset($_POST['cache_types']) ? $_POST['cache_types'] : [];
    $cleared_items = [];

    try {
        // Clear system cache files
        if (in_array('system_cache', $cache_types)) {
            $cache_dirs = [
                '../cache/',
                '../tmp/',
                '../temp/',
                sys_get_temp_dir() . '/civicare/'
            ];

            foreach ($cache_dirs as $dir) {
                if (is_dir($dir)) {
                    $files = glob($dir . '*');
                    foreach ($files as $file) {
                        if (is_file($file) && time() - filemtime($file) > 3600) { // Older than 1 hour
                            unlink($file);
                        }
                    }
                    $cleared_items[] = "System cache files in " . basename($dir);
                }
            }
        }

        // Clear old temporary files
        if (in_array('temp_files', $cache_types)) {
            $temp_files_cleared = 0;
            $temp_dirs = [
                '../uploads/temp/',
                '../backups/temp/',
                sys_get_temp_dir()
            ];

            foreach ($temp_dirs as $dir) {
                if (is_dir($dir)) {
                    $files = glob($dir . '*');
                    foreach ($files as $file) {
                        if (is_file($file) && time() - filemtime($file) > 86400) { // Older than 24 hours
                            unlink($file);
                            $temp_files_cleared++;
                        }
                    }
                }
            }

            if ($temp_files_cleared > 0) {
                $cleared_items[] = "$temp_files_cleared temporary files";
            }
        }

        // Clear old logs
        if (in_array('old_logs', $cache_types)) {
            $logs_cleared = 0;
            $log_dirs = [
                '../logs/',
                '../backups/logs/'
            ];

            foreach ($log_dirs as $dir) {
                if (is_dir($dir)) {
                    $files = glob($dir . '*.log');
                    foreach ($files as $file) {
                        if (is_file($file) && time() - filemtime($file) > 2592000) { // Older than 30 days
                            unlink($file);
                            $logs_cleared++;
                        }
                    }
                }
            }

            if ($logs_cleared > 0) {
                $cleared_items[] = "$logs_cleared old log files";
            }
        }

        // Clear database query cache (if using MySQL query cache)
        if (in_array('db_cache', $cache_types)) {
            $conn->query("FLUSH QUERY CACHE");
            $cleared_items[] = "Database query cache";
        }

        // Clear old sessions
        if (in_array('old_sessions', $cache_types)) {
            $session_path = session_save_path();
            if ($session_path && is_dir($session_path)) {
                $files = glob($session_path . '/sess_*');
                $sessions_cleared = 0;
                foreach ($files as $file) {
                    if (is_file($file) && time() - filemtime($file) > 604800) { // Older than 7 days
                        unlink($file);
                        $sessions_cleared++;
                    }
                }
                if ($sessions_cleared > 0) {
                    $cleared_items[] = "$sessions_cleared old session files";
                }
            }
        }

        // Log cache clearing action (skip if audit_logs table is missing)
        $log_details = "Cache cleared: " . implode(', ', $cleared_items);
        $log_sql = "INSERT INTO audit_logs (user_id, action, details, ip_address) VALUES (?, 'cache_clear', ?, ?)";
        $stmt = $conn->prepare($log_sql);
        if ($stmt) {
            $stmt->bind_param("iss", $_SESSION['user_id'], $log_details, $_SERVER['REMOTE_ADDR']);
            $stmt->execute();
            $stmt->close();
        } else {
            error_log("Audit log insert failed: " . $conn->error);
        }

        if (!empty($cleared_items)) {
            $message = "Cache cleared successfully: " . implode(', ', $cleared_items);
            $message_type = 'success';
        } else {
            $message = "No cache files found to clear.";
            $message_type = 'info';
        }

    } catch (Exception $e) {
        $message = "Error clearing cache: " . $e->getMessage();
        $message_type = 'error';
    }
}

// Get cache statistics
$cache_stats = [
    'system_cache' => 0,
    'temp_files' => 0,
    'old_logs' => 0,
    'old_sessions' => 0
];

// Count system cache files
$cache_dirs = ['../cache/', '../tmp/', '../temp/', sys_get_temp_dir() . '/civicare/'];
foreach ($cache_dirs as $dir) {
    if (is_dir($dir)) {
        $files = glob($dir . '*');
        $cache_stats['system_cache'] += count($files);
    }
}

// Count temp files
$temp_dirs = ['../uploads/temp/', '../backups/temp/', sys_get_temp_dir()];
foreach ($temp_dirs as $dir) {
    if (is_dir($dir)) {
        $files = glob($dir . '*');
        foreach ($files as $file) {
            if (is_file($file) && time() - filemtime($file) > 86400) {
                $cache_stats['temp_files']++;
            }
        }
    }
}

// Count old logs
$log_dirs = ['../logs/', '../backups/logs/'];
foreach ($log_dirs as $dir) {
    if (is_dir($dir)) {
        $files = glob($dir . '*.log');
        foreach ($files as $file) {
            if (is_file($file) && time() - filemtime($file) > 2592000) {
                $cache_stats['old_logs']++;
            }
        }
    }
}

// Count old sessions
$session_path = session_save_path();
if ($session_path && is_dir($session_path)) {
    $files = glob($session_path . '/sess_*');
    foreach ($files as $file) {
        if (is_file($file) && time() - filemtime($file) > 604800) {
            $cache_stats['old_sessions']++;
        }
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clear Cache - Civicare Admin</title>
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

        .cache-form {
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

        .checkbox-group {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
        }

        .checkbox-item {
            display: flex;
            align-items: center;
            padding: 10px;
            border: 2px solid #e0e0e0;
            border-radius: 5px;
            transition: border-color 0.3s;
        }

        .checkbox-item:hover {
            border-color: #3498db;
        }

        .checkbox-item input[type="checkbox"] {
            margin-right: 10px;
            transform: scale(1.2);
        }

        .checkbox-item .cache-count {
            margin-left: auto;
            background: #e74c3c;
            color: white;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 0.8rem;
            font-weight: bold;
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

        .btn-danger {
            background: #e74c3c;
            color: white;
        }

        .btn-danger:hover {
            background: #c0392b;
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
            color: #e74c3c;
            margin-bottom: 10px;
        }

        .stat-card p {
            color: #666;
        }

        .warning-box {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 5px;
            padding: 15px;
            margin-bottom: 20px;
        }

        .warning-box h4 {
            color: #856404;
            margin-bottom: 5px;
        }

        .warning-box p {
            color: #856404;
            margin: 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🗑️ Clear Cache</h1>
            <p>Clean up system cache, temporary files, and old logs</p>
            <a href="index.php" style="color: #3498db; text-decoration: none; font-weight: 600;">← Back to Dashboard</a>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo $message_type; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <div class="warning-box">
            <h4>⚠️ Warning</h4>
            <p>Clearing cache will remove temporary files and old data. Make sure no critical operations are running before proceeding.</p>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <h3><?php echo number_format($cache_stats['system_cache']); ?></h3>
                <p>System Cache Files</p>
            </div>
            <div class="stat-card">
                <h3><?php echo number_format($cache_stats['temp_files']); ?></h3>
                <p>Old Temp Files</p>
            </div>
            <div class="stat-card">
                <h3><?php echo number_format($cache_stats['old_logs']); ?></h3>
                <p>Old Log Files</p>
            </div>
            <div class="stat-card">
                <h3><?php echo number_format($cache_stats['old_sessions']); ?></h3>
                <p>Old Sessions</p>
            </div>
        </div>

        <div class="cache-form">
            <h2 style="color: #2c3e50; margin-bottom: 20px;">Select Cache Types to Clear</h2>
            <form method="POST">
                <div class="checkbox-group">
                    <div class="checkbox-item">
                        <input type="checkbox" id="system_cache" name="cache_types[]" value="system_cache" checked>
                        <label for="system_cache">
                            <strong>System Cache Files</strong><br>
                            <small>Clear cached files older than 1 hour</small>
                        </label>
                        <span class="cache-count"><?php echo $cache_stats['system_cache']; ?></span>
                    </div>

                    <div class="checkbox-item">
                        <input type="checkbox" id="temp_files" name="cache_types[]" value="temp_files" checked>
                        <label for="temp_files">
                            <strong>Temporary Files</strong><br>
                            <small>Remove temp files older than 24 hours</small>
                        </label>
                        <span class="cache-count"><?php echo $cache_stats['temp_files']; ?></span>
                    </div>

                    <div class="checkbox-item">
                        <input type="checkbox" id="old_logs" name="cache_types[]" value="old_logs">
                        <label for="old_logs">
                            <strong>Old Log Files</strong><br>
                            <small>Delete logs older than 30 days</small>
                        </label>
                        <span class="cache-count"><?php echo $cache_stats['old_logs']; ?></span>
                    </div>

                    <div class="checkbox-item">
                        <input type="checkbox" id="db_cache" name="cache_types[]" value="db_cache">
                        <label for="db_cache">
                            <strong>Database Query Cache</strong><br>
                            <small>Flush MySQL query cache</small>
                        </label>
                        <span class="cache-count">N/A</span>
                    </div>

                    <div class="checkbox-item">
                        <input type="checkbox" id="old_sessions" name="cache_types[]" value="old_sessions">
                        <label for="old_sessions">
                            <strong>Old Session Files</strong><br>
                            <small>Remove sessions older than 7 days</small>
                        </label>
                        <span class="cache-count"><?php echo $cache_stats['old_sessions']; ?></span>
                    </div>
                </div>

                <div style="margin-top: 30px;">
                    <button type="submit" name="clear_cache" value="1" class="btn btn-danger">
                        🗑️ Clear Selected Cache
                    </button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
