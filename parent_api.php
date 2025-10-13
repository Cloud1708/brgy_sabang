<?php
date_default_timezone_set('Asia/Manila');
require_once __DIR__.'/inc/db.php';
require_once __DIR__.'/auth.php';
require_role(['Parent']);
if (session_status()===PHP_SESSION_NONE) session_start();

header('Content-Type: application/json; charset=utf-8');

function out($d){ echo json_encode($d); exit; }
function bad($m,$c=400){ http_response_code($c); out(['success'=>false,'error'=>$m]); }

$pid = (int)($_SESSION['user_id'] ?? 0);
if ($pid <= 0) bad('Not authenticated',401);

/*
 Endpoints:
  ?children=1
  ?immunization_card=child_id
  ?vaccination_timeline=child_id
  ?growth=child_id
  ?nutrition_chart=child_id
  ?notifications=1
  ?appointments=child_id
  ?summary=child_id
*/

if (isset($_GET['children'])) {
    $rows=[];
    $sql="
      SELECT c.child_id,c.full_name,c.sex,c.birth_date,
             TIMESTAMPDIFF(MONTH,c.birth_date,CURDATE()) age_months
      FROM parent_child_access p
      JOIN children c ON c.child_id=p.child_id
      WHERE p.parent_user_id=? AND p.is_active=1
      ORDER BY c.full_name
    ";
    $st=$mysqli->prepare($sql);
    $st->bind_param('i',$pid);
    $st->execute();
    $r=$st->get_result();
    while($x=$r->fetch_assoc()) $rows[]=$x;
    $st->close();
    out(['success'=>true,'children'=>$rows]);
}

