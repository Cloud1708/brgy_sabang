<?php
require_once __DIR__.'/../inc/db.php';
require_once __DIR__.'/../inc/auth.php';
require_once __DIR__.'/../inc/mail.php';
require_role(['Admin','BHW']);

if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json; charset=utf-8');

function fail($m,$c=400){ http_response_code($c); echo json_encode(['success'=>false,'error'=>$m]); exit; }
function ok($d=[]){ echo json_encode(array_merge(['success'=>true],$d)); exit; }

$method = $_SERVER['REQUEST_METHOD'];

/**
 * Unified CSRF checker:
 *  - Accepts token from POST field csrf_token OR header X-CSRF-Token
 *  - Returns HTTP 419 on failure.
 */
function require_csrf(){
    if (session_status() === PHP_SESSION_NONE) session_start();
    $postToken = $_POST['csrf_token'] ?? '';
    $hdrToken = '';
    if (function_exists('getallheaders')) {
        foreach (getallheaders() as $k=>$v){
            if (strcasecmp($k,'X-CSRF-Token')===0){
                $hdrToken = $v;
                break;
            }
        }
    }
    $token = $postToken ?: $hdrToken;
    if (empty($_SESSION['csrf_token']) || $token==='' || !hash_equals($_SESSION['csrf_token'],$token)) {
        fail('CSRF failed',419);
    }
}

function rand_password($len=10){
    $chars='ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!@#$%';
    $out='';
    for($i=0;$i<$len;$i++){
        $out.=$chars[random_int(0,strlen($chars)-1)];
    }
    return $out;
}

/* ===== Audit Log Helper ===== */
function log_parent_activity(mysqli $mysqli, int $parent_user_id, string $action_code, ?int $child_id=null, array $meta=[]){
    if($parent_user_id<=0 || $action_code==='') return;
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $ua = substr($_SERVER['HTTP_USER_AGENT'] ?? '',0,250);
    $j  = $meta ? json_encode($meta, JSON_UNESCAPED_UNICODE) : null;
    $stmt = $mysqli->prepare("INSERT INTO parent_audit_log
        (parent_user_id,action_code,child_id,meta_json,ip_address,user_agent)
        VALUES (?,?,?,?,?,?)");
    $stmt->bind_param('isisss',$parent_user_id,$action_code,$child_id,$j,$ip,$ua);
    $stmt->execute();
    $stmt->close();
}

function ensure_mother_record(mysqli $mysqli, string $fullName, ?string $dob, int $created_by): int {
    $q = $mysqli->prepare("SELECT mother_id FROM mothers_caregivers WHERE LOWER(full_name)=LOWER(?) LIMIT 1");
    $q->bind_param('s',$fullName);
    $q->execute(); $q->bind_result($mid);
    if($q->fetch()){ $q->close(); return (int)$mid; }
    $q->close();

    $purok_name = 'Unassigned';
    $purok_id = null;
    $p = $mysqli->prepare("SELECT purok_id FROM puroks WHERE purok_name=? LIMIT 1");
    $p->bind_param('s',$purok_name);
    $p->execute(); $p->bind_result($pid);
    if($p->fetch()) $purok_id = $pid;
    $p->close();
    if(!$purok_id){
        $barangay='Sabang';
        $ins=$mysqli->prepare("INSERT INTO puroks (purok_name,barangay) VALUES (?,?)");
        $ins->bind_param('ss',$purok_name,$barangay);
        if(!$ins->execute()) fail('Failed to create default Purok: '.$ins->error,500);
        $purok_id = $ins->insert_id;
        $ins->close();
    }

    $hasDob = false;
    if($res = $mysqli->query("SHOW COLUMNS FROM mothers_caregivers LIKE 'date_of_birth'")){
        $hasDob = $res->num_rows>0;
        $res->close();
    }
    if($hasDob && $dob && !preg_match('/^\d{4}-\d{2}-\d{2}$/',$dob)) $dob = null;

    if($hasDob){
        $ins=$mysqli->prepare("INSERT INTO mothers_caregivers (full_name,purok_id,date_of_birth,created_by) VALUES (?,?,?,?)");
        $ins->bind_param('sisi',$fullName,$purok_id,$dob,$created_by);
    }else{
        $ins=$mysqli->prepare("INSERT INTO mothers_caregivers (full_name,purok_id,created_by) VALUES (?,?,?)");
        $ins->bind_param('sii',$fullName,$purok_id,$created_by);
    }
    if(!$ins->execute()) fail('Failed to create mother profile: '.$ins->error,500);
    $mid=$ins->insert_id;
    $ins->close();
    return $mid;
}

function create_child(mysqli $mysqli, int $mother_id, string $full_name, string $birth_date, string $sex, int $created_by): int {
    if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$birth_date)) fail('Invalid child birth_date format.');
    if(!in_array($sex,['male','female'],true)) fail('Invalid child sex.');
    $ins=$mysqli->prepare("INSERT INTO children (full_name,sex,birth_date,mother_id,created_by) VALUES (?,?,?,?,?)");
    $ins->bind_param('sssii',$full_name,$sex,$birth_date,$mother_id,$created_by);
    if(!$ins->execute()) fail('Child insert failed: '.$ins->error,500);
    $cid=$ins->insert_id;
    $ins->close();
    return $cid;
}

