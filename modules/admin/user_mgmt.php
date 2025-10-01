<?php
require_once __DIR__.'/../../inc/db.php';
require_once __DIR__.'/../../inc/auth.php';
require_role(['Admin']);
if (session_status() === PHP_SESSION_NONE) session_start();

$q = "
SELECT u.user_id, u.username, u.email, u.first_name, u.last_name,
       r.role_name, u.barangay, u.is_active, u.created_at
FROM users u
JOIN roles r ON r.role_id = u.role_id
WHERE r.role_name IN ('BHW','BNS','Parent')
ORDER BY r.role_name, u.created_at DESC
LIMIT 500
";
$res = $mysqli->query($q);
?>
<div class="card border-0 shadow-sm">
  <div class="card-body">
    <h5 class="mb-3">User Management</h5>
    <div class="table-responsive">
      <table class="table table-sm align-middle table-bordered mb-0 bg-white">
        <thead class="table-light">
          <tr>
            <th style="width:60px;">ID</th>
            <th>Username</th>
            <th>Name</th>
            <th>Role</th>
            <th>Barangay</th>
            <th>Email</th>
            <th>Active</th>
            <th style="width:110px;">Action</th>
          </tr>
        </thead>
        <tbody class="small">
          <?php if ($res && $res->num_rows): while($row=$res->fetch_assoc()): ?>
            <tr>
              <td><?php echo (int)$row['user_id']; ?></td>
              <td><?php echo htmlspecialchars($row['username']); ?></td>
              <td><?php echo htmlspecialchars($row['first_name'].' '.$row['last_name']); ?></td>
              <?php
                // Determine bootstrap color for role in a simple, parse-safe way
                $role = (string)($row['role_name'] ?? '');
                $roleClass = match ($role) {
                  'BHW' => 'primary',
                  'BNS' => 'warning',
                  'Parent' => 'secondary',
                  default => 'secondary',
                };
              ?>
              <td>
                <span class="badge bg-<?php echo $roleClass; ?>">
                  <?php echo htmlspecialchars($role); ?>
                </span>
              </td>
              <td><?php echo htmlspecialchars($row['barangay'] ?? ''); ?></td>
              <td><?php echo htmlspecialchars($row['email'] ?? ''); ?></td>
              <td>
                <?php if ($row['is_active']): ?>
                  <span class="badge bg-success">Yes</span>
                <?php else: ?>
                  <span class="badge bg-danger">No</span>
                <?php endif; ?>
              </td>
              <td>
                <button class="btn btn-sm btn-outline-secondary btn-toggle-active"
                        data-id="<?php echo (int)$row['user_id']; ?>">
                  <?php echo $row['is_active'] ? 'Deactivate' : 'Activate'; ?>
                </button>
              </td>
            </tr>
          <?php endwhile; else: ?>
            <tr><td colspan="8" class="text-center py-4">No users found.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
    <p class="small text-muted mt-2 mb-0">Shows BHW, BNS, Parent accounts only.</p>
  </div>
</div>