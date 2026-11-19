<?php
// civicare/user/notifications.php
session_start();
require_once '../config/database.php';
require_once '../config/functions.php';

// Check if user is logged in and is citizen
if (!isLoggedIn() || !hasRole('citizen')) {
    header('Location: login.php');
    exit();
}

$conn = getDatabaseConnection();

// Check connection
if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}

$user_id = $_SESSION['user_id'];

// Handle mark as read
if (isset($_GET['mark_read']) && is_numeric($_GET['mark_read'])) {
    $notification_id = (int)$_GET['mark_read'];
    $sql = "UPDATE notifications SET is_read = TRUE WHERE id = ? AND user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $notification_id, $user_id);
    $stmt->execute();
    $stmt->close();
    
    // Redirect to avoid duplicate marking
    header('Location: notifications.php');
    exit();
}

// Handle mark all as read
if (isset($_GET['mark_all_read'])) {
    $sql = "UPDATE notifications SET is_read = TRUE WHERE user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->close();
    
    // Redirect to avoid duplicate marking
    header('Location: notifications.php');
    exit();
}

// Handle delete notification
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $notification_id = (int)$_GET['delete'];
    $sql = "DELETE FROM notifications WHERE id = ? AND user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $notification_id, $user_id);
    $stmt->execute();
    $stmt->close();
    
    // Redirect to avoid duplicate deletion
    header('Location: notifications.php');
    exit();
}

// Handle clear all notifications
if (isset($_GET['clear_all'])) {
    $sql = "DELETE FROM notifications WHERE user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->close();
    
    // Redirect to avoid duplicate deletion
    header('Location: notifications.php');
    exit();
}

// Pagination
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';

$where_clause = "n.user_id = ?";

if ($filter === 'unread') {
    $where_clause .= " AND n.is_read = FALSE";
} elseif ($filter === 'read') {
    $where_clause .= " AND n.is_read = TRUE";
}
$limit = 15;
$offset = ($page - 1) * $limit;

// Get total notifications count
$count_sql = "SELECT COUNT(*) as total FROM notifications WHERE user_id = ?";

if ($filter === 'unread') {
    $count_sql .= " AND is_read = FALSE";
} elseif ($filter === 'read') {
    $count_sql .= " AND is_read = TRUE";
}
$count_stmt = $conn->prepare($count_sql);
$count_stmt->bind_param("i", $user_id);
$count_stmt->execute();
$count_result = $count_stmt->get_result();
$count_row = $count_result->fetch_assoc();
$total_notifications = $count_row['total'];
$count_stmt->close();

$total_pages = ceil($total_notifications / $limit);

// Get notifications with pagination
$notifications = [];
$sql = "SELECT n.*, c.tracking_id 
        FROM notifications n 
        LEFT JOIN complaints c ON n.complaint_id = c.id 
        WHERE $where_clause
        ORDER BY n.created_at DESC 
        LIMIT ? OFFSET ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("iii", $user_id, $limit, $offset);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $notifications[] = $row;
}
$stmt->close();

