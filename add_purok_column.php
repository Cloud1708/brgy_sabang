<?php
require_once __DIR__.'/inc/db.php';

echo "<h2>Adding purok_name column to maternal_patients table</h2>";

// Check if column exists
$result = $mysqli->query("SHOW COLUMNS FROM maternal_patients LIKE 'purok_name'");

if ($result && $result->num_rows > 0) {
    echo "✓ Column 'purok_name' already exists<br>";
} else {
    // Add the column
    $sql = "ALTER TABLE maternal_patients ADD COLUMN purok_name VARCHAR(100) DEFAULT NULL AFTER street_name";
    if ($mysqli->query($sql)) {
        echo "✓ Successfully added column 'purok_name'<br>";
    } else {
        echo "✗ Error adding column 'purok_name': " . $mysqli->error . "<br>";
    }
}

echo "<h3>Updated maternal_patients table structure:</h3>";
$result = $mysqli->query("SHOW COLUMNS FROM maternal_patients");
if ($result) {
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>Column Name</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row['Field'] . "</td>";
        echo "<td>" . $row['Type'] . "</td>";
        echo "<td>" . $row['Null'] . "</td>";
        echo "<td>" . $row['Key'] . "</td>";
        echo "<td>" . $row['Default'] . "</td>";
        echo "<td>" . $row['Extra'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "Error: " . $mysqli->error;
}
?>
