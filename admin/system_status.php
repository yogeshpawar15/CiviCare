<?php
// civicare/admin/system_status.php
require_once '../config/database.php';
require_once '../config/functions.php';

// Check if user is logged in and is admin/super_admin
if (!isLoggedIn() || (!hasRole('admin') && !hasRole('staff') && !hasRole('super_admin'))) {
    header('Location: login.php');
    exit();
}

$conn = getDatabaseConnection();

// Get system information
$php_version = phpversion();
$mysql_version = $conn->server_info;
$server_os = php_uname('s') . ' ' . php_uname('r');
$server_hostname = php_uname('n');

// Disk usage
$disk_total = disk_total_space('/');
$disk_free = disk_free_space('/');
$disk_used = $disk_total - $disk_free;
$disk_usage_percent = round(($disk_used / $disk_total) * 100, 1);

// Memory info
$memory_limit = ini_get('memory_limit');
$upload_max = ini_get('upload_max_filesize');
$post_max = ini_get('post_max_size');

// Database stats
$db_stats = [];
$sql = "SELECT
        (SELECT COUNT(*) FROM complaints) as total_complaints,
        (SELECT COUNT(*) FROM users) as total_users,
        (SELECT COUNT(*) FROM departments) as total_departments,
        (SELECT COUNT(*) FROM complaint_updates) as total_updates";
$result = $conn->query($sql);
if ($result) {
    $db_stats = $result->fetch_assoc();
}

$conn->close();

// Function to format bytes
function formatBytes($bytes, $precision = 2) {
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, $precision) . ' ' . $units[$pow];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Status - Civicare Admin</title>
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

        .main-content {
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
            transition: transform 0.3s;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-card h3 {
            font-size: 1.5rem;
            color: #3498db;
            margin-bottom: 10px;
        }

        .stat-card p {
            color: #666;
            margin-bottom: 15px;
        }

        .stat-card .value {
            font-size: 1.2rem;
            font-weight: bold;
            color: #2c3e50;
        }

        .progress-bar {
            width: 100%;
            height: 10px;
            background: #eee;
            border-radius: 5px;
            overflow: hidden;
            margin-top: 10px;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #27ae60, #2ecc71);
            transition: width 0.3s;
        }

        .progress-fill.warning {
            background: linear-gradient(90deg, #f39c12, #e67e22);
        }

        .progress-fill.danger {
            background: linear-gradient(90deg, #e74c3c, #c0392b);
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
    </style>
</head>
<body>
    <div class="main-content">
        <div class="user-info">
            <div>
                <h2>System Status Dashboard</h2>
                <p>Monitor server and application health</p>
            </div>
            <div>
                <a href="index.php" class="logout-btn" style="background: #3498db;">← Back to Dashboard</a>
                <a href="logout.php" class="logout-btn">Logout</a>
            </div>
        </div>

        <div class="header">
            <h1>🖥️ System Information</h1>
            <p>Real-time status of your Civicare system</p>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <h3>🖥️ Server OS</h3>
                <p>Operating System</p>
                <div class="value"><?php echo htmlspecialchars($server_os); ?></div>
            </div>

            <div class="stat-card">
                <h3>🌐 Hostname</h3>
                <p>Server Name</p>
                <div class="value">Civicare</div>
            </div>

            <div class="stat-card">
                <h3>🐘 PHP Version</h3>
                <p>PHP Runtime</p>
                <div class="value"><?php echo htmlspecialchars($php_version); ?></div>
            </div>

            <div class="stat-card">
                <h3>🗄️ MySQL Version</h3>
                <p>Database Server</p>
                <div class="value"><?php echo htmlspecialchars($mysql_version); ?></div>
            </div>

            <div class="stat-card">
                <h3>💾 Disk Usage</h3>
                <p>Storage Space</p>
                <div class="value"><?php echo $disk_usage_percent; ?>% Used</div>
                <div style="font-size: 0.9rem; color: #666; margin-top: 5px;">
                    <?php echo formatBytes($disk_used); ?> / <?php echo formatBytes($disk_total); ?>
                </div>
                <div class="progress-bar">
                    <div class="progress-fill <?php echo $disk_usage_percent > 90 ? 'danger' : ($disk_usage_percent > 75 ? 'warning' : ''); ?>" style="width: <?php echo $disk_usage_percent; ?>%"></div>
                </div>
            </div>

            <div class="stat-card">
                <h3>🧠 Memory Limit</h3>
                <p>PHP Memory</p>
                <div class="value"><?php echo htmlspecialchars($memory_limit); ?></div>
            </div>

            <div class="stat-card">
                <h3>📤 Upload Max</h3>
                <p>File Upload Size</p>
                <div class="value"><?php echo htmlspecialchars($upload_max); ?></div>
            </div>

            <div class="stat-card">
                <h3>📝 Post Max</h3>
                <p>POST Data Size</p>
                <div class="value"><?php echo htmlspecialchars($post_max); ?></div>
            </div>
        </div>

        <div class="header">
            <h1>📊 Database Statistics</h1>
            <p>Current application data overview</p>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <h3>📄 Complaints</h3>
                <p>Total Records</p>
                <div class="value"><?php echo number_format($db_stats['total_complaints']); ?></div>
            </div>

            <div class="stat-card">
                <h3>👥 Users</h3>
                <p>Registered Users</p>
                <div class="value"><?php echo number_format($db_stats['total_users']); ?></div>
            </div>

            <div class="stat-card">
                <h3>🏛️ Departments</h3>
                <p>Active Departments</p>
                <div class="value"><?php echo number_format($db_stats['total_departments']); ?></div>
            </div>

            <div class="stat-card">
                <h3>📝 Updates</h3>
                <p>Complaint Updates</p>
                <div class="value"><?php echo number_format($db_stats['total_updates']); ?></div>
            </div>
        </div>

        <div class="header">
            <h1>🔧 System Health</h1>
            <p>Quick system checks</p>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <h3>✅ PHP Status</h3>
                <p>PHP Execution</p>
                <div class="value" style="color: #27ae60;">Running</div>
            </div>

            <div class="stat-card">
                <h3>✅ Database</h3>
                <p>MySQL Connection</p>
                <div class="value" style="color: #27ae60;">Connected</div>
            </div>

            <div class="stat-card">
                <h3>📅 Last Check</h3>
                <p>Status Updated</p>
                <div class="value"><?php echo date('M d, Y H:i:s'); ?></div>
            </div>

            <div class="stat-card">
                <h3>🔄 Uptime</h3>
                <p>Server Uptime</p>
                <div class="value"><?php echo function_exists('shell_exec') ? shell_exec('uptime -p') : 'N/A'; ?></div>
            </div>
        </div>
    </div>

    <script>
        // Sidebar toggle and collapsible menu behavior
        const sidebar = document.getElementById('sidebar');
        document.getElementById('sidebarToggle').addEventListener('click', function() {
            sidebar.classList.toggle('collapsed');
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
