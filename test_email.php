<?php
// Log to a local file para siguradong may log tayong makikita
ini_set('error_log', __DIR__ . '/php_error.log');

require_once __DIR__.'/inc/mail.php';

// 1) Quick check: tama ba ang path ng PHPMailer?
$path = __DIR__ . '/PHPMailer/src/PHPMailer.php';
echo 'PHPMailer path: ' . htmlspecialchars($path) . '<br>';
echo 'Exists? ' . (file_exists($path) ? 'YES' : 'NO') . '<br><br>';

// 2) TEMP credentials (palitan mo ito ng sayo)
putenv('SMTP_USER=yourgmail@gmail.com');          // iyong Gmail
putenv('SMTP_PASS=your_app_password');            // 16 char Gmail App Password
putenv('FROM_EMAIL=yourgmail@gmail.com');         // dapat tugma sa SMTP_USER para sa Gmail
putenv('FROM_NAME=Barangay Health Center');
putenv('MAIL_DEBUG=1');                           // para lumabas ang [MAIL DEBUG] sa php_error.log

$to = 'destination@gmail.com'; // saan mo gustong makatanggap habang nagte-test
$ok = bhw_mail_send($to, 'BHW SMTP test', '<b>Hello!</b> If you see this, SMTP works.');

if ($ok) {
    echo "OK: message sent";
} else {
    echo "FAILED: ".(function_exists('bhw_mail_last_error') ? bhw_mail_last_error() : 'unknown error');
    // I-print din ang huling error sa log
    error_log('TEST SEND FAILED: '.(function_exists('bhw_mail_last_error') ? bhw_mail_last_error() : 'unknown error'));
}

echo '<br><br>Check php_error.log in this folder for [MAIL DEBUG] lines.';