<?php
require_once __DIR__ . '/inc/db.php';
require_once __DIR__ . '/inc/auth.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $msg = "<div class='alert alert-danger py-2 small mb-3'>Invalid request. Please try again.</div>";
    } else {
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';

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
                    session_regenerate_id(true);
                    $_SESSION['user_id'] = (int)$row['user_id'];
                    $_SESSION['role'] = $row['role_name'];
                    redirect_by_role($row['role_name']);
                } else {
                    $msg = "<div class='alert alert-danger py-2 small mb-3'>Invalid username or password.</div>";
                }
            } else {
                $msg = "<div class='alert alert-danger py-2 small mb-3'>Invalid username or password.</div>";
            }
            $stmt->close();
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
    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons (required for the input icons) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <!-- Project CSS (with cache-busting) -->
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

                <form method="post" action="staff_login.php" novalidate>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Username</label>
                        <div class="input-group">
                            <span class="input-group-text bg-white">
                                <i class="bi bi-person text-secondary"></i>
                            </span>
                            <input type="text" name="username" class="form-control" maxlength="100" required autofocus placeholder="Enter your username">
                        </div>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small fw-semibold">Password</label>
                        <div class="input-group">
                            <span class="input-group-text bg-white">
                                <i class="bi bi-lock text-secondary"></i>
                            </span>
                            <input type="password" name="password" class="form-control" required placeholder="Enter your password">
                        </div>
                    </div>

                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

                    <div class="d-grid mt-3">
                        <button class="btn btn-success btn-login fw-semibold" type="submit">Login</button>
                    </div>

                    <div class="text-center mt-3">
<!-- In your login page -->
<a href="forgot_password.php" class="small d-inline-block mt-2">Forgot Password?</a>
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
</body>
</html>