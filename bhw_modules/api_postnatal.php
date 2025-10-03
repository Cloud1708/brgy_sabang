<?php
require_once __DIR__.'/../inc/db.php';
require_once __DIR__.'/../inc/auth.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD']==='OPTIONS') {
    http_response_code(204); exit;
}

if (session_status() === PHP_SESSION_NONE) session_start();

function fail($msg,$code=400){
    http_response_code($code);
    echo json_encode(['success'=>false,'error'=>$msg]);
    exit;
}

function nullIfEmpty($v){
    return ($v === '' || $v === null) ? null : $v;
}

if ($_SERVER['REQUEST_METHOD']==='POST') {
    require_role(['BHW']);
    $csrf = $_POST['csrf_token'] ?? '';
    if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'],$csrf)) {
        fail('Invalid CSRF token',403);
    }

    if (isset($_POST['add_visit'])) {

        $mother_id  = (int)($_POST['mother_id'] ?? 0);
        if ($mother_id <= 0) fail('mother_id required');

        $child_id       = ($_POST['child_id'] ?? '') === '' ? null : (int)$_POST['child_id'];
        $delivery_date  = nullIfEmpty(trim($_POST['delivery_date'] ?? ''));
        $visit_date     = trim($_POST['visit_date'] ?? '');
        if ($visit_date === '') fail('visit_date required');

        // postpartum_day
        $pp_day = null;
        if ($delivery_date) {
            $d1 = DateTime::createFromFormat('Y-m-d',$delivery_date);
            $d2 = DateTime::createFromFormat('Y-m-d',$visit_date);
            if ($d1 && $d2) {
                $pp_day = (int)$d1->diff($d2)->format('%a');
                if ($pp_day < 0) $pp_day = 0;
            }
        }

        $bp_systolic   = nullIfEmpty($_POST['bp_systolic']  ?? '');
        $bp_diastolic  = nullIfEmpty($_POST['bp_diastolic'] ?? '');
        $bp_systolic   = $bp_systolic  === null ? null : (int)$bp_systolic;
        $bp_diastolic  = $bp_diastolic === null ? null : (int)$bp_diastolic;

        $temperature_c = nullIfEmpty($_POST['temperature_c'] ?? '');
        $temperature_c = $temperature_c === null ? null : (float)$temperature_c;

        $lochia_status        = trim($_POST['lochia_status'] ?? '');
        $breastfeeding_status = trim($_POST['breastfeeding_status'] ?? '');
        $other_findings       = trim($_POST['other_findings'] ?? '');
        $notes                = trim($_POST['notes'] ?? '');

        // Danger flags (store as ints)
        $fever                 = isset($_POST['fever']) ? 1 : 0;
        $foul_lochia           = isset($_POST['foul_lochia']) ? 1 : 0;
        $mastitis              = isset($_POST['mastitis']) ? 1 : 0;
        $postpartum_depression = isset($_POST['postpartum_depression']) ? 1 : 0;
        $swelling              = isset($_POST['swelling']) ? 1 : 0;

        $dangerPieces=[];
        if($fever) $dangerPieces[]='fever';
        if($foul_lochia) $dangerPieces[]='foul_lochia';
        if($mastitis) $dangerPieces[]='mastitis';
        if($postpartum_depression) $dangerPieces[]='postpartum_depression';
        if($swelling) $dangerPieces[]='swelling';
        $danger_signs = implode(',', $dangerPieces);

        $recorded_by = (int)($_SESSION['user_id'] ?? 0);

        $sql = "INSERT INTO postnatal_visits
          (mother_id,child_id,delivery_date,visit_date,postpartum_day,
           bp_systolic,bp_diastolic,temperature_c,lochia_status,breastfeeding_status,
           danger_signs,swelling,fever,foul_lochia,mastitis,postpartum_depression,
           other_findings,notes,recorded_by)
          VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";

        $stmt = $mysqli->prepare($sql);
        if(!$stmt) fail('Prepare failed: '.$mysqli->error,500);

        // Explicit types array (NO hidden chars)
        $typesParts = [
            'i', // mother_id
            'i', // child_id
            's', // delivery_date
            's', // visit_date
            'i', // postpartum_day
            'i', // bp_systolic
            'i', // bp_diastolic
            'd', // temperature_c
            's', // lochia_status
            's', // breastfeeding_status
            's', // danger_signs
            'i', // swelling
            'i', // fever
            'i', // foul_lochia
            'i', // mastitis
            'i', // postpartum_depression
            's', // other_findings
            's', // notes
            'i', // recorded_by
        ];

        $types = implode('', $typesParts);

        $placeholderCount = substr_count($sql,'?');
        if ($placeholderCount !== count($typesParts)) {
            fail("Internal mismatch (placeholders=$placeholderCount typeSlots=".count($typesParts).')',500);
        }

        // Params in exact order as columns
        $params = [
            $mother_id,$child_id,$delivery_date,$visit_date,$pp_day,
            $bp_systolic,$bp_diastolic,$temperature_c,$lochia_status,$breastfeeding_status,
            $danger_signs,$swelling,$fever,$foul_lochia,$mastitis,$postpartum_depression,
            $other_findings,$notes,$recorded_by
        ];

        // Bind dynamically to avoid copy errors
        $bindOk = $stmt->bind_param($types, ...$params);
        if(!$bindOk){
            fail('bind_param failed',500);
        }

        if(!$stmt->execute()){
            fail('Insert failed: '.$stmt->error,500);
        }

        echo json_encode(['success'=>true,'postnatal_visit_id'=>$stmt->insert_id]);
        exit;
    }

    fail('Unknown POST action',400);
}

