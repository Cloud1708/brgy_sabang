<?php
require_once __DIR__.'/../inc/db.php';
require_once __DIR__.'/../inc/auth.php';
require_role(['BHW', 'BNS', 'Admin']);

if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors','0');
error_reporting(E_ALL);

function fail($m,$c=400){
    http_response_code($c);
    echo json_encode(['success'=>false,'error'=>$m]);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

/* =================== GET =================== */
if ($method === 'GET') {
    
    // Get all puroks
    if (isset($_GET['list'])) {
        $stmt = $mysqli->prepare("SELECT purok_id, purok_name, barangay FROM puroks ORDER BY purok_name ASC");
        if(!$stmt) fail('Prepare failed: '.$mysqli->error,500);
        
        $stmt->execute();
        $res = $stmt->get_result();
        $puroks = [];
        
        while($row = $res->fetch_assoc()) {
            $puroks[] = $row;
        }
        $stmt->close();
        
        echo json_encode(['success'=>true,'puroks'=>$puroks]);
        exit;
    }
    
    fail('Unknown GET action',404);
}

fail('Invalid method',405);
?>
