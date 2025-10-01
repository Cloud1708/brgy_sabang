<?php
require_once __DIR__.'/../inc/db.php';
require_once __DIR__.'/../inc/auth.php';
require_role(['BHW']); // Adjust to ['Admin','BNS'] if you want to restrict

if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json; charset=utf-8');

function fail($m,$c=400){ http_response_code($c); echo json_encode(['success'=>false,'error'=>$m]); exit; }
function ok($d=[]){ echo json_encode(array_merge(['success'=>true],$d)); exit; }

$method = $_SERVER['REQUEST_METHOD'];

function require_csrf(){
    if (empty($_POST['csrf_token']) || empty($_SESSION['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
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

if ($method === 'GET') {

    if (isset($_GET['list_parents'])) {
        $rows=[];
        $sql="
          SELECT u.user_id, u.username, u.email, u.first_name, u.last_name,
                 u.is_active, u.created_at,
                 GROUP_CONCAT(DISTINCT CONCAT(c.full_name,' (',pca.relationship_type,')') ORDER BY c.full_name SEPARATOR '; ') AS children_list,
                 COUNT(DISTINCT c.child_id) AS children_count
          FROM users u
          JOIN roles r ON r.role_id=u.role_id
          LEFT JOIN parent_child_access pca ON pca.parent_user_id=u.user_id AND pca.is_active=1
          LEFT JOIN children c ON c.child_id=pca.child_id
          WHERE r.role_name='Parent'
          GROUP BY u.user_id
          ORDER BY u.created_at DESC
          LIMIT 500
        ";
        $res=$mysqli->query($sql);
        while($r=$res->fetch_assoc()) $rows[]=$r;
        ok(['parents'=>$rows]);
    }

    if (isset($_GET['children_basic'])) {
        $rows=[];
        $res=$mysqli->query("
          SELECT child_id, full_name,
                 TIMESTAMPDIFF(MONTH,birth_date,CURDATE()) AS age_months
          FROM children
          ORDER BY full_name ASC
          LIMIT 1000
        ");
        while($r=$res->fetch_assoc()) $rows[]=$r;
        ok(['children'=>$rows]);
    }

    if (isset($_GET['links'])) {
        $rows=[];
        $res=$mysqli->query("
          SELECT pca.access_id, pca.parent_user_id, u.username parent_username,
                 c.child_id, c.full_name child_name, pca.relationship_type,
                 pca.is_active, pca.granted_date
          FROM parent_child_access pca
          JOIN users u ON u.user_id=pca.parent_user_id
          JOIN children c ON c.child_id=pca.child_id
          JOIN roles r ON r.role_id=u.role_id
          WHERE r.role_name='Parent'
          ORDER BY pca.granted_date DESC
          LIMIT 800
        ");
        while($r=$res->fetch_assoc()) $rows[]=$r;
        ok(['links'=>$rows]);
    }

    if (isset($_GET['activity'])) {
        $rows=[];
        $sql="
          SELECT u.user_id, u.username,
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
        while($r=$res->fetch_assoc()){
            $rows[]=$r;
        }
        ok(['activity'=>$rows]);
    }

    fail('Unknown GET action',404);
}

if ($method === 'POST') {
    require_csrf();

    if (isset($_POST['create_parent'])) {
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = trim($_POST['password'] ?? '');
        $first_name = trim($_POST['first_name'] ?? '');
        $last_name = trim($_POST['last_name'] ?? '');
        $relationship_type = $_POST['relationship_type'] ?? '';
        $child_id = (int)($_POST['child_id'] ?? 0);
        $barangay = 'Sabang';
        $creator = (int)($_SESSION['user_id'] ?? 0);

        if ($username==='' || $first_name==='' || $last_name==='' || !$child_id ||
            !in_array($relationship_type,['mother','father','guardian','caregiver'],true)) {
            fail('Kulangan ang fields.');
        }
        $auto_gen=false;
        if ($password===''){ $password=rand_password(10); $auto_gen=true; }

        $chkChild=$mysqli->prepare("SELECT mother_id FROM children WHERE child_id=? LIMIT 1");
        $chkChild->bind_param('i',$child_id);
        $chkChild->execute(); $chkChild->bind_result($mid);
        if(!$chkChild->fetch()){ $chkChild->close(); fail('Child not found',404); }
        $chkChild->close();

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

        $hash=password_hash($password,PASSWORD_DEFAULT);

        $ins=$mysqli->prepare("INSERT INTO users (username,email,password_hash,first_name,last_name,role_id,barangay,created_by_user_id)
                               VALUES (?,?,?,?,?,?,?,?)");
        $ins->bind_param('sssssssi',$username,$email,$hash,$first_name,$last_name,$rid,$barangay,$creator);
        if(!$ins->execute()) fail('Insert user failed: '.$ins->error,500);
        $parent_user_id=$ins->insert_id;
        $ins->close();

        $link=$mysqli->prepare("INSERT INTO parent_child_access (parent_user_id,child_id,relationship_type,access_granted_by,is_active)
                                VALUES (?,?,?,?,1)");
        $link->bind_param('iisi',$parent_user_id,$child_id,$relationship_type,$creator);
        if(!$link->execute()) fail('Link failed: '.$link->error,500);
        $link->close();

        if($relationship_type==='mother' && $mid){
            $up=$mysqli->prepare("UPDATE mothers_caregivers SET user_account_id=? WHERE mother_id=?");
            $up->bind_param('ii',$parent_user_id,$mid);
            $up->execute(); $up->close();
        }

        ok([
            'parent_user_id'=>$parent_user_id,
            'username'=>$username,
            'auto_generated_password'=>$auto_gen?$password:null,
            'message'=>'Parent account created'
        ]);
    }

    if (isset($_POST['link_child'])) {
        $parent_user_id=(int)($_POST['parent_user_id'] ?? 0);
        $child_id=(int)($_POST['child_id'] ?? 0);
        $relationship_type=$_POST['relationship_type'] ?? '';
        $creator=(int)($_SESSION['user_id'] ?? 0);
        if(!$parent_user_id || !$child_id || !in_array($relationship_type,['mother','father','guardian','caregiver'],true))
            fail('Invalid link data.');

        $chk=$mysqli->prepare("
          SELECT u.user_id FROM users u
          JOIN roles r ON r.role_id=u.role_id
          WHERE u.user_id=? AND r.role_name='Parent' LIMIT 1
        ");
        $chk->bind_param('i',$parent_user_id);
        $chk->execute(); $chk->store_result();
        if($chk->num_rows===0){ $chk->close(); fail('Not a parent user'); }
        $chk->close();

        $chk2=$mysqli->prepare("SELECT access_id,is_active FROM parent_child_access WHERE parent_user_id=? AND child_id=? LIMIT 1");
        $chk2->bind_param('ii',$parent_user_id,$child_id);
        $chk2->execute(); $chk2->bind_result($aid,$is_active);
        if($chk2->fetch()){
            $chk2->close();
            if(!$is_active){
                $up=$mysqli->prepare("UPDATE parent_child_access SET is_active=1, relationship_type=?, updated_at=NOW() WHERE access_id=? LIMIT 1");
                $up->bind_param('si',$relationship_type,$aid);
                if(!$up->execute()) fail('Re-activate failed: '.$up->error,500);
                $up->close();
                ok(['relinked_access_id'=>$aid]);
            } else {
                fail('Already linked');
            }
        } else {
            $chk2->close();
            $ins=$mysqli->prepare("INSERT INTO parent_child_access (parent_user_id,child_id,relationship_type,access_granted_by,is_active)
                                   VALUES (?,?,?,?,1)");
            $ins->bind_param('iisi',$parent_user_id,$child_id,$relationship_type,$creator);
            if(!$ins->execute()) fail('Insert link failed: '.$ins->error,500);
            $aid=$ins->insert_id;
            $ins->close();
            ok(['access_id'=>$aid]);
        }
    }

    if (isset($_POST['unlink_access_id'])) {
        $aid=(int)$_POST['unlink_access_id'];
        if($aid<=0) fail('Invalid access id');
        $up=$mysqli->prepare("UPDATE parent_child_access SET is_active=0, updated_at=NOW() WHERE access_id=? LIMIT 1");
        $up->bind_param('i',$aid);
        if(!$up->execute()) fail('Unlink failed: '.$up->error,500);
        $up->close();
        ok(['unlinked_access_id'=>$aid]);
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
        ok(['parent_user_id'=>$parent_user_id,'is_active'=>$new]);
    }

    fail('Unknown POST action',400);
}

fail('Invalid method',405);