<?php
require_once __DIR__.'/../inc/db.php';
require_once __DIR__.'/../inc/auth.php';
require_role(['BNS']);
header('Content-Type: application/json; charset=utf-8');
if (session_status()===PHP_SESSION_NONE) session_start();

function fail($m,$c=400){ http_response_code($c); echo json_encode(['success'=>false,'error'=>$m]); exit; }
$method = $_SERVER['REQUEST_METHOD'];
$mysqli->set_charset('utf8mb4');

/**
 * Provisional BMI-based classification.
 * Replace with WHO Weight-for-Length/Height standards later.
 */
function provisional_classify($weightKg, $lengthCm) {
    if ($weightKg <= 0 || $lengthCm <= 0) return null;
    $m = $lengthCm / 100;
    if ($m <= 0) return null;
    $bmi = $weightKg / ($m*$m);

    if ($bmi < 12.5)       return 'SAM';
    if ($bmi < 13.0)       return 'MAM';
    if ($bmi < 13.5)       return 'UW';
    if ($bmi < 17.5)       return 'NOR';
    if ($bmi < 19.0)       return 'OW';
    return 'OB';
}

if ($method==='GET') {

    /* NEW: on-the-fly classification */
    if (isset($_GET['classify'])) {
        $weight = isset($_GET['weight']) ? (float)$_GET['weight'] : 0;
        $length = isset($_GET['length']) ? (float)$_GET['length'] : 0;
        if ($weight <= 0 || $length <= 0) fail('Invalid weight/length');

        $code = provisional_classify($weight,$length);
        if (!$code) echo json_encode(['success'=>true,'status'=>null]);

        // Fetch mapping
        $stmt = $mysqli->prepare("SELECT status_id, status_description FROM wfl_ht_status_types WHERE status_code=? LIMIT 1");
        $stmt->bind_param('s',$code);
        $stmt->execute();
        $stmt->bind_result($sid,$sdesc);
        if ($stmt->fetch()) {
            echo json_encode([
                'success'=>true,
                'status_code'=>$code,
                'status_id'=>$sid,
                'status_description'=>$sdesc,
                'bmi'=>round($weight / pow($length/100,2),2)
            ]);
            $stmt->close();
            exit;
        }
        $stmt->close();
        echo json_encode(['success'=>true,'status_code'=>$code,'status_id'=>null,'status_description'=>null]); exit;
    }

    if (isset($_GET['recent'])) {
        $sql = "
          SELECT nr.record_id, nr.weighing_date, nr.age_in_months, nr.weight_kg,
                 nr.length_height_cm, nr.remarks,
                 c.full_name AS child_name,
                 s.status_code, s.status_description
          FROM nutrition_records nr
          JOIN children c ON c.child_id = nr.child_id
          LEFT JOIN wfl_ht_status_types s ON s.status_id = nr.wfl_ht_status_id
          ORDER BY nr.weighing_date DESC, nr.record_id DESC
          LIMIT 300
        ";
        $res=$mysqli->query($sql);
        $rows=[]; while($r=$res->fetch_assoc()) $rows[]=$r;
        echo json_encode(['success'=>true,'records'=>$rows]); exit;
    }

    if (isset($_GET['classification_summary'])) {
        $sql = "
          SELECT t.wfl_ht_status_id, w.status_code, w.status_description,
                 COUNT(*) AS child_count
          FROM (
            SELECT nr1.child_id, nr1.wfl_ht_status_id
            FROM nutrition_records nr1
            JOIN (
              SELECT child_id, MAX(weighing_date) AS max_date
              FROM nutrition_records
              GROUP BY child_id
            ) mx ON mx.child_id = nr1.child_id AND mx.max_date = nr1.weighing_date
            GROUP BY nr1.child_id
          ) t
          LEFT JOIN wfl_ht_status_types w ON w.status_id = t.wfl_ht_status_id
          GROUP BY t.wfl_ht_status_id, w.status_code, w.status_description
          ORDER BY child_count DESC
        ";
        $res=$mysqli->query($sql);
        $rows=[]; while($r=$res->fetch_assoc()) $rows[]=$r;
        echo json_encode(['success'=>true,'summary'=>$rows]); exit;
    }

    if (isset($_GET['combined'])) {
        $date = $_GET['date'] ?? date('Y-m-d');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/',$date)) $date = date('Y-m-d');

        $sql = "
          SELECT c.child_id,
                 c.full_name AS child_name,
                 c.sex,
                 c.birth_date,
                 TIMESTAMPDIFF(MONTH,c.birth_date, ?) AS age_months,
                 m.full_name AS mother_name,
                 p.purok_name,
                 nr.record_id,
                 nr.weight_kg,
                 nr.length_height_cm,
                 nr.wfl_ht_status_id,
                 s.status_code,
                 s.status_description
          FROM children c
          LEFT JOIN mothers_caregivers m ON m.mother_id = c.mother_id
          LEFT JOIN puroks p ON p.purok_id = m.purok_id
          LEFT JOIN nutrition_records nr
                 ON nr.child_id = c.child_id
                AND nr.weighing_date = ?
          LEFT JOIN wfl_ht_status_types s ON s.status_id = nr.wfl_ht_status_id
          ORDER BY p.purok_name ASC, m.full_name ASC, c.full_name ASC
        ";
        $stmt = $mysqli->prepare($sql);
        if(!$stmt) fail('DB prepare failed');
        $stmt->bind_param('ss',$date,$date);
        $stmt->execute();
        $res = $stmt->get_result();
        $rows=[];
        while($r=$res->fetch_assoc()) $rows[]=$r;
        $stmt->close();
        echo json_encode([
          'success'=>true,
          'request_date'=>$date,
          'children'=>$rows
        ]);
        exit;
    }

    fail('No action',400);
}

