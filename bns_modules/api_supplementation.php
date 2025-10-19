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
    // Handle notification request
    if (isset($_GET['notify']) && isset($_GET['child_id'])) {
        $child_id = (int)($_GET['child_id'] ?? 0);
        if ($child_id <= 0) err('Invalid child_id');
        
        // Get child and parent information
        $sql = "
            SELECT 
                c.child_id,
                c.full_name AS child_name,
                c.birth_date,
                TIMESTAMPDIFF(MONTH, c.birth_date, CURDATE()) AS current_age_months,
                pca.parent_user_id,
                u.email AS parent_email,
                CONCAT(u.first_name, ' ', u.last_name) AS parent_name,
                pca.relationship_type,
                s.supplement_type,
                s.next_due_date,
                s.dosage
            FROM children c
            JOIN parent_child_access pca ON pca.child_id = c.child_id AND pca.is_active = 1
            JOIN users u ON u.user_id = pca.parent_user_id
            LEFT JOIN supplementation_records s ON s.child_id = c.child_id 
                AND s.next_due_date IS NOT NULL 
                AND s.next_due_date >= CURDATE()
            WHERE c.child_id = ?
            LIMIT 1
        ";
        
        $stmt = $mysqli->prepare($sql);
        if (!$stmt) err('Prepare failed', 500);
        $stmt->bind_param('i', $child_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $data = $result->fetch_assoc();
        $stmt->close();
        
        if (!$data) err('Child or parent not found', 404);
        if (empty($data['parent_email'])) err('Parent email not available', 400);
        
        // Send notification email
        require_once __DIR__.'/../inc/mail.php';
        
        $childName = htmlspecialchars($data['child_name'], ENT_QUOTES, 'UTF-8');
        $parentName = htmlspecialchars($data['parent_name'], ENT_QUOTES, 'UTF-8');
        $supplementType = htmlspecialchars($data['supplement_type'] ?? 'Supplementation', ENT_QUOTES, 'UTF-8');
        $dueDate = $data['next_due_date'] ? date('F j, Y', strtotime($data['next_due_date'])) : 'TBD';
        $ageMonths = $data['current_age_months'];
        
        $subject = "Supplementation Reminder - {$childName}";
        $html = "
            <div style='font-family:Arial,Helvetica,sans-serif;font-size:14px;color:#222;max-width:600px;margin:0 auto;'>
                <div style='background:#e8f5ea;padding:20px;border-radius:8px;margin-bottom:20px;'>
                    <h2 style='margin:0;color:#077a44;'>🍼 Supplementation Reminder</h2>
                </div>
                
                <p>Hello <strong>{$parentName}</strong>,</p>
                
                <p>This is a friendly reminder about your child's upcoming supplementation schedule:</p>
                
                <div style='background:#f8f9fa;border:1px solid #e9ecef;border-radius:6px;padding:15px;margin:15px 0;'>
                    <table style='width:100%;border-collapse:collapse;'>
                        <tr>
                            <td style='padding:8px 0;font-weight:bold;color:#495057;'>Child Name:</td>
                            <td style='padding:8px 0;'>{$childName}</td>
                        </tr>
                        <tr>
                            <td style='padding:8px 0;font-weight:bold;color:#495057;'>Age:</td>
                            <td style='padding:8px 0;'>{$ageMonths} months</td>
                        </tr>
                        <tr>
                            <td style='padding:8px 0;font-weight:bold;color:#495057;'>Supplement Type:</td>
                            <td style='padding:8px 0;'>{$supplementType}</td>
                        </tr>
                        <tr>
                            <td style='padding:8px 0;font-weight:bold;color:#495057;'>Due Date:</td>
                            <td style='padding:8px 0;color:#dc3545;font-weight:bold;'>{$dueDate}</td>
                        </tr>
                    </table>
                </div>
                
                <p><strong>Please visit the Barangay Health Center on or before the due date to ensure your child receives the necessary supplementation.</strong></p>
                
                <div style='background:#fff3cd;border:1px solid #ffeaa7;border-radius:6px;padding:15px;margin:15px 0;'>
                    <p style='margin:0;color:#856404;'><strong>📋 Important Notes:</strong></p>
                    <ul style='margin:10px 0;color:#856404;'>
                        <li>Please bring your child's health record</li>
                        <li>Arrive during clinic hours</li>
                        <li>Contact us if you have any questions</li>
                    </ul>
                </div>
                
                <p style='color:#6c757d;font-size:12px;margin-top:30px;'>
                    This is an automated reminder from the Barangay Health Center.<br>
                    If you have any questions, please contact us directly.
                </p>
                
                <div style='border-top:1px solid #e9ecef;padding-top:15px;margin-top:20px;text-align:center;color:#6c757d;font-size:12px;'>
                    <p>Powered by Barangay Health System</p>
                </div>
            </div>
        ";
        
        $text = "Supplementation Reminder\n\n".
                "Hello {$parentName},\n\n".
                "This is a reminder about your child's upcoming supplementation:\n\n".
                "Child: {$childName}\n".
                "Age: {$ageMonths} months\n".
                "Supplement: {$supplementType}\n".
                "Due Date: {$dueDate}\n\n".
                "Please visit the Barangay Health Center on or before the due date.\n\n".
                "Thank you,\nBarangay Health Center";
        
        $emailSent = bhw_mail_send($data['parent_email'], $subject, $html, $text);
        
        if ($emailSent) {
            ok(['message' => 'Notification sent successfully', 'email' => $data['parent_email']]);
        } else {
            err('Failed to send notification: ' . (bhw_mail_last_error() ?: 'Unknown error'), 500);
        }
    }
    
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
                TIMESTAMPDIFF(MONTH, c.birth_date, CURDATE()) AS current_age_months,
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
                c.birth_date,
                TIMESTAMPDIFF(MONTH, c.birth_date, CURDATE()) AS current_age_months,
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