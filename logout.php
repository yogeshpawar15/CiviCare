<?php
// civicare/logout.php
require_once 'config/database.php';
require_once 'config/functions.php';

// Ensure session is started, then destroy all session data
if (session_status() === PHP_SESSION_NONE) {
	session_start();
}
session_unset();
session_destroy();
?>

<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Logged Out - CiviCare</title>
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
	<style>
		/* Copied main site styles (trimmed to essential rules for header/footer/hero) */
		* { margin: 0; padding: 0; box-sizing: border-box; }
		body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; line-height: 1.6; color: #333; }
		.main-header { background: white; box-shadow: 0 2px 10px rgba(0,0,0,0.1); position: sticky; top:0; z-index:100; }
		.header-content { max-width:1200px; margin:0 auto; padding:15px 20px; display:flex; justify-content:space-between; align-items:center; }
		.logo-section { display:flex; align-items:center; gap:20px; }
		.logo { width:60px; height:60px; background: linear-gradient(135deg,#1e3c72,#2a5298); border-radius:50%; display:flex; align-items:center; justify-content:center; color:white; font-size:28px; font-weight:bold; text-decoration:none; }
		.logo-text h1 { font-size:24px; color:#1e3c72; margin:0; }
		nav ul { list-style:none; display:flex; gap:0; }
		nav > ul > li > a { display:block; padding:20px 18px; color:#333; text-decoration:none; font-weight:500; border-bottom:3px solid transparent; transition:all .3s; font-size:0.95rem; }
		nav > ul > li > a:hover { background:#f0f0f0; color:#1e3c72; border-bottom-color:#ff6b35; }
		.container { width:90%; max-width:1200px; margin:0 auto; padding:0 15px; }
		.btn { display:inline-block; padding:10px 24px; text-decoration:none; border-radius:4px; font-weight:600; transition:all .3s; border:none; cursor:pointer; font-size:0.95rem; }
		.btn-primary { background:#ff6b35; color:white; }
		.btn-primary:hover { background:#e55a24; transform:translateY(-2px); box-shadow:0 4px 12px rgba(255,107,53,0.3); }
		.btn-secondary { background:transparent; color:#1e3c72; border:2px solid #1e3c72; }
		.btn-secondary:hover { background:#1e3c72; color:white; }
		.hero { background: linear-gradient(135deg,#1e3c72 0%, #2a5298 100%); color:white; padding:60px 20px; text-align:center; margin:40px 0; border-radius:8px; }
		.hero h2 { font-size:34px; margin-bottom:10px; }
		.hero p { font-size:16px; margin-bottom:18px; opacity:0.95; }
		.footer { background:#1a1a1a; color:#fff; padding:15px 20px 8px; }
		.footer-content { display:flex; flex-wrap:wrap; justify-content:space-between; gap:15px; margin-bottom:10px; max-width:1200px; margin-left:auto; margin-right:auto; }
		.footer-section { flex:1; min-width:200px; }
		.footer-section h4 { color:#ff6b35; margin-bottom:8px; font-size:13px; font-weight:700; text-transform:uppercase; letter-spacing:0.5px; }
		.footer-section p, .footer-section a { color:#aaa; font-size:12px; text-decoration:none; }
		.footer-bottom { border-top:1px solid #333; padding-top:8px; text-align:center; color:#777; font-size:10px; max-width:1200px; margin:0 auto; }
		@media (max-width:768px) { .header-content { flex-direction:column; gap:15px; } .btn { width:100%; } }
	</style>
</head>
<body>
	<!-- MAIN HEADER (same structure as index.php for consistent styling) -->
	<header class="main-header">
		<div class="header-content">
			<div class="logo-section">
				<a href="index.php" class="logo"><i class="fas fa-city"></i></a>
				<div class="logo-text"><h1>CIVICARE</h1></div>
			</div>

			<nav class="nav-container">
				<ul>
					<li><a href="index.php">Home</a></li>
					<li><a href="user/new_complaint.php">File Complaint</a></li>
					<li><a href="user/track_complaint.php">Track Complaint</a></li>
					<li><a href="about.php">About</a></li>
					<li><a href="contact.php">Contact</a></li>
				</ul>
			</nav>
		</div>
	</header>

	<main class="container">
		<section class="hero" aria-labelledby="logout-title">
			<div class="hero-content">
				<h2 id="logout-title">You have been logged out</h2>
				<p>Your session has ended successfully. For security, all session data has been cleared.</p>
				<div class="hero-buttons">
					<a href="index.php" class="btn btn-primary">Return to Homepage</a>
					<a href="user/login.php" class="btn btn-secondary">Login</a>
				</div>
			</div>
		</section>
	</main>

	<!-- SIMPLE FOOTER -->
	<footer class="footer">
		<div class="footer-content">
			<div class="footer-section">
				<h4>About CiviCare</h4>
				<p>CiviCare is the official municipal complaint management system bridging citizens and government.</p>
			</div>
			<div class="footer-section">
				<h4>Quick Links</h4>
				<p><a href="index.php">Home</a> | <a href="user/new_complaint.php">File Complaint</a></p>
			</div>
			<div class="footer-section">
				<h4>Contact</h4>
				<p>+(91) 9876543210<br>info@civicare.gov.in</p>
			</div>
		</div>
		<div class="footer-bottom">
			<p>&copy; 2026 CiviCare - Municipal Complaint Management System. All rights reserved.</p>
		</div>
	</footer>
</body>
</html>