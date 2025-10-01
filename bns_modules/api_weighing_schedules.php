<?php
// bns_modules/api_weighing_schedules.php
require_once __DIR__.'/../inc/db.php';
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        $sql = "SELECT * FROM events WHERE event_type = 'weighing' AND event_date >= CURDATE() ORDER BY event_date, event_time";
        $result = $mysqli->query($sql);
        $events = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $events[] = $row;
            }
        }
        echo json_encode(['success' => true, 'events' => $events]);
        break;
    case 'POST':
        $data = json_decode(file_get_contents('php://input'), true);
        if (isset($data['event_id']) && $data['event_id']) {
            $stmt = $mysqli->prepare("UPDATE events SET event_title=?, event_description=?, event_date=?, event_time=?, location=?, target_audience=?, is_published=?, updated_at=NOW() WHERE event_id=? AND event_type='weighing'");
            $stmt->bind_param('ssssssii',
                $data['event_title'], $data['event_description'], $data['event_date'], $data['event_time'],
                $data['location'], $data['target_audience'], $data['is_published'], $data['event_id']
            );
            $ok = $stmt->execute();
            $stmt->close();
            echo json_encode(['success' => $ok]);
        } else {
            $stmt = $mysqli->prepare("INSERT INTO events (event_title, event_description, event_type, event_date, event_time, location, target_audience, is_published, created_by, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?,NOW(),NOW())");
            $event_type = 'weighing';
            $created_by = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0;
            $stmt->bind_param('sssssssii',
                $data['event_title'], $data['event_description'], $event_type, $data['event_date'], $data['event_time'],
                $data['location'], $data['target_audience'], $data['is_published'], $created_by
            );
            $ok = $stmt->execute();
            $stmt->close();
            echo json_encode(['success' => $ok]);
        }
        break;
    case 'DELETE':
        $data = json_decode(file_get_contents('php://input'), true);
        $stmt = $mysqli->prepare("DELETE FROM events WHERE event_id=? AND event_type='weighing'");
        $stmt->bind_param('i', $data['event_id']);
        $ok = $stmt->execute();
        $stmt->close();
        echo json_encode(['success' => $ok]);
        break;
    default:
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Method not allowed']);
}
