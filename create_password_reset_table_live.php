<?php
require_once __DIR__ . '/inc/db.php';

// Create password_reset_tokens table for live server
$sql = "
CREATE TABLE IF NOT EXISTS password_reset_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token VARCHAR(64) NOT NULL UNIQUE,
    expires_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_token (token),
    INDEX idx_user_id (user_id),
    INDEX idx_expires_at (expires_at)
)";

if ($mysqli->query($sql) === TRUE) {
    echo "✅ Table 'password_reset_tokens' created successfully on live server!\n";
    
    // Try to add foreign key constraint (may fail if there are data integrity issues)
    $fk_sql = "ALTER TABLE password_reset_tokens ADD CONSTRAINT fk_prt_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE";
    if ($mysqli->query($fk_sql) === TRUE) {
        echo "✅ Foreign key constraint added successfully!\n";
    } else {
        echo "⚠️  Warning: Could not add foreign key constraint: " . $mysqli->error . "\n";
        echo "Table created without foreign key constraint (this is still functional).\n";
    }
} else {
    echo "❌ Error creating table: " . $mysqli->error . "\n";
}

$mysqli->close();
?>
