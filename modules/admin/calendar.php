<?php
require_once __DIR__.'/../../inc/db.php';
require_once __DIR__.'/../../inc/auth.php';
require_role(['Admin']);
$events = $mysqli->query("
  SELECT event_id, event_title, event_type, event_date, location
  FROM events
  WHERE event_date BETWEEN (CURDATE() - INTERVAL 15 DAY) AND (CURDATE() + INTERVAL 60 DAY)
  ORDER BY event_date ASC
  LIMIT 120
");
?>
<div class="card shadow-sm border-0">
  <div class="card-body">
    <h5 class="mb-3">Community Calendar (Compact List)</h5>
    <div class="table-responsive">
      <table class="table table-sm table-striped align-middle small mb-0">
        <thead class="table-light">
          <tr>
            <th style="width:90px;">Date</th>
            <th>Title</th>
            <th style="width:110px;">Type</th>
            <th>Location</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($events && $events->num_rows): while($e=$events->fetch_assoc()): ?>
            <tr>
              <td><?php echo htmlspecialchars($e['event_date']); ?></td>
              <td><?php echo htmlspecialchars($e['event_title']); ?></td>
              <?php
                $etype = (string)($e['event_type'] ?? '');
                $etypeClass = match ($etype) {
                  'health' => 'primary',
                  'nutrition' => 'success',
                  'vaccination' => 'warning',
                  'feeding' => 'info',
                  default => 'secondary',
                };
              ?>
              <td>
                <span class="badge bg-<?php echo $etypeClass; ?>"><?php echo htmlspecialchars($etype); ?></span>
              </td>
              <td><?php echo htmlspecialchars($e['location'] ?? ''); ?></td>
            </tr>
          <?php endwhile; else: ?>
            <tr><td colspan="4" class="text-center py-4">No events in range.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
    <p class="small text-muted mt-3 mb-0">Add full calendar UI plugin in future (e.g., FullCalendar).</p>
  </div>
</div>