function link_parent_child(mysqli $mysqli, int $parent_user_id,int $child_id,string $relationship_type,int $creator){
    $valid=['mother','father','guardian','caregiver'];
    if(!in_array($relationship_type,$valid,true)) fail('Invalid relationship type for link.');
    $chk=$mysqli->prepare("SELECT access_id,is_active FROM parent_child_access WHERE parent_user_id=? AND child_id=? LIMIT 1");
    $chk->bind_param('ii',$parent_user_id,$child_id);
    $chk->execute(); $chk->bind_result($aid,$is_active);
    if($chk->fetch()){
        $chk->close();
        if(!$is_active){
            $up=$mysqli->prepare("UPDATE parent_child_access SET is_active=1, relationship_type=?, updated_at=NOW() WHERE access_id=? LIMIT 1");
            $up->bind_param('si',$relationship_type,$aid);
            if(!$up->execute()) fail('Failed to re-activate child link: '.$up->error,500);
            $up->close();
        }
        return;
    }
    $chk->close();
    $ins=$mysqli->prepare("INSERT INTO parent_child_access (parent_user_id,child_id,relationship_type,access_granted_by,is_active) VALUES (?,?,?,?,1)");
    $ins->bind_param('iisi',$parent_user_id,$child_id,$relationship_type,$creator);
    if(!$ins->execute()) fail('Link child failed: '.$ins->error,500);
    $ins->close();
}

