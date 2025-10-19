<?php
// inc/mail.php
// Centralized email sending with improved diagnostics.

if (!function_exists('bhw_mail_send')) {

    $GLOBALS['BHW_MAIL_LAST_ERROR'] = null;

    function bhw_mail_last_error(): ?string {
        return $GLOBALS['BHW_MAIL_LAST_ERROR'] ?? null;
    }

    function bhw_mail_config(): array {
        return [
            'enabled'      => true,
            'transport'    => 'smtp',
            'smtp_host'    => getenv('SMTP_HOST') ?: 'smtp.gmail.com',
            'smtp_port'    => (int)(getenv('SMTP_PORT') ?: 587),
            'smtp_secure'  => getenv('SMTP_SECURE') ?: 'tls', // tls | ssl | ''
            'smtp_user'    => getenv('SMTP_USER') ?: 'ch512291@gmail.com',
            // REAL APP PASSWORD should come from env; NO SPACES.
            'smtp_pass'    => getenv('SMTP_PASS') ?: 'cldh irej xypy fvnr',
            'from_email'   => getenv('FROM_EMAIL') ?: (getenv('SMTP_USER') ?: 'ch512291@gmail.com'),
            'from_name'    => getenv('FROM_NAME') ?: 'Barangay Health Center',
            'reply_email'  => getenv('REPLY_EMAIL') ?: '',
            'reply_name'   => getenv('REPLY_NAME') ?: 'BHW Desk',
            'debug'        => (bool)(getenv('MAIL_DEBUG') ?: false), // export MAIL_DEBUG=1
            // Email alias configuration
            'alias_email'  => getenv('ALIAS_EMAIL') ?: 'updates@sabanghealthportal.site',
            'alias_name'   => getenv('ALIAS_NAME') ?: 'Barangay Health Center'
        ];
    }

    function bhw_mail_send(string $to, string $subject, string $htmlBody, string $textBody = ''): bool {
        $GLOBALS['BHW_MAIL_LAST_ERROR'] = null;
        $cfg = bhw_mail_config();

        if (!$cfg['enabled']) {
            $GLOBALS['BHW_MAIL_LAST_ERROR'] = 'Email disabled in config.';
            return false;
        }
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            $GLOBALS['BHW_MAIL_LAST_ERROR'] = 'Invalid recipient email: '.$to;
            return false;
        }

        $smtpPass = trim(str_replace(' ', '', $cfg['smtp_pass'] ?? ''));

        if ($cfg['transport'] === 'smtp') {
            // Ensure PHPMailer
            if (!class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
                // Try Composer autoload first
                $autoload = __DIR__ . '/../vendor/autoload.php';
                if (file_exists($autoload)) {
                    require_once $autoload;
                }
            }
            if (!class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
                // Fallback to bundled PHPMailer in project (PHPMailer/src)
                $phpMailerPath = __DIR__ . '/../PHPMailer/src';
                $needed = [
                    $phpMailerPath . '/Exception.php',
                    $phpMailerPath . '/PHPMailer.php',
                    $phpMailerPath . '/SMTP.php',
                ];
                $allExist = true;
                foreach ($needed as $f) { if (!file_exists($f)) { $allExist = false; break; } }
                if ($allExist) {
                    foreach ($needed as $f) { require_once $f; }
                }
            }
            if (!class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
                $GLOBALS['BHW_MAIL_LAST_ERROR'] = "PHPMailer not installed. Add vendor autoload or place library under PHPMailer/src";
                return false;
            }
            try {
                $mail = new PHPMailer\PHPMailer\PHPMailer(true);

                // Debug level 0/1/2/3/4 (2 = client & server messages)
                if ($cfg['debug']) {
                    $mail->SMTPDebug = 2;
                    $mail->Debugoutput = function($str,$level){
                        error_log("[MAIL DEBUG][$level] $str");
                    };
                }

                $mail->isSMTP();
                $mail->Host       = $cfg['smtp_host'];
                $mail->Port       = $cfg['smtp_port'];
                $mail->SMTPAuth   = true;
                // Force STARTTLS when tls
                if ($cfg['smtp_secure'] === 'tls') {
                    $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
                } elseif ($cfg['smtp_secure'] === 'ssl') {
                    $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
                }
                // (Optional) explicitly set
                $mail->AuthType   = 'LOGIN';

                // Allow self-signed when developing locally (avoid verify peer errors)
                $mail->SMTPOptions = [
                    'ssl' => [
                        'verify_peer'       => false,
                        'verify_peer_name'  => false,
                        'allow_self_signed' => true,
                    ],
                ];

                $mail->Username   = $cfg['smtp_user'];
                $mail->Password   = $smtpPass;
                $mail->CharSet    = 'UTF-8';
                
                // Use alias email for "From" field, but authenticate with personal email
                $fromEmail = $cfg['alias_email'] ?? $cfg['from_email'];
                $fromName = $cfg['alias_name'] ?? $cfg['from_name'];
                $mail->setFrom($fromEmail, $fromName);
                
                // Set reply-to to alias email as well
                $replyEmail = $cfg['alias_email'] ?? $cfg['reply_email'];
                $replyName = $cfg['alias_name'] ?? $cfg['reply_name'];
                if (!empty($replyEmail)) {
                    $mail->addReplyTo($replyEmail, $replyName ?: $replyEmail);
                }
                $mail->addAddress($to);
                $mail->Subject = $subject;

                $alt = $textBody !== '' ? $textBody :
                    strip_tags(str_replace(['<br>','<br/>','<br />'], "\n", $htmlBody));
                $mail->isHTML(true);
                $mail->Body    = $htmlBody;
                $mail->AltBody = $alt;

                if(!$mail->send()){
                    $GLOBALS['BHW_MAIL_LAST_ERROR'] = 'PHPMailer send() failed: '.$mail->ErrorInfo;
                    return false;
                }
                return true;
            } catch (\Throwable $e) {
                // Combine PHPMailer internal ErrorInfo (if instantiated) with exception
                $err = isset($mail) ? $mail->ErrorInfo : '';
                $GLOBALS['BHW_MAIL_LAST_ERROR'] = 'PHPMailer exception: '.$e->getMessage().($err ? ' | ErrorInfo: '.$err : '');
                return false;
            }
        }

        // Fallback to mail() only if we reached here without an SMTP error
        if ($GLOBALS['BHW_MAIL_LAST_ERROR']) {
            return false;
        }

        $headers  = "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        
        // Use alias email for "From" field in fallback mail() function
        $fromEmail = $cfg['alias_email'] ?? $cfg['from_email'];
        $fromName = $cfg['alias_name'] ?? $cfg['from_name'];
        $headers .= "From: ".$fromName." <".$fromEmail.">\r\n";
        
        $replyEmail = $cfg['alias_email'] ?? $cfg['reply_email'];
        $replyName = $cfg['alias_name'] ?? $cfg['reply_name'];
        if (!empty($replyEmail)) {
            $headers .= "Reply-To: ".$replyName." <".$replyEmail.">\r\n";
        }
        $ok = @mail(
            $to,
            '=?UTF-8?B?'.base64_encode($subject).'?=',
            $htmlBody,
            $headers
        );
        if(!$ok){
            $GLOBALS['BHW_MAIL_LAST_ERROR'] = 'mail() fallback failed (no local MTA).';
        }
        return $ok;
    }

    function send_parent_credentials(string $toEmail, string $parentName, string $username, string $plainPassword, string $relationship): bool {
        if (!filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
            $GLOBALS['BHW_MAIL_LAST_ERROR'] = 'Invalid email for credentials: '.$toEmail;
            return false;
        }
        $subject = "Your Parent Portal Account Credentials";
        $safeParent = htmlspecialchars($parentName, ENT_QUOTES, 'UTF-8');
        $html = "
            <div style='font-family:Arial,Helvetica,sans-serif;font-size:14px;color:#222;'>
              <h2 style='margin:0 0 10px;'>Parent / Guardian Account Created</h2>
              <p>Hello <strong>{$safeParent}</strong>,</p>
              <p>Your account for the Barangay Health Parent Portal has been created.</p>
              <table cellpadding='6' style='border-collapse:collapse;margin:10px 0;'>
                <tr>
                  <td style='background:#f4f7f8;border:1px solid #cfd8dc;font-weight:bold;'>Relationship</td>
                  <td style='border:1px solid #cfd8dc;'>".htmlspecialchars(ucfirst($relationship))."</td>
                </tr>
                <tr>
                  <td style='background:#f4f7f8;border:1px solid #cfd8dc;font-weight:bold;'>Username</td>
                  <td style='border:1px solid #cfd8dc;'>".htmlspecialchars($username)."</td>
                </tr>
                <tr>
                  <td style='background:#f4f7f8;border:1px solid #cfd8dc;font-weight:bold;'>Password</td>
                  <td style='border:1px solid #cfd8dc;'>".htmlspecialchars($plainPassword)."</td>
                </tr>
              </table>
              <p>Please keep these credentials secure. You may change the password after first login.</p>
              <p style='font-size:12px;color:#555;'>If you did not request this, kindly ignore or contact the BHW office.</p>
              <p style='font-size:12px;color:#888;'>-- Barangay Health Center</p>
            </div>
        ";
        $text = "Parent / Guardian Account Created\n\n".
                "Hello {$parentName},\n\n".
                "Your account has been created.\n".
                "Relationship: {$relationship}\n".
                "Username: {$username}\n".
                "Password: {$plainPassword}\n\n".
                "Please keep these credentials secure.\n\n-- Barangay Health Center";
        return bhw_mail_send($toEmail, $subject, $html, $text);
    }

    function sendEmail(string $to, string $subject, string $message): bool {
        return bhw_mail_send($to, $subject, $message);
    }
}