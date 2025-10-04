<?php
require_once __DIR__.'/../inc/db.php';
require_once __DIR__.'/../inc/auth.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-CSRF-Token');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Start output buffering to prevent any HTML output
ob_start();

try {
    // Verify CSRF token
    $headers = function_exists('getallheaders') ? getallheaders() : [];
    $csrf_token = $headers['X-CSRF-Token'] ?? $headers['x-csrf-token'] ?? $_POST['csrf_token'] ?? '';

    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if ($csrf_token !== ($_SESSION['csrf_token'] ?? '')) {
        throw new Exception('Invalid CSRF token');
    }

    // Check authentication and role
    require_role(['BNS', 'BHW', 'Admin']);

    $action = $_GET['action'] ?? 'list';

    switch ($action) {
        case 'create':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('POST method required');
            }
            $raw = file_get_contents('php://input');
            $data = json_decode($raw, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('Invalid JSON data');
            }
            $event_id = createEvent($data);
            echo json_encode([
                'success' => true,
                'message' => 'Event scheduled successfully',
                'event_id' => $event_id
            ]);
            break;

        case 'list':
            $events = getEventsList();
            echo json_encode([
                'success' => true,
                'events' => $events
            ]);
            break;

        default:
            throw new Exception('Invalid action');
    }

} catch (Exception $e) {
    ob_clean();
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
} finally {
    ob_end_flush();
}

function createEvent($data) {
    global $mysqli;

    // Validate required fields
    $required_fields = ['event_title', 'event_type', 'event_date', 'event_time', 'location'];
    foreach ($required_fields as $field) {
        if (empty($data[$field])) {
            throw new Exception("Missing required field: $field");
        }
    }

    // Validate future datetime
    $eventDateTime = $data['event_date'] . ' ' . $data['event_time'];
    if (strtotime($eventDateTime) <= time()) {
        throw new Exception('Event date and time must be in the future');
    }

    $current_user_id = (int)($_SESSION['user_id'] ?? 1);

    $sql = "INSERT INTO events 
            (event_title, event_description, event_type, event_date, event_time, 
             location, target_audience, is_published, created_by, created_at, updated_at) 
            VALUES (?,?,?,?,?,?,?,?,?,NOW(),NOW())";

    $stmt = $mysqli->prepare($sql);
    $desc = $data['event_description'] ?? '';
    $aud  = $data['target_audience'] ?? '';
    $pub  = isset($data['is_published']) ? (int)$data['is_published'] : 1;

    $stmt->bind_param(
        'sssssssii',
        $data['event_title'],
        $desc,
        $data['event_type'],
        $data['event_date'],
        $data['event_time'],
        $data['location'],
        $aud,
        $pub,
        $current_user_id
    );
    $stmt->execute();
    $id = (int)$mysqli->insert_id;
    $stmt->close();

    return $id;
}

function getEventsList() {
    global $mysqli;

    $sql = "SELECT 
                e.*,
                u.first_name,
                u.last_name
            FROM events e
            LEFT JOIN users u ON e.created_by = u.user_id
            WHERE e.event_date >= CURDATE()
            ORDER BY e.event_date ASC, e.event_time ASC
            LIMIT 100";

    $res = $mysqli->query($sql);
    $rows = [];
    while ($row = $res->fetch_assoc()) {
        $rows[] = $row;
    }
    return $rows;
}