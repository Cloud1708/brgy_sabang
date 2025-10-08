<?php
require_once __DIR__.'/inc/db.php';

echo "<h2>Adding Intervention Columns to health_records table</h2>";

$interventionCols = [
    'iron_folate_prescription' => 'TINYINT(1) DEFAULT 0',
    'additional_iodine' => 'TINYINT(1) DEFAULT 0', 
    'malaria_prophylaxis' => 'TINYINT(1) DEFAULT 0',
    'breastfeeding_plan' => 'TINYINT(1) DEFAULT 0',
    'danger_advice' => 'TINYINT(1) DEFAULT 0',
    'dental_checkup' => 'TINYINT(1) DEFAULT 0',
    'emergency_plan' => 'TINYINT(1) DEFAULT 0',
    'general_risk' => 'TINYINT(1) DEFAULT 0',
    'next_visit_date' => 'DATE NULL'
];

foreach($interventionCols as $col => $definition) {
    // Check if column exists
    $result = $mysqli->query("SHOW COLUMNS FROM health_records LIKE '$col'");
    
    if ($result && $result->num_rows > 0) {
        echo "✓ Column '$col' already exists<br>";
    } else {
        // Add the column
        $sql = "ALTER TABLE health_records ADD COLUMN $col $definition";
        if ($mysqli->query($sql)) {
            echo "✓ Successfully added column '$col'<br>";
        } else {
            echo "✗ Error adding column '$col': " . $mysqli->error . "<br>";
        }
    }
}

echo "<h3>Final Table Structure:</h3>";
$result = $mysqli->query("SHOW COLUMNS FROM health_records");
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
