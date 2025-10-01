<?php
require_once __DIR__.'/../inc/db.php';
require_once __DIR__.'/../inc/auth.php';
require_role(['BNS']);
header('Content-Type: application/json; charset=utf-8');

$res = $mysqli->query("SELECT status_id,status_code,status_description,status_category FROM wfl_ht_status_types ORDER BY status_code ASC");
$rows=[]; while($r=$res->fetch_assoc()) $rows[]=$r;
echo json_encode(['success'=>true,'status_types'=>$rows]);