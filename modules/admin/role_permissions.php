<?php
require_once __DIR__.'/../../inc/db.php';
require_once __DIR__.'/../../inc/auth.php';
require_role(['Admin']);
if (session_status() === PHP_SESSION_NONE) session_start();

$res = $mysqli->query("SELECT role_id, role_name, role_description, created_at FROM roles ORDER BY role_id ASC");
?>
<div class="card border-0 shadow-sm">
  <div class="card-body">
    <h5 class="mb-3">Role Permissions</h5>
    <p class="small text-secondary">Simple role description editor. (Granular permission tables can be added later.)</p>
    <div class="table-responsive">
      <table class="table table-sm table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th style="width:60px;">ID</th>
            <th style="width:140px;">Role</th>
            <th>Description</th>
            <th style="width:160px;">Created</th>
            <th style="width:160px;">Action</th>
          </tr>
        </thead>
        <tbody class="small">
          <?php if ($res && $res->num_rows): while($r=$res->fetch_assoc()): ?>
          <tr data-id="<?php echo (int)$r['role_id']; ?>" class="role-row">
            <td><?php echo (int)$r['role_id']; ?></td>
            <td><span class="fw-semibold"><?php echo htmlspecialchars($r['role_name']); ?></span></td>
            <td>
              <div class="role-desc-display"><?php echo htmlspecialchars($r['role_description'] ?? ''); ?></div>
              <div class="role-desc-edit d-none">
                <textarea class="form-control form-control-sm role-desc-input" rows="2"><?php echo htmlspecialchars($r['role_description'] ?? ''); ?></textarea>
              </div>
            </td>
            <td><?php echo htmlspecialchars($r['created_at']); ?></td>
            <td>
              <div class="btn-group btn-group-sm role-desc-display">
                <button class="btn btn-outline-secondary btn-edit-role">Edit</button>
              </div>
              <div class="btn-group btn-group-sm role-desc-edit d-none">
                <button class="btn btn-primary btn-save-role">Save</button>
                <button class="btn btn-outline-secondary btn-cancel-role">Cancel</button>
              </div>
            </td>
          </tr>
          <?php endwhile; else: ?>
          <tr><td colspan="5" class="text-center py-4">No roles found.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>