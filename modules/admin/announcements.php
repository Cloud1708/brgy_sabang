<?php
require_once __DIR__.'/../../inc/db.php';
require_once __DIR__.'/../../inc/auth.php';
require_role(['Admin']);

$events = $mysqli->query("
  SELECT event_id, event_title, event_type, is_published, event_date
  FROM events
  ORDER BY event_date DESC
  LIMIT 100
");
?>
<div class="card shadow-sm border-0">
  <div class="card-body">
    <h5 class="mb-3">System-wide Announcements (Events)</h5>
    <p class="small text-secondary">CRUD for events (publishing). Add create/edit form below (placeholder only).</p>
    <div class="table-responsive mb-3">
      <table class="table table-sm table-bordered align-middle small mb-0">
        <thead class="table-light">
          <tr>
            <th style="width:70px;">ID</th>
            <th>Title</th>
            <th style="width:120px;">Type</th>
            <th style="width:100px;">Date</th>
            <th style="width:90px;">Published</th>
            <th style="width:120px;">Action</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($events && $events->num_rows): while($ev=$events->fetch_assoc()): ?>
            <tr>
              <td><?php echo (int)$ev['event_id']; ?></td>
              <td><?php echo htmlspecialchars($ev['event_title']); ?></td>
              <?php
                $evType = (string)($ev['event_type'] ?? '');
                $evTypeClass = match ($evType) {
                  'health' => 'primary',
                  'nutrition' => 'success',
                  'vaccination' => 'warning',
                  'feeding' => 'info',
                  default => 'secondary',
                };
              ?>
              <td>
                <span class="badge bg-<?php echo $evTypeClass; ?>"><?php echo htmlspecialchars($evType); ?></span>
              </td>
              <td><?php echo htmlspecialchars($ev['event_date']); ?></td>
              <td><?php echo $ev['is_published'] ? '<span class="badge bg-success">Yes</span>' : '<span class="badge bg-secondary">No</span>'; ?></td>
              <td>
                <button class="btn btn-sm btn-outline-primary disabled">Edit</button>
                <button class="btn btn-sm btn-outline-secondary disabled">Toggle</button>
              </td>
            </tr>
          <?php endwhile; else: ?>
            <tr><td colspan="6" class="text-center py-4">No events yet.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
    <div class="border rounded p-3 bg-white small">
      <strong>Create / Edit Form Placeholder</strong>
      <p class="mb-0">Add actual event form & handlers (actions/events_create.php, etc.) later.</p>
    </div>
  </div>
</div>