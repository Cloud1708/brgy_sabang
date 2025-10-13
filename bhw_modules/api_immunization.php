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
    if (!$stmt) return $cache[$key] = false;
    $stmt->bind_param('ss', $table, $col);
    $stmt->execute();
    $res = $stmt->get_result();
    $exists = false;
    if ($res && ($row = $res->fetch_assoc())) $exists = ((int)$row['c']) > 0;
    $stmt->close();
    return $cache[$key] = $exists;
}

function standard_vaccines(){ return [
  ['code'=>'BCG','name'=>'BCG Vaccine','category'=>'birth','doses_required'=>1,'schedule'=>[['dose'=>1,'age'=>0]]],
  ['code'=>'HEPB','name'=>'Hepatitis B Vaccine','category'=>'birth','doses_required'=>1,'schedule'=>[['dose'=>1,'age'=>0]]],
  ['code'=>'PENTA','name'=>'Pentavalent Vaccine (DPT-Hep B-HIB)','category'=>'infant','doses_required'=>3,'schedule'=>[['dose'=>1,'age'=>1],['dose'=>2,'age'=>2],['dose'=>3,'age'=>3]]],
  ['code'=>'OPV','name'=>'Oral Polio Vaccine (OPV)','category'=>'infant','doses_required'=>3,'schedule'=>[['dose'=>1,'age'=>1],['dose'=>2,'age'=>2],['dose'=>3,'age'=>3]]],
  ['code'=>'IPV','name'=>'Inactivated Polio Vaccine (IPV)','category'=>'infant','doses_required'=>2,'schedule'=>[['dose'=>1,'age'=>3],['dose'=>2,'age'=>14]]],
  ['code'=>'PCV','name'=>'Pneumococcal Conjugate Vaccine (PCV)','category'=>'infant','doses_required'=>3,'schedule'=>[['dose'=>1,'age'=>1],['dose'=>2,'age'=>2],['dose'=>3,'age'=>12]]],
  ['code'=>'MMR','name'=>'Measles, Mumps, Rubella Vaccine (MMR)','category'=>'child','doses_required'=>2,'schedule'=>[['dose'=>1,'age'=>9],['dose'=>2,'age'=>12]]],
  ['code'=>'MCV','name'=>'Measles Containing Vaccine (MCV) MR/MMR Booster','category'=>'child','doses_required'=>1,'schedule'=>[['dose'=>1,'age'=>24]]],
  ['code'=>'TD','name'=>'Tetanus Diphtheria (TD)','category'=>'booster','doses_required'=>2,'schedule'=>[['dose'=>1,'age'=>132],['dose'=>2,'age'=>144]]],
  ['code'=>'HPV','name'=>'Human Papillomavirus Vaccine (HPV)','category'=>'booster','doses_required'=>2,'schedule'=>[['dose'=>1,'age'=>132],['dose'=>2,'age'=>138]]]
]; }

/**
 * Attempt to import a maternal_patients row into mothers_caregivers (so child add works).
 * Uses the SAME mother_id so existing references remain valid.
 * Returns true if mother now exists in mothers_caregivers; false if not found/import failed.
 */
