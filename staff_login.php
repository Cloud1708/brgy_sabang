<?php
require_once __DIR__.'/inc/db.php';
require_once __DIR__.'/inc/auth.php';

if (isset($_SESSION['user_id'], $_SESSION['role'])) {
    redirect_by_role($_SESSION['role']);
}
?>
<!doctype html>
<html lang="en" data-bs-theme="light">
<head>
<meta charset="utf-8">
<title>Staff Login | Barangay Sabang Health</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
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
          <?php if (!empty($_GET['error'])): ?>
            <div class="alert alert-danger py-2 small">Invalid login credentials.</div>
          <?php elseif (!empty($_GET['unauthorized'])): ?>
            <div class="alert alert-warning py-2 small">Please login first.</div>
          <?php endif; ?>
          <form method="post" action="auth/login_process.php" novalidate>
            <div class="mb-3">
              <label class="form-label small fw-semibold">Username</label>
              <input type="text" name="username" class="form-control" maxlength="100" required autofocus>
            </div>
            <div class="mb-3">
              <label class="form-label small fw-semibold">Password</label>
              <input type="password" name="password" class="form-control" required>
            </div>
            <!-- Optional: simple CSRF token -->
            <?php
              if (empty($_SESSION['csrf_token'])) {
                  $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
              }
            ?>
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
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