if ($method==='POST') {
    $raw = file_get_contents('php://input');
    $isJson = strlen(trim($raw)) && (str_starts_with(trim($raw),'[') || str_contains($raw,'"bulk"'));

    if ($isJson) {
        $data = json_decode($raw,true);
        if (!is_array($data)) fail('Invalid JSON');
        $records = isset($data['bulk']) && is_array($data['bulk']) ? $data['bulk'] : (is_array($data)?$data:[]);
        if (empty($records)) fail('No payload');

        $hdr = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'],$hdr)) {
            fail('CSRF failed',419);
        }

        $inserted=0; $updated=0; $saved=0;
        $stmtAge = $mysqli->prepare("SELECT TIMESTAMPDIFF(MONTH,birth_date,?) FROM children WHERE child_id=? LIMIT 1");
        $ins = $mysqli->prepare("
          INSERT INTO nutrition_records
            (child_id,weighing_date,age_in_months,weight_kg,length_height_cm,wfl_ht_status_id,remarks,recorded_by)
          VALUES (?,?,?,?,?,?,?,?)
          ON DUPLICATE KEY UPDATE
            age_in_months=VALUES(age_in_months),
            weight_kg=VALUES(weight_kg),
            length_height_cm=VALUES(length_height_cm),
            wfl_ht_status_id=VALUES(wfl_ht_status_id),
            remarks=VALUES(remarks),
            updated_at=NOW()
        ");
        if(!$stmtAge || !$ins) fail('Prepare failed');

        // Preload status code -> id map
        $map=[]; $rs=$mysqli->query("SELECT status_id,status_code FROM wfl_ht_status_types");
        while($x=$rs->fetch_assoc()) $map[$x['status_code']]=$x['status_id'];

        foreach($records as $r){
            $child_id = (int)($r['child_id'] ?? 0);
            $date = $r['weighing_date'] ?? '';
            if ($child_id<=0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/',$date)) continue;

            $weight = isset($r['weight_kg']) && $r['weight_kg']!=='' ? (float)$r['weight_kg'] : null;
            $length = isset($r['length_height_cm']) && $r['length_height_cm']!=='' ? (float)$r['length_height_cm'] : null;

            // Auto classify if status not explicitly provided
            $status_id = null;
            if (isset($r['wfl_ht_status_id']) && $r['wfl_ht_status_id']!=='') {
                $status_id = (int)$r['wfl_ht_status_id'];
            } else {
                if ($weight && $length) {
                    $code = provisional_classify($weight,$length);
                    if ($code && isset($map[$code])) $status_id = $map[$code];
                }
            }

            $remarks = ''; // reserved
            $recorded_by = (int)($_SESSION['user_id'] ?? 0);

            $stmtAge->bind_param('si',$date,$child_id);
            $stmtAge->execute();
            $stmtAge->bind_result($age_mo);
            if(!$stmtAge->fetch()){ $stmtAge->free_result(); continue; }
            $stmtAge->free_result();

            $ins->bind_param(
                'isiddisi',
                $child_id,
                $date,
                $age_mo,
                $weight,
                $length,
                $status_id,
                $remarks,
                $recorded_by
            );
            if($ins->execute()){
                $saved++;
                if ($ins->affected_rows === 1) $inserted++;
                elseif ($ins->affected_rows === 2) $updated++;
            }
        }
        $stmtAge->close();
        $ins->close();
        echo json_encode(['success'=>true,'saved'=>$saved,'inserted'=>$inserted,'updated'=>$updated]); exit;
    }

    if (empty($_POST['csrf_token']) || empty($_SESSION['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        fail('CSRF failed',419);
    }
    $child_id = (int)($_POST['child_id'] ?? 0);
    $weighing_date = $_POST['weighing_date'] ?? '';
    $weight = $_POST['weight_kg'] !== '' ? (float)$_POST['weight_kg'] : null;
    $length = $_POST['length_height_cm'] !== '' ? (float)$_POST['length_height_cm'] : null;
    $status_id = ($_POST['wfl_ht_status_id'] ?? '') !== '' ? (int)$_POST['wfl_ht_status_id'] : null;
    $remarks = trim($_POST['remarks'] ?? '');
    $rec_by = (int)($_SESSION['user_id'] ?? 0);

    if ($child_id<=0 || !$weighing_date) fail('Missing required fields');

    // Auto classify if not provided
    if (!$status_id && $weight && $length) {
        $code = provisional_classify($weight,$length);
        if ($code) {
            $st = $mysqli->prepare("SELECT status_id FROM wfl_ht_status_types WHERE status_code=? LIMIT 1");
            $st->bind_param('s',$code);
            $st->execute();
            $st->bind_result($sid);
            if ($st->fetch()) $status_id = $sid;
            $st->close();
        }
    }

    $stmt = $mysqli->prepare("SELECT TIMESTAMPDIFF(MONTH,birth_date,?) FROM children WHERE child_id=? LIMIT 1");
    $stmt->bind_param('si',$weighing_date,$child_id);
    $stmt->execute();
    $stmt->bind_result($age_months);
    if(!$stmt->fetch()) { $stmt->close(); fail('Child not found',404); }
    $stmt->close();

    $stmt = $mysqli->prepare("INSERT INTO nutrition_records (child_id,weighing_date,age_in_months,weight_kg,length_height_cm,wfl_ht_status_id,remarks,recorded_by) VALUES (?,?,?,?,?,?,?,?)");
    if(!$stmt) fail('DB prepare failed');
    $stmt->bind_param(
        'isiddisi',
        $child_id,
        $weighing_date,
        $age_months,
        $weight,
        $length,
        $status_id,
        $remarks,
        $rec_by
    );
    if(!$stmt->execute()) {
        if (strpos($stmt->error,'Duplicate')!==false) fail('Record already exists for that child & date',409);
        fail('Insert failed: '.$stmt->error,500);
    }
    $id = $stmt->insert_id;
    $stmt->close();
    echo json_encode(['success'=>true,'record_id'=>$id,'age_in_months'=>$age_months,'auto_status_id'=>$status_id]); exit;
}

fail('Method not allowed',405);