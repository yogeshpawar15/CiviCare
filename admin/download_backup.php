<?php
// civicare/admin/download_backup.php

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../config/functions.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in and is admin
if (!isLoggedIn() || (!hasRole('admin'))) {
    header('Location: login.php');
    exit();
}

// Check if filename is provided
if (!isset($_GET['file']) || empty($_GET['file'])) {
    header('Location: settings.php?tab=system');
    exit();
}

// Sanitize filename
$filename = basename($_GET['file']);
$backup_dir = '../backups/';
$filepath = $backup_dir . $filename;

// Check if file exists and is a valid backup file
if (!file_exists($filepath)) {
    header('Location: settings.php?tab=system&error=Backup file not found');
    exit();
}

// Security check: Ensure file is in backup directory
$realpath = realpath($filepath);
$realdir = realpath($backup_dir);
if (strpos($realpath, $realdir) !== 0) {
    header('Location: settings.php?tab=system&error=Invalid file path');
    exit();
}

// Security check: Ensure file is a .sql backup file
if (pathinfo($filename, PATHINFO_EXTENSION) !== 'sql') {
    header('Location: settings.php?tab=system&error=Invalid file type');
    exit();
}

// Check if file is readable
if (!is_readable($filepath)) {
    header('Location: settings.php?tab=system&error=Cannot read backup file');
    exit();
}

// Set headers for file download
header('Content-Description: File Transfer');
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Expires: 0');
header('Cache-Control: must-revalidate');
header('Pragma: public');
header('Content-Length: ' . filesize($filepath));

// Clear output buffer
ob_clean();
flush();

// Read file and output to browser
readfile($filepath);

// Log download activity
logActivity("Downloaded backup file: $filename", $_SESSION['user_id']);

exit();