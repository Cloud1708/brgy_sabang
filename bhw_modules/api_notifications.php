<?php
require_once __DIR__.'/../inc/db.php';
require_once __DIR__.'/../inc/auth.php';
require_role(['BHW']);
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json; charset=utf-8');

// Ensure errors are JSON, not HTML
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

// Add mail include so we can actually send emails
require_once __DIR__.'/../inc/mail.php';

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

/* Small mail delivery helper: tries project mailer then falls back to PHP mail() */
function deliver_parent_email(string $to, string $subject, string $message): array {
    $ok = false; $err = null;
    try {
        if (function_exists('bhw_mail_send')) {
            $html = nl2br(htmlentities($message, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
            $ok = bhw_mail_send($to, $subject, $html);
            if (!$ok && function_exists('bhw_mail_last_error')) {
                $err = bhw_mail_last_error();
            }
        } else {
            $headers = "MIME-Version: 1.0\r\nContent-Type: text/plain; charset=UTF-8\r\n";
            $ok = @mail($to, $subject, $message, $headers);
        }
    } catch (Throwable $e) {
        $ok = false; $err = $e->getMessage();
    }
    return [$ok, $err];
}

/* Render combined items list for grouped notifications */
function format_items_list(array $items): string {
    // each $item: child_name, vaccine_code, vaccine_name, dose_number, due_date
    $lines = [];
    foreach ($items as $it) {
        $doseTxt = isset($it['dose_number']) && $it['dose_number'] ? ('Dose '.$it['dose_number']) : '';
        $dueTxt  = !empty($it['due_date']) ? (' (due '.$it['due_date'].')') : '';
        $labelV  = $it['vaccine_code'] ?? '';
        if (!empty($it['vaccine_name'])) {
            $labelV = $it['vaccine_name'].' ('.$labelV.')';
        }
        $lines[] = '- '.$labelV.($doseTxt?(' '.$doseTxt):'').$dueTxt;
    }
    return implode("\n", $lines);
}

/* Fill template for grouped notifications */
function build_group_message(string $template, string $childName, array $items, string $type): string {
    $single = count($items) === 1;
    $first  = $items[0];
    $vaccineToken = $single ? ($first['vaccine_code'] ?? '') : 'multiple vaccines';
    $doseToken    = $single ? ('Dose '.($first['dose_number'] ?? '')) : (count($items).' item(s)');
    $dueToken     = $single ? ($first['due_date'] ?? '') : '';

    $msg = str_replace(
        ['[[CHILD]]','[[VACCINE]]','[[DOSE]]','[[DUE_DATE]]'],
        [$childName, $vaccineToken, $doseToken, $dueToken],
        $template
    );

    $list = format_items_list($items);
    if (strpos($msg,'[[ITEMS]]') !== false) {
        $msg = str_replace('[[ITEMS]]', $list, $msg);
    } else {
        // auto-append if token not present
        $header = $type==='vaccine_due' ? "Due items:\n" : "Overdue items:\n";
        $msg .= "\n\n".$header.$list;
    }
    return $msg;
}

/* Notification insert (unchanged) */
function ensure_notification($mysqli,$child_id,$parent_user_id,$type,$title,$message,$due_date,$related_vaccine_id,$fingerprint,$methods=[],$batchKey=null,$template=null){
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
    if($chk->num_rows>0){ $chk->close(); return [false, null]; }
    $chk->close();

    $cols = "parent_user_id,child_id,notification_type,title,message,related_vaccine_id,due_date,is_read,is_sent,created_by,method_sms,method_email,batch_key,original_template";
    $ph   = "?,?,?,?,?,?,?,0,0,?,?,?,?,?";
    if($useFingerprint){ $cols.=",fingerprint"; $ph.=",?"; }

    $sql = "INSERT INTO parent_notifications ($cols) VALUES ($ph)";
    $stmt = $mysqli->prepare($sql);
    $creator = $_SESSION['user_id'] ?? null;
    $sms = 0; $email = 1;

    if($useFingerprint){
        $stmt->bind_param(
            'iisssisiiisss',
            $parent_user_id,$child_id,$type,$title,$message,$related_vaccine_id,$due_date,
            $creator,$sms,$email,$batchKey,$template,$fingerprint
        );
    } else {
        $stmt->bind_param(
            'iisssisiiiss',
            $parent_user_id,$child_id,$type,$title,$message,$related_vaccine_id,$due_date,
            $creator,$sms,$email,$batchKey,$template
        );
    }

    if(!$stmt->execute()){
        $err=$stmt->error; $stmt->close();
        fail('Insert notification failed: '.$err,500);
    }
    $nid = $stmt->insert_id;
    $stmt->close();
    return [true, $nid];
}

/* Core vacc schedule logic reused */
function fetch_schedule_candidates($mysqli){
    $children=[];
    $res=$mysqli->query("SELECT child_id, full_name, birth_date, TIMESTAMPDIFF(MONTH,birth_date,CURDATE()) AS age_months FROM children");
    while($r=$res->fetch_assoc()) $children[$r['child_id']]=$r;

    if(empty($children)) return ['overdue'=>[], 'dueSoon'=>[]];

    $existing=[];
    $res=$mysqli->query("SELECT child_id,vaccine_id,dose_number FROM child_immunizations");
    while($r=$res->fetch_assoc()){
        $existing[$r['child_id']][$r['vaccine_id']][$r['dose_number']] = true;
    }

    $schedule=[];
    $res=$mysqli->query("SELECT s.vaccine_id,s.dose_number,s.recommended_age_months, vt.vaccine_code,vt.vaccine_name
                         FROM immunization_schedule s
                         JOIN vaccine_types vt ON vt.vaccine_id=s.vaccine_id
                         WHERE vt.is_active=1
                         ORDER BY vt.vaccine_name,s.dose_number");
    while($r=$res->fetch_assoc()) $schedule[]=$r;

    $overdue=[]; $dueSoon=[];
    foreach($children as $cid=>$c){
        $age=(int)$c['age_months'];
        foreach($schedule as $sc){
            if(isset($existing[$cid][$sc['vaccine_id']][$sc['dose_number']])) continue;
            $target=(int)$sc['recommended_age_months'];
            $isOver = ($age > $target + 1);
            $isSoon = (!$isOver && ($age >= ($target - 1) && $age <= $target));
            if(!$isOver && !$isSoon) continue;
            $due_date = build_due_date($c['birth_date'],$target);
            $row = [
              'child_id'=>$cid,
              'child_name'=>$c['full_name'],
              'birth_date'=>$c['birth_date'],
              'age_months'=>$age,
              'vaccine_id'=>$sc['vaccine_id'],
              'vaccine_code'=>$sc['vaccine_code'],
              'vaccine_name'=>$sc['vaccine_name'],
              'dose_number'=>$sc['dose_number'],
              'target_age_months'=>$target,
              'due_date'=>$due_date
            ];
            if($isOver) $overdue[]=$row; else $dueSoon[]=$row;
        }
    }
    return ['overdue'=>$overdue,'dueSoon'=>$dueSoon];
}

/* Only include linked parents who have an active parent account AND a non-empty email */
function parent_links_map($mysqli){
    $map=[];
    $res=$mysqli->query("SELECT pca.parent_user_id,pca.child_id,u.username,u.first_name,u.last_name,u.email
                         FROM parent_child_access pca
                         JOIN users u ON u.user_id=pca.parent_user_id AND u.is_active=1
                         JOIN roles r ON r.role_id=u.role_id AND r.role_name='Parent'
                         WHERE pca.is_active=1 AND u.email IS NOT NULL AND u.email<>''");
    while($r=$res->fetch_assoc()){
        $map[$r['child_id']][] = [
          'parent_user_id'=>(int)$r['parent_user_id'],
          'username'=>$r['username'],
          'full_name'=>trim($r['first_name'].' '.$r['last_name']),
          'email'=>$r['email']
        ];
    }
    return $map;
}

/* NEW: grouped send (one email per child-parent) for auto-generation */
function generate_notifications($mysqli){
    $cands = fetch_schedule_candidates($mysqli);
    $links = parent_links_map($mysqli);
    $added=0; $skipped=0; $emails_sent=0;

    // Group overdue per child
    $overByChild = [];
    foreach (($cands['overdue'] ?? []) as $row) {
        $overByChild[$row['child_id']][] = $row;
    }

    // Send one per parent per child for overdue
    foreach ($overByChild as $childId => $items) {
        if (empty($links[$childId])) continue;
        $childName = $items[0]['child_name'] ?? 'Child';
        $title = 'Overdue Vaccination Alert';
        $message = build_group_message(
            // Default fallback template
            "Reminder: [[CHILD]] has overdue vaccinations.\n\n[[ITEMS]]\n\nPlease visit the barangay health center. - BHW",
            $childName,
            $items,
            'vaccine_overdue'
        );
        // No single due_date or vaccine_id in grouped mode
        foreach ($links[$childId] as $p) {
            $fp='agg|overdue|'.$childId.'|'.$p['parent_user_id'].'|'.md5($message);
            [$created, $nid] = ensure_notification(
                $mysqli,$childId,$p['parent_user_id'],'vaccine_overdue',$title,$message,null,null,$fp,[],null,null
            );
            if($created) $added++; else $skipped++;
            if (!empty($p['email'])) {
                [$ok, $err] = deliver_parent_email($p['email'], $title, $message);
                if ($ok) $emails_sent++;
            }
        }
    }

    // Optional: keep dueSoon as-is (one per item). If gusto mo i-group din, puwede mong kopyahin ang logic sa itaas.
    return ['added'=>$added,'skipped'=>$skipped,'emails_sent'=>$emails_sent];
}

/* ====================== GET ====================== */
if ($method === 'GET') {

    if (isset($_GET['list'])) {
        $rows=[];
        $res = $mysqli->query("
          SELECT pn.notification_id, pn.parent_user_id, pn.child_id, pn.notification_type,
                 pn.title, pn.message, pn.due_date, pn.is_read, pn.is_sent,
                 pn.method_sms, pn.method_email, pn.batch_key,
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

    if (isset($_GET['candidates'])) {
        $cand = fetch_schedule_candidates($mysqli);
        $links = parent_links_map($mysqli);
        foreach(['overdue','dueSoon'] as $g){
            foreach($cand[$g] as &$row){
                $row['parents'] = $links[$row['child_id']] ?? [];
            }
        }
        ok(['candidates'=>$cand]);
    }

    if (isset($_GET['recent_summary'])) {
        $rows=[];
        $res=$mysqli->query("
            SELECT batch_key,
                   COUNT(*) AS total_notifs,
                   MIN(created_at) AS started_at,
                   MAX(created_at) AS ended_at,
                   SUM(method_sms) AS sms_count,
                   SUM(method_email) AS email_count,
                   SUM(CASE WHEN notification_type='vaccine_overdue' THEN 1 ELSE 0 END) AS overdue_count,
                   SUM(CASE WHEN notification_type='vaccine_due' THEN 1 ELSE 0 END) AS due_count
            FROM parent_notifications
            WHERE batch_key IS NOT NULL
            GROUP BY batch_key
            ORDER BY MAX(created_at) DESC
            LIMIT 50
        ");
        while($res && $r=$res->fetch_assoc()) $rows[]=$r;
        ok(['batches'=>$rows]);
    }

    if (isset($_GET['parents'])) {
        $rows=[];
        $res=$mysqli->query("
          SELECT u.user_id,u.username,u.first_name,u.last_name,
                 COUNT(DISTINCT pca.child_id) AS children_count
          FROM users u
          JOIN roles r ON r.role_id=u.role_id AND r.role_name='Parent'
          LEFT JOIN parent_child_access pca ON pca.parent_user_id=u.user_id AND pca.is_active=1
          GROUP BY u.user_id
          ORDER BY u.username ASC
          LIMIT 800
        ");
        while($res && $r=$res->fetch_assoc()) $rows[]=$r;
        ok(['parents'=>$rows]);
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

    // Email-only bulk send / preview (grouped per child)
    if (($_POST['action'] ?? '') === 'bulk_send') {
        $mode = $_POST['notification_mode'] ?? 'overdue'; // overdue|due_soon|selected|custom
        $template = trim($_POST['message_template'] ?? '');
        $notifType = $_POST['notification_type'] ?? 'vaccine_overdue';
        $preview = !empty($_POST['preview']);
        if ($template==='') fail('Message template required.');

        $cands = fetch_schedule_candidates($mysqli);
        $links = parent_links_map($mysqli); // only parents with emails

        // Build ungrouped targets first
        $targets = [];
        if ($mode==='overdue') $targets = $cands['overdue'];
        elseif ($mode==='due_soon') $targets = $cands['dueSoon'];
        elseif ($mode==='selected') {
            $ids = isset($_POST['child_ids']) ? (array)$_POST['child_ids'] : [];
            $idMap = [];
            foreach($cands['overdue'] as $r) $idMap[$r['child_id']][] = $r;
            foreach($cands['dueSoon'] as $r) $idMap[$r['child_id']][] = $r;
            foreach($ids as $cid){
                $cid=(int)$cid;
                if(isset($idMap[$cid])){
                    foreach($idMap[$cid] as $row) $targets[]=$row;
                }
            }
        } else { // custom: manual children without vaccine context
            $childIds = isset($_POST['child_ids'])?(array)$_POST['child_ids']:[];
            if(!$childIds) fail('child_ids required for custom mode.');
            foreach($childIds as $cid){
                $cid=(int)$cid;
                $row = $mysqli->query("SELECT child_id,full_name AS child_name,birth_date,TIMESTAMPDIFF(MONTH,birth_date,CURDATE()) age_months FROM children WHERE child_id=$cid LIMIT 1")->fetch_assoc();
                if($row){
                    $row['vaccine_code']=null;
                    $row['vaccine_name']=null;
                    $row['dose_number']=null;
                    $row['due_date']=null;
                    $targets[]=$row;
                }
            }
        }
        if(empty($targets)) fail('No targets found.');

        // Group per child (one email per child-parent)
        $byChild = [];
        foreach ($targets as $t) {
            $byChild[$t['child_id']][] = $t;
        }

        $batchKey = 'B'.date('YmdHis').bin2hex(random_bytes(3));
        $created=0; $skipped=0; $emailSent=0; $sampleMessages=[];

        foreach($byChild as $childId => $items){
            $parents = $links[$childId] ?? [];
            if(empty($parents)) continue;

            $childName = $items[0]['child_name'] ?? 'Child';
            $title = ($notifType==='vaccine_overdue' ? 'Overdue Vaccination Alert'
                    : ($notifType==='vaccine_due' ? 'Upcoming Vaccination Reminder'
                    : 'Health Center Notice'));

            $message = build_group_message($template, $childName, $items, $notifType);

            foreach($parents as $p){
                if($preview){
                    if(count($sampleMessages)<5){
                        $sampleMessages[]=[
                          'parent_user_id'=>$p['parent_user_id'],
                          'child_id'=>$childId,
                          'message'=>$message
                        ];
                    }
                    continue;
                }

                // fingerprint includes all items for dedupe
                $fp = 'bulk_agg|'.$childId.'|'.$p['parent_user_id'].'|'.md5($title.$message);

                [$okCreate, $nid] = ensure_notification(
                    $mysqli,
                    $childId,
                    $p['parent_user_id'],
                    $notifType,
                    $title,
                    $message,
                    null,            // combined -> no single due_date
                    null,            // combined -> no single vaccine_id
                    $fp,
                    ['email'=>true],
                    $batchKey,
                    $template
                );
                $okCreate ? $created++ : $skipped++;

                if (!empty($p['email'])) {
                    [$sentOk, $err] = deliver_parent_email($p['email'], $title, $message);
                    if ($sentOk) $emailSent++;
                }
            }
        }

        if($preview){
            ok([
                'preview'=>true,
                'sample'=>$sampleMessages,
                'targets_count'=>count($byChild)
            ]);
        }

        ok([
          'action'=>'bulk_send',
          'created'=>$created,
          'skipped'=>$skipped,
          'batch_key'=>$batchKey,
          'emails_sent'=>$emailSent
        ]);
    }

    fail('Unknown POST action',400);
}

fail('Invalid method',405);