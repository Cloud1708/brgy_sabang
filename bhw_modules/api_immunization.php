<?php
require_once __DIR__.'/../inc/db.php';
require_once __DIR__.'/../inc/auth.php';
require_role(['BHW']);
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json; charset=utf-8');

function fail($m,$c=400){ http_response_code($c); echo json_encode(['success'=>false,'error'=>$m]); exit; }
function ok($data=[]){ echo json_encode(array_merge(['success'=>true],$data)); exit; }

$method = $_SERVER['REQUEST_METHOD'];

function age_months($birth){
    $b = strtotime($birth);
    if(!$b) return null;
    $now = strtotime(date('Y-m-d'));
    $diff = (int)floor(($now - $b)/86400);
    return (int)floor($diff/30.4375);
}

function require_csrf(){
    if (empty($_POST['csrf_token']) || empty($_SESSION['csrf_token'])
        || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        fail('CSRF failed',419);
    }
}

/* ---------- Standard Vaccine Template (used by bulk add) ---------- */
function standard_vaccines(){
    return [
        [
            'code'=>'BCG','name'=>'BCG Vaccine','category'=>'birth','doses_required'=>1,
            'schedule'=>[['dose'=>1,'age'=>0]]
        ],
        [
            'code'=>'HEPB','name'=>'Hepatitis B Vaccine','category'=>'infant','doses_required'=>3,
            'schedule'=>[['dose'=>1,'age'=>0],['dose'=>2,'age'=>1],['dose'=>3,'age'=>6]]
        ],
        [
            'code'=>'PENTA','name'=>'Pentavalent Vaccine (DPT-Hep B-HIB)','category'=>'infant','doses_required'=>3,
            'schedule'=>[['dose'=>1,'age'=>1],['dose'=>2,'age'=>2],['dose'=>3,'age'=>3]]
        ],
        [
            'code'=>'OPV','name'=>'Oral Polio Vaccine (OPV)','category'=>'infant','doses_required'=>3,
            'schedule'=>[['dose'=>1,'age'=>1],['dose'=>2,'age'=>2],['dose'=>3,'age'=>3]]
        ],
        [
            'code'=>'IPV','name'=>'Inactivated Polio Vaccine (IPV)','category'=>'infant','doses_required'=>2,
            'schedule'=>[['dose'=>1,'age'=>3],['dose'=>2,'age'=>14]]
        ],
        [
            'code'=>'PCV','name'=>'Pneumococcal Conjugate Vaccine (PCV)','category'=>'infant','doses_required'=>3,
            'schedule'=>[['dose'=>1,'age'=>1],['dose'=>2,'age'=>2],['dose'=>3,'age'=>12]]
        ],
        [
            'code'=>'MMR','name'=>'Measles, Mumps, Rubella Vaccine (MMR)','category'=>'child','doses_required'=>2,
            'schedule'=>[['dose'=>1,'age'=>9],['dose'=>2,'age'=>12]]
        ],
        [
            'code'=>'MCV','name'=>'Measles Containing Vaccine (MCV) MR/MMR Booster','category'=>'child','doses_required'=>1,
            'schedule'=>[['dose'=>1,'age'=>24]]
        ],
        [
            'code'=>'TD','name'=>'Tetanus Diphtheria (TD)','category'=>'booster','doses_required'=>2,
            'schedule'=>[['dose'=>1,'age'=>132],['dose'=>2,'age'=>144]]
        ],
        [
            'code'=>'HPV','name'=>'Human Papillomavirus Vaccine (HPV)','category'=>'booster','doses_required'=>2,
            'schedule'=>[['dose'=>1,'age'=>132],['dose'=>2,'age'=>138]]
        ]
    ];
}