// Get unread count
$unread_count = 0;
$unread_sql = "SELECT COUNT(*) as unread FROM notifications WHERE user_id = ? AND is_read = FALSE";
$unread_stmt = $conn->prepare($unread_sql);
$unread_stmt->bind_param("i", $user_id);
$unread_stmt->execute();
$unread_result = $unread_stmt->get_result();
$unread_row = $unread_result->fetch_assoc();
$unread_count = $unread_row['unread'];
$unread_stmt->close();

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications - Civicare</title>
    <style>
        /* ===== MUNICIPAL GOVERNMENT STYLING ===== */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #f8f9fa 0%, #e8ecf1 100%);
            line-height: 1.6;
            color: #333;
        }
        
        .header {
            background: white;
            box-shadow: 0 2px 15px rgba(0, 0, 0, 0.08);
            position: sticky;
            top: 0;
            z-index: 100;
        }
        
        .container {
            width: 90%;
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 15px;
        }
        
        .navbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 0;
        }
        
        .logo {
            font-size: 24px;
            font-weight: bold;
            color: #1e3c72;
            text-decoration: none;
            transition: all 0.3s;
        }

        .logo:hover {
            color: #ff6b35;
            transform: scale(1.05);
        }
        
        .nav-links {
            display: flex;
            gap: 20px;
            align-items: center;
        }
        
        .nav-links a {
            color: #333;
            text-decoration: none;
            padding: 8px 12px;
            border-radius: 4px;
            font-weight: 500;
            transition: all 0.3s;
            font-size: 0.95rem;
        }
        
        .nav-links a:hover {
            background: #f0f0f0;
            color: #1e3c72;
            transform: translateY(-2px);
        }
        
        .btn {
            display: inline-block;
            padding: 10px 24px;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 600;
            transition: all 0.3s;
            border: none;
            cursor: pointer;
            font-size: 0.95rem;
        }

        .btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(255, 107, 53, 0.4);
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #ff6b35, #ff8555);
            color: white;
        }
        
        .btn-primary:hover {
            background: linear-gradient(135deg, #e55a24, #ff7543);
        }
        
        .notification-badge {
            background: linear-gradient(135deg, #ff6b35, #ff8555);
            color: white;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 12px;
            margin-left: 8px;
            font-weight: 700;
            box-shadow: 0 2px 8px rgba(255, 107, 53, 0.3);
        }
        
        .page-header {
            background: linear-gradient(135deg, rgba(30, 60, 114, 0.02), rgba(255, 107, 53, 0.02));
            padding: 30px 25px;
            border-radius: 10px;
            margin: 20px 0;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
            border-left: 6px solid #ff6b35;
            backdrop-filter: blur(10px);
        }
        
        .page-header h1 {
            color: #1e3c72;
            margin-bottom: 8px;
            font-size: 28px;
            font-weight: 700;
        }
        
        .page-header p {
            color: #666;
            font-size: 14px;
        }
        
        .action-buttons {
            display: flex;
            gap: 12px;
            margin-top: 15px;
        }
        
        .btn-action {
            padding: 10px 16px;
            background: linear-gradient(135deg, #2a5298, #1e3c72);
            color: white;
            text-decoration: none;
            border: none;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            font-size: 13px;
        }
        
        .btn-action:hover {
            background: linear-gradient(135deg, #1e3c72, #0f2847);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(30, 60, 114, 0.3);
        }
        
        .btn-action.delete {
            background: linear-gradient(135deg, #f8405a, #dc3545);
        }
        
        .btn-action.delete:hover {
            background: linear-gradient(135deg, #dc3545, #c82333);
        }
        
        .btn-action.success {
            background: #27ae60;
        }
        
        .btn-action.success:hover {
            background: #219653;
        }
        
        .notifications-container {
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
        }
        
        .notification-item {
            padding: 20px;
            border-bottom: 1px solid #f0f0f0;
            position: relative;
            transition: all 0.3s ease;
            border-left: 4px solid #e0e0e0;
            background: linear-gradient(to right, rgba(30, 60, 114, 0.01), transparent);
        }
        
        .notification-item:hover {
            background: linear-gradient(to right, rgba(255, 107, 53, 0.05), rgba(42, 82, 152, 0.02));
            transform: translateX(4px);
            border-left-color: #ff6b35;
            box-shadow: inset 0 0 10px rgba(255, 107, 53, 0.05);
        }
        
        .notification-item.unread {
            background: linear-gradient(to right, rgba(255, 107, 53, 0.08), rgba(42, 82, 152, 0.04));
            border-left: 5px solid #ff6b35;
        }
        
        .notification-item.unread::before {
            content: '';
            position: absolute;
            top: 25px;
            right: 20px;
            width: 10px;
            height: 10px;
            background: #ff6b35;
            border-radius: 50%;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% {
                box-shadow: 0 0 0 0 rgba(255, 107, 53, 0.7);
            }
            70% {
                box-shadow: 0 0 0 8px rgba(255, 107, 53, 0);
            }
            100% {
                box-shadow: 0 0 0 0 rgba(255, 107, 53, 0);
            }
        }
        
        .notification-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 12px;
            gap: 15px;
        }
        
        .notification-title {
            font-weight: 700;
            color: #1e3c72;
            font-size: 16px;
            flex: 1;
        }
        
        .notification-time {
            font-size: 12px;
            color: #999;
            white-space: nowrap;
            background: #f8f9fa;
            padding: 4px 10px;
            border-radius: 4px;
        }
        
        .notification-message {
            color: #555;
            margin-bottom: 12px;
            line-height: 1.6;
            font-size: 14px;
        }
        
        .notification-complaint {
            background: linear-gradient(135deg, #e8f4fc 0%, #fff7f0 100%);
            padding: 10px 14px;
            border-radius: 6px;
            margin-top: 10px;
            display: inline-block;
            font-size: 13px;
            color: #1e3c72;
            border-left: 4px solid #ff6b35;
            font-weight: 600;
            box-shadow: 0 2px 8px rgba(30, 60, 114, 0.1);
        }
        
        .notification-meta {
            display: flex;
            gap: 10px;
            margin-top: 10px;
            flex-wrap: wrap;
        }
        
        .notification-badge-type {
            display: inline-block;
            padding: 4px 10px;
            background: #f0f0f0;
            color: #555;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .notification-badge-type.urgent {
            background: #ffe6e6;
            color: #c82333;
        }
        
        .notification-badge-type.update {
            background: #e8f4fc;
            color: #1e3c72;
        }
        
        .notification-badge-type.success {
            background: #e8f6ef;
            color: #155724;
        }
        
        .notification-actions {
            display: flex;
            gap: 10px;
            margin-top: 12px;
            opacity: 0;
            transition: all 0.3s;
            max-height: 0;
            overflow: hidden;
        }
        
        .notification-item:hover .notification-actions {
            opacity: 1;
            max-height: 50px;
        }
        
        .action-icon {
            padding: 6px 12px;
            background: linear-gradient(135deg, #2a5298, #1e3c72);
            color: white;
            text-decoration: none;
            border-radius: 4px;
            font-size: 12px;
            transition: all 0.3s;
            font-weight: 600;
            border: none;
            cursor: pointer;
        }
        
        .action-icon:hover {
            background: linear-gradient(135deg, #1e3c72, #0f2847);
            transform: scale(1.05);
            box-shadow: 0 2px 8px rgba(30, 60, 114, 0.3);
        }
        
        .no-notifications {
            text-align: center;
            padding: 60px 20px;
            color: #666;
        }
        
        .no-notifications i {
            font-size: 48px;
            color: #ddd;
            margin-bottom: 15px;
            display: block;
        }
        
        .no-notifications h3 {
            color: #1e3c72;
            margin-bottom: 10px;
            font-size: 18px;
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            gap: 5px;
            margin: 20px 0;
            padding: 20px;
            background: white;
            border-radius: 8px;
        }
        
        .pagination a, .pagination span {
            padding: 10px 14px;
            border: 2px solid #e0e0e0;
            border-radius: 6px;
            text-decoration: none;
            color: #333;
            font-size: 14px;
            transition: all 0.3s;
            font-weight: 600;
        }
        
        .pagination a:hover {
            background: linear-gradient(135deg, #ff6b35, #ff8555);
            color: white;
            border-color: #ff6b35;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(255, 107, 53, 0.3);
        }
        
        .pagination .active {
            background: linear-gradient(135deg, #ff6b35, #ff8555);
            color: white;
            border-color: #ff6b35;
        }
        
        .filter-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        
        .filter-tab {
            padding: 10px 18px;
            background: white;
            color: #555;
            text-decoration: none;
            border-radius: 6px;
            transition: all 0.3s;
            font-size: 14px;
            font-weight: 600;
            border: 2px solid #e0e0e0;
        }
        
        .filter-tab:hover {
            background: #f8f9fa;
            border-color: #ff6b35;
            color: #ff6b35;
            transform: translateY(-2px);
        }
        
        .filter-tab.active {
            background: linear-gradient(135deg, #ff6b35, #ff8555);
            color: white;
            border-color: #ff6b35;
        }
        
        @media (max-width: 768px) {
            .navbar {
                flex-direction: column;
                gap: 10px;
            }
            
            .nav-links {
                flex-wrap: wrap;
                justify-content: center;
                gap: 10px;
            }
            
            .page-header {
                flex-direction: column;
                gap: 10px;
                padding: 20px;
            }
            
            .notification-header {
                flex-direction: column;
                gap: 8px;
            }
            
            .notification-time {
                align-self: flex-start;
            }
            
            .notification-item.unread::before {
                top: 15px;
            }
        }
        
        @media (max-width: 480px) {
            .action-buttons {
                flex-direction: column;
                width: 100%;
            }
            
            .btn-action {
                width: 100%;
                text-align: center;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="container">
            <nav class="navbar">
                <a href="../index.php" class="logo">Civicare</a>
                <div class="nav-links">
                    <a href="../index.php">Home</a>
                    <a href="../about.php">About</a>
                    <a href="../complaint/track_complaint.php">Track Complaint</a>
                    <a href="new_complaint.php" class="btn">File Complaint</a>
                    <a href="dashboard.php" class="btn-primary">Dashboard</a>
                    <a href="../logout.php" class="btn">Logout</a>
                </div>
            </nav>
        </div>
    </div>

    <div class="container">
        <div class="page-header">
            <div>
                <h1>My Notifications</h1>
                <p>Stay updated with your complaint status and important announcements</p>
                <p><strong>Unread notifications:</strong> <span class="notification-badge"><?php echo $unread_count; ?></span></p>
            </div>
            
            <div class="action-buttons">
                <?php if ($unread_count > 0): ?>
                    <a href="?mark_all_read=1" class="btn-action success">Mark All as Read</a>
                <?php endif; ?>
                
                <?php if ($total_notifications > 0): ?>
                    <a href="?clear_all=1" class="btn-action delete" onclick="return confirm('Are you sure you want to clear all notifications? This action cannot be undone.')">Clear All</a>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="filter-tabs">
            <a href="notifications.php" class="filter-tab <?php echo !isset($_GET['filter']) || $_GET['filter'] == 'all' ? 'active' : ''; ?>">All</a>
            <a href="?filter=unread" class="filter-tab <?php echo isset($_GET['filter']) && $_GET['filter'] == 'unread' ? 'active' : ''; ?>">Unread</a>
            <a href="?filter=read" class="filter-tab <?php echo isset($_GET['filter']) && $_GET['filter'] == 'read' ? 'active' : ''; ?>">Read</a>
        </div>

        <div class="notifications-container">
            <?php if (!empty($notifications)): ?>
                <?php foreach ($notifications as $notification): ?>
                    <div class="notification-item <?php echo !$notification['is_read'] ? 'unread' : ''; ?>">
                        <div class="notification-header">
                            <div class="notification-title">
                                <?php echo htmlspecialchars($notification['title']); ?>
                            </div>
                            <div class="notification-time">
                                <?php echo date('M d, Y h:i A', strtotime($notification['created_at'])); ?>
                            </div>
                        </div>
                        
                        <div class="notification-message">
                            <?php echo htmlspecialchars($notification['message']); ?>
                        </div>
                        
                        <?php if ($notification['tracking_id']): ?>
                            <div class="notification-complaint">
                                Complaint: #<?php echo htmlspecialchars($notification['tracking_id']); ?>
                            </div>
                        <?php endif; ?>
                        
                        <div class="notification-actions">
                            <?php if (!$notification['is_read']): ?>
                                <a href="?mark_read=<?php echo $notification['id']; ?>" class="action-icon">Mark as Read</a>
                            <?php endif; ?>
                            
                            <?php if ($notification['tracking_id']): ?>
                                <a href="track_complaint.php?tracking_id=<?php echo urlencode($notification['tracking_id']); ?>" class="action-icon">View Complaint</a>
                            <?php endif; ?>
                            
                            <a href="?delete=<?php echo $notification['id']; ?>" class="action-icon" onclick="return confirm('Are you sure you want to delete this notification?')">Delete</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="no-notifications">
                    <div style="font-size: 4rem; color: #ddd; margin-bottom: 1rem;">🔔</div>
                    <h3>No notifications yet</h3>
                    <p>When you have notifications, they'll appear here.</p>
                    <p>You'll receive notifications for:</p>
                    <ul style="text-align: left; display: inline-block; margin: 1rem auto; color: #666;">
                        <li>Complaint status updates</li>
                        <li>New comments on your complaints</li>
                        <li>Department responses</li>
                        <li>Important announcements</li>
                    </ul>
                    <a href="new_complaint.php" class="btn" style="margin-top: 1rem;">File Your First Complaint</a>
                </div>
            <?php endif; ?>
        </div>
        
        <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?page=<?php echo $page - 1; ?><?php echo isset($_GET['filter']) ? '&filter=' . $_GET['filter'] : ''; ?>">Previous</a>
                <?php endif; ?>
                
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <?php if ($i == 1 || $i == $total_pages || ($i >= $page - 2 && $i <= $page + 2)): ?>
                        <a href="?page=<?php echo $i; ?><?php echo isset($_GET['filter']) ? '&filter=' . $_GET['filter'] : ''; ?>"
                           class="<?php echo $i == $page ? 'active' : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php elseif ($i == $page - 3 || $i == $page + 3): ?>
                        <span>...</span>
                    <?php endif; ?>
                <?php endfor; ?>
                
                <?php if ($page < $total_pages): ?>
                    <a href="?page=<?php echo $page + 1; ?><?php echo isset($_GET['filter']) ? '&filter=' . $_GET['filter'] : ''; ?>">Next</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // Auto refresh notifications every 30 seconds
        let refreshTimer = setTimeout(() => {
            window.location.reload();
        }, 30000);
        
        // Stop auto-refresh when user is interacting
        document.addEventListener('mousemove', function() {
            clearTimeout(refreshTimer);
            refreshTimer = setTimeout(() => {
                window.location.reload();
            }, 30000);
        });
        
        // Mark notification as read on click
        document.querySelectorAll('.notification-item.unread').forEach(item => {
            item.addEventListener('click', function(e) {
                // Don't trigger if user clicked on a link
                if (e.target.tagName === 'A' || e.target.closest('a')) return;
                
                const markReadLink = this.querySelector('a[href*="mark_read"]');
                if (markReadLink) {
                    window.location.href = markReadLink.href;
                }
            });
        });
        
        // Enhanced filter notifications with animation
        document.querySelectorAll('.filter-tab').forEach(tab => {
            tab.addEventListener('click', function(e) {
                e.preventDefault();
                const filter = this.getAttribute('href').split('filter=')[1] || 'all';
                
                // Add fade-out animation
                const container = document.querySelector('.notifications-container');
                container.style.opacity = '0.5';
                container.style.transition = 'opacity 0.3s ease';
                
                // Update active tab
                document.querySelectorAll('.filter-tab').forEach(t => t.classList.remove('active'));
                this.classList.add('active');
                
                // Load filtered notifications
                setTimeout(() => {
                    if (filter === 'all') {
                        window.location.href = 'notifications.php';
                    } else {
                        window.location.href = `?filter=${filter}`;
                    }
                }, 300);
            });
        });
        
        // Auto-hide read notifications after 5 seconds (optional - can be disabled)
        // Uncomment to enable auto-hide
        /*
        document.querySelectorAll('.notification-item').forEach(item => {
            if (!item.classList.contains('unread')) {
                setTimeout(() => {
                    item.style.opacity = '0.7';
                }, 5000);
            }
        });
        */
        
        // Notification type indicators - adds visual badges
        document.querySelectorAll('.notification-item').forEach(item => {
            const title = item.querySelector('.notification-title').textContent.toLowerCase();
            const container = item.querySelector('.notification-meta') || 
                            document.createElement('div');
            
            if (!item.querySelector('.notification-meta')) {
                container.className = 'notification-meta';
                item.insertBefore(container, item.querySelector('.notification-complaint'));
            }
            
            let type = 'update';
            let typeText = 'Update';
            
            if (title.includes('urgent') || title.includes('critical')) {
                type = 'urgent';
                typeText = 'Urgent';
            } else if (title.includes('resolve') || title.includes('complete')) {
                type = 'success';
                typeText = 'Success';
            }
            
            if (!container.querySelector('.notification-badge-type')) {
                const badge = document.createElement('span');
                badge.className = `notification-badge-type ${type}`;
                badge.textContent = typeText;
                container.appendChild(badge);
            }
        });
        
        // Show/hide empty state animation
        const noNotifications = document.querySelector('.no-notifications');
        if (noNotifications) {
            const bell = noNotifications.querySelector('div');
            let angle = 0;
            
            const animateBell = () => {
                angle = angle === 0 ? 15 : -15;
                bell.style.transform = `rotate(${angle}deg)`;
                bell.style.transition = 'transform 0.6s ease-in-out';
            };
            
            setInterval(animateBell, 1200);
        }
        
        // Enhance notification items with smooth scroll into view
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.opacity = '1';
                    entry.target.style.transform = 'translateX(0)';
                }
            });
        }, { threshold: 0.1 });
        
        document.querySelectorAll('.notification-item').forEach(item => {
            item.style.opacity = '0';
            item.style.transform = 'translateX(-20px)';
            item.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
            observer.observe(item);
        });
    </script>
</body>
</html>