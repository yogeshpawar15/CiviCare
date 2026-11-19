<?php
session_start();
require 'config/database.php';

$conn = getDatabaseConnection();

// Get department ID from URL
$dept_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Fetch department details
$sql = "SELECT * FROM departments WHERE id = ? AND is_active = 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $dept_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header("Location: departments.php");
    exit();
}

$dept = $result->fetch_assoc();

// Department information database
$deptDetails = [
    1 => [
        'formed' => '1985',
        'works' => 'The Water Department manages the municipal water supply system including pipelines, water treatment, distribution, and drainage systems. They handle complaints regarding water quality, supply interruptions, and drainage issues.',
        'how_works' => [
            'Water Sources' => 'Multiple reservoirs and treatment plants provide clean water',
            'Distribution Network' => '2,500 km of pipelines across the city',
            'Quality Control' => 'Daily water quality testing in all zones',
            'Emergency Response' => '24/7 emergency number for urgent issues',
            'Maintenance' => 'Regular pipeline maintenance and leak detection'
        ],
        'achievements' => 'Providing clean water to 500,000+ citizens | 95% citywide coverage | 24/7 emergency response',
        'contact' => 'Water Department Office, Municipal Building | Phone: (0253) 2345-6789 | Email: water@civicare.gov'
    ],
    2 => [
        'formed' => '1990',
        'works' => 'The Electricity Department manages municipal power supply, electrical infrastructure, street lighting, and public services electricity. They address power outages, electrical faults, street light maintenance, and billing issues.',
        'how_works' => [
            'Power Distribution' => 'High-voltage transmission to local substations',
            'Street Lighting' => 'Smart LED lighting system in all areas',
            'Fault Resolution' => 'Rapid response team for power outages',
            'Maintenance' => 'Preventive maintenance of electrical infrastructure',
            'Public Services' => 'Dedicated power supply to hospitals, police, fire stations'
        ],
        'achievements' => 'Uninterrupted power supply in 98 wards | 15,000+ street lights maintained | Average response time: 2 hours',
        'contact' => 'Electricity Department, Main Office | Phone: (0253) 2345-6790 | Email: electricity@civicare.gov'
    ],
    3 => [
        'formed' => '1988',
        'works' => 'The Road Department maintains and constructs municipal roads and highways. They handle pothole repairs, road construction, traffic management, and infrastructure improvements across the city.',
        'how_works' => [
            'Road Maintenance' => 'Regular patching and resurfacing',
            'Traffic Management' => 'Signal systems and traffic flow optimization',
            'Construction' => 'New road projects and expansions',
            'Drainage' => 'Storm water drainage system maintenance',
            'Safety' => 'Road markings, signs, and safety measures'
        ],
        'achievements' => 'Maintained 1,200 km roads | 50+ new roads constructed | Zero-fatality safety initiative',
        'contact' => 'Road Department Headquarters | Phone: (0253) 2345-6791 | Email: roads@civicare.gov'
    ],
    4 => [
        'formed' => '1992',
        'works' => 'The Sanitation Department manages garbage collection, street cleaning, waste management, and environmental hygiene. They work to maintain cleanliness across all city areas and handle waste disposal efficiently.',
        'how_works' => [
            'Waste Collection' => 'Daily collection from residential and commercial areas',
            'Street Cleaning' => 'Morning and evening street cleaning services',
            'Landfill Management' => 'Proper waste segregation and disposal',
            'Recycling' => 'Waste composting and recycling programs',
            'Public Health' => 'Pest control and sanitization programs'
        ],
        'achievements' => '100% waste collection coverage | 98% cleanliness standards maintained | 500+ sanitation workers',
        'contact' => 'Sanitation Department Office | Phone: (0253) 2345-6792 | Email: sanitation@civicare.gov'
    ],
    5 => [
        'formed' => '1987',
        'works' => 'The Public Works Department handles maintenance of public buildings, community centers, municipal offices, and public infrastructure. They ensure all government facilities are well-maintained and accessible.',
        'how_works' => [
            'Building Maintenance' => 'Regular maintenance of all municipal buildings',
            'Community Centers' => 'Management of public gathering spaces',
            'Infrastructure' => 'Maintenance of bridges, tunnels, and public structures',
            'Repairs' => 'Quick repair services for damaged facilities',
            'Upgrades' => 'Modernization of aging infrastructure'
        ],
        'achievements' => 'Maintained 200+ public buildings | 50+ community centers operational | Quick maintenance response',
        'contact' => 'Public Works Department | Phone: (0253) 2345-6793 | Email: publicworks@civicare.gov'
    ],
    6 => [
        'formed' => '1995',
        'works' => 'The Parks Department manages municipal parks, gardens, green spaces, and recreational areas. They handle landscaping, maintenance of public gardens, tree plantation programs, and public space development.',
        'how_works' => [
            'Park Maintenance' => 'Daily maintenance of all municipal parks',
            'Landscaping' => 'Professional landscaping and gardening',
            'Tree Plantation' => 'Annual tree planting and care programs',
            'Recreation' => 'Management of playgrounds and sports facilities',
            'Environmental' => 'Green city initiative and biodiversity programs'
        ],
        'achievements' => 'Maintained 50+ parks and gardens | 100,000+ trees planted | Green city initiative',
        'contact' => 'Parks Department, Green Office | Phone: (0253) 2345-6794 | Email: parks@civicare.gov'
    ]
];

