<?php
// c:/xampp/htdocs/brgy_sabang/bns_modules/api_events.php
header('Content-Type: application/json');
require_once __DIR__ . '/../inc/db.php';

$sql = "SELECT event_id, event_title, event_description, event_type, event_date, event_time, location, target_audience, is_published FROM events WHERE is_published = 1";
$result = $mysqli->query($sql);
$events = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        // Combine date and time for FullCalendar if time is set
        $start = $row['event_date'];
        if (!empty($row['event_time'])) {
            $start .= 'T' . $row['event_time'];
        }
        $events[] = [
            'id' => $row['event_id'],
            'title' => $row['event_title'],
            'description' => $row['event_description'],
            'type' => $row['event_type'],
            'start' => $start,
            'time' => $row['event_time'],
            'location' => $row['location'],
            'target_audience' => $row['target_audience']
        ];
    }
}
echo json_encode($events);
?>
