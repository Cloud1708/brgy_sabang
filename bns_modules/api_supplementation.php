<?php
// bns_modules/api_supplementation.php
require_once __DIR__.'/../inc/db.php';
require_once __DIR__.'/../inc/auth.php';
require_role(['BNS']); // limit to BNS (adjust if needed)

// Always return JSON (avoid HTML error output breaking JSON parsing)
header('Content-Type: application/json; charset=utf-8');
if (session_status() === PHP_SESSION_NONE) session_start();

// Prevent PHP notices/warnings from emitting HTML that breaks JSON
ini_set('display_errors', '0');
error_reporting(E_ALL);
set_error_handler(function($severity, $message, $file, $line){
  http_response_code(500);
  echo json_encode(['success'=>false,'error'=>"Server error: $message"]);
  exit;
});
set_exception_handler(function($e){
  http_response_code(500);
  echo json_encode(['success'=>false,'error'=>"Server exception: ".$e->getMessage()]);
  exit;
});

$method = $_SERVER['REQUEST_METHOD'];
$mysqli->set_charset('utf8mb4');

function err($msg,$code=400){ http_response_code($code); echo json_encode(['success'=>false,'error'=>$msg]); exit; }
function ok($data){ echo json_encode(['success'=>true]+$data); exit; }

if ($method === 'GET') {
    // List supplementation records with optional filters
    // ?list=1&q=&type=Vitamin%20A|Iron|Deworming&status=completed|overdue
    $q      = trim($_GET['q'] ?? '');
    $type   = trim($_GET['type'] ?? '');
    $status = trim($_GET['status'] ?? '');

    $where = [];
    $params = [];
    $types = '';

    if ($q !== '') {
        $where[] = 'c.full_name LIKE ?';
        $params[] = '%'.$q.'%';
        $types   .= 's';
    }
    if ($type !== '') {
        $where[] = 's.supplement_type = ?';
        $params[] = $type;
        $types   .= 's';
    }

    $sql = "
      SELECT 
        s.supplement_id,
        s.child_id,
        c.full_name AS child_name,
        s.supplement_type,
        s.supplement_date,
        s.dosage,
        s.next_due_date,
        s.administered_by,
        u.first_name AS admin_first_name,
        u.last_name  AS admin_last_name,
        s.notes,
        s.created_at,
        DATEDIFF(s.next_due_date, CURDATE()) AS days_until_due,
        CASE
          WHEN s.next_due_date IS NULL THEN 'completed'
          WHEN s.next_due_date < CURDATE() THEN 'overdue'
          ELSE 'completed'
        END AS status
      FROM supplementation_records s
      JOIN children c ON c.child_id = s.child_id
      LEFT JOIN users u ON u.user_id = s.administered_by
    ";

    $statusHaving = '';
    if ($status !== '') {
        if (!in_array($status, ['completed','overdue'], true)) {
            err('Invalid status filter');
        }
        $statusHaving = " HAVING status = ? ";
        $params[] = $status;
        $types   .= 's';
    }

    if ($where) {
        $sql .= ' WHERE '.implode(' AND ', $where);
    }

    $sql .= ' ORDER BY s.supplement_date DESC, s.supplement_id DESC';
    if ($statusHaving) $sql = "SELECT * FROM ({$sql}) x {$statusHaving}";
    $sql .= ' LIMIT 500';

    $stmt = $mysqli->prepare($sql);
    if (!$stmt) err('Prepare failed', 500);
    if ($params) $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $res = $stmt->get_result();
    $rows = [];
    while ($r = $res->fetch_assoc()) $rows[] = $r;
    $stmt->close();

    ok(['records'=>$rows,'count'=>count($rows)]);
}

if (in_array($method, ['POST','PUT','DELETE'], true)) {
    // CSRF
    $hdr = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $hdr)) {
        err('CSRF failed', 419);
    }
}

