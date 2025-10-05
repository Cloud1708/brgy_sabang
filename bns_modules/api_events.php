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
    // Verify CSRF token (for both GET and write ops in this app)
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

    // Ensure events table has fields for completion tracking
    ensure_events_schema();

    $action = $_GET['action'] ?? 'list';

    switch ($action) {
        case 'create':
            assert_method('POST');
            $data = read_json();
            $event_id = createEvent($data);
            echo json_encode([
                'success' => true,
                'message' => 'Event scheduled successfully',
                'event_id' => $event_id
            ]);
            break;

        case 'update':
            assert_method('POST');
            $data = read_json();
            $id = (int)($data['event_id'] ?? 0);
            if ($id <= 0) throw new Exception('Missing event_id');

            // Allowed fields to update
            $allowed = ['event_title','event_description','event_type','event_date','event_time','location','target_audience','is_published'];
            $set = [];
            $vals = [];
            $types = '';

            foreach ($allowed as $f) {
                if (array_key_exists($f, $data)) {
                    $set[] = "$f = ?";
                    $vals[] = $data[$f];
                    $types .= ($f === 'is_published') ? 'i' : 's';
                }
            }
            if (!$set) throw new Exception('No fields to update');

            global $mysqli;
            $sql = "UPDATE events SET ".implode(',', $set).", updated_at = NOW() WHERE event_id = ? LIMIT 1";
            $stmt = $mysqli->prepare($sql);
            if (!$stmt) throw new Exception('DB prepare failed');
            $types .= 'i';
            $vals[] = $id;
            $stmt->bind_param($types, ...$vals);
            if (!$stmt->execute()) throw new Exception('Update failed: '.$stmt->error);
            $stmt->close();
            echo json_encode(['success'=>true,'updated'=>true]);
            break;

        case 'reschedule':
            assert_method('POST');
            $data = read_json();
            $id = (int)($data['event_id'] ?? 0);
            $date = trim($data['event_date'] ?? '');
            $time = trim($data['event_time'] ?? '');
            if ($id <= 0 || !$date || !$time) throw new Exception('Missing event_id, event_date or event_time');

            global $mysqli;
            $stmt = $mysqli->prepare("UPDATE events SET event_date = ?, event_time = ?, updated_at = NOW() WHERE event_id = ? LIMIT 1");
            if (!$stmt) throw new Exception('DB prepare failed');
            $stmt->bind_param('ssi', $date, $time, $id);
            if (!$stmt->execute()) throw new Exception('Reschedule failed: '.$stmt->error);
            $stmt->close();
            echo json_encode(['success'=>true,'rescheduled'=>true]);
            break;

        case 'complete':
            assert_method('POST');
            $data = read_json();
            $id = (int)($data['event_id'] ?? 0);
            if ($id <= 0) throw new Exception('Missing event_id');

            global $mysqli;
            $stmt = $mysqli->prepare("UPDATE events SET is_completed = 1, completed_at = NOW(), updated_at = NOW() WHERE event_id = ? LIMIT 1");
            if (!$stmt) throw new Exception('DB prepare failed');
            $stmt->bind_param('i', $id);
            if (!$stmt->execute()) throw new Exception('Mark completed failed: '.$stmt->error);
            $stmt->close();
            echo json_encode(['success'=>true,'completed'=>true]);
            break;

        case 'list':
            // Optional filters
            $include_completed = isset($_GET['include_completed']) ? (int)$_GET['include_completed'] : 0;
            $status = $_GET['status'] ?? ''; // '', 'completed', 'upcoming'
            $type   = $_GET['type'] ?? '';   // 'health' | 'weighing' | 'feeding' | 'nutrition'

            echo json_encode([
                'success' => true,
                'events'  => getEventsList($include_completed, $status, $type)
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

/* Helpers */

function assert_method($method) {
    if ($_SERVER['REQUEST_METHOD'] !== $method) {
        throw new Exception("$method method required");
    }
}

function read_json() {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Invalid JSON data');
    }
    return $data;
}

function ensure_events_schema() {
    global $mysqli;
    try {
        $db = $mysqli->query("SELECT DATABASE() AS dbname")->fetch_assoc()['dbname'] ?? '';
        if (!$db) return;

        $col = $mysqli->prepare("
            SELECT COUNT(*) 
            FROM INFORMATION_SCHEMA.COLUMNS 
            WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'events' AND COLUMN_NAME = 'is_completed'
        ");
        $col->bind_param('s', $db);
        $col->execute();
        $col->bind_result($cnt);
        $col->fetch();
        $col->close();

        if ((int)$cnt === 0) {
            // Add columns for completion tracking
            $mysqli->query("ALTER TABLE events 
                ADD COLUMN is_completed TINYINT(1) NOT NULL DEFAULT 0 AFTER is_published,
                ADD COLUMN completed_at DATETIME NULL DEFAULT NULL AFTER is_completed
            ");
        }
    } catch (Throwable $e) {
        // If we fail schema migration, continue without fatal error (feature will be disabled)
        // But log for admins
        error_log('ensure_events_schema: '.$e->getMessage());
    }
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
             location, target_audience, is_published, is_completed, completed_at, created_by, created_at, updated_at) 
            VALUES (?,?,?,?,?,?,?,?,0,NULL,?,NOW(),NOW())";

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

function getEventsList($include_completed = 0, $status = '', $type = '') {
    global $mysqli;

    $where = [];
    $params = [];
    $types  = '';

    // Only future events by default (same behavior)
    if ($status !== 'completed') {
        $where[] = "e.event_date >= CURDATE()";
    }

    if (!$include_completed && $status !== 'completed') {
        $where[] = "COALESCE(e.is_completed,0) = 0";
    }

    if ($status === 'completed') {
        $where[] = "COALESCE(e.is_completed,0) = 1";
    }

    if ($type !== '') {
        $where[] = "e.event_type = ?";
        $params[] = $type;
        $types   .= 's';
    }

    $sql = "SELECT 
                e.*,
                u.first_name,
                u.last_name
            FROM events e
            LEFT JOIN users u ON e.created_by = u.user_id";

    if ($where) {
        $sql .= " WHERE ".implode(' AND ', $where);
    }

    $sql .= " ORDER BY e.event_date ASC, e.event_time ASC
              LIMIT 200";

    $stmt = $mysqli->prepare($sql);
    if (!$stmt) throw new Exception('DB prepare failed');
    if ($params) $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $res = $stmt->get_result();
    $rows = [];
    while ($row = $res->fetch_assoc()) {
        $rows[] = $row;
    }
    $stmt->close();
    return $rows;
}