/* ---------------- GET (Read-Only) ---------------- */
if ($method === 'GET') {

    // Health ping (safe)
    if (isset($_GET['ping'])) {
        ok(['ping'=>'pong','has_csrf'=>!empty($_SESSION['csrf_token'])]);
    }

    if (isset($_GET['list_parents'])) {
        $rows=[];
        $sql="
          SELECT u.user_id,u.username,u.email,u.first_name,u.last_name,
                 u.is_active,u.created_at,u.updated_at,
                 GROUP_CONCAT(DISTINCT CONCAT(c.full_name,' (',pca.relationship_type,')') ORDER BY c.full_name SEPARATOR '; ') AS children_list,
                 COUNT(DISTINCT c.child_id) AS children_count,
                 MAX(pn.created_at) AS last_login_at
          FROM users u
          JOIN roles r ON r.role_id=u.role_id
          LEFT JOIN parent_child_access pca ON pca.parent_user_id=u.user_id AND pca.is_active=1
          LEFT JOIN children c ON c.child_id=pca.child_id
          LEFT JOIN parent_notifications pn ON pn.parent_user_id=u.user_id
          WHERE r.role_name='Parent'
          GROUP BY u.user_id
          ORDER BY u.created_at DESC
          LIMIT 500
        ";
        $res=$mysqli->query($sql);
        while($r=$res->fetch_assoc()) $rows[]=$r;
        ok(['parents'=>$rows]);
    }

    if (isset($_GET['activity'])) {
        $rows=[];
        $sql="
          SELECT u.user_id,u.username,
            COUNT(pn.notification_id) AS total_notifications,
            SUM(CASE WHEN pn.is_read=0 THEN 1 ELSE 0 END) AS unread_notifications,
            MAX(pn.created_at) AS last_notification_date,
            COUNT(DISTINCT pca.child_id) AS children_count
          FROM users u
          JOIN roles r ON r.role_id=u.role_id AND r.role_name='Parent'
          LEFT JOIN parent_notifications pn ON pn.parent_user_id=u.user_id
          LEFT JOIN parent_child_access pca ON pca.parent_user_id=u.user_id AND pca.is_active=1
          GROUP BY u.user_id
          ORDER BY unread_notifications DESC, last_notification_date DESC
          LIMIT 500
        ";
        $res=$mysqli->query($sql);
        while($r=$res->fetch_assoc()) $rows[]=$r;
        ok(['activity'=>$rows]);
    }

    if (isset($_GET['recent_activity'])) {
        $page = max(1, (int)($_GET['page'] ?? 1));
        $pageSize = max(5, min(50, (int)($_GET['page_size'] ?? 10)));
        $offset = ($page - 1) * $pageSize;

        $countSql = "SELECT COUNT(*) as total FROM parent_audit_log l
                     JOIN users u ON u.user_id=l.parent_user_id";
        $countRes = $mysqli->query($countSql);
        $totalCount = $countRes->fetch_assoc()['total'];
        $totalPages = ceil($totalCount / $pageSize);

        $rows=[];
        $sql="
          SELECT l.log_id,l.parent_user_id,l.action_code,l.child_id,l.meta_json,
                 l.ip_address,l.created_at,
                 u.first_name,u.last_name,u.username,
                 c.full_name AS child_name
          FROM parent_audit_log l
          JOIN users u ON u.user_id=l.parent_user_id
          LEFT JOIN children c ON c.child_id=l.child_id
          ORDER BY l.created_at DESC
          LIMIT ? OFFSET ?
        ";
        $stmt=$mysqli->prepare($sql);
        $stmt->bind_param('ii', $pageSize, $offset);
        $stmt->execute();
        $res=$stmt->get_result();
        while($r=$res->fetch_assoc()){
            $desc='';
            switch($r['action_code']){
                case 'login': $desc='Logged in to account'; break;
                case 'view_card': $desc="Viewed child's immunization record"; break;
                case 'download_card': $desc='Downloaded immunization card'; break;
                case 'view_record': $desc='Viewed child record'; break;
                case 'update_profile':
                    $meta=json_decode($r['meta_json']??'[]',true);
                    $field=$meta['field']??null;
                    $desc=$field? 'Updated '.$field : 'Updated contact information';
                    break;
                default: $desc=ucfirst(str_replace('_',' ',$r['action_code']));
            }
            $r['activity_description']=$desc;
            $rows[]=$r;
        }
        $stmt->close();
        
        ok([
            'recent_activity'=>$rows,
            'total_count'=>(int)$totalCount,
            'current_page'=>$page,
            'page_size'=>$pageSize,
            'total_pages'=>$totalPages
        ]);
    }

    if (isset($_GET['children_basic'])) {
        $rows=[];
        $res=$mysqli->query("SELECT child_id, full_name, TIMESTAMPDIFF(MONTH,birth_date,CURDATE()) AS age_months FROM children ORDER BY full_name ASC LIMIT 1000");
        while($r=$res->fetch_assoc()) $rows[]=$r;
        ok(['children'=>$rows]);
    }

    if (isset($_GET['children_of_parent'])) {
        $pid = (int)$_GET['children_of_parent'];
        if ($pid <= 0) fail('Invalid parent_user_id');
        $rows = [];
        $stmt = $mysqli->prepare("
          SELECT pca.access_id, pca.child_id, pca.relationship_type, pca.is_active,
                 c.full_name, c.birth_date,
                 TIMESTAMPDIFF(MONTH,c.birth_date,CURDATE()) AS age_months
          FROM parent_child_access pca
          JOIN children c ON c.child_id = pca.child_id
          WHERE pca.parent_user_id=? 
          ORDER BY c.full_name ASC
        ");
        $stmt->bind_param('i', $pid);
        $stmt->execute();
        $res = $stmt->get_result();
        while($r=$res->fetch_assoc()) $rows[]=$r;
        $stmt->close();
        ok(['children'=>$rows]);
    }

    fail('Unknown GET action',404);
}

/* ---------------- POST (Mutating) ---------------- */
if ($method === 'POST') {
    require_csrf();

    /* (Optional) Test mail moved to POST to eliminate GET side-effect */
    if (isset($_POST['test_mail'])) {
        $to = trim($_POST['to'] ?? '');
        if(!$to) fail('Recipient required');
        $sent = false; $error=null;
        try{
            $sent = send_parent_credentials($to,'Test Parent','testuser','Sample123!','guardian');
            if(!$sent) $error = bhw_mail_last_error();
        }catch(Throwable $e){ $error=$e->getMessage(); }
        ok(['test_mail'=>true,'email_sent'=>$sent,'email_error'=>$error]);
    }

    if (isset($_POST['create_parent'])) {
        $username          = trim($_POST['username'] ?? '');
        $email             = trim($_POST['email'] ?? '');
        $passwordInput     = trim($_POST['password'] ?? '');
        $plain_password    = $passwordInput;
        $first_name        = trim($_POST['first_name'] ?? '');
        $last_name         = trim($_POST['last_name'] ?? '');
        $relationship_type = $_POST['relationship_type'] ?? '';
        $parent_birth_date = trim($_POST['parent_birth_date'] ?? '');

        if($parent_birth_date !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/',$parent_birth_date)){
            $parent_birth_date='';
        }

        if ($username==='' || $first_name==='' || $last_name==='' ||
            !in_array($relationship_type,['mother','father','guardian','caregiver'],true)) {
            fail('Kulangan ang fields. (Missing core parent info)');
        }

        $legacy_child_id = (int)($_POST['child_id'] ?? 0);
        $new_children_raw = $_POST['new_children'] ?? '';
        $parsed_children = [];
        if($new_children_raw !== ''){
            $tmp = json_decode($new_children_raw,true);
            if(is_array($tmp)){
                foreach($tmp as $entry){
                    if(is_array($entry)) $parsed_children[]=$entry;
                }
            }
        }
        if($legacy_child_id <=0 && empty($parsed_children)){
            fail('At least one child must be linked (existing or new).');
        }

        $existing_child_ids=[];
        $new_child_defs=[];
        foreach($parsed_children as $c){
            if(!empty($c['child_id'])){
                $cid=(int)$c['child_id'];
                if($cid>0) $existing_child_ids[]=$cid;
            } else {
                $full=trim($c['full_name'] ?? '');
                $bd=trim($c['birth_date'] ?? '');
                $sex=trim($c['sex'] ?? '');
                if($full==='' || !preg_match('/^\d{4}-\d{2}-\d{2}$/',$bd) || !in_array($sex,['male','female'],true)){
                    fail('Invalid new child data provided.');
                }
                $new_child_defs[]=['full_name'=>$full,'birth_date'=>$bd,'sex'=>$sex];
            }
        }
        if($relationship_type !== 'mother' && count($new_child_defs)>0){
            fail('Only a mother account can create a brand new child here.');
        }
        if($legacy_child_id>0) $existing_child_ids[]=$legacy_child_id;
        $existing_child_ids=array_values(array_unique($existing_child_ids));

        if(!empty($existing_child_ids)){
            $idList=implode(',',array_map('intval',$existing_child_ids));
            $res=$mysqli->query("SELECT child_id FROM children WHERE child_id IN ($idList)");
            $found=[]; while($res && $r=$res->fetch_assoc()) $found[]=(int)$r['child_id'];
            $missing=array_diff($existing_child_ids,$found);
            if(!empty($missing)) fail('One or more selected existing children not found.');
        }

        $chk=$mysqli->prepare("SELECT user_id FROM users WHERE username=? LIMIT 1");
        $chk->bind_param('s',$username);
        $chk->execute(); $chk->store_result();
        if($chk->num_rows>0){ $chk->close(); fail('Username already taken'); }
        $chk->close();

        if ($email!=='') {
            $chk2=$mysqli->prepare("SELECT user_id FROM users WHERE email=? LIMIT 1");
            $chk2->bind_param('s',$email);
            $chk2->execute(); $chk2->store_result();
            if($chk2->num_rows>0){ $chk2->close(); fail('Email already used'); }
            $chk2->close();
        }

        $rid=null;
        $rres=$mysqli->query("SELECT role_id FROM roles WHERE role_name='Parent' LIMIT 1");
        if($rres && $rres->num_rows){ $rid=(int)$rres->fetch_assoc()['role_id']; }
        if(!$rid) fail('Parent role not found',500);

        $auto_gen=false;
        if($plain_password===''){
            $plain_password=rand_password(12);
            $auto_gen=true;
        }
        $hash=password_hash($plain_password,PASSWORD_DEFAULT);
        $barangay='Sabang';
        $creator=(int)($_SESSION['user_id'] ?? 0);

        $mysqli->begin_transaction();
        try{
            $ins=$mysqli->prepare("INSERT INTO users (username,email,password_hash,first_name,last_name,role_id,barangay,created_by_user_id) VALUES (?,?,?,?,?,?,?,?)");
            $ins->bind_param('sssssssi',$username,$email,$hash,$first_name,$last_name,$rid,$barangay,$creator);
            if(!$ins->execute()) throw new Exception('Insert user failed: '.$ins->error);
            $parent_user_id=$ins->insert_id;
            $ins->close();

            $linked_children=[];
            $newly_created=[];
            $mother_id=null;
            if($relationship_type==='mother' && count($new_child_defs)>0){
                $mother_full_name=trim($first_name.' '.$last_name);
                $mother_id=ensure_mother_record($mysqli,$mother_full_name,$parent_birth_date?:null,$creator);
                if($res=$mysqli->query("SHOW COLUMNS FROM mothers_caregivers LIKE 'user_account_id'")){
                    if($res->num_rows>0){
                        $up=$mysqli->prepare("UPDATE mothers_caregivers SET user_account_id=? WHERE mother_id=?");
                        $up->bind_param('ii',$parent_user_id,$mother_id);
                        $up->execute(); $up->close();
                    }
                    $res->close();
                }
            }

            foreach($new_child_defs as $nc){
                if($mother_id===null) throw new Exception('Internal: mother_id not set.');
                $cid=create_child($mysqli,$mother_id,$nc['full_name'],$nc['birth_date'],$nc['sex'],$creator);
                $newly_created[]=$cid;
                $linked_children[]=$cid;
                link_parent_child($mysqli,$parent_user_id,$cid,$relationship_type,$creator);
            }
            foreach($existing_child_ids as $cid){
                link_parent_child($mysqli,$parent_user_id,$cid,$relationship_type,$creator);
                $linked_children[]=$cid;
            }
            $mysqli->commit();

            log_parent_activity($mysqli,$parent_user_id,'create_account');

            $email_sent=false;
            $email_error=null;
            if ($email !== '') {
                try {
                    $email_sent = send_parent_credentials(
                        $email,
                        trim($first_name.' '.$last_name),
                        $username,
                        $plain_password,
                        $relationship_type
                    );
                    if(!$email_sent){
                        $email_error = bhw_mail_last_error();
                    }
                } catch (Throwable $e) {
                    $email_sent = false;
                    $email_error = $e->getMessage();
                }
            }

            ok([
                'parent_user_id'=>$parent_user_id,
                'username'=>$username,
                'auto_generated_password'=>$auto_gen ? $plain_password : null,
                'linked_children'=>array_values(array_unique($linked_children)),
                'newly_created_children'=>$newly_created,
                'email_sent'=>$email_sent,
                'email_error'=>$email_error,
                'message'=>'Parent account created'
            ]);
        } catch(Exception $e){
            $mysqli->rollback();
            fail($e->getMessage(),500);
        }
    }

    if (isset($_POST['reset_password'])) {
        $parent_user_id=(int)$_POST['reset_password'];
        if($parent_user_id<=0) fail('Invalid user id');
        $pw=rand_password(12);
        $hash=password_hash($pw,PASSWORD_DEFAULT);
        $up=$mysqli->prepare("UPDATE users SET password_hash=?, updated_at=NOW() WHERE user_id=? LIMIT 1");
        $up->bind_param('si',$hash,$parent_user_id);
        if(!$up->execute()) fail('Reset failed: '.$up->error,500);
        $up->close();
        log_parent_activity($mysqli,$parent_user_id,'reset_password');
        ok(['parent_user_id'=>$parent_user_id,'new_password'=>$pw]);
    }

    if (isset($_POST['toggle_active'])) {
        $parent_user_id=(int)$_POST['toggle_active'];
        if($parent_user_id<=0) fail('Invalid user id');
        $res=$mysqli->query("SELECT is_active FROM users WHERE user_id=".$parent_user_id." LIMIT 1");
        if(!$res || !$res->num_rows) fail('User not found',404);
        $cur=(int)$res->fetch_assoc()['is_active'];
        $new=$cur?0:1;
        $up=$mysqli->prepare("UPDATE users SET is_active=?, updated_at=NOW() WHERE user_id=? LIMIT 1");
        $up->bind_param('ii',$new,$parent_user_id);
        if(!$up->execute()) fail('Toggle failed: '.$up->error,500);
        $up->close();
        log_parent_activity($mysqli,$parent_user_id,$new?'activate_account':'deactivate_account');
        ok(['parent_user_id'=>$parent_user_id,'is_active'=>$new]);
    }

    if (isset($_POST['link_child'])) {
        $parent_user_id = (int)($_POST['parent_user_id'] ?? 0);
        $child_id       = (int)($_POST['child_id'] ?? 0);
        $rel            = trim($_POST['relationship_type'] ?? 'guardian');
        if ($parent_user_id<=0 || $child_id<=0) fail('Invalid link parameters');
        $chk = $mysqli->prepare("SELECT child_id FROM children WHERE child_id=? LIMIT 1");
        $chk->bind_param('i',$child_id);
        $chk->execute(); $chk->store_result();
        if($chk->num_rows===0){ $chk->close(); fail('Child not found',404); }
        $chk->close();
        $creator = (int)($_SESSION['user_id'] ?? 0);
        link_parent_child($mysqli, $parent_user_id, $child_id, $rel, $creator);
        log_parent_activity($mysqli,$parent_user_id,'link_child',$child_id,['relationship'=>$rel]);
        ok(['linked'=>true,'child_id'=>$child_id]);
    }

    if (isset($_POST['unlink_child'])) {
        $parent_user_id = (int)($_POST['parent_user_id'] ?? 0);
        $child_id       = (int)($_POST['child_id'] ?? 0);
        if ($parent_user_id<=0 || $child_id<=0) fail('Invalid unlink parameters');
        $stmt = $mysqli->prepare("UPDATE parent_child_access SET is_active=0, updated_at=NOW() WHERE parent_user_id=? AND child_id=? AND is_active=1");
        $stmt->bind_param('ii',$parent_user_id,$child_id);
        if(!$stmt->execute()) fail('Unlink failed: '.$stmt->error,500);
        $stmt->close();
        log_parent_activity($mysqli,$parent_user_id,'unlink_child',$child_id);
        ok(['unlinked'=>true,'child_id'=>$child_id]);
    }

    if (isset($_POST['remove_child_link'])) {
        $parent_user_id = (int)($_POST['parent_user_id'] ?? 0);
        $child_id       = (int)($_POST['child_id'] ?? 0);
        if ($parent_user_id<=0 || $child_id<=0) fail('Invalid remove parameters');
        $stmt = $mysqli->prepare("DELETE FROM parent_child_access WHERE parent_user_id=? AND child_id=? LIMIT 1");
        $stmt->bind_param('ii',$parent_user_id,$child_id);
        if(!$stmt->execute()) fail('Remove failed: '.$stmt->error,500);
        $affected = $stmt->affected_rows;
        $stmt->close();
        log_parent_activity($mysqli,$parent_user_id,'remove_child_link',$child_id);
        ok(['removed'=>($affected>0), 'child_id'=>$child_id]);
    }

    fail('Unknown POST action',400);
}

fail('Invalid method',405);