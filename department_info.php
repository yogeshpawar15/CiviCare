 <?php
// civicare/department_info.php - Individual Department Information Page
session_start();
require_once 'config/database.php';

$conn = getDatabaseConnection();

// Get department ID from URL
$dept_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($dept_id <= 0) {
    header('Location: index.php');
    exit();
}

// Fetch department details
$sql = "SELECT * FROM departments WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $dept_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header('Location: index.php');
    exit();
}

$department = $result->fetch_assoc();

// Department-specific information (can be stored in database later)
$dept_info = [
    'Water Supply' => [
        'icon' => '💧',
        'formed' => 'January 1995',
        'mission' => 'To ensure 24x7 clean and safe drinking water supply to all citizens of the municipal area.',
        'services' => [
            'Water Supply Management',
            'Pipeline Maintenance',
            'Water Quality Testing',
            'New Connection Services',
            'Leak Detection & Repair',
            'Water Billing Services'
        ],
        'how_works' => 'Our water supply department operates through a network of treatment plants, pumping stations, and distribution pipelines covering the entire municipal area. We monitor water quality daily and maintain a 24/7 emergency response team for immediate leak repairs.',
        'achievements' => [
            'Installed 500+ km of new pipelines',
            'Achieved 95% coverage in municipal area',
            'Reduced water wastage by 30%',
            '24/7 customer helpline established'
        ],
        'contact_person' => 'Mr. Rajesh Kumar',
        'designation' => 'Chief Engineer, Water Supply',
        'phone' => '+(91) 9876543211',
        'email' => 'water@civicare.gov.in'
    ],
    'Electricity' => [
        'icon' => '⚡',
        'formed' => 'March 1998',
        'mission' => 'To provide uninterrupted power supply and maintain electrical infrastructure across the municipality.',
        'services' => [
            'Street Light Maintenance',
            'Power Distribution',
            'Electrical Safety Inspections',
            'Emergency Power Restoration',
            'New Electrical Connections',
            'Energy Conservation Programs'
        ],
        'how_works' => 'The electricity department manages street lighting, municipal building power supply, and coordinates with state electricity board for power distribution. We have dedicated teams for maintenance and emergency response.',
        'achievements' => [
            'Upgraded to LED street lights (80% coverage)',
            'Reduced power consumption by 25%',
            'Installed solar panels on municipal buildings',
            'Smart grid implementation in progress'
        ],
        'contact_person' => 'Mrs. Priya Sharma',
        'designation' => 'Electrical Engineer',
        'phone' => '+(91) 9876543212',
        'email' => 'electricity@civicare.gov.in'
    ],
    'Roads' => [
        'icon' => '🛣️',
        'formed' => 'July 1992',
        'mission' => 'To develop and maintain a comprehensive road network ensuring safe and smooth transportation.',
        'services' => [
            'Road Construction & Expansion',
            'Pothole Repair & Maintenance',
            'Road Signage Installation',
            'Traffic Management',
            'Footpath Development',
            'Road Surface Quality Testing'
        ],
        'how_works' => 'Our roads department conducts regular inspections, plans infrastructure development, and executes maintenance work. We use modern technology for road quality assessment and prioritize repairs based on traffic density and road condition.',
        'achievements' => [
            'Constructed 200+ km new roads',
            'Repaired 15,000+ potholes annually',
            'Installed 5,000+ LED traffic signals',
            'Developed 50+ km footpaths'
        ],
        'contact_person' => 'Mr. Anil Deshmukh',
        'designation' => 'Chief Engineer, Roads',
        'phone' => '+(91) 9876543213',
        'email' => 'roads@civicare.gov.in'
    ],
    'Sanitation' => [
        'icon' => '🧹',
        'formed' => 'September 1990',
        'mission' => 'To maintain cleanliness and hygiene through efficient waste management and sanitation services.',
        'services' => [
            'Door-to-Door Garbage Collection',
            'Street Cleaning',
            'Waste Segregation Programs',
            'Public Toilet Maintenance',
            'Drainage System Cleaning',
            'Recycling Initiatives'
        ],
        'how_works' => 'The sanitation department operates through scheduled garbage collection, street cleaning teams, and waste processing facilities. We promote waste segregation at source and have established recycling centers.',
        'achievements' => [
            'Achieved 85% garbage collection coverage',
            'Implemented waste segregation in 1000+ buildings',
            'Established 5 recycling centers',
            'Built 200+ public toilets'
        ],
        'contact_person' => 'Mr. Suresh Patil',
        'designation' => 'Sanitation Officer',
        'phone' => '+(91) 9876543214',
        'email' => 'sanitation@civicare.gov.in'
    ],
    'Public Works' => [
        'icon' => '🏗️',
        'formed' => 'May 1988',
        'mission' => 'To plan, execute, and maintain municipal infrastructure and public buildings.',
        'services' => [
            'Building Construction & Maintenance',
            'Infrastructure Development',
            'Project Planning & Execution',
            'Quality Control',
            'Building Safety Inspections',
            'Renovation Projects'
        ],
        'how_works' => 'Public Works department handles all construction projects, from planning to execution. We ensure quality standards, timely completion, and maintain all municipal buildings and infrastructure.',
        'achievements' => [
            'Constructed 50+ municipal buildings',
            'Renovated 200+ government schools',
            'Built 10 community centers',
            'Implemented green building standards'
        ],
        'contact_person' => 'Mr. Vikram Singh',
        'designation' => 'Executive Engineer',
        'phone' => '+(91) 9876543215',
        'email' => 'publicworks@civicare.gov.in'
    ],
    'Parks & Gardens' => [
        'icon' => '🌳',
        'formed' => 'April 2000',
        'mission' => 'To develop and maintain green spaces, parks, and recreational areas for citizens.',
        'services' => [
            'Park Development & Maintenance',
            'Tree Plantation Drives',
            'Garden Landscaping',
            'Horticulture Services',
            'Playground Equipment Installation',
            'Environmental Conservation'
        ],
        'how_works' => 'Our department manages 200+ parks and gardens, conducts regular tree plantation drives, and maintains recreational facilities. We work on creating green spaces and promoting environmental awareness.',
        'achievements' => [
            'Developed 50+ new parks',
            'Planted 100,000+ trees',
            'Created 30+ children playgrounds',
            'Won National Green City Award 2025'
        ],
        'contact_person' => 'Mrs. Meera Joshi',
        'designation' => 'Horticulture Officer',
        'phone' => '+(91) 9876543216',
        'email' => 'parks@civicare.gov.in'
    ]
];