/* =============================== GET =============================== */
if ($method === 'GET') {

    if (isset($_GET['children'])) {
        $rows=[];
        $res=$mysqli->query("
          SELECT child_id, full_name, sex, birth_date,
                 TIMESTAMPDIFF(MONTH,birth_date,CURDATE()) AS age_months
          FROM children
          ORDER BY full_name ASC
          LIMIT 1000
        ");
        while($r=$res->fetch_assoc()) $rows[]=$r;
        ok(['children'=>$rows]);
    }

    if (isset($_GET['vaccines'])) {
        $rows=[];
        $res=$mysqli->query("
          SELECT vaccine_id,
                 vaccine_code,
                 vaccine_name,
                 vaccine_description,
                 target_age_group,
                 vaccine_category,
                 doses_required,
                 interval_between_doses_days,
                 is_active
          FROM vaccine_types
          WHERE is_active=1
          ORDER BY vaccine_category, vaccine_name
          LIMIT 1000
        ");
        while($r=$res->fetch_assoc()) $rows[]=$r;
        ok(['vaccines'=>$rows]);
    }

    if (isset($_GET['records']) && isset($_GET['child_id'])) {
        $cid=(int)$_GET['child_id'];
        if($cid<=0) fail('Invalid child_id');
        $rows=[];
        $stmt=$mysqli->prepare("
          SELECT ci.immunization_id, ci.vaccine_id,
                 vt.vaccine_code, vt.vaccine_name,
                 ci.dose_number, ci.vaccination_date, ci.vaccination_site,
                 ci.batch_lot_number, ci.next_dose_due_date,
                 ci.adverse_reactions, ci.notes, ci.created_at
          FROM child_immunizations ci
          JOIN vaccine_types vt ON vt.vaccine_id=ci.vaccine_id
          WHERE ci.child_id=?
          ORDER BY vt.vaccine_name, ci.dose_number
        ");
        $stmt->bind_param('i',$cid);
        $stmt->execute();
        $res=$stmt->get_result();
        while($r=$res->fetch_assoc()) $rows[]=$r;
        $stmt->close();
        ok(['records'=>$rows]);
    }

    if (isset($_GET['card']) && isset($_GET['child_id'])) {
        $cid=(int)$_GET['child_id'];
        if($cid<=0) fail('Invalid child_id');

        $child=null;
        $stmt=$mysqli->prepare("SELECT child_id,full_name,sex,birth_date FROM children WHERE child_id=? LIMIT 1");
        $stmt->bind_param('i',$cid);
        $stmt->execute(); $res=$stmt->get_result();
        if($res && $res->num_rows) $child=$res->fetch_assoc();
        $stmt->close();
        if(!$child) fail('Child not found',404);

        $vaccines=[];
        $res=$mysqli->query("
          SELECT vaccine_id,vaccine_code,vaccine_name,doses_required,vaccine_category
          FROM vaccine_types
          WHERE is_active=1
          ORDER BY vaccine_category,vaccine_name
        ");
        while($r=$res->fetch_assoc()){ $r['doses']=[]; $vaccines[$r['vaccine_id']]=$r; }
        if($vaccines){
            $ids=implode(',',array_map('intval',array_keys($vaccines)));
            $res=$mysqli->query("
              SELECT vaccine_id,dose_number,vaccination_date,next_dose_due_date
              FROM child_immunizations
              WHERE child_id={$cid} AND vaccine_id IN ($ids)
            ");
            while($r=$res->fetch_assoc()){
                $vaccines[$r['vaccine_id']]['doses'][]=$r;
            }
        }
        ok(['child'=>$child,'vaccines'=>array_values($vaccines)]);
    }

    if (isset($_GET['overdue'])) {
        $children=[];
        $res=$mysqli->query("SELECT child_id,full_name,birth_date, TIMESTAMPDIFF(MONTH,birth_date,CURDATE()) AS age_months FROM children");
        while($r=$res->fetch_assoc()) $children[$r['child_id']]=$r;
        if(!$children) ok(['overdue'=>[],'dueSoon'=>[]]);

        $existing=[];
        $res=$mysqli->query("SELECT child_id,vaccine_id,dose_number FROM child_immunizations");
        while($r=$res->fetch_assoc()){
            $existing[$r['child_id']][$r['vaccine_id']][$r['dose_number']]=true;
        }

        $sched=[];
        $res=$mysqli->query("
          SELECT s.vaccine_id,s.dose_number,s.recommended_age_months,
                 vt.vaccine_name,vt.vaccine_code
          FROM immunization_schedule s
          JOIN vaccine_types vt ON vt.vaccine_id=s.vaccine_id
          ORDER BY vt.vaccine_name,s.dose_number
        ");
        while($r=$res->fetch_assoc()) $sched[]=$r;

        $overdue=[]; $dueSoon=[];
        foreach($children as $c){
            foreach($sched as $sc){
                if(isset($existing[$c['child_id']][$sc['vaccine_id']][$sc['dose_number']])) continue;
                $age = (int)$c['age_months'];
                $target=(int)$sc['recommended_age_months'];
                if($age > $target + 1){
                    $overdue[]=[
                      'child_id'=>$c['child_id'],
                      'child_name'=>$c['full_name'],
                      'age_months'=>$age,
                      'vaccine_code'=>$sc['vaccine_code'],
                      'vaccine_name'=>$sc['vaccine_name'],
                      'dose_number'=>$sc['dose_number'],
                      'target_age_months'=>$target
                    ];
                } elseif ($age >= ($target - 1) && $age <= $target){
                    $dueSoon[]=[
                      'child_id'=>$c['child_id'],
                      'child_name'=>$c['full_name'],
                      'age_months'=>$age,
                      'vaccine_code'=>$sc['vaccine_code'],
                      'vaccine_name'=>$sc['vaccine_name'],
                      'dose_number'=>$sc['dose_number'],
                      'target_age_months'=>$target
                    ];
                }
            }
        }
        ok(['overdue'=>$overdue,'dueSoon'=>$dueSoon]);
    }

    if (isset($_GET['schedule'])) { // still available if needed
        $rows=[];
        $res=$mysqli->query("
          SELECT vt.vaccine_id, vt.vaccine_code, vt.vaccine_name, vt.vaccine_category,
                 vt.doses_required, vt.is_active,
                 s.schedule_id, s.dose_number, s.recommended_age_months
          FROM vaccine_types vt
          LEFT JOIN immunization_schedule s ON s.vaccine_id=vt.vaccine_id
          WHERE vt.is_active=1
          ORDER BY vt.vaccine_category, vt.vaccine_name, s.dose_number
        ");
        while($r=$res->fetch_assoc()) $rows[]=$r;
        ok(['schedule'=>$rows]);
    }

    fail('Unknown GET action',404);
}

/* =============================== POST =============================== */
if ($method === 'POST') {
    require_csrf();

    /* ---- Add child ---- */
    if (isset($_POST['add_child'])) {
        $full_name = trim($_POST['full_name'] ?? '');
        $sex = $_POST['sex'] ?? '';
        $birth_date = $_POST['birth_date'] ?? '';
        $mother_id = (int)($_POST['mother_id'] ?? 0);
        $rec_by = (int)($_SESSION['user_id'] ?? 0);

        if ($full_name==='' || ($sex!=='male' && $sex!=='female') || !$birth_date || $mother_id<=0) {
            fail('Required child fields missing.');
        }

        $chk=$mysqli->prepare("SELECT mother_id FROM mothers_caregivers WHERE mother_id=? LIMIT 1");
        $chk->bind_param('i',$mother_id);
        $chk->execute(); $chk->bind_result($mid);
        if(!$chk->fetch()){ $chk->close(); fail('Mother not found',404); }
        $chk->close();

        $ins=$mysqli->prepare("
          INSERT INTO children (full_name,sex,birth_date,mother_id,created_by)
          VALUES (?,?,?,?,?)
        ");
        $ins->bind_param('sssii',$full_name,$sex,$birth_date,$mother_id,$rec_by);
        if(!$ins->execute()) fail('Child insert failed: '.$ins->error,500);
        $cid=$ins->insert_id;
        $ins->close();

        ok(['child_id'=>$cid,'age_months'=>age_months($birth_date)]);
    }

    /* ---- Add / Update vaccine ---- */
    if (isset($_POST['add_update_vaccine'])) {
        $vaccine_id = (int)($_POST['vaccine_id'] ?? 0);
        $vaccine_code = strtoupper(trim($_POST['vaccine_code'] ?? ''));
        $vaccine_name = trim($_POST['vaccine_name'] ?? '');
        $vaccine_description = trim($_POST['vaccine_description'] ?? '');
        $target_age_group = trim($_POST['target_age_group'] ?? '');
        $vaccine_category = trim($_POST['vaccine_category'] ?? '');
        $doses_required = (int)($_POST['doses_required'] ?? 1);
        $interval_raw = trim($_POST['interval_between_doses_days'] ?? '');
        $interval_between = ($interval_raw==='' ? null : (int)$interval_raw);

        if ($vaccine_code==='' || $vaccine_name==='' || $doses_required<=0) {
            fail('Required vaccine fields missing.');
        }
        $validCats=['birth','infant','child','booster','adult'];
        if(!in_array($vaccine_category,$validCats,true)) fail('Invalid vaccine_category');

        $dup=$mysqli->prepare("SELECT vaccine_id FROM vaccine_types WHERE vaccine_code=? LIMIT 1");
        $dup->bind_param('s',$vaccine_code);
        $dup->execute(); $dup->bind_result($eid);
        $exists=false;
        if($dup->fetch()) $exists=$eid;
        $dup->close();
        if($exists && $vaccine_id===0) fail('Vaccine code already exists.');
        if($exists && $vaccine_id>0 && $exists!=$vaccine_id) fail('Vaccine code belongs to another record.');

        if($vaccine_id>0){
            $sql="UPDATE vaccine_types
                  SET vaccine_code=?,vaccine_name=?,vaccine_description=?,target_age_group=?,
                      vaccine_category=?,doses_required=?,interval_between_doses_days=?
                  WHERE vaccine_id=? LIMIT 1";
            $stmt=$mysqli->prepare($sql);
            $stmt->bind_param('sssssiis',
                $vaccine_code,$vaccine_name,$vaccine_description,$target_age_group,
                $vaccine_category,$doses_required,$interval_between,$vaccine_id
            );
            if(!$stmt->execute()) fail('Update failed: '.$stmt->error,500);
            $stmt->close();
            ok(['mode'=>'updated','vaccine_id'=>$vaccine_id]);
        } else {
            $sql="INSERT INTO vaccine_types
                (vaccine_code,vaccine_name,vaccine_description,target_age_group,
                 vaccine_category,doses_required,interval_between_doses_days,is_active)
                VALUES (?,?,?,?,?,?,?,1)";
            $stmt=$mysqli->prepare($sql);
            $stmt->bind_param('sssssiis',
                $vaccine_code,$vaccine_name,$vaccine_description,$target_age_group,
                $vaccine_category,$doses_required,$interval_between
            );
            if(!$stmt->execute()) fail('Insert failed: '.$stmt->error,500);
            $vid=$stmt->insert_id;
            $stmt->close();
            ok(['mode'=>'inserted','vaccine_id'=>$vid]);
        }
    }

    /* ---- Delete vaccine (no existing immunizations) ---- */
    if (isset($_POST['delete_vaccine_id'])) {
        $vid=(int)$_POST['delete_vaccine_id'];
        if($vid<=0) fail('Invalid vaccine id');
        $chk=$mysqli->prepare("SELECT COUNT(*) c FROM child_immunizations WHERE vaccine_id=?");
        $chk->bind_param('i',$vid);
        $chk->execute(); $chk->bind_result($cnt); $chk->fetch(); $chk->close();
        if($cnt>0) fail('Cannot delete: existing child immunization records.');
        $mysqli->query("DELETE FROM immunization_schedule WHERE vaccine_id=$vid");
        $del=$mysqli->prepare("DELETE FROM vaccine_types WHERE vaccine_id=? LIMIT 1");
        $del->bind_param('i',$vid);
        if(!$del->execute()) fail('Delete failed: '.$del->error,500);
        $del->close();
        ok(['deleted_vaccine_id'=>$vid]);
    }

    /* ---- Add Immunization Record (vaccination entry) ---- */
    if (
        isset($_POST['child_id']) &&
        isset($_POST['dose_number']) &&
        isset($_POST['vaccination_date']) &&
        (isset($_POST['vaccine_id']) || isset($_POST['vaccine_code'])) &&
        !isset($_POST['add_schedule']) &&
        !isset($_POST['add_update_vaccine'])
    ) {
        $child_id=(int)($_POST['child_id'] ?? 0);
        $dose_number=(int)($_POST['dose_number'] ?? 0);
        $vaccination_date=$_POST['vaccination_date'] ?? '';
        $vaccination_site=trim($_POST['vaccination_site'] ?? '');
        $batch_lot_number=trim($_POST['batch_lot_number'] ?? '');
        $notes=trim($_POST['notes'] ?? '');
        $adverse=trim($_POST['adverse_reactions'] ?? '');
        $recorded_by=(int)($_SESSION['user_id'] ?? 0);

        $vaccine_id_raw=trim($_POST['vaccine_id'] ?? '');
        $vaccine_id = ctype_digit($vaccine_id_raw)?(int)$vaccine_id_raw:0;

        // Auto-create via code if numeric id not supplied
        if($vaccine_id<=0 && !empty($_POST['vaccine_code'])){
            $code=strtoupper(preg_replace('/[^A-Z0-9_-]/','',$_POST['vaccine_code']));
            if($code!==''){
                $find=$mysqli->prepare("SELECT vaccine_id,doses_required FROM vaccine_types WHERE UPPER(vaccine_code)=? LIMIT 1");
                $find->bind_param('s',$code);
                $find->execute(); $res=$find->get_result();
                if($row=$res->fetch_assoc()){
                    $vaccine_id=(int)$row['vaccine_id'];
                } else {
                    $name=trim($_POST['vaccine_name'] ?? $code);
                    $cat=trim($_POST['vaccine_category'] ?? 'infant');
                    $dreq=(int)($_POST['doses_required'] ?? 1);
                    if($dreq<=0) $dreq=1;
                    $auto=$mysqli->prepare("INSERT INTO vaccine_types (vaccine_code,vaccine_name,vaccine_category,doses_required,is_active) VALUES (?,?,?,?,1)");
                    $auto->bind_param('sssi',$code,$name,$cat,$dreq);
                    if(!$auto->execute()) fail('Auto-create vaccine failed: '.$auto->error,500);
                    $vaccine_id=$auto->insert_id;
                    $auto->close();
                }
                $find->close();
            }
        }

        if($child_id<=0 || $vaccine_id<=0 || $dose_number<=0 || !$vaccination_date){
            fail('Required fields missing.');
        }

        $stmt=$mysqli->prepare("SELECT doses_required, interval_between_doses_days FROM vaccine_types WHERE vaccine_id=? LIMIT 1");
        $stmt->bind_param('i',$vaccine_id);
        $stmt->execute(); $stmt->bind_result($dreq,$interval_days);
        if(!$stmt->fetch()){ $stmt->close(); fail('Vaccine not found',404); }
        $stmt->close();

        $next_due=null;
        if($dose_number < (int)$dreq && $interval_days !== null){
            $ts=strtotime($vaccination_date.' +'.(int)$interval_days.' days');
            if($ts) $next_due=date('Y-m-d',$ts);
        }

        $ins=$mysqli->prepare("
          INSERT INTO child_immunizations
            (child_id,vaccine_id,dose_number,vaccination_date,vaccination_site,
             batch_lot_number,administered_by,next_dose_due_date,adverse_reactions,notes)
          VALUES (?,?,?,?,?,?,?,?,?,?)
        ");
        $ins->bind_param(
            'iiisssisss',
            $child_id,$vaccine_id,$dose_number,$vaccination_date,$vaccination_site,
            $batch_lot_number,$recorded_by,$next_due,$adverse,$notes
        );
        if(!$ins->execute()){
            if(stripos($ins->error,'duplicate')!==false) fail('Duplicate entry (already recorded dose).',409);
            fail('Insert failed: '.$ins->error,500);
        }
        $iid=$ins->insert_id;
        $ins->close();
        ok(['immunization_id'=>$iid,'next_dose_due_date'=>$next_due]);
    }

    /* ---- (Optional) Add/Update schedule row (still supported) ---- */
    if (isset($_POST['add_schedule'])) {
        $vaccine_code = strtoupper(preg_replace('/[^A-Z0-9_-]/','', $_POST['vaccine_code'] ?? ''));
        $vaccine_name = trim($_POST['vaccine_name'] ?? '');
        $vaccine_category = trim($_POST['vaccine_category'] ?? 'infant');
        $doses_required = (int)($_POST['doses_required'] ?? 1);
        $dose_number = (int)($_POST['dose_number'] ?? 0);
        $age_months = (int)($_POST['recommended_age_months'] ?? -1);
        if($vaccine_code===''||$vaccine_name===''||$dose_number<=0||$age_months<0) fail('Incomplete schedule data.');
        if($doses_required<=0) $doses_required=1;
        $validCats=['birth','infant','child','booster','adult'];
        if(!in_array($vaccine_category,$validCats,true)) fail('Invalid category');

        $vid=0;
        $stmt=$mysqli->prepare("SELECT vaccine_id,doses_required FROM vaccine_types WHERE UPPER(vaccine_code)=? LIMIT 1");
        $stmt->bind_param('s',$vaccine_code);
        $stmt->execute(); $res=$stmt->get_result();
        if($row=$res->fetch_assoc()){
            $vid=(int)$row['vaccine_id'];
            if($doses_required > (int)$row['doses_required']){
                $up=$mysqli->prepare("UPDATE vaccine_types SET doses_required=? WHERE vaccine_id=? LIMIT 1");
                $up->bind_param('ii',$doses_required,$vid);
                $up->execute(); $up->close();
            }
        }
        $stmt->close();
        if($vid===0){
            $ins=$mysqli->prepare("INSERT INTO vaccine_types (vaccine_code,vaccine_name,vaccine_category,doses_required,is_active) VALUES (?,?,?,?,1)");
            $ins->bind_param('sssi',$vaccine_code,$vaccine_name,$vaccine_category,$doses_required);
            if(!$ins->execute()) fail('Create vaccine failed: '.$ins->error,500);
            $vid=$ins->insert_id;
            $ins->close();
        }

        $chk=$mysqli->prepare("SELECT schedule_id FROM immunization_schedule WHERE vaccine_id=? AND dose_number=? LIMIT 1");
        $chk->bind_param('ii',$vid,$dose_number);
        $chk->execute(); $chk->bind_result($sid);
        if($chk->fetch()){
            $chk->close();
            $upd=$mysqli->prepare("UPDATE immunization_schedule SET recommended_age_months=? WHERE schedule_id=?");
            $upd->bind_param('ii',$age_months,$sid);
            $upd->execute(); $upd->close();
            ok(['updated_schedule_id'=>$sid,'vaccine_id'=>$vid,'mode'=>'updated']);
        }
        $chk->close();
        $ins2=$mysqli->prepare("INSERT INTO immunization_schedule (vaccine_id,dose_number,recommended_age_months) VALUES (?,?,?)");
        $ins2->bind_param('iii',$vid,$dose_number,$age_months);
        if(!$ins2->execute()) fail('Insert schedule failed: '.$ins2->error,500);
        $sid=$ins2->insert_id;
        $ins2->close();
        ok(['schedule_id'=>$sid,'vaccine_id'=>$vid,'mode'=>'inserted']);
    }

    if (isset($_POST['delete_schedule_id'])) {
        $sid=(int)$_POST['delete_schedule_id'];
        if($sid<=0) fail('Invalid schedule id');
        $del=$mysqli->prepare("DELETE FROM immunization_schedule WHERE schedule_id=? LIMIT 1");
        $del->bind_param('i',$sid);
        if(!$del->execute()) fail('Delete failed: '.$del->error,500);
        $del->close();
        ok(['deleted_schedule_id'=>$sid]);
    }

    if (isset($_POST['bulk_add_standard'])) {
        $added=[]; $skipped=[];
        foreach(standard_vaccines() as $v){
            $code=$v['code'];
            $stmt=$mysqli->prepare("SELECT vaccine_id FROM vaccine_types WHERE vaccine_code=? LIMIT 1");
            $stmt->bind_param('s',$code);
            $stmt->execute(); $stmt->bind_result($vid);
            if($stmt->fetch()){
                $stmt->close();
                $skipped[]=$code;
            } else {
                $stmt->close();
                $ins=$mysqli->prepare("INSERT INTO vaccine_types (vaccine_code,vaccine_name,vaccine_category,doses_required,is_active) VALUES (?,?,?,?,1)");
                $ins->bind_param('sssi',$v['code'],$v['name'],$v['category'],$v['doses_required']);
                if(!$ins->execute()) continue;
                $vid=$ins->insert_id; $ins->close();
                foreach($v['schedule'] as $row){
                    $ins2=$mysqli->prepare("INSERT INTO immunization_schedule (vaccine_id,dose_number,recommended_age_months) VALUES (?,?,?)");
                    $ins2->bind_param('iii',$vid,$row['dose'],$row['age']);
                    $ins2->execute(); $ins2->close();
                }
                $added[]=$code;
            }
        }
        ok(['added'=>$added,'skipped'=>$skipped]);
    }

    fail('Unknown POST action',400);
}

fail('Invalid method',405);