function ensure_mother_in_caregivers(mysqli $mysqli, int $mother_id, int $user_id): bool {
    if ($mother_id <= 0) return false;

    // Already exists?
    $chk = $mysqli->prepare("SELECT mother_id FROM mothers_caregivers WHERE mother_id=? LIMIT 1");
    if($chk){
        $chk->bind_param('i',$mother_id);
        $chk->execute();
        $chk->bind_result($mid);
        if($chk->fetch()){
            $chk->close();
            return true; // already present
        }
        $chk->close();
    }

    // Fetch from maternal_patients
    $mp = $mysqli->prepare("SELECT first_name,middle_name,last_name,date_of_birth,emergency_contact_name,emergency_contact_number,contact_number,house_number,street_name,subdivision_name FROM maternal_patients WHERE mother_id=? LIMIT 1");
    if(!$mp){
        return false;
    }
    $mp->bind_param('i',$mother_id);
    $mp->execute();
    $res = $mp->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $mp->close();
    if(!$row) return false;

    $first = $row['first_name'] ?? '';
    $middle= $row['middle_name'] ?? '';
    $last  = $row['last_name'] ?? '';
    $full  = trim($first.' '.($middle? $middle.' ':'').$last);

    // Insert explicitly with mother_id
    $ins = $mysqli->prepare("
        INSERT INTO mothers_caregivers
        (mother_id, first_name,middle_name,last_name,full_name,date_of_birth,
         emergency_contact_name,emergency_contact_number,contact_number,
         house_number,street_name,subdivision_name,created_by)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)
    ");
    if(!$ins) return false;
    $ins->bind_param(
        'isssssssssssi',
        $mother_id,
        $first,$middle,$last,$full,$row['date_of_birth'],
        $row['emergency_contact_name'],$row['emergency_contact_number'],$row['contact_number'],
        $row['house_number'],$row['street_name'],$row['subdivision_name'],
        $user_id
    );
    $ok = $ins->execute();
    $ins->close();
    return $ok;
}

