<?php
// civicare/index.php
require_once 'config/database.php';
require_once 'config/functions.php';

$conn = getDatabaseConnection();

// Get statistics for the homepage
$total_complaints = 0;
$resolved_complaints = 0;
$pending_complaints = 0;

// Get total complaints
$sql = "SELECT COUNT(*) as total FROM complaints";
$result = $conn->query($sql);
if ($result) {
    $row = $result->fetch_assoc();
    $total_complaints = $row['total'];
}

// Get resolved complaints
$sql = "SELECT COUNT(*) as resolved FROM complaints WHERE status IN ('resolved', 'closed')";
$result = $conn->query($sql);
if ($result) {
    $row = $result->fetch_assoc();
    $resolved_complaints = $row['resolved'];
}

// Get pending complaints
$sql = "SELECT COUNT(*) as pending FROM complaints WHERE status NOT IN ('resolved', 'closed', 'rejected')";
$result = $conn->query($sql);
if ($result) {
    $row = $result->fetch_assoc();
    $pending_complaints = $row['pending'];
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Civicare - Municipal Complaint Management System</title>
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
            display: flex;
            flex-direction: column;
            min-height: 100vh;
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
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo-section {
            display: flex;
            align-items: center;
            gap: 20px;
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
            font-size: 28px;
            font-weight: bold;
            text-decoration: none;
        }

        .logo-text h1 {
            font-size: 24px;
            color: #1e3c72;
            margin: 0;
        }

        .logo-text p {
            font-size: 12px;
            color: #666;
            margin: 3px 0 0 0;
        }

        /* ===== NAVIGATION ===== */
        .nav-container {
            display: flex;
            gap: 0;
        }

        nav {
            width: 100%;
        }

        nav ul {
            list-style: none;
            display: flex;
            gap: 0;
        }

        nav > ul > li {
            position: relative;
        }

        nav > ul > li > a {
            display: block;
            padding: 20px 18px;
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
            border-bottom-color: #ff6b35;
        }

        /* ===== DROPDOWN MENUS ===== */
        .dropdown {
            position: relative;
        }

        .dropdown-menu {
            display: none;
            position: absolute;
            top: 100%;
            left: 0;
            background: white;
            min-width: 250px;
            list-style: none;
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.1);
            border-radius: 0 0 4px 4px;
        }

        .dropdown:hover .dropdown-menu {
            display: block;
        }

        .dropdown-menu li a {
            display: block;
            padding: 12px 20px;
            color: #333;
            text-decoration: none;
            transition: all 0.3s;
            border-left: 3px solid transparent;
        }

        .dropdown-menu li a i {
            color: #ff6b35;
            font-size: 0.75rem;
            margin-right: 8px;
            transition: all 0.3s;
        }

        .dropdown-menu li a:hover {
            background: #f0f0f0;
            color: #1e3c72;
            border-left-color: #ff6b35;
            padding-left: 26px;
        }

        .dropdown-menu li a:hover i {
            transform: translateX(3px);
            color: #1e3c72;
        }

        .dropdown > a::after {
            content: ' ▼';
            font-size: 0.7rem;
            margin-left: 5px;
        }

        .dropdown-toggle {
            border: none;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 600;
            transition: all 0.3s;
            font-size: 0.95rem;
        }

        .dropdown-toggle:hover {
            background: linear-gradient(135deg, #764ba2, #667eea);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
        }

        /* ===== CONTAINER ===== */
        .container {
            width: 90%;
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 15px;
            flex: 1;
        }

        /* ===== BUTTONS ===== */
        .btn {
            display: inline-block;
            padding: 10px 24px;
            text-decoration: none;
            border-radius: 4px;
            font-weight: 600;
            transition: all 0.3s;
            border: none;
            cursor: pointer;
            font-size: 0.95rem;
        }

        .btn-primary {
            background: #ff6b35;
            color: white;
        }

        .btn-primary:hover {
            background: #e55a24;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(255, 107, 53, 0.3);
        }

        .btn-secondary {
            background: transparent;
            color: #1e3c72;
            border: 2px solid #1e3c72;
        }

        .btn-secondary:hover {
            background: #1e3c72;
            color: white;
        }

        /* ===== HERO SECTION ===== */
        .hero {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            color: white;
            padding: 80px 20px;
            text-align: center;
            position: relative;
            overflow: hidden;
            margin-bottom: 60px;
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
            max-width: 1200px;
            margin: 0 auto;
            position: relative;
            z-index: 1;
        }

        .hero h2 {
            font-size: 42px;
            margin-bottom: 15px;
            font-weight: 700;
        }

        .hero p {
            font-size: 18px;
            margin-bottom: 30px;
            opacity: 0.95;
        }

        .hero-buttons {
            display: flex;
            gap: 15px;
            justify-content: center;
            flex-wrap: wrap;
        }

        /* ===== SERVICES SECTION ===== */
        .services-section {
            background: #f8f9fa;
            padding: 60px 20px;
            margin-bottom: 60px;
        }

        .section-title {
            text-align: center;
            margin-bottom: 50px;
        }

        .section-title h3 {
            font-size: 32px;
            color: #1e3c72;
            margin-bottom: 10px;
        }

        .section-title .title-underline {
            height: 4px;
            width: 60px;
            background: #ff6b35;
            margin: 0 auto 15px;
        }

        .section-title p {
            color: #666;
            font-size: 16px;
        }

        .services-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 25px;
            max-width: 1200px;
            margin: 0 auto;
        }

        .service-card {
            background: white;
            padding: 30px;
            border-radius: 8px;
            text-align: center;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.1);
            transition: all 0.3s;
            border-top: 4px solid #2a5298;
        }

        .service-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
            border-top-color: #ff6b35;
        }

        .service-icon {
            font-size: 48px;
            color: #2a5298;
            margin-bottom: 15px;
        }

        .service-card h4 {
            font-size: 18px;
            color: #1e3c72;
            margin-bottom: 10px;
        }

        .service-card p {
            color: #666;
            font-size: 14px;
            line-height: 1.6;
        }

        /* ===== STATISTICS SECTION ===== */
        .statistics {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            color: white;
            padding: 60px 20px;
            margin-bottom: 60px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
            max-width: 1200px;
            margin: 0 auto;
        }

        .stat-card {
            text-align: center;
            padding: 30px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .stat-number {
            font-size: 40px;
            font-weight: 700;
            margin-bottom: 10px;
            color: #ff6b35;
        }

        .stat-label {
            font-size: 16px;
            opacity: 0.9;
        }

        /* ===== FEATURES SECTION ===== */
        .features-section {
            padding: 60px 20px;
            margin-bottom: 60px;
        }

        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 30px;
            max-width: 1200px;
            margin: 0 auto;
        }

        .feature-item {
            display: flex;
            gap: 20px;
        }

        .feature-icon {
            font-size: 36px;
            color: #ff6b35;
            flex-shrink: 0;
            width: 60px;
            height: 60px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #fff0e6;
            border-radius: 8px;
        }

        .feature-content h4 {
            color: #1e3c72;
            margin-bottom: 8px;
            font-size: 16px;
        }

        .feature-content p {
            color: #666;
            font-size: 14px;
            line-height: 1.6;
        }

        /* ===== FOOTER ===== */
        .footer {
            background: #1a1a1a;
            color: #fff;
            padding: 15px 20px 8px;
            margin-top: auto;
        }

        .footer-content {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            gap: 15px;
            margin-bottom: 10px;
            max-width: 1200px;
            margin-left: auto;
            margin-right: auto;
        }

        .footer-section {
            flex: 1;
            min-width: 200px;
        }

        .footer-section:first-child {
            flex: 2;
        }

        .footer-section h4 {
            color: #ff6b35;
            margin-bottom: 8px;
            font-size: 13px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .footer-section ul {
            list-style: none;
        }

        .footer-section ul li {
            margin-bottom: 4px;
        }

        .footer-section ul li a {
            color: #aaa;
            text-decoration: none;
            transition: color 0.3s;
            font-size: 12px;
        }

        .footer-section ul li a:hover {
            color: #ff6b35;
            padding-left: 5px;
        }

        .footer-section p {
            color: #aaa;
            font-size: 12px;
            line-height: 1.4;
        }

        .footer-contact {
            display: flex;
            gap: 8px;
            align-items: flex-start;
            margin-bottom: 5px;
            font-size: 12px;
        }

        .footer-contact i {
            color: #ff6b35;
            font-size: 12px;
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
            gap: 8px;
            margin-top: 8px;
        }

        .footer-social a {
            color: white;
            font-size: 14px;
            width: 28px;
            height: 28px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #333;
            border-radius: 50%;
            transition: all 0.3s;
        }

        .footer-social a:hover {
            background: #ff6b35;
            transform: translateY(-2px);
        }

        .footer-bottom {
            border-top: 1px solid #333;
            padding-top: 8px;
            text-align: center;
            color: #777;
            font-size: 10px;
            max-width: 1200px;
            margin: 0 auto;
        }

        .footer-bottom p {
            margin-bottom: 2px;
        }

        /* ===== RESPONSIVE ===== */
        @media (max-width: 768px) {
            .header-content {
                flex-direction: column;
                gap: 15px;
            }

            nav ul {
                flex-wrap: wrap;
                gap: 0;
            }

            .hero h2 {
                font-size: 28px;
            }

            .hero p {
                font-size: 14px;
            }

            .hero-buttons {
                flex-direction: column;
            }

            .btn {
                width: 100%;
            }

            .section-title h3 {
                font-size: 24px;
            }

            .stat-number {
                font-size: 28px;
            }
        }

        @media (max-width: 600px) {
            .logo-text h1 {
                font-size: 18px;
            }

            .logo-text p {
                font-size: 10px;
            }

            nav ul {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <!-- MAIN HEADER -->
    <header class="main-header">
        <div class="header-content">
            <!-- LOGO -->
            <div class="logo-section">
                <a href="index.php" class="logo">
                    <i class="fas fa-city"></i>
                </a>
                <div class="logo-text">
                    <h1>CIVICARE</h1>
                </div>
            </div>

            <!-- NAVIGATION -->
            <nav class="nav-container">
                <ul>
                    <li><a href="index.php">Home</a></li>
                    
                    <li class="dropdown">
                        <a href="#">Services</a>
                        <ul class="dropdown-menu">
                            <li><a href="user/new_complaint.php"><i class="fas fa-chevron-right"></i> File Complaint</a></li>
                            <li><a href="user/track_complaint.php"><i class="fas fa-chevron-right"></i> Track Complaint</a></li>
                            <li><a href="user/complaints.php"><i class="fas fa-chevron-right"></i> View History</a></li>
                        </ul>
                    </li>
                    
                    <li class="dropdown">
                        <a href="#">Departments</a>
                        <ul class="dropdown-menu">
                            <li><a href="department_info.php?id=1"><span class="icon">💧</span> Water Supply</a></li>
                            <li><a href="department_info.php?id=2"><span class="icon">⚡</span> Electricity</a></li>
                            <li><a href="department_info.php?id=3"><span class="icon">🛣️</span> Roads</a></li>
                            <li><a href="department_info.php?id=4"><span class="icon">🧹</span> Sanitation</a></li>
                            <li><a href="department_info.php?id=5"><span class="icon">🏗️</span> Public Works</a></li>
                            <li><a href="department_info.php?id=6"><span class="icon">🌳</span> Parks & Gardens</a></li>
                        </ul>
                    </li>
                    
                    <li class="dropdown">
                        <a href="#">Information</a>
                        <ul class="dropdown-menu">
                            <li><a href="about.php"><i class="fas fa-chevron-right"></i> About Us</a></li>
                            <li><a href="about.php#how-it-works"><i class="fas fa-chevron-right"></i> How It Works</a></li>
                            <li><a href="user/login.php"><i class="fas fa-chevron-right"></i> Status Updates</a></li>
                            <li><a href="faqs.php"><i class="fas fa-chevron-right"></i> FAQs</a></li>
                        </ul>
                    </li>
                    
                    <li><a href="contact.php">Contact</a></li>
                    
                    <li class="dropdown">
                        <a href="#">Login</a>
                        <ul class="dropdown-menu">
                            <li><a href="user/login.php"><span class="icon">👤</span> Citizen Login</a></li>
                            <li><a href="staff/login.php"><span class="icon">👨‍💼</span> Staff Login</a></li>
                            <li><a href="admin/login.php"><span class="icon">👨‍💻</span> Admin Login</a></li>
                        </ul>
                    </li>
                </ul>
            </nav>
        </div>
    </header>

    <!-- HERO SECTION -->
    <section class="hero">
        <div class="hero-content">
            <h2 id="hero-title">Welcome to Civicare</h2>
            <p id="hero-desc">Your one-stop platform for municipal complaint management. Report issues, track resolutions, and contribute to a better community.</p>
            <div class="hero-buttons">
                <a href="user/new_complaint.php" class="btn btn-primary">File a Complaint</a>
                <a href="user/track_complaint.php" class="btn btn-secondary">Track Status</a>
            </div>
        </div>
    </section>

    <div class="container">
        <!-- OUR SERVICES SECTION -->
        <section class="services-section">
            <div class="section-title">
                <h3 id="services-title">Our Services</h3>
                <div class="title-underline"></div>
                <p id="services-desc">Access our comprehensive complaint management services</p>
            </div>
            <div class="services-grid">
                <div class="service-card">
                    <div class="service-icon">
                        <i class="fas fa-file-medical"></i>
                    </div>
                    <h4 id="service1-title">File Complaint</h4>
                    <p id="service1-desc">Submit your grievance with detailed information, photos, and location. Get automatic confirmation and tracking ID.</p>
                </div>
                <div class="service-card">
                    <div class="service-icon">
                        <i class="fas fa-search"></i>
                    </div>
                    <h4 id="service2-title">Track Status</h4>
                    <p id="service2-desc">Monitor your complaint progress in real-time. Get updates via email and SMS about the current status and resolution timeline.</p>
                </div>
                <div class="service-card">
                    <div class="service-icon">
                        <i class="fas fa-redo"></i>
                    </div>
                    <h4 id="service3-title">Get Resolution</h4>
                    <p id="service3-desc">Follow up with assigned staff, provide feedback, and rate the resolution. Help us improve our service quality continuously.</p>
                </div>
            </div>
        </section>
        <!-- WHY CHOOSE CIVICARE SECTION -->
        <section class="features-section">
            <div class="section-title">
                <h3 id="why-title">Why Choose CiviCare?</h3>
                <div class="title-underline"></div>
                <p id="why-desc">Better service, faster resolution, complete transparency</p>
            </div>
            <div class="features-grid">
                <div class="feature-item">
                    <div class="feature-icon">
                        <i class="fas fa-mobile-alt"></i>
                    </div>
                    <div class="feature-content">
                        <h4 id="feature1-title">Mobile Friendly</h4>
                        <p id="feature1-desc">Access from anywhere on any device. File and track complaints on the go with our responsive platform.</p>
                    </div>
                </div>
                <div class="feature-item">
                    <div class="feature-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="feature-content">
                        <h4 id="feature2-title">Real-time Updates</h4>
                        <p id="feature2-desc">Get instant notifications about your complaint status, assigned staff, and resolution progress.</p>
                    </div>
                </div>
                <div class="feature-item">
                    <div class="feature-icon">
                        <i class="fas fa-shield-alt"></i>
                    </div>
                    <div class="feature-content">
                        <h4 id="feature3-title">Secure & Confidential</h4>
                        <p id="feature3-desc">Your personal information is protected with enterprise-grade security and encryption technology.</p>
                    </div>
                </div>
                <div class="feature-item">
                    <div class="feature-icon">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div class="feature-content">
                        <h4 id="feature4-title">Performance Metrics</h4>
                        <p id="feature4-desc">View city-wide statistics, resolution rates, and department performance on our transparent dashboard.</p>
                    </div>
                </div>
                <div class="feature-item">
                    <div class="feature-icon">
                        <i class="fas fa-headset"></i>
                    </div>
                    <div class="feature-content">
                        <h4 id="feature5-title">24/7 Support</h4>
                        <p id="feature5-desc">Our support team is available round the clock to assist you with any queries or issues.</p>
                    </div>
                </div>
                <div class="feature-item">
                    <div class="feature-icon">
                        <i class="fas fa-star"></i>
                    </div>
                    <div class="feature-content">
                        <h4 id="feature6-title">Feedback System</h4>
                        <p id="feature6-desc">Rate and review the resolution provided. Your feedback helps us deliver better services.</p>
                    </div>
                </div>
            </div>
        </section>
    </div>

    <!-- FOOTER -->
    <footer class="footer">
        <div class="footer-content">
            <!-- ABOUT -->
            <div class="footer-section">
                <h4>About CiviCare</h4>
                <p id="footer-about">CiviCare is the official municipal complaint management system. We bridge the gap between citizens and government for efficient service delivery.</p>
                <div class="footer-social">
                    <a href="#"><i class="fab fa-facebook-f"></i></a>
                    <a href="#"><i class="fab fa-twitter"></i></a>
                    <a href="#"><i class="fab fa-youtube"></i></a>
                    <a href="#"><i class="fab fa-instagram"></i></a>
                </div>
            </div>

            <!-- QUICK LINKS -->
            <div class="footer-section">
                <h4 id="footer-links-title">Quick Links</h4>
                <ul>
                    <li><a href="index.php">Home</a></li>
                    <li><a href="user/new_complaint.php">File Complaint</a></li>
                    <li><a href="user/track_complaint.php">Track Status</a></li>
                    <li><a href="contact.php">Contact Us</a></li>
                    <li><a href="faqs.php">FAQs</a></li>
                    <li><a href="#">Privacy Policy</a></li>
                </ul>
            </div>

            <!-- DEPARTMENTS -->
            <div class="footer-section">
                <h4 id="footer-depts-title">Departments</h4>
                <ul>
                    <li><a href="department_info.php?id=1">Water Supply</a></li>
                    <li><a href="department_info.php?id=2">Electricity</a></li>
                    <li><a href="department_info.php?id=3">Roads</a></li>
                    <li><a href="department_info.php?id=4">Sanitation</a></li>
                    <li><a href="department_info.php?id=5">Public Works</a></li>
                    <li><a href="department_info.php?id=6">Parks & Gardens</a></li>
                </ul>
            </div>

            <!-- CONTACT -->
            <div class="footer-section">
                <h4 id="footer-contact-title">Contact Us</h4>
                <div class="footer-contact">
                    <i class="fas fa-map-marker-alt"></i>
                    <span>
                        <strong>Address:</strong><br>
                        Three Archives Building<br>
                        Pandit Colony, Nashik - 422002
                    </span>
                </div>
                <div class="footer-contact">
                    <i class="fas fa-phone"></i>
                    <span><a href="tel:+919876543210">+(91) 9876543210</a></span>
                </div>
                <div class="footer-contact">
                    <i class="fas fa-envelope"></i>
                    <span><a href="mailto:info@civicare.gov.in">info@civicare.gov.in</a></span>
                </div>
                <div class="footer-contact">
                    <i class="fas fa-clock"></i>
                    <span>
                        <strong>Helpline:</strong><br>
                        Monday - Saturday<br>
                        10:00 AM - 6:00 PM
                    </span>
                </div>
            </div>
        </div>

        <!-- FOOTER BOTTOM -->
        <div class="footer-bottom">
            <p>&copy; 2026 CiviCare - Municipal Complaint Management System. All rights reserved.</p>
            <p>Total Visitors: 42,580 | This Month: 8,420 | Today: 1,245</p>
            <p><a href="#" style="color: #ff6b35;">Privacy Policy</a> | <a href="#" style="color: #ff6b35;">Terms & Conditions</a> | <a href="#" style="color: #ff6b35;">Sitemap</a></p>
        </div>
    </footer>
</body>
</html>