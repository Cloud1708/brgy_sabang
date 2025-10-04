<?php
require_once __DIR__ . '/inc/db.php';
require_once __DIR__ . '/inc/auth.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Initialize CSRF token if not set
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $msg = "<div class='alert alert-danger py-2 small'>Invalid request. Please try again.</div>";
    } else {
        $username = $_POST['username'];
        $password = $_POST['password'];

        // Use prepared statement to prevent SQL injection
        $stmt = $mysqli->prepare("
            SELECT u.user_id, u.password_hash, r.role_name 
            FROM users u 
            JOIN roles r ON u.role_id = r.role_id 
            WHERE u.username = ? AND u.is_active = 1
        ");
        if ($stmt === false) {
            $msg = "<div class='alert alert-danger py-2 small'>Database error. Please try again later.</div>";
        } else {
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($row = $result->fetch_assoc()) {
                if (password_verify($password, $row['password_hash'])) {
                    // Regenerate session ID to prevent session fixation
                    session_regenerate_id(true);
                    $_SESSION['user_id'] = $row['user_id'];
                    $_SESSION['role'] = $row['role_name']; // Use only 'role' for consistency
                    redirect_by_role($row['role_name']);
                } else {
                    $msg = "<div class='alert alert-danger py-2 small'>Invalid username or password.</div>";
                }
            } else {
                $msg = "<div class='alert alert-danger py-2 small'>Invalid username or password.</div>";
            }
            $stmt->close();
        }
    }
    // Regenerate CSRF token for next request
    $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}
?>
<!doctype html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <title>Staff Login | Barangay Sabang Health</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="auth-body">
<div class="auth-wrapper container py-5">
    <div class="row justify-content-center">
        <div class="col-md-7 col-lg-5">
            <div class="card shadow-lg border-0 auth-card">
                <div class="card-body p-4 p-md-5">
                    <div class="text-center mb-4">
                        <h1 class="h4 mb-1 fw-bold">Staff Access</h1>
                        <p class="small text-secondary mb-0">Admin / BHW / BNS</p>
                    </div>
                    <?php echo $msg; ?>
                    <form method="post" action="staff_login.php" novalidate>
                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Username</label>
                            <input type="text" name="username" class="form-control" maxlength="100" required autofocus>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Password</label>
                            <input type="password" name="password" class="form-control" required>
                        </div>
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                        <div class="d-grid mt-4">
                            <button class="btn btn-primary py-2 fw-semibold" type="submit">Login</button>
                        </div>
                        <p class="small text-center text-muted mt-3 mb-0">
                            For authorized staff only. Activity may be logged.
                        </p>
                    </form>
                    <div class="text-center mt-4">
                        <a href="index.php" class="small text-decoration-none">&larr; Back to Portal</a>
                    </div>
                </div>
            </div>
            <p class="small text-center text-muted mt-3 mb-0">&copy; <?php echo date('Y'); ?> Barangay Sabang</p>
        </div>
    </div>
</div>
</body>
</html>