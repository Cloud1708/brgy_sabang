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

// Map child_id => name for display
$child_names = [];
foreach ($children as $c) { $child_names[(int)$c['child_id']] = $c['full_name']; }

// (Activity tab removed) Skipping loading of generic parent_notifications

// Community health announcements from events table (published & upcoming)
$announcements=[];
if ($stmt = $mysqli->prepare("SELECT event_id, event_title, event_description, event_type, event_date, event_time, location FROM events WHERE is_published=1 AND (event_date IS NULL OR event_date >= CURDATE()) ORDER BY event_date ASC, event_time ASC LIMIT 100")) {
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) { $announcements[] = $row; }
    $stmt->close();
}

// Vaccine reminders based on next_dose_due_date (aggregate across all linked children)
$vaccine_reminders=[];
if ($uid>0) {
    $sql="SELECT ci.child_id, vt.vaccine_name, vt.vaccine_code, ci.dose_number, ci.next_dose_due_date
          FROM parent_child_access p
          JOIN child_immunizations ci ON ci.child_id = p.child_id
          JOIN vaccine_types vt ON vt.vaccine_id = ci.vaccine_id
          WHERE p.parent_user_id = ? AND p.is_active = 1 AND ci.next_dose_due_date IS NOT NULL
          ORDER BY ci.next_dose_due_date ASC
          LIMIT 200";
    if ($s=$mysqli->prepare($sql)) { $s->bind_param('i',$uid); $s->execute(); $r=$s->get_result(); while($x=$r->fetch_assoc()) $vaccine_reminders[]=$x; $s->close(); }
}

