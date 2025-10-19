<?php
date_default_timezone_set('Asia/Manila');
require_once __DIR__ . '/inc/db.php';
require_once __DIR__ . '/inc/auth.php';
require_once __DIR__ . '/inc/captcha_config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}

// Function to get client IP address
function getClientIP() {
    $ipKeys = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];
    foreach ($ipKeys as $key) {
        if (array_key_exists($key, $_SERVER) === true) {
            foreach (explode(',', $_SERVER[$key]) as $ip) {
                $ip = trim($ip);
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                    return $ip;
                }
            }
        }
    }
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

// Function to check if account is locked
function isAccountLocked($mysqli, $username, $ip) {
    $stmt = $mysqli->prepare("
        SELECT attempt_count, locked_until, is_locked 
        FROM login_attempts 
        WHERE username = ? AND ip_address = ?
    ");
    $stmt->bind_param("ss", $username, $ip);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        // Check if still locked
        if ($row['is_locked'] == 1 && $row['locked_until'] && strtotime($row['locked_until']) > time()) {
            return [
                'locked' => true,
                'attempts' => $row['attempt_count'],
                'locked_until' => $row['locked_until']
            ];
        }
        // If lockout expired, reset the attempts
        if ($row['is_locked'] == 1 && $row['locked_until'] && strtotime($row['locked_until']) <= time()) {
            $reset_stmt = $mysqli->prepare("
                UPDATE login_attempts 
                SET attempt_count = 0, is_locked = 0, locked_until = NULL 
                WHERE username = ? AND ip_address = ?
            ");
            $reset_stmt->bind_param("ss", $username, $ip);
            $reset_stmt->execute();
            $reset_stmt->close();
        }
    }
    $stmt->close();
    return ['locked' => false, 'attempts' => $row['attempt_count'] ?? 0];
}

// Function to record failed login attempt
function recordFailedAttempt($mysqli, $username, $ip) {
    $stmt = $mysqli->prepare("
        INSERT INTO login_attempts (username, ip_address, attempt_count, last_attempt) 
        VALUES (?, ?, 1, NOW())
        ON DUPLICATE KEY UPDATE 
        attempt_count = attempt_count + 1, 
        last_attempt = NOW()
    ");
    $stmt->bind_param("ss", $username, $ip);
    $stmt->execute();
    $stmt->close();
    
    // Check if we need to lock the account
    $check_stmt = $mysqli->prepare("
        SELECT attempt_count FROM login_attempts 
        WHERE username = ? AND ip_address = ?
    ");
    $check_stmt->bind_param("ss", $username, $ip);
    $check_stmt->execute();
    $result = $check_stmt->get_result();
    $row = $result->fetch_assoc();
    $check_stmt->close();
    
    if ($row && $row['attempt_count'] >= 5) {
        // Lock the account for 5 minutes
        $lock_stmt = $mysqli->prepare("
            UPDATE login_attempts 
            SET is_locked = 1, locked_until = DATE_ADD(NOW(), INTERVAL 5 MINUTE)
            WHERE username = ? AND ip_address = ?
        ");
        $lock_stmt->bind_param("ss", $username, $ip);
        $lock_stmt->execute();
        $lock_stmt->close();
    }
}

// Function to clear failed attempts on successful login
function clearFailedAttempts($mysqli, $username, $ip) {
    $stmt = $mysqli->prepare("
        DELETE FROM login_attempts 
        WHERE username = ? AND ip_address = ?
    ");
    $stmt->bind_param("ss", $username, $ip);
    $stmt->execute();
    $stmt->close();
}

$msg = '';
$remaining_attempts = 5;
$is_locked = false;
$locked_until = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $msg = "<div class='alert alert-danger py-2 small mb-3'>Invalid request. Please try again.</div>";
    } elseif (!isset($_POST['g-recaptcha-response']) || empty($_POST['g-recaptcha-response'])) {
        $msg = "<div class='alert alert-danger py-2 small mb-3'>Please complete the CAPTCHA verification.</div>";
    } else {
        // Verify CAPTCHA
        if (!verifyCaptcha($_POST['g-recaptcha-response'])) {
            $msg = "<div class='alert alert-danger py-2 small mb-3'>CAPTCHA verification failed. Please try again.</div>";
        } else {
            $username = $_POST['username'] ?? '';
            $password = $_POST['password'] ?? '';
            $client_ip = getClientIP();
        
        // Check if account is locked
        $lock_status = isAccountLocked($mysqli, $username, $client_ip);
        
        if ($lock_status['locked']) {
            $is_locked = true;
            $locked_until = $lock_status['locked_until'];
            $remaining_time = strtotime($locked_until) - time();
            $minutes = ceil($remaining_time / 60);
            $msg = "<div class='alert alert-danger py-2 small mb-3'>Account locked due to too many failed attempts. <span id='countdown'>Please try again in {$minutes} minute(s).</span></div>";
        } else {
            $remaining_attempts = 5 - $lock_status['attempts'];
            
            $stmt = $mysqli->prepare("
                SELECT u.user_id, u.password_hash, r.role_name 
                FROM users u 
                JOIN roles r ON u.role_id = r.role_id 
                WHERE u.username = ? AND u.is_active = 1
                LIMIT 1
            ");
            if ($stmt === false) {
                $msg = "<div class='alert alert-danger py-2 small mb-3'>Database error. Please try again later.</div>";
            } else {
                $stmt->bind_param("s", $username);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($row = $result->fetch_assoc()) {
                    if (password_verify($password, $row['password_hash'])) {
                        // Clear failed attempts on successful login
                        clearFailedAttempts($mysqli, $username, $client_ip);
                        session_regenerate_id(true);
                        $_SESSION['user_id'] = (int)$row['user_id'];
                        $_SESSION['role'] = $row['role_name'];
                        redirect_by_role($row['role_name']);
                    } else {
                        // Record failed attempt
                        recordFailedAttempt($mysqli, $username, $client_ip);
                        $remaining_attempts = max(0, $remaining_attempts - 1);
                        if ($remaining_attempts > 0) {
                            $msg = "<div class='alert alert-danger py-2 small mb-3'>Invalid username or password. {$remaining_attempts} attempt(s) remaining.</div>";
                        } else {
                            $msg = "<div class='alert alert-danger py-2 small mb-3'>Account locked due to too many failed attempts. Please try again in 5 minutes.</div>";
                        }
                    }
                } else {
                    // Record failed attempt for non-existent user
                    recordFailedAttempt($mysqli, $username, $client_ip);
                    $remaining_attempts = max(0, $remaining_attempts - 1);
                    if ($remaining_attempts > 0) {
                        $msg = "<div class='alert alert-danger py-2 small mb-3'>Invalid username or password. {$remaining_attempts} attempt(s) remaining.</div>";
                    } else {
                        $msg = "<div class='alert alert-danger py-2 small mb-3'>Account locked due to too many failed attempts. Please try again in 5 minutes.</div>";
                    }
                }
                $stmt->close();
            }
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
    <title>Staff Login | Barangay Health & Nutrition</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/jpeg" href="assets/img/sabang.jpg">
    <link rel="shortcut icon" type="image/jpeg" href="assets/img/sabang.jpg">
    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons (required for the input icons) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <!-- Project CSS (with cache-busting) -->
    <link rel="stylesheet" href="assets/css/style.css?v=20251005">
    <!-- Google reCAPTCHA -->
    <script src="https://www.google.com/recaptcha/api.js" async defer></script>
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
                <strong class="small">Welcome to Barangay Health &amp; Nutrition System</strong>
                <span class="xsmall text-white-50">Serving our community with care and compassion</span>
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
                        Barangay Health &amp; Nutrition<br class="d-none d-md-block">Management System
                    </h1>
                    <p class="small text-secondary mb-0">Please login to continue</p>
                </div>

                <?php echo $msg; ?>

                <form method="post" action="staff_login" novalidate <?php echo $is_locked ? 'onsubmit="return false;"' : ''; ?>>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Username</label>
                        <div class="input-group">
                            <span class="input-group-text bg-white">
                                <i class="bi bi-person text-secondary"></i>
                            </span>
                            <input type="text" name="username" class="form-control" maxlength="100" required 
                                   <?php echo $is_locked ? 'disabled' : 'autofocus'; ?> 
                                   placeholder="Enter your username"
                                   value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">
                        </div>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small fw-semibold">Password</label>
                        <div class="input-group">
                            <span class="input-group-text bg-white">
                                <i class="bi bi-lock text-secondary"></i>
                            </span>
                            <input type="password" name="password" class="form-control" required 
                                   <?php echo $is_locked ? 'disabled' : ''; ?> 
                                   placeholder="Enter your password">
                        </div>
                    </div>

                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

                    <div class="mb-3">
                        <div class="g-recaptcha" data-sitekey="<?php echo RECAPTCHA_SITE_KEY; ?>"></div>
                    </div>

                    <div class="d-grid mt-3">
                        <button class="btn btn-success btn-login fw-semibold" type="submit" 
                                <?php echo $is_locked ? 'disabled' : ''; ?>>
                            <?php echo $is_locked ? 'Account Locked' : 'Login'; ?>
                        </button>
                    </div>

                    <?php if (!$is_locked && $remaining_attempts < 5): ?>
                    <div class="alert alert-warning py-2 small mt-2 mb-0">
                        <i class="bi bi-exclamation-triangle me-1"></i>
                        <?php echo $remaining_attempts; ?> attempt(s) remaining before account lockout.
                    </div>
                    <?php endif; ?>

                    <div class="text-center mt-3">
<!-- In your login page -->
<a href="forgot_password" class="small d-inline-block mt-2">Forgot Password?</a>
                    </div>
                </form>
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
    
    <?php if ($is_locked && $locked_until): ?>
    <script>
        // Countdown timer for account lockout
        function updateCountdown() {
            const lockedUntil = new Date('<?php echo $locked_until; ?>');
            const now = new Date();
            const timeLeft = lockedUntil - now;
            
            if (timeLeft <= 0) {
                // Lockout expired, reload the page
                location.reload();
                return;
            }
            
            const minutes = Math.floor(timeLeft / 60000);
            const seconds = Math.floor((timeLeft % 60000) / 1000);
            
            // Update the message if there's a countdown element
            const countdownElement = document.getElementById('countdown');
            if (countdownElement) {
                countdownElement.textContent = `Please try again in ${minutes}:${seconds.toString().padStart(2, '0')}`;
            }
        }
        
        // Update countdown every second
        setInterval(updateCountdown, 1000);
        updateCountdown(); // Initial call
    </script>
    <?php endif; ?>
</body>
</html>