<?php
require_once __DIR__.'/../inc/db.php';
require_once __DIR__.'/../inc/auth.php';

// Ensure user is logged in and has BHW role
require_role(['BHW']);

header('Content-Type: application/json');

function fail($msg, $code = 400) {
    http_response_code($code);
    echo json_encode(['success' => false, 'error' => $msg]);
    exit;
}

function nz($val) { return $val === null || $val === '' ? null : $val; }

try {
    // Database connection is available from inc/db.php
    
    // Handle POST request for creating labor record
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Validate CSRF token
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            fail('Invalid CSRF token');
        }
        
        $mother_id = (int)($_POST['mother_id'] ?? 0);
        if ($mother_id <= 0) fail('Invalid mother_id');
        
        // Get form data
        $immediate_breastfeeding = isset($_POST['immediate_breastfeeding']) ? (int)$_POST['immediate_breastfeeding'] : 0;
        $delivery_type = nz($_POST['delivery_type'] ?? '');
        $delivery_date = nz($_POST['delivery_date'] ?? '');
        $place_of_delivery = nz($_POST['delivery_place'] ?? '');
        $attendant = nz($_POST['birth_attendant'] ?? '');
        $birth_weight_grams = isset($_POST['birth_weight']) && $_POST['birth_weight'] !== '' ? (int)$_POST['birth_weight'] : null;
        $postpartum_hemorrhage = isset($_POST['excessive_bleeding']) ? (int)$_POST['excessive_bleeding'] : 0;
        $baby_alive = isset($_POST['baby_alive']) ? (int)$_POST['baby_alive'] : 1;
        $baby_healthy = isset($_POST['baby_healthy']) ? (int)$_POST['baby_healthy'] : 1;
        
        // Validate required fields
        if (!$delivery_date) fail('Delivery date is required');
        
        // Validate date format
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $delivery_date)) {
            fail('Invalid delivery date format');
        }
        
        // Check if mother exists
        $stmt = $mysqli->prepare("SELECT mother_id FROM maternal_patients WHERE mother_id = ?");
        $stmt->bind_param('i', $mother_id);
        $stmt->execute();
        if (!$stmt->get_result()->fetch_assoc()) {
            fail('Mother not found');
        }
        $stmt->close();
        
        // Insert labor record
        $stmt = $mysqli->prepare("
            INSERT INTO labor_delivery_records (
                mother_id, delivery_date, delivery_type, place_of_delivery, 
                attendant, immediate_breastfeeding, birth_weight_grams, 
                postpartum_hemorrhage, baby_alive, baby_healthy, recorded_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $recorded_by = (int)($_SESSION['user_id'] ?? 0);
        
        $stmt->bind_param('issssiisiii', 
            $mother_id, $delivery_date, $delivery_type, $place_of_delivery,
            $attendant, $immediate_breastfeeding, $birth_weight_grams, 
            $postpartum_hemorrhage, $baby_alive, $baby_healthy, $recorded_by
        );
        
        if (!$stmt->execute()) {
            fail('Failed to save labor record: ' . $mysqli->error);
        }
        
        $stmt->close();
        
        echo json_encode(['success' => true, 'message' => 'Labor record saved successfully']);
        exit;
    }
    
    // Handle GET request for listing labor records
    if (isset($_GET['list'])) {
        $mother_id = isset($_GET['mother_id']) ? (int)$_GET['mother_id'] : 0;
        
        $sql = "
            SELECT ldr.*, CONCAT(mp.first_name, ' ', mp.middle_name, ' ', mp.last_name) as mother_name
            FROM labor_delivery_records ldr
            JOIN maternal_patients mp ON ldr.mother_id = mp.mother_id
        ";
        
        $params = [];
        $types = '';
        
        if ($mother_id > 0) {
            $sql .= " WHERE ldr.mother_id = ?";
            $params[] = $mother_id;
            $types .= 'i';
        }
        
        $sql .= " ORDER BY ldr.delivery_date DESC, ldr.created_at DESC";
        
        $stmt = $mysqli->prepare($sql);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        
        $records = [];
        while ($row = $result->fetch_assoc()) {
            $records[] = $row;
        }
        
        $stmt->close();
        
        echo json_encode(['success' => true, 'records' => $records]);
        exit;
    }
    
    fail('Invalid request');
    
} catch (Exception $e) {
    fail('Server error: ' . $e->getMessage(), 500);
}
?>
