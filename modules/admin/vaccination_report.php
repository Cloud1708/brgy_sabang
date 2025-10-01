<?php
require_once __DIR__.'/../../inc/db.php';
require_once __DIR__.'/../../inc/auth.php';
require_role(['Admin']);
$agg = $mysqli->query("
  SELECT AVG(completion_percentage) avg_completion,
         SUM(overdue_doses) total_overdue,
         SUM(upcoming_doses) total_upcoming,
         COUNT(DISTINCT child_id) children_tracked
  FROM parent_child_vaccination_summary
")->fetch_assoc();
?>
<div class="card shadow-sm border-0">
  <div class="card-body">
    <h5 class="mb-3">Vaccination Coverage Report</h5>
    <div class="row g-3">
      <div class="col-md-3">
        <div class="p-3 border rounded small bg-white">
          <div class="text-uppercase fw-semibold mb-1" style="font-size:.65rem;opacity:.65;">Children Tracked</div>
          <div class="fs-4 fw-semibold"><?php echo (int)($agg['children_tracked'] ?? 0); ?></div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="p-3 border rounded small bg-white">
          <div class="text-uppercase fw-semibold mb-1" style="font-size:.65rem;opacity:.65;">Avg Completion %</div>
          <div class="fs-4 fw-semibold"><?php echo number_format((float)($agg['avg_completion'] ?? 0),1); ?>%</div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="p-3 border rounded small bg-white">
          <div class="text-uppercase fw-semibold mb-1" style="font-size:.65rem;opacity:.65;">Overdue Doses</div>
          <div class="fs-4 fw-semibold text-danger"><?php echo (int)($agg['total_overdue'] ?? 0); ?></div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="p-3 border rounded small bg-white">
          <div class="text-uppercase fw-semibold mb-1" style="font-size:.65rem;opacity:.65;">Upcoming (30d)</div>
          <div class="fs-4 fw-semibold text-warning"><?php echo (int)($agg['total_upcoming'] ?? 0); ?></div>
        </div>
      </div>
    </div>
    <p class="small text-muted mt-3 mb-0">Source: parent_child_vaccination_summary view.</p>
  </div>
</div>