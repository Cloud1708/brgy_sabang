<?php
require_once __DIR__.'/../inc/db.php';
require_once __DIR__.'/../inc/auth.php';
require_role(['BHW']);

if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json; charset=utf-8');

function table_exists($mysqli, $name){
    $stmt = $mysqli->prepare("SHOW TABLES LIKE ?");
    $stmt->bind_param('s',$name);
    $stmt->execute();
    $res = $stmt->get_result();
    $ok = $res && $res->num_rows > 0;
    $stmt->close();
    return $ok;
}
function table_count($mysqli,$name){
    if(!table_exists($mysqli,$name)) return null;
    $res = $mysqli->query("SELECT COUNT(*) c FROM `$name`");
    if(!$res) return null;
    $row = $res->fetch_assoc();
    return (int)$row['c'];
}

$tables = [
  'vaccine_types'         => ['exists'=> false, 'count'=>0],
  'immunization_schedule' => ['exists'=> false, 'count'=>0],
  'children'              => ['exists'=> false, 'count'=>0],
  'child_immunizations'   => ['exists'=> false, 'count'=>0],
  'parent_notifications'  => ['exists'=> false, 'count'=>0],
];

foreach($tables as $t=>$_){
    $ex = table_exists($mysqli,$t);
    $cnt = $ex ? table_count($mysqli,$t) : 0;
    $tables[$t]['exists'] = $ex;
    $tables[$t]['count']  = $cnt;
}

/*
 Feature rules (simplistic):
  immunization: vaccine_types + children exist (at least 1 vaccine or pwede kahit zero children)
  schedule: immunization_schedule + vaccine_types
  overdue_alerts: same as immunization + immunization_schedule
  notifications: parent_notifications table exists
*/
$features = [
  'vaccination_entry'     => $tables['vaccine_types']['exists'] && $tables['children']['exists'],
  'immunization_card'     => $tables['vaccine_types']['exists'] && $tables['children']['exists'],
  'vaccine_schedule'      => $tables['immunization_schedule']['exists'] && $tables['vaccine_types']['exists'],
  'overdue_alerts'        => $tables['vaccine_types']['exists'] && $tables['immunization_schedule']['exists'] && $tables['children']['exists'],
  'parent_notifications'  => $tables['parent_notifications']['exists']
];

echo json_encode([
  'success'=>true,
  'tables'=>$tables,
  'features'=>$features
]);