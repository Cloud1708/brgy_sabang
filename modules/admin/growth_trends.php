<?php
require_once __DIR__.'/../../inc/db.php';
require_once __DIR__.'/../../inc/auth.php';
require_role(['Admin']);
$recent = $mysqli->query("
  SELECT c.full_name, nr.weight_kg, nr.length_height_cm, nr.weighing_date
  FROM nutrition_records nr
  JOIN children c ON c.child_id = nr.child_id
  ORDER BY nr.weighing_date DESC
  LIMIT 15
");
?>
<div class="card shadow-sm border-0">
  <div class="card-body">
    <h5 class="mb-3">Child Growth Trends (Recent Entries)</h5>
    <div class="table-responsive">
      <table class="table table-sm table-striped align-middle mb-0 small">
        <thead class="table-light">
          <tr>
            <th>Child</th>
            <th>Date</th>
            <th>Weight (kg)</th>
            <th>Length/Height (cm)</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($recent && $recent->num_rows): while($r=$recent->fetch_assoc()): ?>
            <tr>
              <td><?php echo htmlspecialchars($r['full_name']); ?></td>
              <td><?php echo htmlspecialchars($r['weighing_date']); ?></td>
              <td><?php echo htmlspecialchars($r['weight_kg']); ?></td>
              <td><?php echo htmlspecialchars($r['length_height_cm']); ?></td>
            </tr>
          <?php endwhile; else: ?>
            <tr><td colspan="4" class="text-center py-4">No records.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
    <p class="small text-muted mt-3 mb-0">Add charts (line graph) later via a JS chart library.</p>
  </div>
</div>