$info = $deptDetails[$dept_id] ?? null;

if (!$info) {
    header("Location: departments.php");
    exit();
}

// Get complaint statistics for this department
$sql_stats = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) as resolved,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending
    FROM complaints WHERE department_id = ?";
$stmt = $conn->prepare($sql_stats);
$stmt->bind_param("i", $dept_id);
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($dept['name']); ?> - CiviCare</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8f9fa;
            color: #333;
        }

        header {
            background: linear-gradient(135deg, #ff6b35 0%, #ff8555 100%);
            color: white;
            padding: 3rem 0;
        }

        .header-content {
            max-width: 1000px;
            margin: 0 auto;
            padding: 0 2rem;
        }

        .header-top {
            display: flex;
            align-items: center;
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .dept-icon {
            font-size: 4rem;
            opacity: 0.9;
        }

        .header-content h1 {
            font-size: 2.5rem;
            margin-bottom: 0.5rem;
        }

        .header-content p {
            font-size: 1.05rem;
            opacity: 0.95;
        }

        .container {
            max-width: 1000px;
            margin: 0 auto;
            padding: 0 2rem 4rem;
        }

        .nav-back {
            margin: 2rem 0 3rem 0;
        }

        .nav-back a {
            color: #1e3c72;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s;
        }

        .nav-back a:hover {
            color: #ff6b35;
        }

        .content-section {
            background: white;
            padding: 2rem;
            margin-bottom: 2rem;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .section-title {
            font-size: 1.5rem;
            color: #1e3c72;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.8rem;
        }

        .section-title i {
            color: #ff6b35;
            font-size: 1.8rem;
        }

        .section-content {
            color: #555;
            line-height: 1.8;
            font-size: 1.05rem;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin: 2rem 0;
        }

        .stat-box {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            color: white;
            padding: 1.5rem;
            border-radius: 8px;
            text-align: center;
        }

        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            display: block;
        }

        .stat-label {
            font-size: 0.9rem;
            opacity: 0.9;
            margin-top: 0.5rem;
        }

        .how-works-list {
            list-style: none;
        }

        .how-works-list li {
            padding: 1rem 0;
            border-bottom: 1px solid #eee;
            display: flex;
            gap: 1rem;
        }

        .how-works-list li:last-child {
            border-bottom: none;
        }

        .how-works-list strong {
            color: #1e3c72;
            min-width: 150px;
            font-size: 1.05rem;
        }

        .cta-buttons {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
            flex-wrap: wrap;
        }

        .btn {
            padding: 0.8rem 1.8rem;
            border-radius: 4px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s;
            border: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-primary {
            background: linear-gradient(135deg, #ff6b35 0%, #ff8555 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(255, 107, 53, 0.3);
        }

        .btn-secondary {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            color: white;
        }

        .btn-secondary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(30, 60, 114, 0.3);
        }

        footer {
            background: #1a1a1a;
            color: white;
            padding: 2rem 0 1rem;
            margin-top: 4rem;
            text-align: center;
        }

        @media (max-width: 768px) {
            .header-top {
                flex-direction: column;
                align-items: flex-start;
            }

            .header-content h1 {
                font-size: 1.8rem;
            }

            .container {
                padding: 0 1rem 2rem;
            }

            .cta-buttons {
                flex-direction: column;
            }

            .btn {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <!-- HEADER -->
    <header>
        <div class="header-content">
            <div class="header-top">
                <div class="dept-icon">
                    <?php
                        $icons = [
                            1 => 'fa-tint',
                            2 => 'fa-bolt',
                            3 => 'fa-road',
                            4 => 'fa-trash',
                            5 => 'fa-tools',
                            6 => 'fa-leaf'
                        ];
                        echo '<i class="fas ' . $icons[$dept['id']] . '"></i>';
                    ?>
                </div>
                <div>
                    <h1><?php echo htmlspecialchars($dept['name']); ?></h1>
                    <p>Established in <?php echo htmlspecialchars($info['formed']); ?></p>
                </div>
            </div>
        </div>
    </header>

    <div class="container">
        <div class="nav-back">
            <a href="departments.php"><i class="fas fa-arrow-left"></i> Back to All Departments</a>
        </div>

        <!-- OVERVIEW SECTION -->
        <div class="content-section">
            <div class="section-title">
                <i class="fas fa-info-circle"></i>
                Overview
            </div>
            <div class="section-content">
                <?php echo htmlspecialchars($info['works']); ?>
            </div>
        </div>

        <!-- STATISTICS SECTION -->
        <?php if ($stats['total'] > 0): ?>
        <div class="content-section">
            <div class="section-title">
                <i class="fas fa-chart-bar"></i>
                Performance Statistics
            </div>
            <div class="stats-grid">
                <div class="stat-box">
                    <span class="stat-number"><?php echo $stats['total']; ?></span>
                    <span class="stat-label">Total Complaints</span>
                </div>
                <div class="stat-box">
                    <span class="stat-number"><?php echo $stats['resolved']; ?></span>
                    <span class="stat-label">Resolved</span>
                </div>
                <div class="stat-box">
                    <span class="stat-number"><?php echo $stats['pending']; ?></span>
                    <span class="stat-label">Pending</span>
                </div>
                <div class="stat-box">
                    <span class="stat-number"><?php echo $stats['total'] > 0 ? round(($stats['resolved'] / $stats['total']) * 100) : 0; ?>%</span>
                    <span class="stat-label">Resolution Rate</span>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- HOW IT WORKS SECTION -->
        <div class="content-section">
            <div class="section-title">
                <i class="fas fa-cogs"></i>
                How This Department Works
            </div>
            <ul class="how-works-list">
                <?php foreach ($info['how_works'] as $key => $value): ?>
                <li>
                    <strong><?php echo htmlspecialchars($key); ?></strong>
                    <span><?php echo htmlspecialchars($value); ?></span>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>

        <!-- ACHIEVEMENTS SECTION -->
        <div class="content-section">
            <div class="section-title">
                <i class="fas fa-trophy"></i>
                Key Achievements
            </div>
            <div class="section-content">
                <?php echo htmlspecialchars($info['achievements']); ?>
            </div>
        </div>

        <!-- CONTACT SECTION -->
        <div class="content-section">
            <div class="section-title">
                <i class="fas fa-phone"></i>
                Contact Information
            </div>
            <div class="section-content">
                <?php echo htmlspecialchars($info['contact']); ?>
            </div>
            <div class="cta-buttons">
                <a href="user/new_complaint.php?dept_id=<?php echo $dept['id']; ?>" class="btn btn-primary">
                    <i class="fas fa-briefcase"></i>
                    File a Complaint
                </a>
                <a href="contact.php" class="btn btn-secondary">
                    <i class="fas fa-envelope"></i>
                    Send Message
                </a>
            </div>
        </div>
    </div>

    <footer>
        <p>&copy; 2026 CiviCare - Municipal Services. All rights reserved.</p>
    </footer>
</body>
</html>
