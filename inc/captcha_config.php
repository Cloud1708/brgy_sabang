<?php
// CAPTCHA Configuration
// Replace these with your actual reCAPTCHA keys from Google reCAPTCHA console
// https://www.google.com/recaptcha/admin

// Test keys (for development - always pass)
define('RECAPTCHA_SITE_KEY', '6Ldu1u8rAAAAAPpY7QR8YvYpYlJLcBEgko_CwBxd');
define('RECAPTCHA_SECRET_KEY', '6Ldu1u8rAAAAAKc4b89WgUQHzAxUK1oMQtfE9pf5');

// Production keys (replace with your actual keys)
// define('RECAPTCHA_SITE_KEY', 'YOUR_SITE_KEY_HERE');
// define('RECAPTCHA_SECRET_KEY', 'YOUR_SECRET_KEY_HERE');

// Function to verify CAPTCHA
function verifyCaptcha($response, $remoteip = null) {
    if (empty($response)) {
        return false;
    }
    
    $secret = RECAPTCHA_SECRET_KEY;
    $url = "https://www.google.com/recaptcha/api/siteverify";
    $data = [
        'secret' => $secret,
        'response' => $response,
        'remoteip' => $remoteip ?: $_SERVER['REMOTE_ADDR']
    ];
    
    $options = [
        'http' => [
            'header' => "Content-type: application/x-www-form-urlencoded\r\n",
            'method' => 'POST',
            'content' => http_build_query($data)
        ]
    ];
    
    $context = stream_context_create($options);
    $result = file_get_contents($url, false, $context);
    $json = json_decode($result, true);
    
    return isset($json['success']) && $json['success'] === true;
}
?>
