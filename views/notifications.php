<?php
if (session_status()===PHP_SESSION_NONE) session_start();
require_once __DIR__.'/../inc/db.php';

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$uid = (int)($_SESSION['user_id'] ?? 0);
$children = [];
if ($uid>0) {
    $st=$mysqli->prepare("SELECT c.child_id,c.full_name,c.birth_date, TIMESTAMPDIFF(MONTH,c.birth_date,CURDATE()) age_months FROM parent_child_access p JOIN children c ON c.child_id=p.child_id WHERE p.parent_user_id=? AND p.is_active=1 ORDER BY c.full_name ASC");
    if($st){ $st->bind_param('i',$uid); $st->execute(); $r=$st->get_result(); while($x=$r->fetch_assoc()) $children[]=$x; $st->close(); }
}

// Selected child for per-child reminders
$selected_child_id = 0; $selected_child = null;
if ($children) {
    $req = isset($_GET['child_id']) ? (int)$_GET['child_id'] : 0;
    foreach ($children as $c) { if ($req>0 && (int)$c['child_id']===$req) { $selected_child_id=$req; $selected_child=$c; break; } }
    if ($selected_child_id===0) { $selected_child_id = (int)$children[0]['child_id']; $selected_child = $children[0]; }
}

// Load parent notifications (generic announcements/messages)
$notifications=[];
if ($uid>0) {
    $res=$mysqli->query("SELECT notification_id,notification_type,title,message,due_date,read_at,created_at,child_id FROM parent_notifications WHERE parent_user_id={$uid} ORDER BY created_at DESC LIMIT 200");
    while($row=$res->fetch_assoc()) $notifications[]=$row;
}

// Community health announcements from events table (published & upcoming)
$announcements=[];
if ($stmt = $mysqli->prepare("SELECT event_id, event_title, event_description, event_type, event_date, event_time, location FROM events WHERE is_published=1 AND (event_date IS NULL OR event_date >= CURDATE()) ORDER BY event_date ASC, event_time ASC LIMIT 100")) {
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) { $announcements[] = $row; }
    $stmt->close();
}

// Vaccine reminders based on next_dose_due_date
$vaccine_reminders=[];
if ($selected_child_id>0) {
    $sql="SELECT vt.vaccine_name,vt.vaccine_code,ci.dose_number,ci.next_dose_due_date FROM child_immunizations ci JOIN vaccine_types vt ON vt.vaccine_id=ci.vaccine_id WHERE ci.child_id=? AND ci.next_dose_due_date IS NOT NULL ORDER BY ci.next_dose_due_date ASC LIMIT 50";
    if ($s=$mysqli->prepare($sql)) { $s->bind_param('i',$selected_child_id); $s->execute(); $r=$s->get_result(); while($x=$r->fetch_assoc()) $vaccine_reminders[]=$x; $s->close(); }
}

// Helper: priority
function priority_from_date(?string $date): string {
    if (!$date) return 'low';
    $today = strtotime(date('Y-m-d'));
    $d = strtotime($date);
    if ($d === false) return 'low';
    if ($d < $today) return 'overdue';
    $days = ($d - $today)/86400;
    if ($days <= 7) return 'high';
    if ($days <= 14) return 'medium';
    return 'low';
}
?>

