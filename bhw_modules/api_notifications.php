<?php
require_once __DIR__.'/../inc/db.php';
require_once __DIR__.'/../inc/auth.php';
require_role(['BHW']);
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json; charset=utf-8');

function fail($m,$c=400){ http_response_code($c); echo json_encode(['success'=>false,'error'=>$m]); exit; }
function ok($d=[]){ echo json_encode(array_merge(['success'=>true],$d)); exit; }

$method = $_SERVER['REQUEST_METHOD'];

/* ----------------- HELPERS ----------------- */

function build_due_date($birth_date,$target_months){
    if(!$birth_date) return null;
    try{
        $dt = new DateTime($birth_date);
        $dt->modify("+{$target_months} months");
        return $dt->format('Y-m-d');
    }catch(Exception $e){ return null; }
}

function ensure_notification($mysqli,$child_id,$parent_user_id,$type,$title,$message,$due_date,$related_vaccine_id,$fingerprint){
    // If fingerprint column exists use it; else fallback duplicate check by (child,type,title)
    $useFingerprint = false;
    $colsRes = $mysqli->query("SHOW COLUMNS FROM parent_notifications LIKE 'fingerprint'");
    if($colsRes && $colsRes->num_rows>0) $useFingerprint = true;

    if($useFingerprint){
        $chk = $mysqli->prepare("SELECT notification_id FROM parent_notifications WHERE fingerprint=? LIMIT 1");
        $chk->bind_param('s',$fingerprint);
    } else {
        $chk = $mysqli->prepare("SELECT notification_id FROM parent_notifications WHERE child_id=? AND parent_user_id=? AND notification_type=? AND title=? LIMIT 1");
        $chk->bind_param('iiss',$child_id,$parent_user_id,$type,$title);
    }
    $chk->execute(); $chk->store_result();
    if($chk->num_rows>0){ $chk->close(); return false; }
    $chk->close();

    $sql = "INSERT INTO parent_notifications
        (parent_user_id,child_id,notification_type,title,message,related_vaccine_id,due_date,is_read,is_sent,created_by"
        .($useFingerprint?',fingerprint':'').")
        VALUES (?,?,?,?,?,?,?,0,0,?,"
        .($useFingerprint?'?':'').")";
    $stmt = $mysqli->prepare($sql);
    $creator = $_SESSION['user_id'] ?? null;

    if($useFingerprint){
        $stmt->bind_param(
            'iisssissis',
            $parent_user_id,$child_id,$type,$title,$message,$related_vaccine_id,$due_date,$creator,$fingerprint
        );
    } else {
        $stmt->bind_param(
            'iisssissi',
            $parent_user_id,$child_id,$type,$title,$message,$related_vaccine_id,$due_date,$creator
        );
    }

    if(!$stmt->execute()){
        $err=$stmt->error;
        $stmt->close();
        fail('Insert notification failed: '.$err,500);
    }
    $stmt->close();
    return true;
}

/* ------------- CORE GENERATION LOGIC ------------- */
function generate_notifications($mysqli){
    // Pull children + birthdates first
    $children=[];
    $res=$mysqli->query("SELECT child_id, full_name, birth_date, TIMESTAMPDIFF(MONTH,birth_date,CURDATE()) AS age_months FROM children");
    while($r=$res->fetch_assoc()) $children[$r['child_id']]=$r;
    if(empty($children)) return ['added'=>0,'skipped'=>0,'reason'=>'No children'];

    // Map parent links (parent_child_access)
    $parentLinks=[];
    $res=$mysqli->query("SELECT parent_user_id, child_id FROM parent_child_access WHERE is_active=1");
    while($r=$res->fetch_assoc()){
        $parentLinks[$r['child_id']][] = (int)$r['parent_user_id'];
    }
    if(empty($parentLinks)) return ['added'=>0,'skipped'=>0,'reason'=>'No active parent links'];

    // Existing immunizations map
    $immunMap=[];
    $res=$mysqli->query("SELECT child_id,vaccine_id,dose_number FROM child_immunizations");
    while($r=$res->fetch_assoc()){
        $immunMap[$r['child_id']][$r['vaccine_id']][$r['dose_number']] = true;
    }

    // Fetch schedule with vaccine meta
    $schedule=[];
    $res=$mysqli->query("
      SELECT s.vaccine_id, s.dose_number, s.recommended_age_months,
             vt.vaccine_code, vt.vaccine_name
      FROM immunization_schedule s
      JOIN vaccine_types vt ON vt.vaccine_id=s.vaccine_id
      WHERE vt.is_active=1
      ORDER BY vt.vaccine_name,s.dose_number
    ");
    while($r=$res->fetch_assoc()) $schedule[]=$r;

    $added=0; $skipped=0; $processedDue=0; $processedOverdue=0;

    foreach($children as $cid=>$c){
        $age = (int)$c['age_months'];
        foreach($schedule as $sc){
            // skip if child not linked to parents
            if(empty($parentLinks[$cid])) continue;
            // skip if already immunized
            if(isset($immunMap[$cid][$sc['vaccine_id']][$sc['dose_number']])) continue;

            $target = (int)$sc['recommended_age_months'];

            // classification
            $isOverdue = ($age > $target + 1);
            $isDueSoon = (!$isOverdue && ($age >= ($target - 1) && $age <= $target));

            if(!$isOverdue && !$isDueSoon) continue;

            $due_date = build_due_date($c['birth_date'],$target);
            $dose = $sc['dose_number'];
            $vcode = $sc['vaccine_code'];
            $vid   = $sc['vaccine_id'];

            if($isOverdue){
                $processedOverdue++;
                $type='vaccine_overdue';
                $title="Overdue {$vcode} Dose {$dose}";
                $msg="The scheduled dose {$dose} of {$sc['vaccine_name']} ({$vcode}) is overdue.";
                $fp="{$cid}|over|{$vcode}|{$dose}";
            } else {
                $processedDue++;
                $type='vaccine_due';
                $title="Upcoming {$vcode} Dose {$dose}";
                $msg="Dose {$dose} of {$sc['vaccine_name']} ({$vcode}) is due soon.";
                $fp="{$cid}|due|{$vcode}|{$dose}";
            }

            foreach($parentLinks[$cid] as $pid){
                $ok = ensure_notification($mysqli,$cid,$pid,$type,$title,$msg,$due_date,$vid,$fp);
                $ok ? $added++ : $skipped++;
            }
        }
    }

    return [
        'added'=>$added,
        'skipped'=>$skipped,
        'due_candidates'=>$processedDue,
        'overdue_candidates'=>$processedOverdue
    ];
}

/* ====================== GET ====================== */
if ($method === 'GET') {

    if (isset($_GET['list'])) {
        $rows=[];
        $res = $mysqli->query("
          SELECT pn.notification_id, pn.parent_user_id, pn.child_id, pn.notification_type,
                 pn.title, pn.message, pn.due_date, pn.is_read, pn.is_sent,
                 pn.created_at,
                 c.full_name AS child_name,
                 u.username AS parent_username
          FROM parent_notifications pn
          JOIN children c ON c.child_id = pn.child_id
          JOIN users u ON u.user_id = pn.parent_user_id
          ORDER BY pn.created_at DESC
          LIMIT 500
        ");
        while($r=$res->fetch_assoc()) $rows[]=$r;
        ok(['notifications'=>$rows]);
    }

    if (isset($_GET['generate'])) {
        $stats = generate_notifications($mysqli);
        ok(['generated'=>true,'stats'=>$stats]);
    }

    fail('Unknown GET',404);
}

/* ====================== POST ====================== */
if ($method === 'POST') {
    if (empty($_POST['csrf_token']) || empty($_SESSION['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        fail('CSRF failed',419);
    }

    if (isset($_POST['generate_notifications'])) {
        $stats = generate_notifications($mysqli);
        ok(['generated'=>true,'stats'=>$stats]);
    }

    if (isset($_POST['mark_read'])) {
        $nid = (int)$_POST['mark_read'];
        if ($nid<=0) fail('Invalid notification_id');
        $stmt=$mysqli->prepare("UPDATE parent_notifications SET is_read=1, read_at=NOW() WHERE notification_id=? LIMIT 1");
        $stmt->bind_param('i',$nid);
        if(!$stmt->execute()) fail('Update failed: '.$stmt->error,500);
        $stmt->close();
        ok(['updated'=>1]);
    }

    if (isset($_POST['mark_all_read'])) {
        $filterType = trim($_POST['filter_type'] ?? '');
        if($filterType!==''){
            $stmt=$mysqli->prepare("UPDATE parent_notifications SET is_read=1, read_at=NOW() WHERE notification_type=? AND is_read=0");
            $stmt->bind_param('s',$filterType);
        } else {
            $stmt=$mysqli->prepare("UPDATE parent_notifications SET is_read=1, read_at=NOW() WHERE is_read=0");
        }
        if(!$stmt->execute()) fail('Update failed: '.$stmt->error,500);
        $affected=$stmt->affected_rows;
        $stmt->close();
        ok(['marked_read'=>$affected]);
    }

    fail('Unknown POST action',400);
}

fail('Invalid method',405);