<?php
// test_connection.php
// Place this file in your civicare/staff/ directory and access it via browser

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>🔧 CiviCare Connection Test</h1>";

// Include database configuration
require_once '../config/database.php';

echo "<h2>1. PHP Version:</h2>";
echo "PHP Version: " . phpversion() . "<br><br>";

echo "<h2>2. MySQLi Extension:</h2>";
if (extension_loaded('mysqli')) {
    echo "✅ MySQLi extension is loaded<br>";
} else {
    echo "❌ MySQLi extension is NOT loaded<br>";
}

echo "<h2>3. Database Connection Test:</h2>";

try {
    // Test database connection using constants from database.php
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    if ($conn->connect_error) {
        echo "❌ Connection failed: " . $conn->connect_error . "<br>";
    } else {
        echo "✅ Database connected successfully!<br>";
        echo "Server info: " . $conn->server_info . "<br>";
        echo "Host info: " . $conn->host_info . "<br>";
        
        // Test query
        $result = $conn->query("SELECT VERSION() as version");
        $row = $result->fetch_assoc();
        echo "MySQL Version: " . $row['version'] . "<br>";
        
        $conn->close();
    }
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
}

echo "<h2>4. Database Constants:</h2>";
echo "DB_HOST: " . DB_HOST . "<br>";
echo "DB_USER: " . DB_USER . "<br>";
echo "DB_NAME: " . DB_NAME . "<br>";
echo "DB_PORT: " . (defined('DB_PORT') ? DB_PORT : 'Not defined') . "<br>";

echo "<h2>5. Required Tables Check:</h2>";

try {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    $tables = ['users', 'departments', 'complaints'];
    
    foreach ($tables as $table) {
        $result = $conn->query("SHOW TABLES LIKE '$table'");
        if ($result->num_rows > 0) {
            echo "✅ Table '$table' exists<br>";
            
            // Show row count
            $count = $conn->query("SELECT COUNT(*) as total FROM $table");
            $row = $count->fetch_assoc();
            echo "&nbsp;&nbsp;&nbsp;Rows: " . $row['total'] . "<br>";
        } else {
            echo "❌ Table '$table' does NOT exist<br>";
        }
    }
    
    $conn->close();
} catch (Exception $e) {
    echo "❌ Error checking tables: " . $e->getMessage() . "<br>";
}

echo "<h2>6. Session Check:</h2>";
if (session_status() === PHP_SESSION_NONE) {
    session_start();
    echo "Session started<br>";
}

if (isset($_SESSION)) {
    echo "✅ Session is working<br>";
    echo "Session save path: " . session_save_path() . "<br>";
} else {
    echo "❌ Session is NOT working<br>";
}

echo "<h2>7. File Permissions:</h2>";
$files_to_check = [
    '../config/database.php',
    '../config/functions.php',
    'dashboard.php'
];

foreach ($files_to_check as $file) {
    if (file_exists($file)) {
        echo "✅ $file exists<br>";
        echo "&nbsp;&nbsp;&nbsp;Permissions: " . substr(sprintf('%o', fileperms($file)), -4) . "<br>";
    } else {
        echo "❌ $file does NOT exist<br>";
    }
}

echo "<hr>";
echo "<h3>📝 Next Steps:</h3>";
echo "If any checks above failed, please verify your database credentials in config/database.php<br>";
echo "Make sure MySQL server is running and the database is created";
?>