/* ==== READ endpoints ==== */
if ($_SERVER['REQUEST_METHOD']==='GET') {
    require_role(['BHW']);

    if (isset($_GET['list']) && isset($_GET['mother_id'])) {
        $mother_id = (int)$_GET['mother_id'];
        $res = $mysqli->prepare("SELECT * FROM postnatal_visits WHERE mother_id=? ORDER BY visit_date ASC, postnatal_visit_id ASC");
        $res->bind_param('i',$mother_id);
        $res->execute();
        $q = $res->get_result();
        $vis = [];
        while($row=$q->fetch_assoc()){
            $vis[] = $row;
        }
        echo json_encode(['success'=>true,'visits'=>$vis]);
        exit;
    }

    if (isset($_GET['children_of'])) {
        $mid = (int)$_GET['children_of'];
        $res = $mysqli->prepare("SELECT child_id, full_name, birth_date FROM children WHERE mother_id=? ORDER BY birth_date ASC");
        $res->bind_param('i',$mid);
        $res->execute();
        $q=$res->get_result();
        $kids=[];
        while($r=$q->fetch_assoc()) $kids[]=$r;
        echo json_encode(['success'=>true,'children'=>$kids]);
        exit;
    }

    if (isset($_GET['followups'])) {
        $rows = $mysqli->query("SELECT v.*,
            (v.fever+v.foul_lochia+v.mastitis+v.postpartum_depression+v.swelling) AS danger_score
            FROM postnatal_visits v
            INNER JOIN (
              SELECT mother_id, MAX(visit_date) AS latest_visit
              FROM postnatal_visits GROUP BY mother_id
            ) t ON t.mother_id = v.mother_id AND t.latest_visit = v.visit_date
        ");
        $out=[];
        $today = new DateTime('today');
        while($r=$rows->fetch_assoc()){
            $needs = 0;
            if ((int)$r['danger_score']>0) {
                $needs=1;
            } else {
                if ($r['visit_date']) {
                    $vd = DateTime::createFromFormat('Y-m-d',$r['visit_date']);
                    if($vd){
                        $diff = $today->diff($vd)->days;
                        if ($diff>7 && (int)$r['postpartum_day'] <= 42) $needs=1;
                    }
                }
            }
            $r['needs_followup']=$needs;
            $out[]=$r;
        }
        echo json_encode(['success'=>true,'followups'=>$out]);
        exit;
    }

    fail('Unknown GET action',400);
}

fail('Unsupported method',405);