<?php
// civicare/admin/track_complaints.php
require_once '../config/database.php';
require_once '../config/functions.php';

// 1. Session and Security
if (!isLoggedIn() || (!hasRole('admin') && !hasRole('super_admin'))) {
    header('Location: login.php');
    exit();
}

$conn = getDatabaseConnection();

$error = '';
$complaint = null;
$updates = [];

// 2. Logic to handle ID from either URL (from Complaints page) or POST (manual search)
$tracking_id = '';
if (isset($_GET['tracking_id'])) {
    $tracking_id = sanitizeInput($_GET['tracking_id']);
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tracking_id'])) {
    $tracking_id = sanitizeInput($_POST['tracking_id']);
}

if (!empty($tracking_id)) {
    // 3. Database Query (Sync with User file logic but Admin context)
    $sql = "SELECT c.*, 
                   u.full_name as user_name, u.email as user_email,
                   d.name as department_name,
                   staff.full_name as staff_name
            FROM complaints c 
            JOIN users u ON c.user_id = u.id 
            JOIN departments d ON c.department_id = d.id 
            LEFT JOIN users staff ON c.assigned_to = staff.id 
            WHERE c.tracking_id = ?";
            
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $tracking_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result && $result->num_rows === 1) {
        $complaint = $result->fetch_assoc();
        
        // 4. Fetch Timeline Updates
        $sql_updates = "SELECT cu.*, u.full_name as updated_by_name 
                        FROM complaint_updates cu 
                        LEFT JOIN users u ON cu.user_id = u.id 
                        WHERE cu.complaint_id = ? 
                        ORDER BY cu.created_at DESC";
        $stmt_up = $conn->prepare($sql_updates);
        $stmt_up->bind_param("i", $complaint['id']);
        $stmt_up->execute();
        $updates_result = $stmt_up->get_result();
        
        while ($row = $updates_result->fetch_assoc()) {
            $updates[] = $row;
        }
    } else {
        $error = "No complaint records found for ID: <strong>" . htmlspecialchars($tracking_id) . "</strong>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Tracking - Civicare</title>
    <style>
        /* ENHANCED ADMIN THEME CSS - Dynamic & Creative */
        :root {
            --ink: #1f2a3a;
            --slate: #2c3e50;
            --accent: #3498db;
            --accent-2: #1abc9c;
            --card: #ffffff;
            --soft: #f5f7fb;
            --muted: #7f8c8d;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea22 0%, #764ba222 100%), 
                        linear-gradient(to bottom, #f0f4ff, #e8eef8);
            background-attachment: fixed;
            color: var(--slate);
            min-height: 100vh;
        }
        
        .header { 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 50%, #3498db 100%);
            background-size: 200% 200%;
            animation: gradientShift 8s ease infinite;
            color: white; 
            padding: 1rem 0; 
            box-shadow: 0 4px 20px rgba(102, 126, 234, 0.3);
            position: relative;
            overflow: hidden;
        }
        
        .header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(45deg, transparent 40%, rgba(255,255,255,0.1) 50%, transparent 60%);
            background-size: 200% 200%;
            animation: shimmer 3s ease-in-out infinite;
        }
        
        @keyframes gradientShift {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }
        
        @keyframes shimmer {
            0% { transform: translateX(-100%); }
            100% { transform: translateX(100%); }
        }
        
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        @keyframes pulse {
            0%, 100% { transform: scale(1); opacity: 0.5; }
            50% { transform: scale(1.2); opacity: 0.8; }
        }
        
        .container { width: 90%; max-width: 1100px; margin: 0 auto; position: relative; z-index: 1; }
        .navbar { display: flex; justify-content: space-between; align-items: center; }
        .logo { font-size: 1.5rem; font-weight: bold; color: white; text-decoration: none; transition: transform 0.3s ease; }
        .logo:hover { transform: scale(1.05); }
        .nav-links a { color: white; text-decoration: none; margin-left: 15px; font-size: 0.9rem; opacity: 0.9; padding: 0.5rem 1rem; border-radius: 25px; transition: all 0.3s ease; position: relative; overflow: hidden; }
        .nav-links a::before { content: ''; position: absolute; top: 0; left: -100%; width: 100%; height: 100%; background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent); transition: left 0.5s ease; }
        .nav-links a:hover::before { left: 100%; }
        .nav-links a:hover { opacity: 1; background: rgba(255,255,255,0.2); transform: translateY(-2px); }

        /* Search Section */
        .search-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            padding: 2.5rem;
            border-radius: 16px;
            margin: 2rem 0;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1), 0 0 0 1px rgba(255,255,255,0.5) inset;
            text-align: center;
            position: relative;
            overflow: hidden;
            animation: fadeInUp 0.6s ease;
        }

        .search-container::after {
            content: '';
            position: absolute;
            right: -80px;
            top: -80px;
            width: 160px;
            height: 160px;
            background: radial-gradient(circle, rgba(102, 126, 234, 0.3) 0%, rgba(102, 126, 234, 0) 70%);
            animation: pulse 4s ease-in-out infinite;
        }
        
        .search-container h2 { 
            margin-bottom: 1rem; 
            background: linear-gradient(135deg, #667eea, #764ba2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            font-size: 1.8rem;
        }
        
        .search-box { display: flex; max-width: 500px; margin: 0 auto; gap: 10px; }
        input[type="text"] { 
            flex: 1; 
            padding: 12px 20px; 
            border: 2px solid #e0e0e0; 
            border-radius: 50px; 
            font-size: 1rem; 
            transition: all 0.3s ease;
            background: #f8f9fa;
        }
        input[type="text"]:focus { 
            border-color: #667eea; 
            outline: none; 
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
            transform: scale(1.02);
        }
        .btn-search { 
            background: linear-gradient(135deg, #667eea, #764ba2); 
            color: white; 
            border: none; 
            padding: 12px 25px; 
            border-radius: 50px; 
            font-weight: bold; 
            cursor: pointer; 
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
            transition: all 0.3s ease;
        }
        .btn-search:hover { 
            background: linear-gradient(135deg, #5a6fd6, #6a4190); 
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.5);
        }

        /* Result Cards */
        .complaint-main { 
            background: var(--card); 
            border-radius: 16px; 
            overflow: hidden; 
            box-shadow: 0 10px 40px rgba(0,0,0,0.1); 
            margin-bottom: 3rem; 
            animation: fadeInUp 0.6s ease 0.2s both;
        }
        
        .comp-header { 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 50%, #3498db 100%);
            background-size: 200% 200%;
            animation: gradientShift 8s ease infinite;
            color: white; 
            padding: 2.25rem; 
            display: flex; 
            justify-content: space-between; 
            align-items: flex-start; 
            gap: 1.5rem;
            position: relative;
            overflow: hidden;
        }
        
        .comp-header::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 100%;
            height: 100%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            animation: pulse 4s ease-in-out infinite;
        }
        
        .comp-title { position: relative; z-index: 1; }
        .comp-title h1 { font-size: 1.8rem; margin: 5px 0; }
        .badge-row { display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap; position: relative; z-index: 1; }
        
        .status-pill { 
            padding: 6px 14px; 
            border-radius: 50px; 
            font-size: 0.8rem; 
            font-weight: 700; 
            text-transform: uppercase; 
            background: #3498db; 
            letter-spacing: 0.5px;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .status-pill::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s ease;
        }
        
        .status-pill:hover::before { left: 100%; }
        
        .status-pending { background: linear-gradient(135deg, #f39c12, #e67e22); box-shadow: 0 4px 15px rgba(243, 156, 18, 0.4); }
        .status-validated { background: linear-gradient(135deg, #3498db, #2980b9); box-shadow: 0 4px 15px rgba(52, 152, 219, 0.4); }
        .status-rejected { background: linear-gradient(135deg, #e74c3c, #c0392b); box-shadow: 0 4px 15px rgba(231, 76, 60, 0.4); }
        .status-assigned { background: linear-gradient(135deg, #9b59b6, #8e44ad); box-shadow: 0 4px 15px rgba(155, 89, 182, 0.4); }
        .status-in_progress { background: linear-gradient(135deg, #1abc9c, #16a085); box-shadow: 0 4px 15px rgba(26, 188, 156, 0.4); animation: badgePulse 2s ease-in-out infinite; }
        .status-resolved { background: linear-gradient(135deg, #27ae60, #1e8449); box-shadow: 0 4px 15px rgba(39, 174, 96, 0.4); }
        .status-escalated { background: linear-gradient(135deg, #e67e22, #d35400); box-shadow: 0 4px 15px rgba(230, 126, 34, 0.4); }
        .status-closed { background: linear-gradient(135deg, #7f8c8d, #616a6b); box-shadow: 0 4px 15px rgba(127, 140, 141, 0.4); }

        @keyframes badgePulse {
            0%, 100% { box-shadow: 0 4px 15px rgba(26, 188, 156, 0.4); }
            50% { box-shadow: 0 4px 25px rgba(26, 188, 156, 0.6); }
        }

        /* Grid Layout */
        .section-title { 
            padding: 0 2rem; 
            margin-top: 1.5rem; 
            font-size: 1.05rem; 
            color: #2c3e50; 
            font-weight: 700;
            background: linear-gradient(135deg, #667eea, #764ba2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .details-grid { 
            display: grid; 
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); 
            gap: 20px; 
            padding: 1.5rem 2rem 2rem; 
            background: var(--card); 
        }
        
        .info-card {
            background: linear-gradient(135deg, #f8fafc, #fff);
            padding: 16px;
            border-radius: 12px;
            border-top: 3px solid #667eea;
            box-shadow: 0 6px 14px rgba(16, 24, 40, 0.08);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .info-card::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, transparent 50%, rgba(102, 126, 234, 0.05) 50%);
            transition: all 0.3s ease;
        }

        .info-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(16, 24, 40, 0.12);
            border-top-color: #764ba2;
        }
        
        .info-card:hover::before {
            width: 120px;
            height: 120px;
        }
        
        .info-label { font-size: 0.75rem; color: #7f8c8d; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 5px; }
        .info-value { font-weight: 600; color: #2c3e50; font-size: 1.02rem; }

        .desc-section { padding: 0 2rem 2rem; }
        .desc-content { 
            background: linear-gradient(135deg, #f8fafc, #fff);
            border: 1px solid #edf0f2; 
            padding: 1.5rem; 
            border-radius: 12px; 
            line-height: 1.6; 
            color: #444; 
            box-shadow: 0 4px 15px rgba(16, 24, 40, 0.05);
            transition: all 0.3s ease;
        }
        
        .desc-content:hover {
            box-shadow: 0 8px 25px rgba(16, 24, 40, 0.1);
        }

        .rating-box { 
            background: linear-gradient(135deg, #fff9e6, #fff);
            border: 1px solid #eee; 
            padding: 1.5rem; 
            border-radius: 12px; 
            border-left: 4px solid #f39c12; 
            line-height: 1.6; 
            color: #444;
            box-shadow: 0 4px 15px rgba(243, 156, 18, 0.1);
        }
        .rating-stars { color: #f39c12; font-size: 1.2rem; letter-spacing: 2px; text-shadow: 0 2px 4px rgba(243, 156, 18, 0.3); }
        .rating-note { margin-top: 0.5rem; color: #666; font-size: 0.9rem; }

        /* Timeline / Updates */
        .updates-section { 
            padding: 2rem; 
            border-top: 1px solid #eef2f6; 
            background: linear-gradient(135deg, #fbfcfe, #f0f4ff);
        }
        
        .timeline-box { 
            position: relative; 
            padding-left: 30px; 
            margin-top: 20px; 
        }
        
        .timeline-box::before { 
            content: ''; 
            position: absolute; 
            left: 0; 
            top: 0; 
            bottom: 0; 
            width: 3px; 
            background: linear-gradient(to bottom, #667eea, #764ba2, #3498db);
            border-radius: 3px;
        }
        
        .update-item { 
            position: relative; 
            margin-bottom: 25px; 
            animation: fadeInLeft 0.5s ease;
        }
        
        @keyframes fadeInLeft {
            from { opacity: 0; transform: translateX(-20px); }
            to { opacity: 1; transform: translateX(0); }
        }
        
        .update-item::after { 
            content: ''; 
            position: absolute; 
            left: -34px; 
            top: 5px; 
            width: 12px; 
            height: 12px; 
            border-radius: 50%; 
            background: linear-gradient(135deg, #667eea, #764ba2); 
            border: 3px solid white; 
            box-shadow: 0 0 0 3px #667eea;
            transition: all 0.3s ease;
        }
        
        .update-item:hover::after {
            transform: scale(1.3);
            box-shadow: 0 0 0 5px rgba(102, 126, 234, 0.3);
        }
        
        .update-date { font-size: 0.8rem; color: #94a3b8; margin-bottom: 4px; }
        .update-text { 
            background: linear-gradient(135deg, #fff, #f8fafc); 
            padding: 12px; 
            border-radius: 10px; 
            border: 1px solid #e2e8f0; 
            font-size: 0.95rem;
            transition: all 0.3s ease;
        }
        
        .update-text:hover {
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        }

        .alert { 
            background: linear-gradient(135deg, #ff6b6b, #ee5a5a); 
            color: white; 
            padding: 1rem; 
            border-radius: 12px; 
            margin: 1rem auto; 
            max-width: 500px; 
            box-shadow: 0 4px 15px rgba(255, 107, 107, 0.3);
            animation: shake 0.5s ease;
        }
        
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }
        
        @media (max-width: 768px) {
            .comp-header { flex-direction: column; gap: 15px; }
            .search-box { flex-direction: column; }
            .search-container, .comp-header, .comp-title { padding: 1.5rem; }
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
                <a href="complaints.php">Complaints List</a>
                <a href="../logout.php" style="color: #ff7675;">Logout</a>
            </div>
        </nav>
    </div>
</div>

<div class="container">
    <div class="search-container">
        <h2>Verify Complaint Status</h2>
        <form method="POST" class="search-box">
            <input type="text" name="tracking_id" placeholder="Enter Tracking ID (e.g. CIV24...)" 
                   value="<?php echo htmlspecialchars($tracking_id); ?>" required>
            <button type="submit" class="btn-search">Search Record</button>
        </form>
    </div>

    <?php if ($error): ?>
        <div class="alert"><?php echo $error; ?></div>
    <?php endif; ?>

    <?php if ($complaint): ?>
        <div class="complaint-main">
            <div class="comp-header">
                <div class="comp-title">
                    <span style="font-size: 0.8rem; opacity: 0.8;">Case Reference: #<?php echo $complaint['tracking_id']; ?></span>
                    <h1><?php echo htmlspecialchars($complaint['title']); ?></h1>
                </div>
                <div class="badge-row">
                    <div class="status-pill status-<?php echo $complaint['status']; ?>">
                        <?php echo getStatusText($complaint['status']); ?>
                    </div>
                </div>
            </div>

            <div class="section-title">Case Details</div>
            <div class="details-grid">
                <div class="info-card">
                    <div class="info-label">Complainant</div>
                    <div class="info-value"><?php echo htmlspecialchars($complaint['user_name']); ?></div>
                    <div style="font-size: 0.8rem; color: #7f8c8d;"><?php echo htmlspecialchars($complaint['user_email']); ?></div>
                </div>
                <div class="info-card">
                    <div class="info-label">Department</div>
                    <div class="info-value"><?php echo htmlspecialchars($complaint['department_name']); ?></div>
                </div>
                <div class="info-card">
                    <div class="info-label">Assignee</div>
                    <div class="info-value"><?php echo $complaint['staff_name'] ? htmlspecialchars($complaint['staff_name']) : 'Not Assigned'; ?></div>
                </div>
                <div class="info-card">
                    <div class="info-label">Registration Date</div>
                    <div class="info-value"><?php echo date('M d, Y', strtotime($complaint['created_at'])); ?></div>
                </div>
            </div>

            <div class="desc-section">
                <div class="section-title" style="padding: 0; margin-bottom: 8px;">Detailed Complaint Statement</div>
                <div class="desc-content">
                    <?php echo nl2br(htmlspecialchars($complaint['description'])); ?>
                </div>
            </div>

            <?php if (in_array($complaint['status'], ['resolved', 'closed'], true)): ?>
            <div class="desc-section">
                <div class="section-title" style="padding: 0; margin-bottom: 8px;">Citizen Feedback</div>
                <div class="rating-box">
                    <?php if (!empty($complaint['citizen_rating'])): ?>
                        <div class="rating-stars">
                            <?php echo str_repeat('★', (int)$complaint['citizen_rating']) . str_repeat('☆', 5 - (int)$complaint['citizen_rating']); ?>
                            <span>(<?php echo (int)$complaint['citizen_rating']; ?>/5)</span>
                        </div>
                        <?php if (!empty($complaint['citizen_feedback'])): ?>
                            <div class="rating-note">
                                <?php echo nl2br(htmlspecialchars($complaint['citizen_feedback'])); ?>
                            </div>
                        <?php else: ?>
                            <div class="rating-note">No feedback comment provided.</div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="rating-note">Not rated yet.</div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <div class="updates-section">
                <h3 style="color: #2c3e50;">Action Log / History</h3>
                <div class="timeline-box">
                    <?php if (empty($updates)): ?>
                        <p style="color: #94a3b8; font-style: italic;">No history logs found for this case.</p>
                    <?php else: ?>
                        <?php foreach ($updates as $update): ?>
                            <div class="update-item">
                                <div class="update-date">
                                    <?php echo date('d M Y - h:i A', strtotime($update['created_at'])); ?>
                                </div>
                                <div class="update-text">
                                    <strong>Status: <?php echo getStatusText($update['new_status']); ?></strong><br>
                                    <span style="color: #555;"><?php echo nl2br(htmlspecialchars($update['remarks'])); ?></span>
                                    <div style="margin-top: 8px; font-size: 0.75rem; color: #3498db;">
                                        Updated by: <?php echo htmlspecialchars($update['updated_by_name'] ?? 'System'); ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    
                    <div class="update-item">
                        <div class="update-date"><?php echo date('d M Y', strtotime($complaint['created_at'])); ?></div>
                        <div class="update-text" style="background: #f0fdf4;">
                            <strong>Complaint Filed</strong><br>
                            The case was successfully registered in the system.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

</body>
</html>