<?php
require_once __DIR__.'/../../inc/db.php';
require_once __DIR__.'/../../inc/auth.php';
require_role(['Admin']);
if (session_status() === PHP_SESSION_NONE) session_start();
$csrf = $_SESSION['csrf_token'] ?? '';
?>
<div class="card border-0 shadow-sm">
  <div class="card-body">
    <h5 class="mb-3">Create BNS Account</h5>
    <div id="createStaffAlert" class="alert d-none small"></div>
    <form id="staffCreateForm" class="needs-validation" novalidate>
      <input type="hidden" name="role" value="BNS">
      <div class="row g-3">
        <div class="col-md-4">
          <label class="form-label small fw-semibold">First Name</label>
          <input type="text" name="first_name" class="form-control form-control-sm" required>
        </div>
        <div class="col-md-4">
          <label class="form-label small fw-semibold">Last Name</label>
          <input type="text" name="last_name" class="form-control form-control-sm" required>
        </div>
        <div class="col-md-4">
          <label class="form-label small fw-semibold">Barangay</label>
          <input type="text" name="barangay" class="form-control form-control-sm" value="Sabang" readonly required>
        </div>
        <div class="col-md-4">
          <label class="form-label small fw-semibold">Username</label>
          <input type="text" name="username" class="form-control form-control-sm" required pattern="[A-Za-z0-9_]{4,}">
        </div>
        <div class="col-md-4">
          <label class="form-label small fw-semibold">Email (optional)</label>
          <input type="email" name="email" class="form-control form-control-sm">
        </div>
        <div class="col-md-4">
          <label class="form-label small fw-semibold">Password</label>
          <input type="text" name="password" class="form-control form-control-sm" value="" placeholder="Auto-generate if blank">
        </div>
        <div class="col-12">
          <button class="btn btn-warning btn-sm" type="submit">Create Account</button>
        </div>
      </div>
    </form>
    <p class="small text-muted mt-3 mb-0">Stored procedure: CreateStaffAccount</p>
  </div>
</div>