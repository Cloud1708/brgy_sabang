<?php
date_default_timezone_set('Asia/Manila');
require_once __DIR__ . '/inc/db.php';

// Check database connection
if ($mysqli->connect_error) {
    die("Database connection failed: " . $mysqli->connect_error);
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}

$msg = '';
$success = false;
$valid_token = false;
$user_info = null;

// Check if token is provided and valid
$token = $_GET['token'] ?? '';
if (!empty($token)) {
    // Debug: Log token for troubleshooting
    error_log("Reset password attempt with token: " . substr($token, 0, 10) . "...");
    $stmt = $mysqli->prepare("
        SELECT prt.user_id, prt.expires_at, u.username, u.email, r.role_name
        FROM password_reset_tokens prt
        JOIN users u ON prt.user_id = u.user_id
        JOIN roles r ON u.role_id = r.role_id
        WHERE prt.token = ? AND prt.expires_at > NOW() AND u.is_active = 1
        LIMIT 1
    ");
    
    if ($stmt !== false) {
        $stmt->bind_param("s", $token);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            $valid_token = true;
            $user_info = $row;
        } else {
            $msg = "<div class='alert alert-danger py-2 small mb-3'>Invalid or expired reset token. Please request a new password reset.</div>";
        }
        $stmt->close();
    } else {
        $msg = "<div class='alert alert-danger py-2 small mb-3'>Database error: " . $mysqli->error . ". Please try again later.</div>";
    }
} else {
    $msg = "<div class='alert alert-danger py-2 small mb-3'>No reset token provided. Please use the link from your email.</div>";
}

// Handle password reset form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $valid_token) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $msg = "<div class='alert alert-danger py-2 small mb-3'>Invalid request. Please try again.</div>";
    } else {
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        if (empty($new_password)) {
            $msg = "<div class='alert alert-danger py-2 small mb-3'>Please enter a new password.</div>";
        } elseif (strlen($new_password) < 8) {
            $msg = "<div class='alert alert-danger py-2 small mb-3'>Password must be at least 8 characters long.</div>";
        } elseif ($new_password !== $confirm_password) {
            $msg = "<div class='alert alert-danger py-2 small mb-3'>Passwords do not match.</div>";
        } else {
            // Update password
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $update_stmt = $mysqli->prepare("
                UPDATE users 
                SET password_hash = ? 
                WHERE user_id = ?
            ");
            
            if ($update_stmt === false) {
                $msg = "<div class='alert alert-danger py-2 small mb-3'>Database error. Please try again later.</div>";
            } else {
                $update_stmt->bind_param("si", $hashed_password, $user_info['user_id']);
                
                if ($update_stmt->execute()) {
                    // Delete the used reset token
                    $delete_stmt = $mysqli->prepare("
                        DELETE FROM password_reset_tokens 
                        WHERE token = ?
                    ");
                    if ($delete_stmt !== false) {
                        $delete_stmt->bind_param("s", $token);
                        $delete_stmt->execute();
                        $delete_stmt->close();
                    }
                    
                    $success = true;
                    $msg = "<div class='alert alert-success py-2 small mb-3'>Password has been successfully reset. You can now login with your new password.</div>";
                } else {
                    $msg = "<div class='alert alert-danger py-2 small mb-3'>Failed to update password. Please try again.</div>";
                }
                $update_stmt->close();
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
    <title>Reset Password | Barangay Health & Nutrition</title>
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
                <span class="xsmall text-white-50">Password Reset</span>
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
                        Reset Password
                    </h1>
                    <?php if ($valid_token && $user_info): ?>
                    <p class="small text-secondary mb-0">Set a new password for <strong><?php echo htmlspecialchars($user_info['username']); ?></strong></p>
                    <?php else: ?>
                    <p class="small text-secondary mb-0">Enter your new password</p>
                    <?php endif; ?>
                </div>

                <?php echo $msg; ?>

                <?php if ($valid_token && $user_info && !$success): ?>
                <form method="post" action="reset_password.php?token=<?php echo htmlspecialchars($token); ?>" novalidate>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">New Password</label>
                        <div class="input-group">
                            <span class="input-group-text bg-white">
                                <i class="bi bi-lock text-secondary"></i>
                            </span>
                            <input type="password" name="new_password" class="form-control" required 
                                   autofocus placeholder="Enter new password" minlength="8">
                        </div>
                        <div class="form-text">Password must be at least 8 characters long.</div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Confirm New Password</label>
                        <div class="input-group">
                            <span class="input-group-text bg-white">
                                <i class="bi bi-lock-fill text-secondary"></i>
                            </span>
                            <input type="password" name="confirm_password" class="form-control" required 
                                   placeholder="Confirm new password" minlength="8">
                        </div>
                    </div>

                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

                    <div class="d-grid mt-3">
                        <button class="btn btn-success btn-login fw-semibold" type="submit">
                            Reset Password
                        </button>
                    </div>
                </form>
                <?php endif; ?>

                <div class="text-center mt-3">
                    <?php if ($success): ?>
                    <a href="staff_login" class="btn btn-primary btn-sm">
                        <i class="bi bi-box-arrow-in-right me-1"></i>Go to Login
                    </a>
                    <?php else: ?>
                    <a href="forgot_password" class="small d-inline-block mt-2">
                        <i class="bi bi-arrow-left me-1"></i>Back to Forgot Password
                    </a>
                    <?php endif; ?>
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