$dept_name = $department['name'];
$info = $dept_info[$dept_name] ?? [
    'icon' => '🏛️',
    'formed' => 'Year 2000',
    'mission' => 'To serve the citizens with dedication and efficiency.',
    'services' => ['General Services', 'Complaint Resolution', 'Public Support'],
    'how_works' => 'This department works to provide essential services to all citizens in the municipal area.',
    'achievements' => ['Serving the community since inception'],
    'contact_person' => 'Department Head',
    'designation' => 'Chief Officer',
    'phone' => '+(91) 9876543210',
    'email' => 'info@civicare.gov.in'
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($dept_name); ?> - CiviCare Departments</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f8f9fa;
            color: #333;
            line-height: 1.6;
        }

        /* ===== HEADER TOP BAR ===== */
        .header-top {
            background: #1e3c72;
            color: white;
            padding: 10px 0;
            font-size: 0.9rem;
        }

        .header-top-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header-top-left {
            display: flex;
            gap: 20px;
        }

        .header-top-item {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .header-top-item i {
            color: #ff6b35;
        }

        /* ===== MAIN HEADER ===== */
        .main-header {
            background: white;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        .header-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo-section {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 15px 0;
        }

        .logo {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #1e3c72, #2a5298);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.8rem;
            text-decoration: none;
        }

        .logo-text h1 {
            font-size: 1.8rem;
            color: #1e3c72;
            font-weight: 700;
        }

        .logo-text p {
            font-size: 0.85rem;
            color: #666;
        }

        nav ul {
            list-style: none;
            display: flex;
            gap: 0;
        }

        nav > ul > li > a {
            display: block;
            padding: 20px 18px;
            color: #333;
            text-decoration: none;
            font-weight: 500;
            border-bottom: 3px solid transparent;
            transition: all 0.3s;
        }

        nav > ul > li > a:hover {
            background: #f0f0f0;
            color: #1e3c72;
            border-bottom-color: #ff6b35;
        }

        /* ===== HERO SECTION ===== */
        .dept-hero {
            background: linear-gradient(135deg, #1e3c72, #2a5298);
            color: white;
            padding: 80px 0 60px;
            text-align: center;
        }

        .dept-hero-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }

        .dept-icon {
            font-size: 5rem;
            margin-bottom: 20px;
        }

        .dept-hero h1 {
            font-size: 3rem;
            margin-bottom: 15px;
        }

        .dept-hero .tagline {
            font-size: 1.2rem;
            opacity: 0.9;
            margin-bottom: 10px;
        }

        .dept-hero .formed {
            font-size: 0.95rem;
            opacity: 0.8;
        }

        .breadcrumb-links {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-top: 20px;
            font-size: 0.9rem;
        }

        .breadcrumb-links a {
            color: #fff;
            text-decoration: none;
        }

        .breadcrumb-links a:hover {
            color: #ff6b35;
        }

        /* ===== MAIN CONTENT ===== */
        .container {
            max-width: 1200px;
            margin: 40px auto;
            padding: 0 20px;
        }

        .content-section {
            background: white;
            border-radius: 12px;
            padding: 40px;
            margin-bottom: 30px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
        }

        .content-section h2 {
            color: #1e3c72;
            font-size: 2rem;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 3px solid #ff6b35;
            display: inline-block;
        }

        .content-section p {
            font-size: 1.05rem;
            color: #555;
            line-height: 1.8;
            margin-bottom: 15px;
        }

        .services-list {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }

        .service-item {
            background: #f8f9fa;
            padding: 15px 20px;
            border-radius: 8px;
            border-left: 4px solid #ff6b35;
            display: flex;
            align-items: center;
            gap: 12px;
            transition: all 0.3s;
        }

        .service-item:hover {
            background: #e9ecef;
            transform: translateX(5px);
        }

        .service-item i {
            color: #1e3c72;
            font-size: 1.3rem;
        }

        .achievements-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .achievement-card {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 25px;
            border-radius: 10px;
            text-align: center;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
        }

        .achievement-card i {
            font-size: 2.5rem;
            margin-bottom: 15px;
            opacity: 0.9;
        }

        .achievement-card p {
            color: white;
            font-size: 1.05rem;
        }

        /* ===== CONTACT CARD ===== */
        .contact-card {
            background: linear-gradient(135deg, #1e3c72, #2a5298);
            color: white;
            border-radius: 12px;
            padding: 40px;
            margin-top: 30px;
        }

        .contact-card h3 {
            font-size: 1.8rem;
            margin-bottom: 25px;
        }

        .contact-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }

        .contact-item {
            display: flex;
            align-items: flex-start;
            gap: 15px;
        }

        .contact-item i {
            font-size: 1.5rem;
            color: #ff6b35;
            margin-top: 5px;
        }

        .contact-item-content h4 {
            font-size: 1.1rem;
            margin-bottom: 5px;
        }

        .contact-item-content p {
            opacity: 0.9;
            font-size: 0.95rem;
            color: white;
        }

        /* ===== CTA SECTION ===== */
        .cta-section {
            text-align: center;
            padding: 40px;
            background: #f8f9fa;
            border-radius: 12px;
            margin-top: 30px;
        }

        .cta-section h3 {
            color: #1e3c72;
            font-size: 1.8rem;
            margin-bottom: 20px;
        }

        .cta-buttons {
            display: flex;
            justify-content: center;
            gap: 20px;
            flex-wrap: wrap;
        }

        .btn {
            display: inline-block;
            padding: 15px 35px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s;
            font-size: 1.05rem;
        }

        .btn-primary {
            background: linear-gradient(135deg, #ff6b35, #ff8c42);
            color: white;
        }

        .btn-primary:hover {
            transform: scale(1.05);
            box-shadow: 0 6px 20px rgba(255, 107, 53, 0.4);
        }

        .btn-secondary {
            background: white;
            color: #1e3c72;
            border: 2px solid #1e3c72;
        }

        .btn-secondary:hover {
            background: #1e3c72;
            color: white;
        }

        /* ===== FOOTER ===== */
        .footer {
            background: #1a1a1a;
            color: #fff;
            margin-top: 60px;
            padding: 40px 0 20px;
        }

        .footer-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 30px;
        }

        .footer-section h4 {
            color: #ff6b35;
            margin-bottom: 15px;
        }

        .footer-section p,
        .footer-section ul {
            color: #ccc;
            line-height: 1.8;
        }

        .footer-section ul {
            list-style: none;
        }

        .footer-section ul li a {
            color: #ccc;
            text-decoration: none;
        }

        .footer-section ul li a:hover {
            color: #ff6b35;
        }

        .footer-bottom {
            text-align: center;
            padding: 20px;
            margin-top: 30px;
            border-top: 1px solid #333;
            color: #999;
        }

        /* ===== RESPONSIVE ===== */
        @media (max-width: 768px) {
            .dept-hero h1 {
                font-size: 2rem;
            }

            .content-section {
                padding: 25px;
            }

            .services-list {
                grid-template-columns: 1fr;
            }

            nav ul {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <!-- TOP HEADER -->
    <div class="header-top">
        <div class="header-top-content">
            <div class="header-top-left">
                <div class="header-top-item">
                    <i class="fas fa-phone"></i>
                    <span>+(91) 9876543210</span>
                </div>
                <div class="header-top-item">
                    <i class="fas fa-envelope"></i>
                    <span>info@civicare.gov.in</span>
                </div>
            </div>
        </div>
    </div>

    <!-- MAIN HEADER -->
    <header class="main-header">
        <div class="header-content">
            <div class="logo-section">
                <a href="index.php" class="logo">
                    <i class="fas fa-city"></i>
                </a>
                <div class="logo-text">
                    <h1>CIVICARE</h1>
                    <p>Municipal Complaint Management</p>
                </div>
            </div>
            <nav>
                <ul>
                    <li><a href="index.php">Home</a></li>
                    <li><a href="about.php">About</a></li>
                    <li><a href="faqs.php">FAQs</a></li>
                    <li><a href="contact.php">Contact</a></li>
                </ul>
            </nav>
        </div>
    </header>

    <!-- HERO SECTION -->
    <section class="dept-hero">
        <div class="dept-hero-content">
            <div class="dept-icon"><?php echo $info['icon']; ?></div>
            <h1><?php echo htmlspecialchars($dept_name); ?></h1>
            <p class="tagline"><?php echo htmlspecialchars($info['mission']); ?></p>
            <p class="formed"><i class="fas fa-calendar-alt"></i> Established: <?php echo $info['formed']; ?></p>
            <div class="breadcrumb-links">
                <a href="index.php"><i class="fas fa-home"></i> Home</a>
                <span>/</span>
                <span>Department</span>
                <span>/</span>
                <span><?php echo htmlspecialchars($dept_name); ?></span>
            </div>
        </div>
    </section>

    <!-- MAIN CONTENT -->
    <div class="container">
        <!-- MISSION SECTION -->
        <div class="content-section">
            <h2><i class="fas fa-bullseye"></i> Our Mission</h2>
            <p><?php echo htmlspecialchars($info['mission']); ?></p>
        </div>

        <!-- SERVICES SECTION -->
        <div class="content-section">
            <h2><i class="fas fa-cogs"></i> Services We Provide</h2>
            <div class="services-list">
                <?php foreach ($info['services'] as $service): ?>
                    <div class="service-item">
                        <i class="fas fa-check-circle"></i>
                        <span><?php echo htmlspecialchars($service); ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- HOW IT WORKS SECTION -->
        <div class="content-section">
            <h2><i class="fas fa-info-circle"></i> How We Work</h2>
            <p><?php echo htmlspecialchars($info['how_works']); ?></p>
        </div>

        <!-- ACHIEVEMENTS SECTION -->
        <div class="content-section">
            <h2><i class="fas fa-trophy"></i> Key Achievements</h2>
            <div class="achievements-grid">
                <?php foreach ($info['achievements'] as $achievement): ?>
                    <div class="achievement-card">
                        <i class="fas fa-award"></i>
                        <p><?php echo htmlspecialchars($achievement); ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- CONTACT SECTION -->
        <div class="contact-card">
            <h3><i class="fas fa-address-book"></i> Contact Department</h3>
            <div class="contact-info">
                <div class="contact-item">
                    <i class="fas fa-user-tie"></i>
                    <div class="contact-item-content">
                        <h4><?php echo htmlspecialchars($info['contact_person']); ?></h4>
                        <p><?php echo htmlspecialchars($info['designation']); ?></p>
                    </div>
                </div>
                <div class="contact-item">
                    <i class="fas fa-phone-alt"></i>
                    <div class="contact-item-content">
                        <h4>Phone</h4>
                        <p><?php echo htmlspecialchars($info['phone']); ?></p>
                    </div>
                </div>
                <div class="contact-item">
                    <i class="fas fa-envelope"></i>
                    <div class="contact-item-content">
                        <h4>Email</h4>
                        <p><?php echo htmlspecialchars($info['email']); ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- CTA SECTION -->
        <div class="cta-section">
            <h3>Have a complaint related to <?php echo htmlspecialchars($dept_name); ?>?</h3>
            <p style="color: #666; margin-bottom: 25px;">File your complaint now and track its resolution in real-time</p>
            <div class="cta-buttons">
                <a href="user/new_complaint.php" class="btn btn-primary">
                    <i class="fas fa-file-medical"></i> File Complaint
                </a>
                <a href="index.php" class="btn btn-secondary">
                    <i class="fas fa-home"></i> Back to Home
                </a>
            </div>
        </div>
    </div>

    <!-- FOOTER -->
    <footer class="footer">
        <div class="footer-content">
            <div class="footer-section">
                <h4>About CiviCare</h4>
                <p>CiviCare is the official municipal complaint management system serving citizens efficiently.</p>
            </div>
            <div class="footer-section">
                <h4>Quick Links</h4>
                <ul>
                    <li><a href="index.php">Home</a></li>
                    <li><a href="about.php">About</a></li>
                    <li><a href="faqs.php">FAQs</a></li>
                    <li><a href="contact.php">Contact</a></li>
                </ul>
            </div>
            <div class="footer-section">
                <h4>Contact</h4>
                <p><i class="fas fa-phone"></i> +(91) 9876543210</p>
                <p><i class="fas fa-envelope"></i> info@civicare.gov.in</p>
            </div>
        </div>
        <div class="footer-bottom">
            <p>&copy; 2026 CiviCare - All rights reserved.</p>
        </div>
    </footer>
</body>
</html>
