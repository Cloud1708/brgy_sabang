<?php
require_once __DIR__.'/../../inc/db.php';
require_once __DIR__.'/../../inc/auth.php';
require_role(['Admin']);
$summary = $mysqli->query("
  SELECT COUNT(*) total_records,
         SUM(vaginal_bleeding) vaginal_bleeding,
         SUM(urinary_infection) urinary_infection,
         SUM(high_blood_pressure) high_bp,
         SUM(fever_38_celsius) fever_cases
  FROM health_records
")->fetch_assoc();
?>
<div class="card shadow-sm border-0">
  <div class="card-body">
    <h5 class="mb-3">Maternal Health Statistics</h5>
    <div class="row g-3 small">
      <div class="col-sm-6 col-lg-3">
        <div class="p-3 border rounded bg-white">
          <div class="text-uppercase small mb-1" style="opacity:.65;">Total Records</div>
          <div class="fs-5 fw-semibold"><?php echo (int)($summary['total_records'] ?? 0); ?></div>
        </div>
      </div>
      <div class="col-sm-6 col-lg-3">
        <div class="p-3 border rounded bg-white">
          <div class="text-uppercase small mb-1" style="opacity:.65;">Vaginal Bleeding</div>
          <div class="fs-5 fw-semibold text-danger"><?php echo (int)($summary['vaginal_bleeding'] ?? 0); ?></div>
        </div>
      </div>
      <div class="col-sm-6 col-lg-3">
        <div class="p-3 border rounded bg-white">
          <div class="text-uppercase small mb-1" style="opacity:.65;">Urinary Infection</div>
          <div class="fs-5 fw-semibold text-warning"><?php echo (int)($summary['urinary_infection'] ?? 0); ?></div>
        </div>
      </div>
      <div class="col-sm-6 col-lg-3">
        <div class="p-3 border rounded bg-white">
          <div class="text-uppercase small mb-1" style="opacity:.65;">High BP</div>
          <div class="fs-5 fw-semibold text-danger"><?php echo (int)($summary['high_bp'] ?? 0); ?></div>
        </div>
      </div>
    </div>
    <p class="small text-muted mb-0 mt-3">Counts are raw occurrences (sum of boolean fields) across all prenatal records.</p>
  </div>
</div>