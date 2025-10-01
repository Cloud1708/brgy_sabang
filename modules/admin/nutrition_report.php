<?php
require_once __DIR__.'/../../inc/db.php';
require_once __DIR__.'/../../inc/auth.php';
require_role(['Admin']);
/*
  Simple aggregates as placeholder (expand as needed)
*/
$totChildren = $mysqli->query("SELECT COUNT(*) FROM children")->fetch_row()[0] ?? 0;
$records = $mysqli->query("
  SELECT w.status_category, COUNT(*) cnt
  FROM nutrition_records nr
  LEFT JOIN wfl_ht_status_types w ON nr.wfl_ht_status_id = w.status_id
  GROUP BY w.status_category
")->fetch_all(MYSQLI_ASSOC);
?>
<div class="card shadow-sm border-0">
  <div class="card-body">
    <h5 class="mb-3">Nutrition Status Overview</h5>
    <p class="small text-secondary mb-3">Distribution based on latest recorded statuses.</p>
    <div class="row g-3">
      <div class="col-md-4">
        <div class="p-3 rounded border bg-white small">
          <div class="text-uppercase fw-semibold mb-1" style="font-size:.65rem;opacity:.65;">Children</div>
          <div class="fs-4 fw-semibold"><?php echo (int)$totChildren; ?></div>
        </div>
      </div>
      <div class="col-md-8">
        <table class="table table-sm table-bordered mb-0 small">
          <thead class="table-light">
            <tr><th>Status Category</th><th>Count</th></tr>
          </thead>
          <tbody>
            <?php if ($records): foreach ($records as $row): ?>
            <tr>
              <td><?php echo htmlspecialchars($row['status_category'] ?? '—'); ?></td>
              <td><?php echo (int)$row['cnt']; ?></td>
            </tr>
            <?php endforeach; else: ?>
            <tr><td colspan="2" class="text-center py-3">No nutrition records yet.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>