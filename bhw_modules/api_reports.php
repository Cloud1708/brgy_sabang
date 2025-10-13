<?php
require_once __DIR__.'/../inc/db.php';
require_once __DIR__.'/../inc/auth.php';
require_role(['BHW']);

if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json; charset=utf-8');

ini_set('display_errors','0');
error_reporting(E_ALL);

set_error_handler(function($sev,$msg,$file,$line){
    http_response_code(500);
    echo json_encode([
        'success'=>false,
        'error'=>"PHP Error: $msg",
        'file'=>$file,
        'line'=>$line
    ]);
    exit;
});
set_exception_handler(function($ex){
    http_response_code(500);
    echo json_encode([
        'success'=>false,
        'error'=>"Exception: ".$ex->getMessage()
    ]);
    exit;
});

function fail($m,$c=400){
    http_response_code($c);
    echo json_encode(['success'=>false,'error'=>$m]);
    exit;
}
function ok($d=[]){
    echo json_encode(array_merge(['success'=>true],$d));
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
if($method!=='GET') fail('Invalid method',405);

/* =========================================================
   VACCINATION COVERAGE
   ========================================================= */
if (isset($_GET['vaccination_coverage'])) {

    // Total children
    $res = $mysqli->query("SELECT COUNT(*) c FROM children");
    $row = $res? $res->fetch_assoc():['c'=>0];
    $totalChildren = (int)$row['c'];

    // Active vaccines & total required doses
    $activeVaccines = [];
    $sumRequired = 0;
    $res = $mysqli->query("SELECT vaccine_id,doses_required FROM vaccine_types WHERE is_active=1");
    if($res){
        while($r=$res->fetch_assoc()){
            $activeVaccines[] = (int)$r['vaccine_id'];
            $sumRequired += (int)$r['doses_required'];
        }
    }
    $activeVaxCount = count($activeVaccines);

    // Total administered doses
    $res = $mysqli->query("SELECT COUNT(*) c FROM child_immunizations");
    $row = $res? $res->fetch_assoc():['c'=>0];
    $totalAdmin = (int)$row['c'];

    $theoretical = ($totalChildren>0 && $sumRequired>0) ? $totalChildren * $sumRequired : 0;
    $overallPct = $theoretical>0 ? round($totalAdmin / $theoretical * 100, 2) : 0.0;

    // Fully immunized children:
    // A child is fully immunized if for every active vaccine, the number of doses
    // recorded for that vaccine >= doses_required.
    $fully = 0;
    if ($activeVaxCount > 0 && $totalChildren > 0) {
        $sqlFully = "
          SELECT COUNT(*) fully_immunized FROM (
            SELECT c.child_id
            FROM children c
            /* active vaccines */
            JOIN vaccine_types vt_all ON vt_all.is_active=1
            /* map each active vaccine to recorded dose counts */
            LEFT JOIN (
              SELECT child_id,vaccine_id,COUNT(*) dose_count
              FROM child_immunizations
              GROUP BY child_id,vaccine_id
            ) d ON d.child_id=c.child_id AND d.vaccine_id=vt_all.vaccine_id
            GROUP BY c.child_id
            HAVING SUM(
              CASE
                WHEN d.dose_count IS NULL THEN 1           -- no record yet for an active vaccine
                WHEN d.dose_count < vt_all.doses_required THEN 1
                ELSE 0
              END
            ) = 0
          ) x
        ";
        $fr = $mysqli->query($sqlFully);
        if($fr && $fr->num_rows){
            $fully = (int)$fr->fetch_assoc()['fully_immunized'];
        }
    }

    // Per vaccine coverage
    $per = [];
    $sql = "
        SELECT vt.vaccine_id,
               vt.vaccine_code,
               vt.vaccine_name,
               vt.doses_required,
               IFNULL(anyDose.cnt_any,0) AS children_with_any_dose,
               IFNULL(fullDose.cnt_full,0) AS children_completed
        FROM vaccine_types vt
        LEFT JOIN (
            SELECT vaccine_id, COUNT(DISTINCT child_id) AS cnt_any
            FROM child_immunizations
            GROUP BY vaccine_id
        ) anyDose ON anyDose.vaccine_id = vt.vaccine_id
        LEFT JOIN (
            SELECT t.vaccine_id, COUNT(*) AS cnt_full
            FROM (
                SELECT vaccine_id, child_id, COUNT(*) dose_count
                FROM child_immunizations
                GROUP BY vaccine_id, child_id
            ) t
            JOIN vaccine_types vx ON vx.vaccine_id = t.vaccine_id
            WHERE t.dose_count >= vx.doses_required
            GROUP BY t.vaccine_id
        ) fullDose ON fullDose.vaccine_id = vt.vaccine_id
        WHERE vt.is_active=1
        ORDER BY vt.vaccine_name
    ";
    $res = $mysqli->query($sql);
    if(!$res){
        fail('Coverage query failed: '.$mysqli->error,500);
    }
    while($r=$res->fetch_assoc()){
        $any = (int)$r['children_with_any_dose'];
        $full = (int)$r['children_completed'];
        $r['any_coverage_pct']  = $totalChildren>0 ? round($any / $totalChildren * 100, 2) : 0.0;
        $r['full_coverage_pct'] = $totalChildren>0 ? round($full / $totalChildren * 100, 2) : 0.0;
        $per[] = $r;
    }

    ok([
        'total_children'              => $totalChildren,
        'active_vaccines'             => $activeVaxCount,
        'total_required_doses'        => $theoretical,
        'total_administered_doses'    => $totalAdmin,
        'overall_dose_coverage_pct'   => $overallPct,
        'fully_immunized_children'    => $fully,
        'per_vaccine'                 => $per
    ]);
}

/* ========== MATERNAL & RISK REPORTS UNCHANGED BELOW ========== */

if (isset($_GET['maternal_stats'])) {
    $row = $mysqli->query("SELECT COUNT(*) c FROM maternal_patients")->fetch_assoc();
    $totalMothers = (int)$row['c'];

    $row = $mysqli->query("SELECT COUNT(*) c FROM health_records")->fetch_assoc();
    $totalConsults = (int)$row['c'];

    $row = $mysqli->query("
        SELECT COUNT(DISTINCT mother_id) risky
        FROM health_records
        WHERE (vaginal_bleeding=1 OR urinary_infection=1 OR high_blood_pressure=1
               OR fever_38_celsius=1 OR pallor=1 OR abnormal_abdominal_size=1
               OR abnormal_presentation=1 OR absent_fetal_heartbeat=1
               OR swelling=1 OR vaginal_infection=1)
    ")->fetch_assoc();
    $mothersWithRisk = (int)$row['risky'];

    $row = $mysqli->query("SELECT MAX(consultation_date) last_date FROM health_records")->fetch_assoc();
    $lastDate = $row['last_date'];

    $avgPerMother = $totalMothers>0 ? round($totalConsults / $totalMothers, 2) : 0;

    $recent = [];
    $res = $mysqli->query("
      SELECT m.mother_id,CONCAT(m.first_name, ' ', COALESCE(m.middle_name, ''), ' ', m.last_name) as full_name,
        (SELECT COUNT(*) FROM health_records hr WHERE hr.mother_id=m.mother_id) AS consults,
        (SELECT MAX(consultation_date) FROM health_records hr2 WHERE hr2.mother_id=m.mother_id) AS last_consult
      FROM maternal_patients m
      ORDER BY last_consult DESC
      LIMIT 10
    ");
    while($res && $r=$res->fetch_assoc()) $recent[]=$r;

    ok([
        'total_mothers'            => $totalMothers,
        'total_consultations'      => $totalConsults,
        'mothers_with_risks'       => $mothersWithRisk,
        'last_consultation_date'   => $lastDate,
        'avg_consults_per_mother'  => $avgPerMother,
        'recent'                   => $recent
    ]);
}

if (isset($_GET['health_risks'])) {
    $agg = $mysqli->query("
      SELECT
        SUM(vaginal_bleeding) vb,
        SUM(urinary_infection) ui,
        SUM(high_blood_pressure) hbp,
        SUM(fever_38_celsius) fev,
        SUM(pallor) pal,
        SUM(abnormal_abdominal_size) abd,
        SUM(abnormal_presentation) pres,
        SUM(absent_fetal_heartbeat) fht,
        SUM(swelling) swl,
        SUM(vaginal_infection) vag
      FROM (
        SELECT hr.*
        FROM health_records hr
        JOIN (
          SELECT mother_id, MAX(consultation_date) max_date
          FROM health_records
          GROUP BY mother_id
        ) x ON x.mother_id=hr.mother_id AND x.max_date=hr.consultation_date
      ) latest
    ");
    $aggregate = $agg? $agg->fetch_assoc():[];

    $details = [];
    $sql = "
      SELECT m.mother_id, CONCAT(m.first_name, ' ', COALESCE(m.middle_name, ''), ' ', m.last_name) as full_name,
        hr.consultation_date, hr.pregnancy_age_weeks,
        (hr.vaginal_bleeding + hr.urinary_infection + hr.high_blood_pressure +
         hr.fever_38_celsius + hr.pallor + hr.abnormal_abdominal_size +
         hr.abnormal_presentation + hr.absent_fetal_heartbeat + hr.swelling +
         hr.vaginal_infection) AS risk_score,
        hr.vaginal_bleeding, hr.urinary_infection, hr.high_blood_pressure,
        hr.fever_38_celsius, hr.pallor, hr.abnormal_abdominal_size,
        hr.abnormal_presentation, hr.absent_fetal_heartbeat, hr.swelling,
        hr.vaginal_infection
      FROM maternal_patients m
      JOIN (
        SELECT x.*
        FROM health_records x
        JOIN (
          SELECT mother_id, MAX(consultation_date) AS max_date
          FROM health_records
          GROUP BY mother_id
        ) r ON r.mother_id = x.mother_id AND r.max_date = x.consultation_date
      ) hr ON hr.mother_id = m.mother_id
      WHERE (hr.vaginal_bleeding=1 OR hr.urinary_infection=1 OR hr.high_blood_pressure=1
         OR hr.fever_38_celsius=1 OR hr.pallor=1 OR hr.abnormal_abdominal_size=1
         OR hr.abnormal_presentation=1 OR hr.absent_fetal_heartbeat=1
         OR hr.swelling=1 OR hr.vaginal_infection=1)
      ORDER BY risk_score DESC, hr.consultation_date DESC
      LIMIT 500
    ";
    $res = $mysqli->query($sql);
    if($res){
        while($r=$res->fetch_assoc()) $details[]=$r;
    }

    ok([
        'aggregate'=>$aggregate,
        'details'=>$details
    ]);
}

fail('Unknown report',404);