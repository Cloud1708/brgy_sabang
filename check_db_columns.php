<?php
require_once __DIR__.'/inc/db.php';

echo "<h2>Health Records Table Structure</h2>";

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

echo "<h3>Checking for Intervention Columns:</h3>";
$interventionCols = [
    'iron_folate_prescription',
    'additional_iodine', 
    'malaria_prophylaxis',
    'breastfeeding_plan',
    'danger_advice',
    'dental_checkup',
    'emergency_plan',
    'general_risk',
    'next_visit_date'
];

foreach($interventionCols as $col) {
    $result = $mysqli->query("SHOW COLUMNS FROM health_records LIKE '$col'");
    if ($result && $result->num_rows > 0) {
        echo "✓ $col - EXISTS<br>";
    } else {
        echo "✗ $col - MISSING<br>";
    }
}
?>