if (isset($_GET['immunization_card'])) {
    $cid=(int)$_GET['immunization_card'];
    if(!$cid) bad('child_id required');
    $auth=$mysqli->prepare("SELECT 1 FROM parent_child_access WHERE parent_user_id=? AND child_id=? AND is_active=1");
    $auth->bind_param('ii',$pid,$cid);
    $auth->execute(); $auth->store_result();
    if($auth->num_rows===0){ $auth->close(); bad('Forbidden',403); }
    $auth->close();

    $card=[];
    $res=$mysqli->query("
      SELECT vt.vaccine_code,vt.vaccine_name,ci.dose_number,ci.vaccination_date,ci.next_dose_due_date
      FROM vaccine_types vt
      LEFT JOIN child_immunizations ci
        ON ci.vaccine_id=vt.vaccine_id AND ci.child_id={$cid}
      WHERE vt.is_active=1
      ORDER BY vt.vaccine_name, ci.dose_number
    ");
    while($r=$res->fetch_assoc()) $card[]=$r;
    out(['success'=>true,'card'=>$card]);
}

if (isset($_GET['vaccination_timeline'])) {
    $cid=(int)$_GET['vaccination_timeline'];
    if(!$cid) bad('child_id required');
    $chk=$mysqli->prepare("SELECT 1 FROM parent_child_access WHERE parent_user_id=? AND child_id=? AND is_active=1");
    $chk->bind_param('ii',$pid,$cid);
    $chk->execute(); $chk->store_result();
    if($chk->num_rows===0){ $chk->close(); bad('Forbidden',403); }
    $chk->close();

    $timeline=[];
    $res=$mysqli->query("
      SELECT ci.immunization_id,vt.vaccine_code,vt.vaccine_name,ci.dose_number,
             ci.vaccination_date,ci.next_dose_due_date,ci.batch_lot_number
      FROM child_immunizations ci
      JOIN vaccine_types vt ON vt.vaccine_id=ci.vaccine_id
      WHERE ci.child_id={$cid}
      ORDER BY ci.vaccination_date ASC, ci.immunization_id ASC
    ");
    while($r=$res->fetch_assoc()) $timeline[]=$r;
    out(['success'=>true,'timeline'=>$timeline]);
}

if (isset($_GET['growth'])) {
    $cid=(int)$_GET['growth'];
    if(!$cid) bad('child_id required');
    $chk=$mysqli->prepare("SELECT 1 FROM parent_child_access WHERE parent_user_id=? AND child_id=? AND is_active=1");
    $chk->bind_param('ii',$pid,$cid);
    $chk->execute(); $chk->store_result();
    if($chk->num_rows===0){ $chk->close(); bad('Forbidden',403); }
    $chk->close();

    $rows=[];
    $res=$mysqli->query("
      SELECT nr.record_id,nr.weighing_date,nr.age_in_months,nr.weight_kg,nr.length_height_cm,
             s.status_code,s.status_description
      FROM nutrition_records nr
      LEFT JOIN wfl_ht_status_types s ON s.status_id=nr.wfl_ht_status_id
      WHERE nr.child_id={$cid}
      ORDER BY nr.weighing_date ASC,nr.record_id ASC
    ");
    while($r=$res->fetch_assoc()) $rows[]=$r;
    out(['success'=>true,'growth'=>$rows]);
}

if (isset($_GET['notifications'])) {
    $rows=[];
    $res=$mysqli->query("
      SELECT notification_id,notification_type,title,message,due_date,is_read,created_at,child_id
      FROM parent_notifications
      WHERE parent_user_id={$pid}
      ORDER BY created_at DESC
      LIMIT 200
    ");
    while($r=$res->fetch_assoc()) $rows[]=$r;
    out(['success'=>true,'notifications'=>$rows]);
}

if (isset($_GET['appointments'])) {
    // Placeholder: could be derived from events involving that child, for now vaccination due soon
    $cid=(int)$_GET['appointments'];
    if(!$cid) bad('child_id required');
    $check=$mysqli->prepare("SELECT 1 FROM parent_child_access WHERE parent_user_id=? AND child_id=? AND is_active=1");
    $check->bind_param('ii',$pid,$cid);
    $check->execute(); $check->store_result();
    if($check->num_rows===0){ $check->close(); bad('Forbidden',403); }
    $check->close();

    $dueSoon=[];
    $res=$mysqli->query("
      SELECT vt.vaccine_code,ci.next_dose_due_date,ci.dose_number
      FROM child_immunizations ci
      JOIN vaccine_types vt ON vt.vaccine_id=ci.vaccine_id
      WHERE ci.child_id={$cid} AND ci.next_dose_due_date IS NOT NULL
      ORDER BY ci.next_dose_due_date ASC
      LIMIT 20
    ");
    while($r=$res->fetch_assoc()) $dueSoon[]=$r;
    out(['success'=>true,'upcoming'=>$dueSoon]);
}

if (isset($_GET['summary'])) {
    $cid=(int)$_GET['summary'];
    if(!$cid) bad('child_id required');
    $chk=$mysqli->prepare("SELECT 1 FROM parent_child_access WHERE parent_user_id=? AND child_id=? AND is_active=1");
    $chk->bind_param('ii',$pid,$cid);
    $chk->execute(); $chk->store_result();
    if($chk->num_rows===0){ $chk->close(); bad('Forbidden',403); }
    $chk->close();

    $child=null;
    $res=$mysqli->query("SELECT child_id,full_name,sex,birth_date FROM children WHERE child_id={$cid} LIMIT 1");
    if($res && $res->num_rows) $child=$res->fetch_assoc();

    $vaccTotal=0;$vaccGiven=0;
    $v1=$mysqli->query("SELECT SUM(doses_required) total_req FROM vaccine_types WHERE is_active=1");
    if($v1 && $r=$v1->fetch_assoc()) $vaccTotal=(int)$r['total_req'];
    $v2=$mysqli->query("SELECT COUNT(*) c FROM child_immunizations WHERE child_id={$cid}");
    if($v2 && $r=$v2->fetch_assoc()) $vaccGiven=(int)$r['c'];
    $pct = ($vaccTotal>0)? round(($vaccGiven/$vaccTotal)*100,1):0.0;

    out(['success'=>true,'child'=>$child,'vaccination_completion_pct'=>$pct,'doses_given'=>$vaccGiven,'doses_total'=>$vaccTotal]);
}

bad('Unknown action',404);