if ($method === 'POST') {
    // Create supplementation record
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    if (!is_array($data)) err('Invalid JSON');

    $child_id        = (int)($data['child_id'] ?? 0);
    $supp_type       = trim($data['supplement_type'] ?? '');
    $supp_date       = trim($data['supplement_date'] ?? '');
    $dosage          = isset($data['dosage']) ? trim($data['dosage']) : null;
    $next_due        = isset($data['next_due_date']) && $data['next_due_date'] !== '' ? trim($data['next_due_date']) : null;
    $notes           = isset($data['notes']) ? trim($data['notes']) : null;
    $administered_by = (int)($_SESSION['user_id'] ?? 0);

    if ($child_id <= 0 || $supp_type === '' || $supp_date === '') {
        err('Missing required fields: child_id, supplement_type, supplement_date');
    }

    // Basic child existence check
    $chk = $mysqli->prepare('SELECT 1 FROM children WHERE child_id=? LIMIT 1');
    $chk->bind_param('i', $child_id);
    $chk->execute();
    $chk->store_result();
    if ($chk->num_rows === 0) { $chk->close(); err('Child not found', 404); }
    $chk->close();

    // If your DB enforces FK to users, set NULL when not logged in properly
    if ($administered_by <= 0) {
        $administered_by = null; // will insert NULL
    }

    $sql = "INSERT INTO supplementation_records
              (child_id, supplement_type, supplement_date, dosage, next_due_date, administered_by, notes, created_at)
            VALUES (?,?,?,?,?,?,?,NOW())";
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) err('Prepare failed', 500);

    // IMPORTANT FIX: match 7 params -> types must be 'issssis'
    $stmt->bind_param(
        'issssis',
        $child_id,
        $supp_type,
        $supp_date,
        $dosage,
        $next_due,
        $administered_by,
        $notes
    );

    if (!$stmt->execute()) err('Insert failed: '.$stmt->error, 500);
    $id = (int)$stmt->insert_id;
    $stmt->close();

    // Return the inserted row
    $get = $mysqli->prepare("
      SELECT 
        s.supplement_id,
        s.child_id,
        c.full_name AS child_name,
        s.supplement_type,
        s.supplement_date,
        s.dosage,
        s.next_due_date,
        s.administered_by,
        s.notes,
        s.created_at,
        DATEDIFF(s.next_due_date, CURDATE()) AS days_until_due,
        CASE
          WHEN s.next_due_date IS NULL THEN 'completed'
          WHEN s.next_due_date < CURDATE() THEN 'overdue'
          ELSE 'completed'
        END AS status
      FROM supplementation_records s
      JOIN children c ON c.child_id = s.child_id
      WHERE s.supplement_id = ?
      LIMIT 1
    ");
    $get->bind_param('i', $id);
    $get->execute();
    $row = $get->get_result()->fetch_assoc();
    $get->close();

    ok(['supplement_id'=>$id,'record'=>$row]);
}

if ($method === 'PUT') {
    // Optional: Update record (simple)
    parse_str($_SERVER['QUERY_STRING'] ?? '', $qs);
    $id = isset($qs['id']) ? (int)$qs['id'] : 0;
    if ($id <= 0) err('Missing id');

    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    if (!is_array($data)) err('Invalid JSON');

    // Only allow safe fields
    $dosage   = array_key_exists('dosage',$data) ? (trim((string)$data['dosage']) ?: null) : null;
    $next_due = array_key_exists('next_due_date',$data) ? (trim((string)$data['next_due_date']) ?: null) : null;
    $notes    = array_key_exists('notes',$data) ? (trim((string)$data['notes']) ?: null) : null;

    $stmt = $mysqli->prepare("
      UPDATE supplementation_records
      SET dosage = ?, next_due_date = ?, notes = ?
      WHERE supplement_id = ?
      LIMIT 1
    ");
    if (!$stmt) err('Prepare failed', 500);
    $stmt->bind_param('sssi', $dosage, $next_due, $notes, $id);
    if (!$stmt->execute()) err('Update failed: '.$stmt->error, 500);
    $stmt->close();

    ok(['updated'=>true]);
}

if ($method === 'DELETE') {
    // Optional: delete record
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    $id = (int)($data['supplement_id'] ?? 0);
    if ($id <= 0) err('Missing supplement_id');

    $stmt = $mysqli->prepare("DELETE FROM supplementation_records WHERE supplement_id=? LIMIT 1");
    if (!$stmt) err('Prepare failed',500);
    $stmt->bind_param('i', $id);
    if (!$stmt->execute()) err('Delete failed: '.$stmt->error, 500);
    $stmt->close();

    ok(['deleted'=>true]);
}

err('Method not allowed', 405);