<div class="space-y-6">
    <!-- Page Header & child filter -->
    <div class="flex flex-col md:flex-row md:items-end md:justify-between gap-3">
        <div>
            <h2 class="text-xl font-medium">Notification Center</h2>
            <p class="text-sm" style="color: #6b7280;">Stay updated on your children's health appointments and reminders</p>
        </div>
        <?php if (!empty($children)): ?>
        <form method="get" class="flex items-center gap-2">
            <input type="hidden" name="view" value="notifications" />
            <label for="child_id" class="text-sm" style="color:#6b7280;">For Child:</label>
            <select id="child_id" name="child_id" class="border rounded-lg px-3 py-2 text-sm">
                <?php foreach ($children as $c): ?>
                    <option value="<?php echo (int)$c['child_id']; ?>" <?php echo ((int)$c['child_id']===$selected_child_id)?'selected':''; ?>>
                        <?php echo h($c['full_name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="px-3 py-2 rounded-lg text-white text-sm" style="background-color:#3b82f6;">Apply</button>
        </form>
        <?php endif; ?>
    </div>

    <!-- System Announcements Banner -->
    <div class="bg-white rounded-xl shadow-sm" style="border: 1px solid rgba(59, 130, 246, 0.5); background-color: rgba(59, 130, 246, 0.05);">
        <div class="p-6 border-b" style="border-color: #e5e7eb;">
            <div class="flex items-center gap-3">
                <i data-lucide="megaphone" class="w-6 h-6" style="color: #3b82f6;"></i>
                <div>
                    <h3 class="font-medium">Community Health Announcements</h3>
                    <p class="text-sm" style="color: #6b7280;">Important updates from your health center</p>
                </div>
            </div>
        </div>
        <div class="p-6">
            <div class="space-y-3">
                <?php if (empty($announcements)): ?>
                    <div class="text-sm" style="color:#6b7280;">No announcements yet.</div>
                <?php else: ?>
                    <?php foreach ($announcements as $ev): ?>
                        <div class="p-4 bg-white rounded-lg border" style="border-color: #e5e7eb;">
                            <div class="flex items-start justify-between mb-2">
                                <h4 class="font-medium"><?php echo h($ev['event_title'] ?? ''); ?></h4>
                                <span class="px-3 py-1 rounded-full text-sm" style="border: 1px solid #e5e7eb;">
                                    <?php echo h(ucfirst((string)($ev['event_type'] ?? 'announcement'))); ?>
                                </span>
                            </div>
                            <?php if (!empty($ev['location'])): ?>
                                <p class="text-xs mb-1" style="color:#6b7280;">Location: <?php echo h($ev['location']); ?></p>
                            <?php endif; ?>
                            <p class="text-sm mb-2" style="color: #6b7280;"><?php echo nl2br(h($ev['event_description'] ?? '')); ?></p>
                            <p class="text-sm" style="color: #6b7280;">
                                <?php if (!empty($ev['event_date'])): ?>
                                    Date: <?php echo h(date('M d, Y', strtotime($ev['event_date']))); ?>
                                <?php endif; ?>
                                <?php if (!empty($ev['event_time'])): ?>
                                    • Time: <?php echo h(date('h:i A', strtotime($ev['event_time']))); ?>
                                <?php endif; ?>
                            </p>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Vaccine Reminders -->
    <div class="bg-white rounded-xl shadow-sm p-6" style="border: 1px solid #e5e7eb;">
        <div class="flex items-center justify-between mb-6">
            <div class="flex items-center gap-3">
                <i data-lucide="bell" class="w-6 h-6" style="color: #ef4444;"></i>
                <div>
                    <h3 class="font-medium">Vaccine Reminders</h3>
                    <p class="text-sm" style="color: #6b7280;">Upcoming immunization appointments</p>
                </div>
            </div>
            <span class="px-3 py-1 rounded-full text-white text-sm font-medium" style="background-color: #ef4444;">
                <?php echo count($vaccine_reminders); ?> Due
            </span>
        </div>
        <div class="space-y-3">
            <?php foreach ($vaccine_reminders as $rem): ?>
                <?php
                    $priority = priority_from_date($rem['next_dose_due_date'] ?? null);
                    $bg_color = $priority === 'high' ? 'rgba(239, 68, 68, 0.1)' : ($priority==='medium' ? 'rgba(245, 158, 11, 0.1)' : 'rgba(229,231,235,0.5)');
                    $border_color = $priority === 'high' ? '#ef4444' : ($priority==='medium' ? '#f59e0b' : '#6b7280');
                ?>
                <div class="p-4 rounded-lg border-l-4" style="background-color: <?php echo $bg_color; ?>; border-left-color: <?php echo $border_color; ?>;">
                    <div class="flex items-start justify-between mb-3">
                        <div>
                            <p class="font-medium mb-1"><?php echo h(($rem['vaccine_name'] ?? $rem['vaccine_code']).' Dose '.$rem['dose_number']); ?></p>
                            <p class="text-sm" style="color: #6b7280;"><?php echo h($selected_child['full_name'] ?? ''); ?></p>
                        </div>
                        <span class="px-3 py-1 rounded-full text-white text-sm font-medium" style="background-color: <?php echo $border_color; ?>;">
                            <?php echo $priority === 'overdue' ? 'Overdue' : ($priority==='high' ? 'Urgent' : 'Soon'); ?>
                        </span>
                    </div>
                    <div class="grid grid-cols-2 gap-2 mb-3">
                        <div class="flex items-center gap-2">
                            <i data-lucide="calendar" class="w-4 h-4"></i>
                            <span><?php echo h(date('M d, Y', strtotime($rem['next_dose_due_date']))); ?></span>
                        </div>
                    </div>
                    <div class="flex gap-2">
                        <button class="px-3 py-1 rounded-lg text-white text-sm flex items-center gap-2 btn-confirm" data-child="<?php echo (int)$selected_child_id; ?>" style="background-color: #10b981;">
                            <i data-lucide="check" class="w-4 h-4"></i>
                            Confirm
                        </button>
                        <button class="px-3 py-1 rounded-lg border text-sm btn-resched" data-child="<?php echo (int)$selected_child_id; ?>" style="border-color: #e5e7eb;">
                            Reschedule
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
            <?php if (empty($vaccine_reminders)): ?>
                <div class="p-4 rounded-lg" style="background-color:#f9fafb; border:1px dashed #e5e7eb;">
                    <p class="text-sm" style="color:#6b7280;">No upcoming vaccine reminders for this child.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Health Appointments (placeholder) -->
    <div class="bg-white rounded-xl shadow-sm p-6" style="border: 1px solid #e5e7eb;">
        <div class="flex items-center gap-3 mb-6">
            <i data-lucide="calendar" class="w-6 h-6" style="color: #3b82f6;"></i>
            <div>
                <h3 class="font-medium">Health Appointment Notifications</h3>
                <p class="text-sm" style="color: #6b7280;">Scheduled consultation reminders</p>
            </div>
        </div>
        <div class="space-y-3">
            <div class="p-4 rounded-lg border" style="background-color: rgba(59, 130, 246, 0.05); border-color: #e5e7eb;">
                <p class="text-sm" style="color:#6b7280;">More appointment integrations coming soon.</p>
            </div>
        </div>
    </div>

    <!-- All Notifications -->
    <div class="bg-white rounded-xl shadow-sm p-6" style="border: 1px solid #e5e7eb;">
        <div class="flex items-center justify-between mb-6">
            <div>
                <h3 class="font-medium">All Notifications</h3>
                <p class="text-sm" style="color: #6b7280;">Announcements and messages</p>
            </div>
            <button id="btn-mark-all" class="px-3 py-1 rounded-lg text-white text-sm" style="background-color:#3b82f6;">Mark all as read</button>
        </div>
        <div class="space-y-3">
            <?php if (empty($notifications)): ?>
                <div class="p-4 rounded-lg" style="background-color:#f9fafb; border:1px dashed #e5e7eb;">
                    <p class="text-sm" style="color:#6b7280;">No notifications yet.</p>
                </div>
            <?php endif; ?>
            <?php foreach ($notifications as $n): ?>
                <div class="p-4 rounded-lg border flex items-start justify-between gap-4" style="background-color: rgba(229, 231, 235, 0.5); border-color: #e5e7eb;">
                    <div class="flex-1">
                        <h4 class="font-medium mb-1"><?php echo h($n['title'] ?? ''); ?></h4>
                        <p class="text-sm" style="color:#6b7280;"><?php echo h($n['message'] ?? ''); ?></p>
                        <p class="text-xs mt-1" style="color:#6b7280;">Posted: <?php echo h(date('M d, Y', strtotime($n['created_at']))); ?></p>
                    </div>
                    <div class="flex items-center gap-2">
                        <?php if (empty($n['read_at'])): ?>
                            <button class="px-3 py-1 rounded-lg border text-sm btn-mark-read" data-id="<?php echo (int)$n['notification_id']; ?>" style="border-color:#e5e7eb;">Mark read</button>
                        <?php else: ?>
                            <span class="px-3 py-1 rounded-lg text-sm" style="background-color:#10b981; color:white;">Read</span>
                        <?php endif; ?>
                        <?php if (!empty($n['child_id'])): ?>
                            <a class="px-3 py-1 rounded-lg text-sm" style="background-color:#f59e0b; color:white;" href="parent_portal.php?view=immunization&child_id=<?php echo (int)$n['child_id']; ?>">View card</a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<script>
(function(){
  const meta = document.querySelector('meta[name="csrf-token"]');
  const csrf = meta ? meta.getAttribute('content') : '';
  function post(url, body){
    return fetch(url, {method:'POST', headers:{'Content-Type':'application/json','X-CSRF-Token':csrf}, body: JSON.stringify(body||{})});
  }
  // mark one
  document.querySelectorAll('.btn-mark-read').forEach(btn=>{
    btn.addEventListener('click', async ()=>{
      const id = parseInt(btn.dataset.id,10);
      if (!id) return;
      await post('parent_api.php?mark_read=1', {notification_id:id});
      location.reload();
    });
  });
  // mark all
  const markAll = document.getElementById('btn-mark-all');
  if (markAll) markAll.addEventListener('click', async ()=>{ await post('parent_api.php?mark_all=1', {}); location.reload(); });
  // confirm
  document.querySelectorAll('.btn-confirm').forEach(btn=>{
    btn.addEventListener('click', async ()=>{
      const childId = parseInt(btn.dataset.child,10)||null;
      await post('parent_api.php?confirm=1', {notification_id:0, child_id: childId});
      alert('Thanks! We have recorded your confirmation.');
    });
  });
  // reschedule
  document.querySelectorAll('.btn-resched').forEach(btn=>{
    btn.addEventListener('click', async ()=>{
      const childId = parseInt(btn.dataset.child,10)||null;
      const proposed = prompt('Enter proposed date (YYYY-MM-DD):');
      if (proposed){ await post('parent_api.php?reschedule=1', {notification_id:0, child_id: childId, proposed_date: proposed}); alert('Reschedule request sent.'); }
    });
  });
})();
</script>