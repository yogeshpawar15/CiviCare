<?php
// civicare/about.php
require_once 'config/database.php';
require_once 'config/functions.php';

$conn = getDatabaseConnection();

// Get real statistics from database
$total_complaints = $conn->query("SELECT COUNT(*) as count FROM complaints")->fetch_assoc()['count'];
$resolved_complaints = $conn->query("SELECT COUNT(*) as count FROM complaints WHERE status IN ('resolved', 'closed')")->fetch_assoc()['count'];
$active_departments = $conn->query("SELECT COUNT(*) as count FROM departments")->fetch_assoc()['count'];
$total_citizens = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'citizen'")->fetch_assoc()['count'];

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About Us - Civicare Municipal Complaint System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #333;
            background: #f8f9fa;
        }

        /* ===== MAIN HEADER ===== */
        .main-header {
            background: white;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .header-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 12px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo-section {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .logo {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, #1e3c72, #2a5298);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 24px;
            font-weight: bold;
            text-decoration: none;
        }

        .logo-text h1 {
            font-size: 20px;
            color: #1e3c72;
            margin: 0;
        }

        .logo-text p {
            font-size: 11px;
            color: #666;
            margin: 2px 0 0 0;
        }

        /* ===== NAVIGATION - ONLY 3 TABS ===== */
        .nav-container {
            display: flex;
            gap: 0;
        }

        nav ul {
            list-style: none;
            display: flex;
            gap: 5px;
        }

        nav > ul > li {
            position: relative;
        }

        nav > ul > li > a {
            display: block;
            padding: 15px 20px;
            color: #333;
            text-decoration: none;
            font-weight: 500;
            border-bottom: 3px solid transparent;
            transition: all 0.3s;
            font-size: 0.95rem;
        }

        nav > ul > li > a:hover {
            background: #f0f0f0;
            color: #1e3c72;
        }

        nav > ul > li > a.active {
            color: #1e3c72;
            border-bottom-color: #ff6b35;
        }

        /* ===== DROPDOWN ===== */
        .dropdown {
            position: relative;
        }

        .dropdown-menu {
            display: none;
            position: absolute;
            top: 100%;
            left: 0;
            background: white;
            min-width: 200px;
            list-style: none;
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.1);
            border-radius: 0 0 4px 4px;
            z-index: 100;
        }

        .dropdown:hover .dropdown-menu {
            display: block;
            animation: slideDown 0.3s ease-out;
        }

        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .dropdown-menu li a {
            display: block;
            padding: 10px 18px;
            color: #333;
            text-decoration: none;
            transition: all 0.3s;
            font-size: 0.9rem;
        }

        .dropdown-menu li a:hover {
            background: #f0f0f0;
            color: #1e3c72;
            padding-left: 22px;
        }

        .dropdown > a::after {
            content: ' \f107';
            font-family: 'Font Awesome 6 Free';
            font-weight: 900;
            font-size: 0.65rem;
            margin-left: 5px;
        }

        /* ===== BUTTONS ===== */
        .btn {
            display: inline-block;
            padding: 8px 20px;
            text-decoration: none;
            border-radius: 4px;
            font-weight: 600;
            transition: all 0.3s;
            border: none;
            cursor: pointer;
            font-size: 0.9rem;
        }

        .btn-primary {
            background: #ff6b35;
            color: white;
        }

        .btn-primary:hover {
            background: #e55a24;
            transform: translateY(-2px);
        }

        /* ===== HERO SECTION ===== */
        .hero {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            color: white;
            padding: 60px 20px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .hero::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1200 600"><rect fill="%23fff" opacity="0.05" width="1200" height="600"/></svg>');
            opacity: 0.1;
        }

        .hero-content {
            max-width: 800px;
            margin: 0 auto;
            position: relative;
            z-index: 1;
        }

        .hero h2 {
            font-size: 36px;
            margin-bottom: 12px;
            font-weight: 700;
        }

        .hero p {
            font-size: 16px;
            margin-bottom: 25px;
            opacity: 0.95;
        }

        /* ===== SECTIONS ===== */
        .container {
            max-width: 1100px;
            margin: 0 auto;
            padding: 0 20px;
        }

        .section {
            padding: 50px 0;
        }

        .section-title {
            text-align: center;
            margin-bottom: 40px;
        }

        .section-title h3 {
            font-size: 28px;
            color: #1e3c72;
            margin-bottom: 10px;
        }

        .section-title .title-underline {
            height: 4px;
            width: 50px;
            background: #ff6b35;
            margin: 0 auto 12px;
        }

        .section-title p {
            color: #666;
            font-size: 15px;
        }

        /* ===== MISSION SECTION ===== */
        .mission-section {
            background: white;
            padding: 50px 20px;
        }

        .mission-content {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 40px;
            align-items: center;
        }

        .mission-text {
            font-size: 0.95rem;
            color: #555;
            line-height: 1.7;
        }

        .mission-text p {
            margin-bottom: 1rem;
        }

        .mission-vision {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-top: 1.5rem;
        }

        .vision-card, .values-card {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 6px;
            border-left: 3px solid #2a5298;
        }

        .values-card {
            border-left-color: #ff6b35;
        }

        .vision-card h4, .values-card h4 {
            color: #1e3c72;
            margin-bottom: 8px;
            font-size: 1rem;
        }

        .highlight {
            color: #ff6b35;
            font-weight: 600;
        }

        .mission-image {
            background: linear-gradient(135deg, #1e3c72, #2a5298);
            border-radius: 8px;
            padding: 30px;
            color: white;
            text-align: center;
        }

        .mission-image h3 {
            margin-bottom: 8px;
        }

        .mission-image .stats-row {
            display: flex;
            justify-content: center;
            gap: 25px;
            margin-top: 15px;
        }

        .mission-image .stat-item {
            text-align: center;
        }

        .mission-image .stat-num {
            font-size: 1.8rem;
            font-weight: bold;
            color: #ff6b35;
        }

        .mission-image .stat-label {
            font-size: 0.85rem;
            opacity: 0.9;
        }

        /* ===== STATISTICS SECTION ===== */
        .statistics {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            color: white;
            padding: 50px 20px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            max-width: 1100px;
            margin: 0 auto;
        }

        .stat-card {
            text-align: center;
            padding: 25px 15px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .stat-number {
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 8px;
            color: #ff6b35;
        }

        .stat-label {
            font-size: 14px;
            opacity: 0.9;
        }

        /* ===== FEATURES SECTION ===== */
        .features-section {
            background: white;
            padding: 50px 20px;
        }

        .features-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
        }

        .feature-card {
            padding: 25px 20px;
            background: #f8f9fa;
            border-radius: 8px;
            text-align: center;
            transition: all 0.3s;
            border: 1px solid #e0e0e0;
        }

        .feature-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .feature-icon {
            font-size: 40px;
            color: #2a5298;
            margin-bottom: 12px;
        }

        .feature-card h4 {
            color: #1e3c72;
            margin-bottom: 8px;
            font-size: 1.1rem;
        }

        .feature-card p {
            color: #666;
            font-size: 0.85rem;
            line-height: 1.5;
        }

        /* ===== HOW IT WORKS SECTION ===== */
        .how-it-works {
            background: #f8f9fa;
            padding: 50px 20px;
        }

        .process-steps {
            display: flex;
            justify-content: center;
            gap: 30px;
            margin-top: 30px;
            flex-wrap: wrap;
        }

        .process-step {
            flex: 1;
            min-width: 180px;
            max-width: 200px;
            text-align: center;
            position: relative;
        }

        .process-step::after {
            content: '\f061';
            font-family: 'Font Awesome 6 Free';
            font-weight: 900;
            position: absolute;
            right: -20px;
            top: 25px;
            color: #2a5298;
            font-size: 1.2rem;
        }

        .process-step:last-child::after {
            display: none;
        }

        .step-number {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #1e3c72, #2a5298);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 12px;
            font-size: 1.5rem;
            font-weight: bold;
        }

        .process-step h4 {
            color: #1e3c72;
            margin-bottom: 6px;
            font-size: 1rem;
        }

        .process-step p {
            color: #666;
            font-size: 0.8rem;
            line-height: 1.4;
        }

        /* ===== CTA BUTTON ===== */
        .cta-button {
            display: inline-block;
            padding: 12px 28px;
            background: #ff6b35;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            font-weight: 600;
            font-size: 0.95rem;
            transition: all 0.3s;
            margin-top: 25px;
        }

        .cta-button:hover {
            background: #e55a24;
            transform: translateY(-2px);
        }

        /* ===== FOOTER - SMALLER ===== */
        .footer {
            background: #1a1a1a;
            color: #fff;
            padding: 25px 20px 10px;
        }

        .footer-content {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 15px;
            max-width: 1200px;
            margin-left: auto;
            margin-right: auto;
        }

        .footer-section h4 {
            color: #ff6b35;
            margin-bottom: 10px;
            font-size: 14px;
            font-weight: 700;
        }

        .footer-section ul {
            list-style: none;
        }

        .footer-section ul li {
            margin-bottom: 5px;
        }

        .footer-section ul li a {
            color: #aaa;
            text-decoration: none;
            transition: color 0.3s;
            font-size: 14px;
        }

        .footer-section ul li a:hover {
            color: #ff6b35;
            padding-left: 5px;
        }

        .footer-section p {
            color: #aaa;
            font-size: 13px;
            line-height: 1.5;
        }

        .footer-contact {
            display: flex;
            gap: 10px;
            align-items: flex-start;
            margin-bottom: 8px;
            font-size: 13px;
        }

        .footer-contact i {
            color: #ff6b35;
            font-size: 14px;
            margin-top: 2px;
            flex-shrink: 0;
        }

        .footer-contact a {
            color: #ff6b35;
            text-decoration: none;
        }

        .footer-contact a:hover {
            text-decoration: underline;
        }

        .footer-social {
            display: flex;
            gap: 10px;
            margin-top: 10px;
        }

        .footer-social a {
            color: white;
            font-size: 16px;
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #333;
            border-radius: 50%;
            transition: all 0.3s;
        }

        .footer-social a:hover {
            background: #ff6b35;
            transform: translateY(-3px);
        }

        .footer-bottom {
            border-top: 1px solid #333;
            padding-top: 10px;
            text-align: center;
            color: #aaa;
            font-size: 11px;
            max-width: 1200px;
            margin: 0 auto;
        }

        .footer-bottom p {
            margin-bottom: 4px;
        }

        /* ===== RESPONSIVE ===== */
        @media (max-width: 900px) {
            .mission-content {
                grid-template-columns: 1fr;
            }
            
            .mission-image {
                order: -1;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .features-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 768px) {
            .logo-text p {
                display: none;
            }

            nav > ul > li > a {
                padding: 12px 15px;
                font-size: 0.85rem;
            }

            .hero h2 {
                font-size: 26px;
            }

            .hero p {
                font-size: 14px;
            }

            .section-title h3 {
                font-size: 22px;
            }

            .stats-grid {
                grid-template-columns: 1fr 1fr;
            }

            .features-grid {
                grid-template-columns: 1fr;
            }

            .mission-vision {
                grid-template-columns: 1fr;
            }

            .process-step::after {
                display: none;
            }

            .process-steps {
                flex-direction: column;
                align-items: center;
            }

            .footer-content {
                flex-direction: column;
                gap: 25px;
            }
        }

        @media (max-width: 576px) {
            .header-content {
                flex-direction: column;
                gap: 10px;
            }

            nav ul {
                width: 100%;
                flex-direction: column;
                text-align: center;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
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

            <!-- NAVIGATION - ONLY 3 TABS -->
            <nav class="nav-container">
                <ul>
                    <li><a href="index.php">Home</a></li>
                    <li><a href="about.php" class="active">About</a></li>
                    <li class="dropdown">
                        <a href="#">Login</a>
                        <ul class="dropdown-menu">
                            <li><a href="user/login.php"><span class="icon">👤</span><span>Citizen Login</span></a></li>
                            <li><a href="staff/login.php"><span class="icon">👨‍💼</span><span>Staff Login</span></a></li>
                            <li><a href="admin/login.php"><span class="icon">👨‍💻</span><span>Admin Login</span></a></li>
                        </ul>
                    </li>
                </ul>
            </nav>
        </div>
    </header>

    <!-- HERO SECTION -->
    <section class="hero">
        <div class="hero-content">
            <h2>Building Better Communities Together</h2>
            <p>Your voice matters. Civicare bridges the gap between citizens and municipal authorities.</p>
            <a href="user/register.php" class="btn btn-primary">Join Our Community</a>
        </div>
    </section>

    <div class="container">
        <!-- MISSION SECTION -->
        <section class="mission-section">
            <div class="section-title">
                <h3>Our Mission & Vision</h3>
                <div class="title-underline"></div>
            </div>
            <div class="mission-content">
                <div class="mission-text">
                    <p>Civicare is not just a complaint management system; it's a <span class="highlight">digital revolution in municipal governance</span>. Born from the need for transparent and efficient public service delivery, we've created a platform that empowers citizens.</p>
                    
                    <div class="mission-vision">
                        <div class="vision-card">
                            <h4><i class="fas fa-trophy"></i> Our Vision</h4>
                            <p>To create smart, responsive cities where every citizen's concern is heard and resolved efficiently.</p>
                        </div>
                        <div class="values-card">
                            <h4><i class="fas fa-star"></i> Our Values</h4>
                            <p>Transparency, Accountability, Efficiency, and Community Engagement.</p>
                        </div>
                    </div>
                    
                    <p>Since 2020, we've helped over <span class="highlight"><?php echo $total_citizens; ?>+ citizens</span> report and resolve civic issues.</p>
                </div>
                <div class="mission-image">
                    <i class="fas fa-city" style="font-size: 50px; margin-bottom: 15px;"></i>
                    <h3>Digital Governance</h3>
                    <p>Transforming Municipal Services</p>
                    <div class="stats-row">
                        <div class="stat-item">
                            <div class="stat-num">5+</div>
                            <div class="stat-label">Cities</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-num"><?php echo $total_citizens; ?>+</div>
                            <div class="stat-label">Citizens</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-num">98%</div>
                            <div class="stat-label">Satisfaction</div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- STATISTICS SECTION -->
        <section class="statistics">
            <div class="section-title" style="color: white;">
                <h3 style="color: white;">Making Real Impact</h3>
                <div class="title-underline"></div>
                <p style="color: rgba(255,255,255,0.9);">Numbers that tell our story</p>
            </div>
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $total_complaints; ?>+</div>
                    <div class="stat-label">Complaints Resolved</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $resolved_complaints; ?>+</div>
                    <div class="stat-label">Issues Closed</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $active_departments; ?></div>
                    <div class="stat-label">Departments</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number">24/7</div>
                    <div class="stat-label">Support</div>
                </div>
            </div>
        </section>

        <!-- FEATURES SECTION -->
        <section class="features-section">
            <div class="section-title">
                <h3>Why Choose Civicare</h3>
                <div class="title-underline"></div>
                <p>Revolutionizing municipal complaint management</p>
            </div>
            <div class="features-grid">
                <div class="feature-card">
                    <div class="feature-icon"><i class="fas fa-bolt"></i></div>
                    <h4>Smart Routing</h4>
                    <p>AI-powered complaint categorization reducing processing time by 70%</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon"><i class="fas fa-mobile-alt"></i></div>
                    <h4>Mobile-First</h4>
                    <p>Seamless experience across all devices with offline filing</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon"><i class="fas fa-link"></i></div>
                    <h4>Department Integration</h4>
                    <p>Real-time sync between all municipal departments</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon"><i class="fas fa-chart-bar"></i></div>
                    <h4>Analytics Dashboard</h4>
                    <p>Comprehensive reporting for planning and tracking</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon"><i class="fas fa-shield-alt"></i></div>
                    <h4>Secure & Private</h4>
                    <p>End-to-end encryption and GDPR compliance</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon"><i class="fas fa-globe"></i></div>
                    <h4>Multi-Language</h4>
                    <p>Available in regional languages for accessibility</p>
                </div>
            </div>
        </section>

        <!-- HOW IT WORKS SECTION -->
        <section class="how-it-works" id="how-it-works">
            <div class="section-title">
                <h3>How Civicare Works</h3>
                <div class="title-underline"></div>
                <p>A simple process from complaint to resolution</p>
            </div>
            <div class="process-steps">
                <div class="process-step">
                    <div class="step-number">1</div>
                    <h4>Register</h4>
                    <p>Create your citizen account</p>
                </div>
                <div class="process-step">
                    <div class="step-number">2</div>
                    <h4>File Complaint</h4>
                    <p>Report issues with details</p>
                </div>
                <div class="process-step">
                    <div class="step-number">3</div>
                    <h4>Smart Routing</h4>
                    <p>AI assigns to department</p>
                </div>
                <div class="process-step">
                    <div class="step-number">4</div>
                    <h4>Track Progress</h4>
                    <p>Real-time updates</p>
                </div>
                <div class="process-step">
                    <div class="step-number">5</div>
                    <h4>Resolution</h4>
                    <p>Get feedback & rating</p>
                </div>
            </div>
            <div style="text-align: center;">
                <a href="user/new_complaint.php" class="cta-button">File Your First Complaint</a>
            </div>
        </section>
    </div>

    <!-- FOOTER - SMALLER -->
    <footer class="footer">
        <div class="footer-content">
            <div class="footer-section" style="flex: 2;">
                <h4>About CiviCare</h4>
                <p>Digital platform transforming municipal governance through technology and citizen engagement since 2020.</p>
                <div class="footer-social">
                    <a href="#"><i class="fab fa-facebook-f"></i></a>
                    <a href="#"><i class="fab fa-twitter"></i></a>
                    <a href="#"><i class="fab fa-youtube"></i></a>
                    <a href="#"><i class="fab fa-instagram"></i></a>
                </div>
            </div>
            <div class="footer-section">
                <h4>Quick Links</h4>
                <ul>
                    <li><a href="index.php">Home</a></li>
                    <li><a href="about.php">About Us</a></li>
                    <li><a href="user/login.php">Login</a></li>
                </ul>
            </div>
            <div class="footer-section">
                <h4>Contact Us</h4>
                <div class="footer-contact">
                    <i class="fas fa-map-marker-alt"></i>
                    <span>Municipal Corp Building, City Center</span>
                </div>
                <div class="footer-contact">
                    <i class="fas fa-phone"></i>
                    <span><a href="tel:+919876543210">+(91) 9876543210</a></span>
                </div>
                <div class="footer-contact">
                    <i class="fas fa-envelope"></i>
                    <span><a href="mailto:info@civicare.gov.in">info@civicare.gov.in</a></span>
                </div>
            </div>
        </div>
        <div class="footer-bottom">
            <p>&copy; <?php echo date('Y'); ?> CiviCare - Municipal Complaint Management System. All rights reserved.</p>
            <p><a href="#">Privacy Policy</a> | <a href="#">Terms</a> | <a href="#">Sitemap</a></p>
        </div>
    </footer>

    <script>
        // Smooth scroll
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function(e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({ behavior: 'smooth' });
                }
            });
        });
    </script>
</body>
</html>
