<?php
require_once __DIR__.'/../inc/db.php';
require_once __DIR__.'/../inc/auth.php';
require_role(['BHW']); // adjust if only Admin should create

if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json; charset=utf-8');

function fail($m,$c=400){ http_response_code($c); echo json_encode(['success'=>false,'error'=>$m]); exit; }
function ok($d=[]){ echo json_encode(array_merge(['success'=>true],$d)); exit; }

$method = $_SERVER['REQUEST_METHOD'];

function require_csrf(){
    if (empty($_POST['csrf_token']) || empty($_SESSION['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        fail('CSRF failed',419);
    }
}

/* Helper: column check with cache
   NOTE: Use INFORMATION_SCHEMA instead of SHOW COLUMNS with '?'
   because MariaDB prepared statements don't allow placeholders
   in SHOW statements (causing "near '?'" syntax errors). */
function has_column(mysqli $mysqli, string $table, string $col): bool {
    static $cache = [];
    $key = $table.'|'.$col;
    if (isset($cache[$key])) return $cache[$key];

    $sql = "SELECT COUNT(*) AS c
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = ?
              AND COLUMN_NAME = ?";
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        // Fallback: assume column absent if prepare fails
        return $cache[$key] = false;
    }
    $stmt->bind_param('ss', $table, $col);
    $stmt->execute();
    $res = $stmt->get_result();
    $exists = false;
    if ($res && ($row = $res->fetch_assoc())) {
        $exists = ((int)$row['c']) > 0;
    }
    $stmt->close();
    return $cache[$key] = $exists;
}

if ($method === 'GET') {
    // Expect ?month=YYYY-MM  (defaults to current month)
    $month = preg_replace('/[^0-9\-]/','', $_GET['month'] ?? date('Y-m'));
    if (!preg_match('/^\d{4}-\d{2}$/',$month)) fail('Invalid month');
    $start = $month.'-01';
    $end   = date('Y-m-d', strtotime($start.' +1 month'));

    // Build selectable columns dynamically (add target_participants if present)
    $selectCols = [
        'event_id','event_title','event_type','event_date','event_time','location'
    ];
    if (has_column($mysqli,'events','target_participants')) $selectCols[] = 'target_participants';
    if (has_column($mysqli,'events','event_description')) $selectCols[] = 'event_description';
    // NEW: include completion/status columns if available
    if (has_column($mysqli,'events','status')) $selectCols[] = 'status';
    if (has_column($mysqli,'events','is_completed')) $selectCols[] = 'is_completed';
    if (has_column($mysqli,'events','completed_at')) $selectCols[] = 'completed_at';

    $rows=[];
    $sql = "SELECT ".implode(',', $selectCols)."
            FROM events
            WHERE event_date >= ? AND event_date < ?
            ORDER BY event_date, event_time, event_id
            LIMIT 1000";
    $stmt=$mysqli->prepare($sql);
    $stmt->bind_param('ss',$start,$end);
    $stmt->execute(); $res=$stmt->get_result();
    while($r=$res->fetch_assoc()) $rows[]=$r;
    $stmt->close();
    ok(['month'=>$month,'events'=>$rows]);
}

if ($method === 'POST') {
    require_csrf();
    if (isset($_POST['create_event'])) {
        $title = trim($_POST['event_title'] ?? '');
        $type  = $_POST['event_type'] ?? '';
        $date  = $_POST['event_date'] ?? '';
        $time  = $_POST['event_time'] ?? '';
        $loc   = trim($_POST['location'] ?? '');
        $desc  = trim($_POST['event_description'] ?? '');
        $creator = (int)($_SESSION['user_id'] ?? 0);

        // Optional numeric: target participants
        $targetRaw = trim($_POST['target_participants'] ?? '');
        $target = ($targetRaw === '' ? null : (int)$targetRaw);
        if ($target !== null && $target < 0) $target = null;

        $validTypes = ['health','nutrition','vaccination','feeding','weighing','general','other'];
        if ($title==='' || !preg_match('/^\d{4}-\d{2}-\d{2}$/',$date) || !in_array($type,$validTypes,true)){
            fail('Kulangan / invalid fields.');
        }
        if ($time !== '' && !preg_match('/^\d{2}:\d{2}(:\d{2})?$/',$time)) $time = null;

        // Dynamic insert – include target_participants only if the column exists
        $fields = ['event_title','event_description','event_type','event_date','event_time','location','created_by'];
        $types  = 'ssssssi';
        $values = [$title,$desc,$type,$date,$time,$loc,$creator];

        if (has_column($mysqli,'events','target_participants')) {
            // Insert before created_by for readability (order doesn’t matter to MySQL)
            array_splice($fields, -1, 0, 'target_participants');
            $types = substr($types,0,-1) . 'i' . substr($types,-1); // add an 'i' before final 'i'
            array_splice($values, -1, 0, $target);
        }

        $sql = "INSERT INTO events (".implode(',',$fields).") VALUES (".implode(',', array_fill(0,count($fields),'?')).")";

        $stmt=$mysqli->prepare($sql);
        if(!$stmt) fail('Prepare failed: '.$mysqli->error,500);

        $bind = [];
        $bind[] = &$types;
        foreach($values as $k=>$v){ $bind[] = &$values[$k]; }
        call_user_func_array([$stmt,'bind_param'],$bind);

        if(!$stmt->execute()) fail('Insert failed: '.$stmt->error,500);
        $id=$stmt->insert_id;
        $stmt->close();
        ok(['event_id'=>$id]);
    }

    // NEW: reschedule event (update date/time)
    if (isset($_POST['reschedule_event'])) {
        $event_id = (int)$_POST['reschedule_event'];
        $newDate  = $_POST['event_date'] ?? $_POST['new_date'] ?? '';
        $newTime  = $_POST['event_time'] ?? $_POST['new_time'] ?? '';

        if ($event_id <= 0) fail('Invalid event_id');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $newDate)) fail('Invalid date format');

        if ($newTime !== '') {
            if (!preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $newTime)) $newTime = null;
        } else {
            $newTime = null;
        }

        $sql = "UPDATE events SET event_date=?, event_time=? WHERE event_id=? LIMIT 1";
        $stmt = $mysqli->prepare($sql);
        if(!$stmt) fail('Prepare failed: '.$mysqli->error,500);
        $stmt->bind_param('ssi', $newDate, $newTime, $event_id);
        if(!$stmt->execute()) fail('Update failed: '.$stmt->error,500);
        $stmt->close();
        ok(['event_id'=>$event_id,'event_date'=>$newDate,'event_time'=>$newTime]);
    }

    // NEW: mark as completed (support multiple schemas)
    if (isset($_POST['complete_event'])) {
        $event_id = (int)$_POST['complete_event'];
        if ($event_id <= 0) fail('Invalid event_id');

        $hasIsCompleted = has_column($mysqli,'events','is_completed');
        $hasStatus      = has_column($mysqli,'events','status');
        $hasCompletedAt = has_column($mysqli,'events','completed_at');
        $hasDesc        = has_column($mysqli,'events','event_description');

        $sets = [];
        $types = '';
        $vals  = [];

        if ($hasIsCompleted) $sets[] = "is_completed=1";
        if ($hasStatus) { $sets[] = "status=?"; $types.='s'; $vals[]='completed'; }
        if ($hasCompletedAt) $sets[] = "completed_at=NOW()";

        // Fallback: prefix description so UI can detect completion
        if (!$hasIsCompleted && !$hasStatus && !$hasCompletedAt && $hasDesc) {
            $sets[] = "event_description = CONCAT('[COMPLETED] ', COALESCE(event_description,''))";
        }

        if (empty($sets)) {
            // Nothing we can update, but don't fail; return success
            ok(['event_id'=>$event_id,'marked'=>true,'note'=>'No completion column found; nothing updated']);
        }

        $sql = "UPDATE events SET ".implode(', ',$sets)." WHERE event_id=? LIMIT 1";
        $types.='i';
        $vals[] = $event_id;

        $stmt = $mysqli->prepare($sql);
        if(!$stmt) fail('Prepare failed: '.$mysqli->error,500);
        $stmt->bind_param($types, ...$vals);
        if(!$stmt->execute()) fail('Update failed: '.$stmt->error,500);
        $stmt->close();

        ok(['event_id'=>$event_id,'marked'=>true]);
    }

    fail('Unknown POST action',400);
}

fail('Invalid method',405);