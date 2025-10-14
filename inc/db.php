<?php
date_default_timezone_set('Asia/Manila');
// Database connection (adjust credentials)
$DB_HOST = 'mysql.hostinger.com';
$DB_USER = 'u689218423_brgysabang';
$DB_PASS = '@Brgy_sabang12345';
$DB_NAME = 'u689218423_brgysabang';

$mysqli = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
if ($mysqli->connect_error) {
    die('Database connection failed: ' . $mysqli->connect_error);
}
$mysqli->set_charset('utf8mb4');
?>