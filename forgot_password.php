<?php
date_default_timezone_set('Asia/Manila');
require_once __DIR__ . '/inc/db.php';
require_once __DIR__ . '/inc/mail.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}

$msg = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $msg = "<div class='alert alert-danger py-2 small mb-3'>Invalid request. Please try again.</div>";
    } else {
        $email = trim($_POST['email'] ?? '');
        
        if (empty($email)) {
            $msg = "<div class='alert alert-danger py-2 small mb-3'>Please enter your email address.</div>";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $msg = "<div class='alert alert-danger py-2 small mb-3'>Please enter a valid email address.</div>";
        } else {
            // Check if email exists in users table
            $stmt = $mysqli->prepare("
                SELECT u.user_id, u.username, u.email, r.role_name 
                FROM users u 
                JOIN roles r ON u.role_id = r.role_id 
                WHERE u.email = ? AND u.is_active = 1
                LIMIT 1
            ");
            
            if ($stmt === false) {
                $msg = "<div class='alert alert-danger py-2 small mb-3'>Database error. Please try again later.</div>";
            } else {
                $stmt->bind_param("s", $email);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($row = $result->fetch_assoc()) {
                    // Generate reset token
                    $reset_token = bin2hex(random_bytes(32));
                    $expires_at = date('Y-m-d H:i:s', strtotime('+1 hour')); // Token expires in 1 hour
                    
                    // Store reset token in database
                    $token_stmt = $mysqli->prepare("
                        INSERT INTO password_reset_tokens (user_id, token, expires_at, created_at) 
                        VALUES (?, ?, ?, NOW())
                        ON DUPLICATE KEY UPDATE 
                        token = VALUES(token), 
                        expires_at = VALUES(expires_at), 
                        created_at = NOW()
                    ");
                    
                    if ($token_stmt === false) {
                        $msg = "<div class='alert alert-danger py-2 small mb-3'>Database error. Please try again later.</div>";
                    } else {
                        $token_stmt->bind_param("iss", $row['user_id'], $reset_token, $expires_at);
                        
                        if ($token_stmt->execute()) {
                            // Send reset email
                            $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
                            $base_url = $protocol . '://' . $_SERVER['HTTP_HOST'];
                            $reset_link = $base_url . '/brgy_sabangbackup/reset_password.php?token=' . $reset_token;
                            
                            $subject = "Password Reset - Barangay Health & Nutrition System";
                            $message = "
                                <html>
                                <head>
                                    <title>Password Reset Request</title>
                                </head>
                                <body>
                                    <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
                                        <div style='background: linear-gradient(135deg, #047857, #059669); color: white; padding: 20px; text-align: center;'>
                                            <h2 style='margin: 0;'>Password Reset Request</h2>
                                        </div>
                                        <div style='padding: 30px; background: #f8f9fa;'>
                                            <p>Hello " . htmlspecialchars($row['username']) . ",</p>
                                            <p>You have requested to reset your password for the Barangay Health & Nutrition Management System.</p>
                                            <p>Click the button below to reset your password:</p>
                                            <div style='text-align: center; margin: 30px 0;'>
                                                <a href='" . $reset_link . "' style='background: #047857; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; display: inline-block;'>Reset Password</a>
                                            </div>
                                            <p><strong>This link will expire in 1 hour.</strong></p>
                                            <p>If you did not request this password reset, please ignore this email.</p>
                                            <hr style='margin: 30px 0; border: none; border-top: 1px solid #dee2e6;'>
                                            <p style='font-size: 12px; color: #6c757d;'>
                                                This is an automated message from the Barangay Health & Nutrition Management System.<br>
                                                Please do not reply to this email.
                                            </p>
                                        </div>
                                    </div>
                                </body>
                                </html>
                            ";
                            
                            if (sendEmail($email, $subject, $message)) {
                                $success = true;
                                $msg = "<div class='alert alert-success py-2 small mb-3'>Password reset instructions have been sent to your email address.</div>";
                            } else {
                                $msg = "<div class='alert alert-danger py-2 small mb-3'>Failed to send email. Please try again later.</div>";
                            }
                        } else {
                            $msg = "<div class='alert alert-danger py-2 small mb-3'>Failed to generate reset token. Please try again.</div>";
                        }
                        $token_stmt->close();
                    }
                } else {
                    // Don't reveal if email exists or not for security
                    $success = true;
                    $msg = "<div class='alert alert-success py-2 small mb-3'>If an account with that email exists, password reset instructions have been sent.</div>";
                }
                $stmt->close();
            }
        }
    }
    $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}
?>
<!doctype html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <title>Forgot Password | Barangay Health & Nutrition</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/jpeg" href="assets/img/sabang.jpg">
    <link rel="shortcut icon" type="image/jpeg" href="assets/img/sabang.jpg">
    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <!-- Project CSS -->
    <link rel="stylesheet" href="assets/css/style.css?v=20251005">
</head>
<body class="auth-body brgy-bg">
    <header class="auth-topbar text-white">
        <div class="container d-flex align-items-center gap-3">
            <div class="d-flex align-items-center gap-2">
                <div class="brand-circle" aria-label="Lipa City Seal">
                    <img src="assets/img/Lipa_City_Seal.svg.png" alt="Lipa City Seal">
                </div>
                <div class="brand-circle" aria-label="Barangay Sabang logo">
                    <img src="assets/img/sabang.jpg" alt="Barangay Sabang logo">
                </div>
            </div>
            <div class="d-flex flex-column">
                <strong class="small">Barangay Health &amp; Nutrition System</strong>
                <span class="xsmall text-white-50">Password Recovery</span>
            </div>
        </div>
    </header>

    <main class="container d-flex align-items-center justify-content-center auth-stage">
        <div class="card login-card shadow-lg border-0">
            <div class="card-body p-4 p-md-5">
                <div class="text-center mb-4">
                    <div class="d-flex justify-content-center gap-2 mx-auto mb-3">
                        <div class="brand-circle brand-circle-lg" aria-label="Lipa City Seal">
                            <img src="assets/img/Lipa_City_Seal.svg.png" alt="Lipa City Seal">
                        </div>
                        <div class="brand-circle brand-circle-lg" aria-label="Barangay Sabang logo">
                            <img src="assets/img/sabang.jpg" alt="Barangay Sabang logo">
                        </div>
                    </div>
                    <h1 class="h6 mb-1 fw-semibold text-primary-emphasis">
                        Forgot Password
                    </h1>
                    <p class="small text-secondary mb-0">Enter your email to reset your password</p>
                </div>

                <?php echo $msg; ?>

                <?php if (!$success): ?>
                <form method="post" action="forgot_password" novalidate>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Email Address</label>
                        <div class="input-group">
                            <span class="input-group-text bg-white">
                                <i class="bi bi-envelope text-secondary"></i>
                            </span>
                            <input type="email" name="email" class="form-control" maxlength="255" required 
                                   autofocus placeholder="Enter your email address"
                                   value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                        </div>
                    </div>

                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

                    <div class="d-grid mt-3">
                        <button class="btn btn-success btn-login fw-semibold" type="submit">
                            Send Reset Instructions
                        </button>
                    </div>
                </form>
                <?php endif; ?>

                <div class="text-center mt-3">
                    <a href="staff_login" class="small d-inline-block mt-2">
                        <i class="bi bi-arrow-left me-1"></i>Back to Login
                    </a>
                </div>
            </div>
        </div>
    </main>

    <footer class="auth-footer text-center text-white-50 small">
        <div class="container">
            <div>&copy; <?php echo date('Y'); ?> Barangay Health &amp; Nutrition Management System</div>
            <div class="xsmall">In partnership with the City Health Office</div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