/* ===================== GET ===================== */
if ($method === 'GET') {

    /* Children list (ENHANCED + backward compatibility if full_name column missing) */
    if (isset($_GET['children'])) {
        $rows=[];
        $createdCol = has_column($mysqli,'children','created_at') ? ', c.created_at' : '';
        $hasFullNameCol = has_column($mysqli,'children','full_name');
        $fullNameExpr = $hasFullNameCol
            ? 'c.full_name'
            : "TRIM(CONCAT(c.first_name,' ',COALESCE(NULLIF(c.middle_name,''),''),' ',c.last_name)) AS full_name";
        $orderExpr = $hasFullNameCol ? 'c.full_name' : 'c.last_name, c.first_name';
        $sql = "SELECT
  c.child_id,
  c.first_name,
  c.middle_name,
  c.last_name,
  $fullNameExpr,
  c.sex,
  c.birth_date,
  c.weight_kg,
  c.height_cm,
  TIMESTAMPDIFF(MONTH,c.birth_date,CURDATE()) AS age_months,
  c.mother_id,
  mp.first_name AS mother_first_name,
  mp.middle_name AS mother_middle_name,
  mp.last_name AS mother_last_name,
  CONCAT(mp.first_name, ' ', COALESCE(mp.middle_name, ''), ' ', mp.last_name) AS mother_name,
  mp.contact_number AS mother_contact,
  mp.house_number,
  mp.street_name,
  mp.subdivision_name,
  p.purok_name,
  p.barangay
  $createdCol
FROM children c
LEFT JOIN maternal_patients mp ON mp.mother_id=c.mother_id
LEFT JOIN puroks p ON p.purok_id = mp.purok_id
ORDER BY $orderExpr ASC
LIMIT 1000";
        $res=$mysqli->query($sql);
        while($res && ($r=$res->fetch_assoc())) $rows[]=$r;
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
        $hasFullNameCol = has_column($mysqli,'children','full_name');
        $childNameExpr = $hasFullNameCol ? 'full_name' : "TRIM(CONCAT(first_name,' ',COALESCE(NULLIF(middle_name,''),''),' ',last_name)) AS full_name";
        $stmt=$mysqli->prepare("SELECT child_id,$childNameExpr,sex,birth_date FROM children WHERE child_id=? LIMIT 1");
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
              ORDER BY vaccine_id,dose_number
            ");
            while($r=$res->fetch_assoc()){
                $vaccines[$r['vaccine_id']]['doses'][]=$r;
            }
        }
        ok(['child'=>$child,'vaccines'=>array_values($vaccines)]);
    }

    // Ensure overdue_notifications table exists (used by overdue/dismiss/restore)
    $mysqli->query("
        CREATE TABLE IF NOT EXISTS overdue_notifications (
            id INT AUTO_INCREMENT PRIMARY KEY,
            child_id INT NOT NULL,
            vaccine_id INT NOT NULL,
            dose_number INT NOT NULL,
            status ENUM('active','dismissed','expired') DEFAULT 'active',
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            dismissed_at TIMESTAMP NULL,
            expires_at TIMESTAMP NULL,
            INDEX idx_child_vaccine_dose (child_id, vaccine_id, dose_number),
            INDEX idx_status (status),
            INDEX idx_expires (expires_at)
        )
    ");

    /* Overdue with pagination */
    if (isset($_GET['overdue'])) {
        // auto-expire > 1 week
        $mysqli->query("
            UPDATE overdue_notifications 
            SET status = 'expired', expires_at = NOW() 
            WHERE status = 'active' 
            AND created_at < DATE_SUB(NOW(), INTERVAL 1 WEEK)
        ");

        $page = max(1, (int)($_GET['page'] ?? 1));
        $pageSize = max(5, min(50, (int)($_GET['page_size'] ?? 10)));
        $offset = ($page - 1) * $pageSize;
        $showType = $_GET['show'] ?? 'active'; // active|recycle|all

        $children=[];
        $res=$mysqli->query("
          SELECT c.child_id,c.full_name,c.birth_date,
                 TIMESTAMPDIFF(MONTH,c.birth_date,CURDATE()) AS age_months,
                 c.mother_id, m.full_name AS mother_name, m.contact_number AS mother_contact
          FROM children c
          LEFT JOIN mothers_caregivers m ON m.mother_id=c.mother_id
        ");
        while($r=$res->fetch_assoc()) $children[$r['child_id']]=$r;
        if(!$children) ok(['overdue'=>[],'dueSoon'=>[],'pagination'=>['page'=>$page,'pageSize'=>$pageSize,'total'=>0,'totalPages'=>0]]);

        $existing=[];
        $res=$mysqli->query("SELECT child_id,vaccine_id,dose_number FROM child_immunizations");
        while($r=$res->fetch_assoc()){
            $existing[$r['child_id']][$r['vaccine_id']][$r['dose_number']]=true;
        }

        $notifications = [];
        $res = $mysqli->query("SELECT child_id, vaccine_id, dose_number, status, created_at, dismissed_at, expires_at FROM overdue_notifications");
        while($r = $res->fetch_assoc()) {
            $notifications[$r['child_id']][$r['vaccine_id']][$r['dose_number']] = $r;
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

        $allOverdue=[]; $dueSoon=[];
        $today = new DateTime('today');
        foreach($children as $c){
            foreach($sched as $sc){
                if(isset($existing[$c['child_id']][$sc['vaccine_id']][$sc['dose_number']])) continue;
                $age = (int)$c['age_months'];
                $target=(int)$sc['recommended_age_months'];
                $dueDate = null;
                if($c['birth_date']){
                    $dt = DateTime::createFromFormat('Y-m-d',$c['birth_date']);
                    if($dt){ $dt->modify('+'.$target.' months'); $dueDate = $dt->format('Y-m-d'); }
                }
                $isOver = ($age > $target + 1);
                $isSoon = (!$isOver && ($age >= ($target - 1) && $age <= $target));
                if(!$isOver && !$isSoon) continue;

                $base = [
                  'child_id'=>$c['child_id'],
                  'child_name'=>$c['full_name'],
                  'birth_date'=>$c['birth_date'],
                  'age_months'=>$age,
                  'vaccine_id'=>$sc['vaccine_id'],
                  'vaccine_code'=>$sc['vaccine_code'],
                  'vaccine_name'=>$sc['vaccine_name'],
                  'dose_number'=>$sc['dose_number'],
                  'target_age_months'=>$target,
                  'due_date'=>$dueDate,
                  'mother_name'=>$c['mother_name'],
                  'parent_contact'=>$c['mother_contact']
                ];

                $notif = $notifications[$c['child_id']][$sc['vaccine_id']][$sc['dose_number']] ?? null;
                $base['notification_status'] = $notif ? $notif['status'] : null;
                $base['notification_created'] = $notif ? $notif['created_at'] : null;
                $base['notification_dismissed'] = $notif ? $notif['dismissed_at'] : null;
                $base['notification_expires'] = $notif ? $notif['expires_at'] : null;

                if($isOver){
                    $daysOverdue = null;
                    if($dueDate){
                        $dd = DateTime::createFromFormat('Y-m-d',$dueDate);
                        if($dd){
                            $diff = $today->diff($dd)->days;
                            if($dd < $today) $daysOverdue = $diff;
                        }
                    }
                    $base['days_overdue']=$daysOverdue;

                    if (!$notif) {
                        $mysqli->query("
                            INSERT INTO overdue_notifications (child_id, vaccine_id, dose_number) 
                            VALUES ({$c['child_id']}, {$sc['vaccine_id']}, {$sc['dose_number']})
                        ");
                        $base['notification_status'] = 'active';
                        $base['notification_created'] = date('Y-m-d H:i:s');
                    }
                    $allOverdue[]=$base;
                } else {
                    $dueSoon[]=$base;
                }
            }
        }

        $filteredOverdue = array_filter($allOverdue, function($item) use ($showType) {
            if ($showType === 'active') {
                return !$item['notification_status'] || $item['notification_status'] === 'active';
            } elseif ($showType === 'recycle') {
                return $item['notification_status'] === 'dismissed' || $item['notification_status'] === 'expired';
            }
            return true;
        });

        $total = count($filteredOverdue);
        $totalPages = (int)ceil($total / $pageSize);
        $pagedOverdue = array_slice(array_values($filteredOverdue), $offset, $pageSize);

        ok([
            'overdue' => $pagedOverdue,
            'dueSoon' => $dueSoon,
            'pagination' => [
                'page' => $page,
                'pageSize' => $pageSize,
                'total' => $total,
                'totalPages' => $totalPages,
                'showType' => $showType
            ]
        ]);
    }

    if (isset($_GET['schedule'])) {
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

    if (isset($_GET['recent_vaccinations'])) {
        $limit = isset($_GET['limit']) ? max(1,min(100,(int)$_GET['limit'])) : 20;

        $cols = [
          "ci.immunization_id",
          "ci.vaccine_id",
          "ci.vaccination_date",
          "ci.dose_number",
          "ci.batch_lot_number",
          "ci.next_dose_due_date",
          "vt.vaccine_code",
          "vt.vaccine_name",
          "c.full_name AS child_name"
        ];
        if (has_column($mysqli,'child_immunizations','vaccine_expiry_date')) {
            $cols[] = "ci.vaccine_expiry_date";
        }

        $sql = "
          SELECT ".implode(',', $cols)."
          FROM child_immunizations ci
          JOIN vaccine_types vt ON vt.vaccine_id=ci.vaccine_id
          JOIN children c ON c.child_id=ci.child_id
          ORDER BY ci.vaccination_date DESC, ci.immunization_id DESC
          LIMIT ?
        ";
        $rows=[];
        $stmt=$mysqli->prepare($sql);
        if(!$stmt) fail('Prepare failed: '.$mysqli->error,500);
        $stmt->bind_param('i',$limit);
        $stmt->execute();
        $res=$stmt->get_result();
        while($r=$res->fetch_assoc()) $rows[]=$r;
        $stmt->close();
        ok(['recent_vaccinations'=>$rows]);
    }

    if (isset($_GET['cards_summary'])) {
        $page = max(1, (int)($_GET['page'] ?? 1));
        $pageSize = max(5, min(50, (int)($_GET['page_size'] ?? 10)));
        $search = trim($_GET['search'] ?? '');
        $offset = ($page - 1) * $pageSize;

        $vaccSql = "SELECT vaccine_id, doses_required FROM vaccine_types WHERE is_active=1";
        $vaccRes = $mysqli->query($vaccSql);
        $activeVaccines = [];
        while($vaccRes && $row=$vaccRes->fetch_assoc()){
            $activeVaccines[]=$row;
        }
        if(empty($activeVaccines)){
            ok(['cards'=>[],'total_vaccines'=>0,'total_count'=>0,'current_page'=>$page,'page_size'=>$pageSize,'total_pages'=>0]);
        }
        $totalVaccines = count($activeVaccines);

        $doseMap = [];
        $res = $mysqli->query("
          SELECT child_id, vaccine_id, COUNT(*) dose_count
          FROM child_immunizations
          GROUP BY child_id, vaccine_id
        ");
        while($res && $r=$res->fetch_assoc()){
            $doseMap[$r['child_id']][$r['vaccine_id']] = (int)$r['dose_count'];
        }

        $searchWhere = '';
        $searchParams = [];
        if (!empty($search)) {
            $searchWhere = "WHERE full_name LIKE ?";
            $searchParams[] = '%' . $search . '%';
        }

        $countSql = "SELECT COUNT(*) as total FROM children $searchWhere";
        $countStmt = $mysqli->prepare($countSql);
        if (!empty($searchParams)) {
            $countStmt->bind_param('s', $searchParams[0]);
        }
        $countStmt->execute();
        $totalCount = $countStmt->get_result()->fetch_assoc()['total'];
        $totalPages = ceil($totalCount / $pageSize);
        $countStmt->close();

        $cards=[];
        $childSql = "
          SELECT child_id, full_name, birth_date,
            TIMESTAMPDIFF(MONTH,birth_date,CURDATE()) AS age_months
          FROM children
          $searchWhere
          ORDER BY full_name ASC
          LIMIT ? OFFSET ?
        ";
        
        $stmt = $mysqli->prepare($childSql);
        if (!empty($searchParams)) {
            $stmt->bind_param('sii', $searchParams[0], $pageSize, $offset);
        } else {
            $stmt->bind_param('ii', $pageSize, $offset);
        }
        $stmt->execute();
        $cres = $stmt->get_result();
        
        while($c = $cres->fetch_assoc()){
            $completed=0;
            foreach($activeVaccines as $v){
                $vid=(int)$v['vaccine_id'];
                $need=(int)$v['doses_required'];
                $have = $doseMap[$c['child_id']][$vid] ?? 0;
                if($have >= $need) $completed++;
            }
            $percent = $totalVaccines>0 ? round(($completed/$totalVaccines)*100,0) : 0;
            $cards[] = [
              'child_id'=>(int)$c['child_id'],
              'full_name'=>$c['full_name'],
              'birth_date'=>$c['birth_date'],
              'age_months'=>(int)$c['age_months'],
              'vaccines_completed'=>$completed,
              'total_vaccines'=>$totalVaccines,
              'percent_complete'=>$percent
            ];
        }
        $stmt->close();
        
        ok([
            'cards'=>$cards,
            'total_vaccines'=>$totalVaccines,
            'total_count'=>(int)$totalCount,
            'current_page'=>$page,
            'page_size'=>$pageSize,
            'total_pages'=>$totalPages,
            'search'=>$search
        ]);
    }

    // Get individual child immunization card data for PDF export
    if (isset($_GET['card'])) {
        $childId = (int)($_GET['child_id'] ?? 0);
        if ($childId <= 0) fail('child_id required');

        // Get child info
        $stmt = $mysqli->prepare("
            SELECT child_id, full_name, birth_date, sex, 
                   TIMESTAMPDIFF(MONTH,birth_date,CURDATE()) AS age_months,
                   parent_id
            FROM children 
            WHERE child_id=?
        ");
        $stmt->bind_param('i', $childId);
        $stmt->execute();
        $result = $stmt->get_result();
        if (!$result->num_rows) fail('Child not found', 404);
        $child = $result->fetch_assoc();
        $stmt->close();

        // Get parent info if available
        if ($child['parent_id']) {
            $pStmt = $mysqli->prepare("
                SELECT full_name, email, contact_number, address
                FROM parent_accounts 
                WHERE parent_id=?
            ");
            $pStmt->bind_param('i', $child['parent_id']);
            $pStmt->execute();
            $pRes = $pStmt->get_result();
            if ($pRes->num_rows) {
                $child['parent_info'] = $pRes->fetch_assoc();
            }
            $pStmt->close();
        }

        // Get all active vaccines with their requirements
        $vaccineTypes = [];
        $res = $mysqli->query("
            SELECT vaccine_id, vaccine_code, vaccine_name, doses_required
            FROM vaccine_types 
            WHERE is_active=1
            ORDER BY vaccine_name
        ");
        while ($res && $r = $res->fetch_assoc()) {
            $vaccineTypes[] = $r;
        }

        // Get all immunization records for this child
        $stmt = $mysqli->prepare("
            SELECT ci.immunization_id, ci.vaccine_id, ci.dose_number,
                   ci.date_given, ci.batch_number, ci.expiry_date,
                   ci.next_dose_date, ci.administered_by, ci.remarks,
                   vt.vaccine_code, vt.vaccine_name, vt.doses_required,
                   u.username as administered_by_name
            FROM child_immunizations ci
            JOIN vaccine_types vt ON vt.vaccine_id = ci.vaccine_id
            LEFT JOIN users u ON u.user_id = ci.administered_by
            WHERE ci.child_id = ?
            ORDER BY ci.date_given ASC, ci.vaccine_id ASC
        ");
        $stmt->bind_param('i', $childId);
        $stmt->execute();
        $res = $stmt->get_result();
        
        $doses = [];
        while ($r = $res->fetch_assoc()) {
            $doses[] = $r;
        }
        $stmt->close();

        // Organize doses by vaccine
        $vaccines = [];
        foreach ($vaccineTypes as $vt) {
            $vid = (int)$vt['vaccine_id'];
            $childDoses = array_filter($doses, function($d) use ($vid) {
                return (int)$d['vaccine_id'] === $vid;
            });
            
            $vaccines[] = [
                'vaccine_id' => $vid,
                'vaccine_code' => $vt['vaccine_code'],
                'vaccine_name' => $vt['vaccine_name'],
                'doses_required' => (int)$vt['doses_required'],
                'doses_given' => count($childDoses),
                'doses' => array_values($childDoses)
            ];
        }

        ok([
            'child' => $child,
            'vaccines' => $vaccines,
            'generated_date' => date('Y-m-d H:i:s')
        ]);
    }

    fail('Unknown GET action',404);
}

/* ===================== POST ===================== */
if ($method === 'POST') {
    require_csrf();

    if (isset($_POST['dismiss_notification'])) {
        $childId = (int)($_POST['child_id'] ?? 0);
        $vaccineId = (int)($_POST['vaccine_id'] ?? 0);
        $doseNumber = (int)($_POST['dose_number'] ?? 0);
        if (!$childId || !$vaccineId || !$doseNumber) fail('Missing required parameters');

        $mysqli->query("
            CREATE TABLE IF NOT EXISTS overdue_notifications (
                id INT AUTO_INCREMENT PRIMARY KEY,
                child_id INT NOT NULL,
                vaccine_id INT NOT NULL,
                dose_number INT NOT NULL,
                status ENUM('active','dismissed','expired') DEFAULT 'active',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                dismissed_at TIMESTAMP NULL,
                expires_at TIMESTAMP NULL,
                INDEX idx_child_vaccine_dose (child_id, vaccine_id, dose_number),
                INDEX idx_status (status),
                INDEX idx_expires (expires_at)
            )
        ");

        $stmt = $mysqli->prepare("
            UPDATE overdue_notifications
            SET status='dismissed', dismissed_at=NOW()
            WHERE child_id=? AND vaccine_id=? AND dose_number=?
        ");
        $stmt->bind_param('iii',$childId,$vaccineId,$doseNumber);
        $stmt->execute();
        $aff = $stmt->affected_rows;
        $stmt->close();

        if ($aff === 0) {
            $ins = $mysqli->prepare("
                INSERT INTO overdue_notifications (child_id, vaccine_id, dose_number, status, dismissed_at)
                VALUES (?,?,?,?, NOW())
            ");
            $status='dismissed';
            $ins->bind_param('iiis',$childId,$vaccineId,$doseNumber,$status);
            $ins->execute();
            $ins->close();
        }
        ok(['dismissed'=>true]);
    }

    if (isset($_POST['restore_notification'])) {
        $childId = (int)($_POST['child_id'] ?? 0);
        $vaccineId = (int)($_POST['vaccine_id'] ?? 0);
        $doseNumber = (int)($_POST['dose_number'] ?? 0);
        if (!$childId || !$vaccineId || !$doseNumber) fail('Missing required parameters');

        $stmt = $mysqli->prepare("
            UPDATE overdue_notifications
            SET status='active', dismissed_at=NULL, expires_at=NULL
            WHERE child_id=? AND vaccine_id=? AND dose_number=?
        ");
        $stmt->bind_param('iii',$childId,$vaccineId,$doseNumber);
        $stmt->execute();
        $stmt->close();

        ok(['restored'=>true]);
    }

    /* Add child (Enhanced: now auto-imports maternal_patients row into mothers_caregivers if needed) */
    if (isset($_POST['add_child'])) {
        $first_name  = trim($_POST['first_name'] ?? '');
        $middle_name = trim($_POST['middle_name'] ?? '');
        $last_name   = trim($_POST['last_name'] ?? '');
        $sex         = $_POST['sex'] ?? '';
        $birth_date  = $_POST['birth_date'] ?? '';
        $mother_id   = (int)($_POST['mother_id'] ?? 0);
        $weight_kg   = ($_POST['weight_kg'] !== '' ? (float)$_POST['weight_kg'] : null);
        $height_cm   = ($_POST['height_cm'] !== '' ? (float)$_POST['height_cm'] : null);
        $rec_by      = (int)($_SESSION['user_id'] ?? 0);
        $parent_user_id = (int)($_POST['parent_user_id'] ?? $_POST['parent_account_id'] ?? 0); // optional future use

        if ($first_name==='' || $last_name==='' || ($sex!=='male' && $sex!=='female') || $birth_date==='') {
            fail('Required child fields missing.');
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/',$birth_date)) {
            fail('Invalid birth_date format (YYYY-MM-DD expected).');
        }

        if ($mother_id <= 0) {
            fail('mother_id is required (cannot resolve parent).');
        }

        // Make sure mother exists or import from maternal_patients
        ensure_mother_in_caregivers($mysqli, $mother_id, $rec_by);

        // Compose full name; include middle name only if present
        $full_name = trim($first_name.' '.($middle_name? $middle_name.' ':'').$last_name);

        // Avoid duplicates (same mother, same full name, same birth date)
        $hasFullNameCol = has_column($mysqli,'children','full_name');
        if ($hasFullNameCol) {
            $dupSql = "SELECT child_id FROM children WHERE mother_id=? AND full_name=? AND birth_date=? LIMIT 1";
            $dup = $mysqli->prepare($dupSql);
            $dup->bind_param('iss',$mother_id,$full_name,$birth_date);
        } else {
            $dupSql = "SELECT child_id FROM children
                       WHERE mother_id=? AND first_name=? AND middle_name=? AND last_name=? AND birth_date=? LIMIT 1";
            $dup = $mysqli->prepare($dupSql);
            $dup->bind_param('issss',$mother_id,$first_name,$middle_name,$last_name,$birth_date);
        }
        $dup->execute();
        $dup->bind_result($cidDup);
        if($dup->fetch()){
            $dup->close();
            ok([
                'duplicate'=>true,
                'success'=>true,
                'child_id'=>$cidDup,
                'message'=>'Child already exists for this mother.'
            ]);
        }
        $dup->close();

        // Build dynamic insert
        $cols = ['first_name','middle_name','last_name','sex','birth_date','mother_id','weight_kg','height_cm','created_by'];
        $ph   = ['?','?','?','?','?','?','?','?','?'];
        $types='sssssiddd';
        $vals = [$first_name,$middle_name,$last_name,$sex,$birth_date,$mother_id,$weight_kg,$height_cm,$rec_by];

        if ($hasFullNameCol) {
            array_splice($cols,3,0,'full_name'); // insert after last_name
            array_splice($ph,3,0,'?');
            $types = 'sss'.'s'.substr($types,3); // add one more 's'
            array_splice($vals,3,0,$full_name);
        }

        $sql="INSERT INTO children (".implode(',',$cols).") VALUES (".implode(',',$ph).")";
        $stmt=$mysqli->prepare($sql);
        if(!$stmt) fail('Child prepare failed: '.$mysqli->error,500);
        $stmt->bind_param($types, ...$vals);
        if(!$stmt->execute()) fail('Child insert failed: '.$stmt->error,500);
        $child_id=$stmt->insert_id;
        $stmt->close();

        ok([
            'child_id'=>$child_id,
            'mother_id'=>$mother_id,
            'full_name'=>$full_name,
            'age_months'=>age_months($birth_date),
            'has_full_name_column'=>$hasFullNameCol
        ]);
    }

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
        if ($vaccine_code==='' || $vaccine_name==='' || $doses_required<=0) fail('Required vaccine fields missing.');
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

    if (
        isset($_POST['child_id']) &&
        isset($_POST['dose_number']) &&
        isset($_POST['vaccination_date']) &&
        (isset($_POST['vaccine_id']) || isset($_POST['vaccine_code'])) &&
        !isset($_POST['add_schedule']) &&
        !isset($_POST['add_update_vaccine']) &&
        !isset($_POST['add_child'])
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

        $expiry_raw = trim($_POST['vaccine_expiry_date'] ?? '');
        $vaccine_expiry_date = ($expiry_raw !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/',$expiry_raw)) ? $expiry_raw : null;

        $next_due_override_raw = trim($_POST['next_dose_due_date'] ?? '');
        $next_due_override = ($next_due_override_raw !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/',$next_due_override_raw)) ? $next_due_override_raw : null;

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
                    $dreq=(int)$_POST['doses_required'] ?? 1;
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

        $next_due = $next_due_override;
        if($next_due === null && $dose_number < (int)$dreq && $interval_days !== null){
            $ts=strtotime($vaccination_date.' +'.(int)$interval_days.' days');
            if($ts) $next_due=date('Y-m-d',$ts);
        }

        $fields = [
          'child_id','vaccine_id','dose_number','vaccination_date',
          'vaccination_site','batch_lot_number',
        ];
        $place  = array_fill(0, count($fields), '?');
        $types  = 'iiisss';
        $values = [$child_id,$vaccine_id,$dose_number,$vaccination_date,$vaccination_site,$batch_lot_number];

        if (has_column($mysqli,'child_immunizations','vaccine_expiry_date')) {
            $fields[] = 'vaccine_expiry_date';
            $place[]  = '?';
            $types   .= 's';
            $values[] = $vaccine_expiry_date;
        }

        $fields = array_merge($fields, ['administered_by','next_dose_due_date','adverse_reactions','notes']);
        $place  = array_merge($place,  ['?','?','?','?']);
        $types .= 'isss';
        $values = array_merge($values, [$recorded_by,$next_due,$adverse,$notes]);

        $sql = "INSERT INTO child_immunizations (".implode(',',$fields).") VALUES (".implode(',',$place).")";
        $ins=$mysqli->prepare($sql);
        if(!$ins) fail('Prepare failed: '.$mysqli->error,500);

        $ins->bind_param($types, ...$values);
        if(!$ins->execute()){
            if(stripos($ins->error,'duplicate')!==false) fail('Duplicate entry (already recorded dose).',409);
            fail('Insert failed: '.$ins->error,500);
        }
        $iid=$ins->insert_id;
        $ins->close();
        ok(['immunization_id'=>$iid,'next_dose_due_date'=>$next_due,'vaccine_expiry_date'=>$vaccine_expiry_date]);
    }

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