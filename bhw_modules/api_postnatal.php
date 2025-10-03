<?php
require_once __DIR__.'/../inc/db.php';
require_once __DIR__.'/../inc/auth.php';
require_role(['BHW']);
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json; charset=utf-8');

ini_set('display_errors','0');
error_reporting(E_ALL);

function fail($m,$c=400){ http_response_code($c); echo json_encode(['success'=>false,'error'=>$m]); exit; }
function ok($d=[]){ echo json_encode(array_merge(['success'=>true],$d)); exit; }

$method = $_SERVER['REQUEST_METHOD'];

function csrf_ok(){
  return !empty($_POST['csrf_token']) && !empty($_SESSION['csrf_token'])
    && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token']);
}

/* --------------------- GET --------------------- */
if($method==='GET'){

  // List visits per mother
  if(isset($_GET['list']) && isset($_GET['mother_id'])){
    $mother_id=(int)$_GET['mother_id'];
    if($mother_id<=0) fail('Invalid mother_id');
    $rows=[];
    $stmt=$GLOBALS['mysqli']->prepare("
      SELECT pv.postnatal_visit_id, pv.mother_id, pv.child_id,
             c.full_name AS child_name,
             pv.delivery_date, pv.visit_date, pv.postpartum_day,
             pv.bp_systolic, pv.bp_diastolic, pv.temperature_c,
             pv.lochia_status, pv.breastfeeding_status, pv.danger_signs,
             pv.swelling, pv.fever, pv.foul_lochia, pv.mastitis, pv.postpartum_depression,
             pv.other_findings, pv.notes, pv.created_at
      FROM postnatal_visits pv
      LEFT JOIN children c ON c.child_id=pv.child_id
      WHERE pv.mother_id=?
      ORDER BY pv.visit_date DESC, pv.postnatal_visit_id DESC
      LIMIT 300
    ");
    if(!$stmt) fail('Prepare failed: '.$GLOBALS['mysqli']->error,500);
    $stmt->bind_param('i',$mother_id);
    $stmt->execute();
    $res=$stmt->get_result();
    while($r=$res->fetch_assoc()) $rows[]=$r;
    $stmt->close();
    ok(['visits'=>$rows]);
  }

  // Summary (latest per mother) risk snapshot
  if(isset($_GET['summary'])){
    $rows=[];
    $sql="
      SELECT m.mother_id, m.full_name,
             pv.visit_date, pv.postpartum_day,
             (pv.fever + pv.foul_lochia + pv.mastitis + pv.postpartum_depression + pv.swelling) AS danger_score,
             pv.fever, pv.foul_lochia, pv.mastitis, pv.postpartum_depression, pv.swelling
      FROM mothers_caregivers m
      JOIN (
        SELECT x.*
        FROM postnatal_visits x
        JOIN (
          SELECT mother_id, MAX(visit_date) max_date
          FROM postnatal_visits
          GROUP BY mother_id
        ) r ON r.mother_id=x.mother_id AND r.max_date=x.visit_date
      ) pv ON pv.mother_id=m.mother_id
      WHERE (pv.fever=1 OR pv.foul_lochia=1 OR pv.mastitis=1 OR pv.postpartum_depression=1 OR pv.swelling=1)
      ORDER BY danger_score DESC, pv.visit_date DESC
      LIMIT 300
    ";
    $res=$GLOBALS['mysqli']->query($sql);
    if($res) while($r=$res->fetch_assoc()) $rows[]=$r;
    ok(['summary'=>$rows]);
  }

  // Children of a mother (helper)
  if(isset($_GET['children_of']) ){
    $mid=(int)$_GET['children_of'];
    $rows=[];
    if($mid>0){
      $stmt=$GLOBALS['mysqli']->prepare("SELECT child_id, full_name, birth_date FROM children WHERE mother_id=? ORDER BY birth_date DESC");
      $stmt->bind_param('i',$mid);
      $stmt->execute();
      $res=$stmt->get_result();
      while($r=$res->fetch_assoc()) $rows[]=$r;
      $stmt->close();
    }
    ok(['children'=>$rows]);
  }

  fail('Unknown GET action',404);
}

/* --------------------- POST --------------------- */
if($method==='POST'){
  if(!csrf_ok()) fail('CSRF failed',419);

  if(isset($_POST['add_visit'])){
    $mother_id=(int)($_POST['mother_id']??0);
    $child_id = ($_POST['child_id']!=='') ? (int)$_POST['child_id'] : null;
    $delivery_date = trim($_POST['delivery_date']??'');
    if($delivery_date==='') $delivery_date=null;
    $visit_date = trim($_POST['visit_date']??'');
    if($mother_id<=0 || !$visit_date) fail('Required: mother_id & visit_date');

    // Vitals
    $bp_sys = ($_POST['bp_systolic']!=='') ? (int)$_POST['bp_systolic'] : null;
    $bp_dia = ($_POST['bp_diastolic']!=='') ? (int)$_POST['bp_diastolic'] : null;
    $temp   = ($_POST['temperature_c']!=='') ? (float)$_POST['temperature_c'] : null;

    $lochia = trim($_POST['lochia_status']??'');
    $bf     = trim($_POST['breastfeeding_status']??'');
    $dangerSigns=[];
    foreach(['fever','foul_lochia','mastitis','postpartum_depression','swelling'] as $k){
      $$k = isset($_POST[$k]) ? 1 : 0;
      if($$k) $dangerSigns[]=$k;
    }
    $other_findings = trim($_POST['other_findings']??'');
    $notes          = trim($_POST['notes']??'');

    // compute postpartum day
    $pp_day=null;
    if($delivery_date){
      $vd=strtotime($visit_date.' 00:00:00');
      $dd=strtotime($delivery_date.' 00:00:00');
      if($vd && $dd){
        $pp_day = (int)floor(($vd-$dd)/86400);
      }
    }

    $danger_text = trim(implode(',',$dangerSigns));
    $recorded_by = (int)($_SESSION['user_id']??0);

    $stmt=$GLOBALS['mysqli']->prepare("
      INSERT INTO postnatal_visits
        (mother_id,child_id,delivery_date,visit_date,postpartum_day,
         bp_systolic,bp_diastolic,temperature_c,lochia_status,breastfeeding_status,
         danger_signs,swelling,fever,foul_lochia,mastitis,postpartum_depression,
         other_findings,notes,recorded_by)
      VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
    ");
    if(!$stmt) fail('Prepare failed: '.$GLOBALS['mysqli']->error,500);
    $stmt->bind_param(
      'iissiiidsssiiiiiissi',
      $mother_id,$child_id,$delivery_date,$visit_date,$pp_day,
      $bp_sys,$bp_dia,$temp,$lochia,$bf,
      $danger_text,$swelling,$fever,$foul_lochia,$mastitis,$postpartum_depression,
      $other_findings,$notes,$recorded_by
    );
    if(!$stmt->execute()) fail('Insert failed: '.$stmt->error,500);
    $id=$stmt->insert_id;
    $stmt->close();
    ok(['postnatal_visit_id'=>$id,'postpartum_day'=>$pp_day]);
  }

  fail('Unknown POST action',400);
}

fail('Invalid method',405);