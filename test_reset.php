<?php
// Simple test file to check if reset_password.php is accessible
echo "<h1>Reset Password Test</h1>";
echo "<p>Current time: " . date('Y-m-d H:i:s') . "</p>";
echo "<p>Server: " . $_SERVER['HTTP_HOST'] . "</p>";
echo "<p>Request URI: " . $_SERVER['REQUEST_URI'] . "</p>";

// Check if reset_password.php exists
if (file_exists('reset_password.php')) {
    echo "<p style='color: green;'>✓ reset_password.php file exists</p>";
} else {
    echo "<p style='color: red;'>✗ reset_password.php file NOT found</p>";
}

// Check if forgot_password.php exists
if (file_exists('forgot_password.php')) {
    echo "<p style='color: green;'>✓ forgot_password.php file exists</p>";
} else {
    echo "<p style='color: red;'>✗ forgot_password.php file NOT found</p>";
}

// Test database connection
require_once __DIR__ . '/inc/db.php';
if ($mysqli->connect_error) {
    echo "<p style='color: red;'>✗ Database connection failed: " . $mysqli->connect_error . "</p>";
} else {
    echo "<p style='color: green;'>✓ Database connection successful</p>";
    
    // Check if password_reset_tokens table exists
    $result = $mysqli->query("SHOW TABLES LIKE 'password_reset_tokens'");
    if ($result->num_rows > 0) {
        echo "<p style='color: green;'>✓ password_reset_tokens table exists</p>";
        
        // Check table structure
        $result = $mysqli->query("DESCRIBE password_reset_tokens");
        if ($result) {
            echo "<p>Table structure:</p><ul>";
            while ($row = $result->fetch_assoc()) {
                echo "<li>" . $row['Field'] . " - " . $row['Type'] . "</li>";
            }
            echo "</ul>";
        }
    } else {
        echo "<p style='color: red;'>✗ password_reset_tokens table NOT found</p>";
    }
}

echo "<hr>";
echo "<p><a href='reset_password.php?token=test123'>Test reset_password.php directly</a></p>";
echo "<p><a href='forgot_password.php'>Test forgot_password.php</a></p>";
?>
