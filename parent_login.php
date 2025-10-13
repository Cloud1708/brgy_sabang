<?php
require_once __DIR__ . '/inc/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// CSRF token specific for parent login
if (empty($_SESSION['parent_csrf'])) {
    $_SESSION['parent_csrf'] = bin2hex(random_bytes(16));
}

$err = $_GET['error'] ?? '';
$msg = '';
if ($err === '1') {
    $msg = "<div class='alert alert-danger py-2 small mb-3'>Invalid username or password.</div>";
} elseif ($err === 'csrf') {
    $msg = "<div class='alert alert-danger py-2 small mb-3'>Invalid request. Please try again.</div>";
} elseif ($err === 'db') {
    $msg = "<div class='alert alert-danger py-2 small mb-3'>Database error. Please try again later.</div>";
}
?>
<!doctype html>
<html lang="en" data-bs-theme="light">
<head>
  <meta charset="utf-8">
  <title>Parent Login | Barangay Health & Nutrition</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
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
        <strong class="small">Parent / Guardian Portal</strong>
        <span class="xsmall text-white-50">Access your child’s immunization & growth records</span>
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
          <h1 class="h6 mb-1 fw-semibold text-primary-emphasis">Barangay Health &amp; Nutrition<br class="d-none d-md-block">Parent / Guardian Portal</h1>
          <p class="small text-secondary mb-0">Please login to continue</p>
        </div>

        <?php echo $msg; ?>

        <form method="post" action="auth/login_parent_process.php" novalidate>
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

          <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['parent_csrf']); ?>">

          <div class="d-grid mt-3">
            <button class="btn btn-success btn-login fw-semibold" type="submit">Login</button>
          </div>

          <div class="text-center mt-3">
            <a href="index.php#contact" class="small d-inline-block mt-2">Need help? Contact us</a>
          </div>
        </form>
      </div>
    </div>
  </main>

  <footer class="auth-footer text-center text-white-50 small">
    <div class="container">
      <div>&copy; <?php echo date('Y'); ?> Barangay Health &amp; Nutrition</div>
      <div class="xsmall">For parents and guardians</div>
    </div>
  </footer>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
