<?php
require_once __DIR__.'/../../inc/db.php';
require_once __DIR__.'/../../inc/auth.php';
require_role(['Admin']);
$agg = $mysqli->query("
  SELECT supplement_type, COUNT(*) cnt
  FROM supplementation_records
  GROUP BY supplement_type
  ORDER BY cnt DESC
")->fetch_all(MYSQLI_ASSOC);
?>
<div class="card shadow-sm border-0">
  <div class="card-body">
    <h5 class="mb-3">Supplementation Compliance (Counts)</h5>
    <div class="row g-3">
      <div class="col-12">
        <table class="table table-sm table-bordered small mb-0">
          <thead class="table-light">
            <tr><th>Supplement Type</th><th>Occurrences</th></tr>
          </thead>
          <tbody>
            <?php if ($agg): foreach($agg as $a): ?>
              <tr>
                <td><?php echo htmlspecialchars($a['supplement_type']); ?></td>
                <td><?php echo (int)$a['cnt']; ?></td>
              </tr>
            <?php endforeach; else: ?>
              <tr><td colspan="2" class="text-center py-4">No supplementation records.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
    <p class="small text-muted mt-3 mb-0">Add compliance rate logic (e.g., expected vs actual) later.</p>
  </div>
</div>