// Also compute overdue vaccines based on the immunization schedule (not just recorded next_dose_due_date)
// Mirrors the logic used in Immunization view for Pending/Overdue
if ($uid>0 && !empty($children)) {
    $OVERDUE_GRACE_DAYS = 0; // overdue immediately after due date
    $now = new DateTime('now');

    // Prepare re-usable statements
    $stmt_pairs = $mysqli->prepare("SELECT vaccine_id, dose_number FROM child_immunizations WHERE child_id = ?");
    $stmt_sched = $mysqli->prepare("SELECT s.vaccine_id, s.dose_number, s.recommended_age_months, vt.vaccine_name, vt.vaccine_code
                                     FROM immunization_schedule s
                                     LEFT JOIN vaccine_types vt ON s.vaccine_id = vt.vaccine_id
                                     WHERE s.recommended_age_months IS NOT NULL AND s.recommended_age_months <= ?
                                     ORDER BY s.recommended_age_months, s.vaccine_id, s.dose_number");
    // optional table; ignore if missing
    $stmt_od   = $mysqli->prepare("SELECT vaccine_id, dose_number FROM overdue_notifications WHERE child_id = ? AND status = 'active'");

    // Build a set of existing reminders to avoid duplicates
    // Prefer vaccine_code when vaccine_id is not present in the record
    $existing_keys = [];
    foreach ($vaccine_reminders as $vr) {
        $cid  = (int)($vr['child_id'] ?? 0);
        $vid  = (int)($vr['vaccine_id'] ?? 0);
        $code = strtoupper(trim((string)($vr['vaccine_code'] ?? '')));
        $dose = (int)($vr['dose_number'] ?? 0);
        if ($cid && $dose) {
            if ($code !== '') {
                $existing_keys[$cid . ':code:' . $code . ':' . $dose] = true;
            }
            if ($vid > 0) {
                $existing_keys[$cid . ':id:' . $vid . ':' . $dose] = true;
            }
        }
    }

    foreach ($children as $c) {
        $cid = (int)$c['child_id'];
        if ($cid <= 0 || empty($c['birth_date'])) continue;
        $birth = new DateTime($c['birth_date']);
        $age_months = (int)$c['age_months'];

        // Administered doses for this child
        $administered = [];
        if ($stmt_pairs) {
            $stmt_pairs->bind_param('i', $cid);
            if ($stmt_pairs->execute()) {
                $res = $stmt_pairs->get_result();
                while ($p = $res->fetch_assoc()) {
                    $administered[$p['vaccine_id'] . ':' . $p['dose_number']] = true;
                }
            }
        }

        // Overdue overrides (manual flags)
        $overdue_set = [];
        if ($stmt_od) {
            $stmt_od->bind_param('i', $cid);
            if ($stmt_od->execute()) {
                $res = $stmt_od->get_result();
                while ($o = $res->fetch_assoc()) {
                    $overdue_set[$o['vaccine_id'] . ':' . $o['dose_number']] = true;
                }
            }
        }

        // Walk schedule entries up to current age; anything not administered is pending/overdue
        if ($stmt_sched) {
            $stmt_sched->bind_param('i', $age_months);
            if ($stmt_sched->execute()) {
                $res = $stmt_sched->get_result();
                while ($srow = $res->fetch_assoc()) {
                    $sk = $srow['vaccine_id'] . ':' . $srow['dose_number'];
                    if (isset($administered[$sk])) continue;

                    $due_dt = (clone $birth)->modify('+' . (int)$srow['recommended_age_months'] . ' months');
                    $overdue_threshold = (clone $now)->modify('-' . (int)$OVERDUE_GRACE_DAYS . ' days');
                    $is_overdue = ($due_dt < $overdue_threshold) || isset($overdue_set[$sk]);
                    if (!$is_overdue) continue; // only bring in overdue here; upcoming handled by next_dose_due_date list

                    $key_id   = $cid . ':id:' . (int)$srow['vaccine_id'] . ':' . (int)$srow['dose_number'];
                    $key_code = $cid . ':code:' . strtoupper(trim((string)($srow['vaccine_code'] ?? ''))) . ':' . (int)$srow['dose_number'];
                    if (isset($existing_keys[$key_id]) || isset($existing_keys[$key_code])) continue; // already present
                    if (!empty($srow['vaccine_code'])) $existing_keys[$key_code] = true; else $existing_keys[$key_id] = true;

                    $vaccine_reminders[] = [
                        'child_id' => $cid,
                        'vaccine_id' => (int)$srow['vaccine_id'],
                        'vaccine_name' => $srow['vaccine_name'] ?? null,
                        'vaccine_code' => $srow['vaccine_code'] ?? null,
                        'dose_number' => (int)$srow['dose_number'],
                        'next_dose_due_date' => $due_dt->format('Y-m-d')
                    ];
                }
            }
        }
    }

    // Final sort ascending by due date
    usort($vaccine_reminders, function($a,$b){
        $da = strtotime($a['next_dose_due_date'] ?? '9999-12-31');
        $db = strtotime($b['next_dose_due_date'] ?? '9999-12-31');
        return $da <=> $db;
    });
}

// Parent (maternal) checkup reminders based on next_visit_date in health_records
$parent_checkups = [];
if ($uid>0) {
    $sql = "SELECT hr.mother_id, mp.first_name, mp.last_name, MIN(hr.next_visit_date) AS next_visit_date
            FROM parent_child_access p
            JOIN children c ON c.child_id = p.child_id
            JOIN maternal_patients mp ON mp.mother_id = c.mother_id
            JOIN health_records hr ON hr.mother_id = mp.mother_id
            WHERE p.parent_user_id = ? AND p.is_active = 1 AND hr.next_visit_date IS NOT NULL AND hr.next_visit_date >= CURDATE()
            GROUP BY hr.mother_id, mp.first_name, mp.last_name
            ORDER BY next_visit_date ASC
            LIMIT 50";
    if ($s = $mysqli->prepare($sql)) { $s->bind_param('i',$uid); $s->execute(); $r=$s->get_result(); while($x=$r->fetch_assoc()) $parent_checkups[]=$x; $s->close(); }
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
    <!-- Page Header -->
    <div class="flex flex-col md:flex-row md:items-end md:justify-between gap-3">
        <div>
            <h2 class="text-xl font-medium">Notification Center</h2>
            <p class="text-sm" style="color: #6b7280;">Stay updated on your children's health announcements and reminders</p>
        </div>
    </div>

    <!-- Tabs -->
    <div class="mt-2">
        <div class="flex gap-2 overflow-x-auto pb-2 border-b" style="border-color:#e5e7eb;">
            <button class="tab-btn px-3 py-2 rounded-lg text-sm font-medium border" data-tab="reminders" aria-selected="true" style="border-color:#e5e7eb; background-color:#f3f4f6;">
                Reminders
                <span class="ml-1 px-2 py-0.5 rounded-full text-xs" style="background-color:#e5e7eb;">
                    <?php echo count($vaccine_reminders) + count($parent_checkups); ?>
                </span>
            </button>
            <button class="tab-btn px-3 py-2 rounded-lg text-sm font-medium border" data-tab="announcements" aria-selected="false" style="border-color:#e5e7eb;">
                Announcements
                <span class="ml-1 px-2 py-0.5 rounded-full text-xs" style="background-color:#e5e7eb;">
                    <?php echo count($announcements); ?>
                </span>
            </button>
        </div>
    </div>

    <!-- Announcements Panel -->
    <div data-panel="announcements" style="display:none;" class="space-y-6">
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
    </div>

    <!-- Reminders Panel -->
    <div data-panel="reminders" class="space-y-6">
        <div class="bg-white rounded-xl shadow-sm p-6" style="border: 1px solid #e5e7eb;">
            <button id="reminders-toggle" type="button" class="w-full flex items-center justify-between mb-4 text-left">
                <div class="flex items-center gap-3">
                    <i data-lucide="bell" class="w-6 h-6" style="color: #ef4444;"></i>
                    <div>
                        <h3 class="font-medium">Vaccine Reminders</h3>
                        <p class="text-sm" style="color: #6b7280;">Upcoming immunization reminders for all your children</p>
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <span class="px-3 py-1 rounded-full text-white text-sm font-medium" style="background-color: #ef4444;">
                        <?php echo count($vaccine_reminders); ?> Due
                    </span>
                    <i id="reminders-chevron" data-lucide="chevron-down" class="w-5 h-5" style="transition: transform 0.2s ease; color:#6b7280;"></i>
                </div>
            </button>
            <div id="reminders-content" class="space-y-3">
                <?php foreach ($vaccine_reminders as $rem): ?>
                    <?php
                        $priority = priority_from_date($rem['next_dose_due_date'] ?? null);
                        $bg_color = $priority === 'high' ? 'rgba(239, 68, 68, 0.1)' : ($priority==='medium' ? 'rgba(245, 158, 11, 0.1)' : 'rgba(229,231,235,0.5)');
                        $border_color = $priority === 'high' ? '#ef4444' : ($priority==='medium' ? '#f59e0b' : '#6b7280');
                        $cname = $child_names[(int)($rem['child_id'] ?? 0)] ?? '';
                    ?>
                    <div class="p-4 rounded-lg border-l-4" style="background-color: <?php echo $bg_color; ?>; border-left-color: <?php echo $border_color; ?>;">
                        <div class="flex items-start justify-between mb-3">
                            <div>
                                <p class="font-medium mb-1"><?php echo h(($rem['vaccine_name'] ?? $rem['vaccine_code']).' Dose '.$rem['dose_number']); ?></p>
                                <p class="text-sm" style="color: #6b7280;">Child: <?php echo h($cname); ?></p>
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
                    </div>
                <?php endforeach; ?>
                <?php if (empty($vaccine_reminders)): ?>
                    <div class="p-4 rounded-lg" style="background-color:#f9fafb; border:1px dashed #e5e7eb;">
                        <p class="text-sm" style="color:#6b7280;">No upcoming vaccine reminders for your linked children.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Parent Checkup Reminders -->
        <div class="bg-white rounded-xl shadow-sm p-6" style="border: 1px solid #e5e7eb;">
            <button id="checkups-toggle" type="button" class="w-full flex items-center justify-between mb-4 text-left">
                <div class="flex items-center gap-3">
                    <i data-lucide="calendar" class="w-6 h-6" style="color: #3b82f6;"></i>
                    <div>
                        <h3 class="font-medium">Parent Checkup Reminders</h3>
                        <p class="text-sm" style="color: #6b7280;">Upcoming maternal consultations</p>
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <span class="px-3 py-1 rounded-full text-white text-sm font-medium" style="background-color: #3b82f6;">
                        <?php echo count($parent_checkups); ?> Scheduled
                    </span>
                    <i id="checkups-chevron" data-lucide="chevron-down" class="w-5 h-5" style="transition: transform 0.2s ease; color:#6b7280;"></i>
                </div>
            </button>
            <div id="checkups-content" class="space-y-3">
                <?php foreach ($parent_checkups as $chk): ?>
                    <?php
                        $date = $chk['next_visit_date'] ?? null;
                        $priority = priority_from_date($date);
                        $bg_color = $priority === 'high' ? 'rgba(59, 130, 246, 0.1)' : ($priority==='medium' ? 'rgba(245, 158, 11, 0.1)' : 'rgba(229,231,235,0.5)');
                        $border_color = $priority === 'high' ? '#3b82f6' : ($priority==='medium' ? '#f59e0b' : '#6b7280');
                        $name = trim(($chk['first_name'] ?? '').' '.($chk['last_name'] ?? ''));
                    ?>
                    <div class="p-4 rounded-lg border-l-4" style="background-color: <?php echo $bg_color; ?>; border-left-color: <?php echo $border_color; ?>;">
                        <div class="flex items-start justify-between mb-3">
                            <div>
                                <p class="font-medium mb-1">Maternal Checkup</p>
                                <p class="text-sm" style="color: #6b7280;">Parent: <?php echo h($name ?: 'Linked mother'); ?></p>
                            </div>
                            <span class="px-3 py-1 rounded-full text-white text-sm font-medium" style="background-color: <?php echo $border_color; ?>;">
                                <?php echo $priority === 'overdue' ? 'Overdue' : ($priority==='high' ? 'Soon' : 'Scheduled'); ?>
                            </span>
                        </div>
                        <div class="grid grid-cols-2 gap-2 mb-3">
                            <div class="flex items-center gap-2">
                                <i data-lucide="calendar" class="w-4 h-4"></i>
                                <span><?php echo h(date('M d, Y', strtotime($date))); ?></span>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
                <?php if (empty($parent_checkups)): ?>
                    <div class="p-4 rounded-lg" style="background-color:#f9fafb; border:1px dashed #e5e7eb;">
                        <p class="text-sm" style="color:#6b7280;">No scheduled maternal checkups.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    

    
</div>

<script>
(function(){
    // tabs
    const tabButtons = document.querySelectorAll('.tab-btn');
    const panels = document.querySelectorAll('[data-panel]');
        const validTabs = ['reminders','announcements'];
    function activateTab(tab){
        tabButtons.forEach(btn=>{
            const isActive = btn.getAttribute('data-tab')===tab;
            btn.setAttribute('aria-selected', isActive ? 'true' : 'false');
            if (isActive) {
                btn.style.backgroundColor = '#f3f4f6';
                btn.style.borderColor = '#3b82f6';
                btn.style.boxShadow = '0 0 0 2px rgba(59,130,246,.25)';
                btn.style.fontWeight = '600';
            } else {
                btn.style.backgroundColor = '';
                btn.style.borderColor = '#e5e7eb';
                btn.style.boxShadow = '';
                btn.style.fontWeight = '';
            }
        });
        panels.forEach(p=>{ p.style.display = (p.getAttribute('data-panel')===tab) ? '' : 'none'; });
    }
    let initialTab = 'reminders';
    if (location.hash) {
        const hash = location.hash.replace('#','').replace('tab=','');
        if (validTabs.includes(hash)) initialTab = hash;
    }
    activateTab(initialTab);
    tabButtons.forEach(btn=> btn.addEventListener('click', ()=>{ const t = btn.getAttribute('data-tab'); if (validTabs.includes(t)) { activateTab(t); history.replaceState(null,'','#'+t); } }));
    
    // reminders dropdown
    const remToggle = document.getElementById('reminders-toggle');
    const remContent = document.getElementById('reminders-content');
    const remChevron = document.getElementById('reminders-chevron');
    const lsKey = 'notif.reminders.collapsed';
    function setCollapsed(collapsed){
        if (!remContent || !remChevron) return;
        remContent.style.display = collapsed ? 'none' : '';
        remChevron.style.transform = collapsed ? 'rotate(-90deg)' : 'rotate(0deg)';
        try { localStorage.setItem(lsKey, collapsed ? '1' : '0'); } catch(e){}
    }
    // init from localStorage
    try {
        const saved = localStorage.getItem(lsKey);
        if (saved === '1') setCollapsed(true);
    } catch(e){}
    if (remToggle) remToggle.addEventListener('click', ()=>{
        const isHidden = remContent && remContent.style.display === 'none';
        setCollapsed(!isHidden ? true : false);
    });
    
    // checkups dropdown
    const chkToggle = document.getElementById('checkups-toggle');
    const chkContent = document.getElementById('checkups-content');
    const chkChevron = document.getElementById('checkups-chevron');
    const chkKey = 'notif.checkups.collapsed';
    function setChkCollapsed(collapsed){
        if (!chkContent || !chkChevron) return;
        chkContent.style.display = collapsed ? 'none' : '';
        chkChevron.style.transform = collapsed ? 'rotate(-90deg)' : 'rotate(0deg)';
        try { localStorage.setItem(chkKey, collapsed ? '1' : '0'); } catch(e){}
    }
    try {
        const saved = localStorage.getItem(chkKey);
        if (saved === '1') setChkCollapsed(true);
    } catch(e){}
    if (chkToggle) chkToggle.addEventListener('click', ()=>{
        const isHidden = chkContent && chkContent.style.display === 'none';
        setChkCollapsed(!isHidden ? true : false);
    });
  
})();
</script>