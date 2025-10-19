<?php
// Ensure session is started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../inc/db.php';

$parent_user_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
$children = [];
$immunizations_by_child = [];
$upcoming_by_child = [];
$pending_by_child = [];
$overdue_by_child = [];
$nutrition_history_by_child = [];
// New: supplementation records per child for the Supplementation view
$supplementation_by_child = [];

// Configuration
$OVERDUE_GRACE_DAYS = 0; // 0 = overdue immediately after due date
$UPCOMING_HORIZON_DAYS = 60;

// Resolve parent/guardian name once
$parent_name = '';
if (isset($_SESSION['first_name'], $_SESSION['last_name'])) {
    $parent_name = trim($_SESSION['first_name'] . ' ' . $_SESSION['last_name']);
} elseif ($parent_user_id) {
    if ($stmt_parent = $mysqli->prepare("SELECT first_name, last_name FROM users WHERE user_id = ?")) {
        $stmt_parent->bind_param('i', $parent_user_id);
        $stmt_parent->execute();
        $res_parent = $stmt_parent->get_result();
        if ($rp = $res_parent->fetch_assoc()) {
            $parent_name = trim(($rp['first_name'] ?? '') . ' ' . ($rp['last_name'] ?? ''));
        }
        $stmt_parent->close();
    }
}

if ($parent_user_id) {
    $sql = "SELECT c.child_id, c.full_name, c.sex, c.birth_date, c.weight_kg, c.height_cm, c.updated_at, c.created_by, c.mother_id
            FROM parent_child_access pca
            JOIN children c ON pca.child_id = c.child_id
            WHERE pca.parent_user_id = ? AND pca.is_active = 1";
    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param('i', $parent_user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $birth_date = new DateTime($row['birth_date']);
        $now = new DateTime('today');
        $age = $now->diff($birth_date);
        $age_str = $age->y > 0 ? $age->y . ' years' : $age->m . ' months';
        $age_months = ($age->y * 12) + $age->m;

        $latest_immunization = 'N/A';
        $next_due_date = 'N/A';
        $next_due_days = null;
        $progress_percent = 0;
        $last_vax_date_dt = null;

        if (!isset($upcoming_by_child[$row['child_id']])) {
            $upcoming_by_child[$row['child_id']] = [];
        }

        // Immunization history with vaccine names
        $sql_hist = "SELECT ci.immunization_id, ci.vaccine_id, ci.dose_number, ci.vaccination_date, ci.vaccination_site, ci.batch_lot_number,
                             ci.vaccine_expiry_date, ci.administered_by, ci.next_dose_due_date, ci.notes,
                             u.first_name AS provider_first, u.last_name AS provider_last,
                             vt.vaccine_name, vt.vaccine_code
                      FROM child_immunizations ci
                      LEFT JOIN users u ON ci.administered_by = u.user_id
                      LEFT JOIN vaccine_types vt ON ci.vaccine_id = vt.vaccine_id
                      WHERE ci.child_id = ?
                      ORDER BY ci.vaccination_date ASC";
        if ($stmt_hist = $mysqli->prepare($sql_hist)) {
            $stmt_hist->bind_param('i', $row['child_id']);
            $stmt_hist->execute();
            $res_hist = $stmt_hist->get_result();
            $immunizations_by_child[$row['child_id']] = [];
            $latest_date = null;
            $earliest_upcoming = null;
            $upcoming_keys = [];
            while ($h = $res_hist->fetch_assoc()) {
                $display_date = $h['vaccination_date'] ? date('M d, Y', strtotime($h['vaccination_date'])) : '';
                $provider_name = trim(($h['provider_first'] ?? '') . ' ' . ($h['provider_last'] ?? ''));
                $expiry_disp = $h['vaccine_expiry_date'] ? date('Y-m-d', strtotime($h['vaccine_expiry_date'])) : '';
                $immunizations_by_child[$row['child_id']][] = [
                    'vaccine_name' => $h['vaccine_name'] ?? ('Vaccine #' . ($h['vaccine_id'] ?? '')),
                    'dose_number' => $h['dose_number'],
                    'date' => $display_date,
                    'site' => $h['vaccination_site'] ?? '',
                    'batch' => $h['batch_lot_number'] ?? '',
                    'expiry' => $expiry_disp,
                    'provider' => $provider_name !== '' ? $provider_name : ('User #' . ($h['administered_by'] ?? '')),
                    'notes' => $h['notes'] ?? ''
                ];
                if (!empty($h['vaccination_date'])) {
                    $vd = new DateTime($h['vaccination_date']);
                    if ($latest_date === null || $vd > $latest_date) {
                        $latest_date = $vd;
                    }
                }
                if (!empty($h['next_dose_due_date'])) {
                    $nd = new DateTime($h['next_dose_due_date']);
                    if ($nd >= $now) {
                        $days_left = $now->diff($nd)->days;
                        $urgency = $days_left <= 14 ? 'urgent' : ($days_left <= 45 ? 'soon' : 'scheduled');
                        $key = ($h['vaccine_id'] ?? 'x') . ':' . (int)$h['dose_number'];
                        if (!isset($upcoming_keys[$key])) {
                            $upcoming_by_child[$row['child_id']][] = [
                                'label' => (($h['vaccine_name'] ?? null) ? ($h['vaccine_name'] . ' • ') : '') . 'Dose ' . (int)$h['dose_number'] . ' (next)',
                                'due_date_fmt' => $nd->format('M d, Y'),
                                'days_left' => $days_left,
                                'urgency' => $urgency,
                                'due_date' => $nd->format('Y-m-d')
                            ];
                            $upcoming_keys[$key] = true;
                        }
                        if ($earliest_upcoming === null || $nd < $earliest_upcoming) {
                            $earliest_upcoming = $nd;
                        }
                    }
                }
            }
            $stmt_hist->close();
            if ($latest_date) {
                $latest_immunization = $latest_date->format('M d, Y');
                $last_vax_date_dt = $latest_date;
            }
            if ($earliest_upcoming) {
                $next_due_date = $earliest_upcoming->format('M d, Y');
                $next_due_days = $now->diff($earliest_upcoming)->days;
            }
        }

        // Progress
        $admin_cnt = 0;
        if ($stmt_cnt1 = $mysqli->prepare("SELECT COUNT(*) AS cnt FROM child_immunizations WHERE child_id = ?")) {
            $stmt_cnt1->bind_param('i', $row['child_id']);
            $stmt_cnt1->execute();
            $res1 = $stmt_cnt1->get_result();
            if ($r1 = $res1->fetch_assoc()) { $admin_cnt = (int)$r1['cnt']; }
            $stmt_cnt1->close();
        }
        $sched_cnt = 0;
        if ($stmt_cnt2 = $mysqli->prepare("SELECT COUNT(*) AS cnt FROM immunization_schedule WHERE recommended_age_months IS NOT NULL AND recommended_age_months <= ?")) {
            $stmt_cnt2->bind_param('i', $age_months);
            $stmt_cnt2->execute();
            $res2 = $stmt_cnt2->get_result();
            if ($r2 = $res2->fetch_assoc()) { $sched_cnt = (int)$r2['cnt']; }
            $stmt_cnt2->close();
        }
        if ($sched_cnt > 0) {
            $progress_percent = max(0, min(100, (int)round(($admin_cnt / $sched_cnt) * 100)));
        }

        // Pending/Overdue using schedule
        $administered_pairs = [];
        if ($stmt_pairs = $mysqli->prepare("SELECT vaccine_id, dose_number FROM child_immunizations WHERE child_id = ?")) {
            $stmt_pairs->bind_param('i', $row["child_id"]);
            $stmt_pairs->execute();
            $res_pairs = $stmt_pairs->get_result();
            while ($p = $res_pairs->fetch_assoc()) {
                $key = $p['vaccine_id'] . ':' . $p['dose_number'];
                $administered_pairs[$key] = true;
            }
            $stmt_pairs->close();
        }
        $pending_by_child[$row['child_id']] = [];
        $overdue_by_child[$row['child_id']] = [];
        $overdue_set = [];
        if ($stmt_od = $mysqli->prepare("SELECT vaccine_id, dose_number FROM overdue_notifications WHERE child_id = ? AND status = 'active'")) {
            $stmt_od->bind_param('i', $row['child_id']);
            $stmt_od->execute();
            $res_od = $stmt_od->get_result();
            while ($o = $res_od->fetch_assoc()) {
                $overdue_set[$o['vaccine_id'] . ':' . $o['dose_number']] = true;
            }
            $stmt_od->close();
        }
        if ($stmt_sched = $mysqli->prepare("SELECT s.vaccine_id, s.dose_number, s.recommended_age_months, vt.vaccine_name, vt.vaccine_code
                                            FROM immunization_schedule s
                                            LEFT JOIN vaccine_types vt ON s.vaccine_id = vt.vaccine_id
                                            WHERE s.recommended_age_months IS NOT NULL AND s.recommended_age_months <= ?
                                            ORDER BY s.recommended_age_months, s.vaccine_id, s.dose_number")) {
            $stmt_sched->bind_param('i', $age_months);
            $stmt_sched->execute();
            $res_sched = $stmt_sched->get_result();
            if (!isset($upcoming_keys)) { $upcoming_keys = []; }
            while ($s = $res_sched->fetch_assoc()) {
                $sk = $s['vaccine_id'] . ':' . $s['dose_number'];
                if (!isset($administered_pairs[$sk])) {
                    $due_dt = (clone $birth_date);
                    $due_dt->modify('+' . (int)$s['recommended_age_months'] . ' months');
                    $label_name = !empty($s['vaccine_name']) ? $s['vaccine_name'] : ('Vaccine ID ' . (int)$s['vaccine_id']);
                    $label = $label_name . ' • Dose ' . (int)$s['dose_number'];

                    $overdue_threshold = (clone $now)->modify('-' . (int)$OVERDUE_GRACE_DAYS . ' days');
                    if ($due_dt < $overdue_threshold || isset($overdue_set[$sk])) {
                        $overdue_by_child[$row['child_id']][] = $label . ' • Due: ' . $due_dt->format('M d, Y');
                    } else {
                        $pending_by_child[$row['child_id']][] = $label . ' • Due: ' . $due_dt->format('M d, Y');
                    }

                    if ($due_dt >= $now) {
                        $horizon = (clone $now)->modify('+' . (int)$UPCOMING_HORIZON_DAYS . ' days');
                        if ($due_dt <= $horizon) {
                            $days_left = $now->diff($due_dt)->days;
                            $urgency = $days_left <= 14 ? 'urgent' : ($days_left <= 45 ? 'soon' : 'scheduled');
                            if (!isset($upcoming_keys[$sk])) {
                                $upcoming_by_child[$row['child_id']][] = [
                                    'label' => $label,
                                    'due_date_fmt' => $due_dt->format('M d, Y'),
                                    'days_left' => $days_left,
                                    'urgency' => $urgency,
                                    'due_date' => $due_dt->format('Y-m-d')
                                ];
                                $upcoming_keys[$sk] = true;
                            }
                            if ($earliest_upcoming === null || $due_dt < $earliest_upcoming) { $earliest_upcoming = $due_dt; }
                        }
                    }
                }
            }
            $stmt_sched->close();
        }

        if (!empty($upcoming_by_child[$row['child_id']])) {
            usort($upcoming_by_child[$row['child_id']], function($a, $b) {
                $da = $a['due_date'] ?? (isset($a['due_date_fmt']) ? date('Y-m-d', strtotime($a['due_date_fmt'])) : '9999-12-31');
                $db = $b['due_date'] ?? (isset($b['due_date_fmt']) ? date('Y-m-d', strtotime($b['due_date_fmt'])) : '9999-12-31');
                return strcmp($da, $db);
            });
            if ($earliest_upcoming === null) {
                $first = $upcoming_by_child[$row['child_id']][0];
                $first_dt = new DateTime($first['due_date'] ?? date('Y-m-d', strtotime($first['due_date_fmt'])));
                $earliest_upcoming = $first_dt;
            }
            if ($earliest_upcoming) {
                $next_due_date = $earliest_upcoming->format('M d, Y');
                $next_due_days = $now->diff($earliest_upcoming)->days;
            }
        }

        // Anthropometry from children table
        $weight_disp = 'N/A';
        $height_disp = 'N/A';
        $weigh_date_disp = 'N/A';
        if (array_key_exists('weight_kg', $row) && !is_null($row['weight_kg'])) {
            $weight_disp = number_format((float)$row['weight_kg'], 2) . ' kg';
        }
        if (array_key_exists('height_cm', $row) && !is_null($row['height_cm'])) {
            $height_disp = number_format((float)$row['height_cm'], 2) . ' cm';
        }
        if (!empty($row['updated_at'])) {
            $weigh_date_disp = date('M d, Y', strtotime($row['updated_at']));
        }

        // Address resolution via maternal tables
        $address_disp = 'N/A';
        if (!empty($row['mother_id'])) {
            if ($stmt_addr = $mysqli->prepare("SELECT house_number, street_name, purok_name, subdivision_name FROM maternal_patients WHERE mother_id = ?")) {
                $stmt_addr->bind_param('i', $row['mother_id']);
                $stmt_addr->execute();
                $res_addr = $stmt_addr->get_result();
                if ($ad = $res_addr->fetch_assoc()) {
                    $parts = [];
                    if (!empty($ad['house_number'])) $parts[] = trim('#' . $ad['house_number']);
                    if (!empty($ad['street_name'])) $parts[] = $ad['street_name'];
                    if (!empty($ad['purok_name'])) $parts[] = $ad['purok_name'];
                    if (!empty($ad['subdivision_name'])) $parts[] = $ad['subdivision_name'];
                    if (!empty($parts)) { $address_disp = implode(', ', $parts); }
                }
                $stmt_addr->close();
            }
        }
        if ($address_disp === 'N/A' && !empty($row['mother_id'])) {
            if ($stmt_mc = $mysqli->prepare("SELECT house_number, street_name, subdivision_name, purok_id, address_details FROM mothers_caregivers WHERE mother_id = ?")) {
                $stmt_mc->bind_param('i', $row['mother_id']);
                $stmt_mc->execute();
                $res_mc = $stmt_mc->get_result();
                if ($mc = $res_mc->fetch_assoc()) {
                    $purok_name = '';
                    if (!empty($mc['purok_id'])) {
                        if ($stmt_pk = $mysqli->prepare("SELECT purok_name FROM puroks WHERE purok_id = ?")) {
                            $stmt_pk->bind_param('i', $mc['purok_id']);
                            $stmt_pk->execute();
                            $res_pk = $stmt_pk->get_result();
                            if ($pk = $res_pk->fetch_assoc()) { $purok_name = $pk['purok_name']; }
                            $stmt_pk->close();
                        }
                    }
                    $parts = [];
                    if (!empty($mc['house_number'])) $parts[] = trim('#' . $mc['house_number']);
                    if (!empty($mc['street_name'])) $parts[] = $mc['street_name'];
                    if (!empty($purok_name)) $parts[] = $purok_name;
                    if (!empty($mc['subdivision_name'])) $parts[] = $mc['subdivision_name'];
                    if (empty($parts) && !empty($mc['address_details'])) $parts[] = $mc['address_details'];
                    if (!empty($parts)) { $address_disp = implode(', ', $parts); }
                }
                $stmt_mc->close();
            }
        }

        // Assigned staff
        $assigned_staff = 'N/A';
        if (!empty($row['created_by'])) {
            if ($stmt_staff = $mysqli->prepare("SELECT first_name, last_name FROM users WHERE user_id = ?")) {
                $stmt_staff->bind_param('i', $row['created_by']);
                $stmt_staff->execute();
                $res_staff = $stmt_staff->get_result();
                if ($sf = $res_staff->fetch_assoc()) {
                    $assigned_staff = trim(($sf['first_name'] ?? '') . ' ' . ($sf['last_name'] ?? ''));
                    if ($assigned_staff === '') { $assigned_staff = 'User #' . (int)$row['created_by']; }
                }
                $stmt_staff->close();
            }
        }

        // Nutrition recent history (last up to 6) with status labels
        $nutrition_history_by_child[$row['child_id']] = [];
        $weight_trend = null;
        $most_recent_measurement_dt = null;
        if ($stmt_nh = $mysqli->prepare("SELECT nr.weighing_date, nr.weight_kg, nr.length_height_cm, nr.wfl_ht_status_id,
                                                st.status_description, st.status_code, st.status_category
                                         FROM nutrition_records nr
                                         LEFT JOIN wfl_ht_status_types st ON nr.wfl_ht_status_id = st.status_id
                                         WHERE nr.child_id = ?
                                         ORDER BY nr.weighing_date DESC, nr.record_id DESC LIMIT 6")) {
            $stmt_nh->bind_param('i', $row['child_id']);
            $stmt_nh->execute();
            $res_nh = $stmt_nh->get_result();
            $hist = [];
            while ($nh = $res_nh->fetch_assoc()) {
                $hist[] = [
                    'date' => !empty($nh['weighing_date']) ? date('M d, Y', strtotime($nh['weighing_date'])) : 'N/A',
                    'weight' => is_null($nh['weight_kg']) ? null : (float)$nh['weight_kg'],
                    'height' => is_null($nh['length_height_cm']) ? null : (float)$nh['length_height_cm'],
                    'status_id' => $nh['wfl_ht_status_id'],
                    'status_desc' => $nh['status_description'] ?? null,
                    'status_code' => $nh['status_code'] ?? null,
                    'raw_date' => $nh['weighing_date'] ?? null
                ];
            }
            foreach ($hist as $hrow) {
                $display = ($hrow['weight'] !== null ? number_format($hrow['weight'], 2) . ' kg' : 'N/A') . ' • ' . ($hrow['height'] !== null ? number_format($hrow['height'], 2) . ' cm' : 'N/A');
                if (!empty($hrow['status_desc'])) { $display .= ' • Status: ' . $hrow['status_desc']; }
                elseif (!empty($hrow['status_id'])) { $display .= ' • Status ID: ' . (int)$hrow['status_id']; }
                $nutrition_history_by_child[$row['child_id']][] = ['date' => $hrow['date'], 'display' => $display, 'weight' => $hrow['weight']];
                if ($hrow['raw_date'] && !$most_recent_measurement_dt) { $most_recent_measurement_dt = new DateTime($hrow['raw_date']); }
            }
            if (count($hist) >= 2) {
                $w0 = $hist[0]['weight'];
                $w1 = $hist[1]['weight'];
                if ($w0 !== null && $w1 !== null) {
                    if (abs($w0 - $w1) < 0.01) $weight_trend = 'flat';
                    elseif ($w0 > $w1) $weight_trend = 'up';
                    else $weight_trend = 'down';
                }
            }
            $stmt_nh->close();
        }

        // Supplementation records for this child (used in Supplementation tab)
        if ($stmt_sup = $mysqli->prepare("SELECT s.supplement_type, s.supplement_date, s.dosage, s.next_due_date, COALESCE(CONCAT(u.first_name,' ',u.last_name),'') AS provider FROM supplementation_records s LEFT JOIN users u ON u.user_id=s.administered_by WHERE s.child_id=? ORDER BY s.supplement_date DESC, s.supplement_id DESC LIMIT 50")) {
            $stmt_sup->bind_param('i', $row['child_id']);
            $stmt_sup->execute();
            $res_sup = $stmt_sup->get_result();
            $supplementation_by_child[$row['child_id']] = [];
            while ($sp = $res_sup->fetch_assoc()) {
                $supplementation_by_child[$row['child_id']][] = [
                    'type' => $sp['supplement_type'] ?? 'Supplement',
                    'date' => !empty($sp['supplement_date']) ? date('M d, Y', strtotime($sp['supplement_date'])) : 'N/A',
                    'dosage' => $sp['dosage'] ?? '',
                    'next_due' => !empty($sp['next_due_date']) ? date('M d, Y', strtotime($sp['next_due_date'])) : null,
                    'provider' => $sp['provider'] ?? ''
                ];
            }
            $stmt_sup->close();
        }

        $stale_badge = false;
        $freshness_cutoff = (clone $now)->modify('-90 days');
        $latest_measure_dt = null;
        if (!empty($most_recent_measurement_dt)) { $latest_measure_dt = $most_recent_measurement_dt; }
        elseif (!empty($row['updated_at'])) { $latest_measure_dt = new DateTime($row['updated_at']); }
        if ($latest_measure_dt && $latest_measure_dt < $freshness_cutoff) { $stale_badge = true; }

        $children[] = [
            'id' => (int)$row['child_id'],
            'name' => $row['full_name'],
            'age' => $age_str,
            'sex' => $row['sex'],
            'photo' => $row['sex'] === 'female' ? '👧' : '👶',
            'latest_immunization' => $latest_immunization,
            'next_due_date' => $next_due_date,
            'next_due_days' => $next_due_days,
            'progress_percent' => $progress_percent,
            'birth_date' => $row['birth_date'],
            'weight' => $weight_disp,
            'height' => $height_disp,
            'weighing_date' => $weigh_date_disp,
            'address' => $address_disp,
            'assigned_staff' => $assigned_staff,
            'last_vax_date' => $last_vax_date_dt ? $last_vax_date_dt->format('M d, Y') : 'N/A',
            'weight_trend' => $weight_trend,
            'is_stale' => $stale_badge
        ];
    }
    $stmt->close();
}

    // Child selection: if multiple children, wait for selection; if single, auto-select
    $selected_child_id = null;
    if (!empty($_GET['child'])) {
        $cid = (int)$_GET['child'];
        foreach ($children as $c) { if ($c['id'] === $cid) { $selected_child_id = $cid; break; } }
    }
    if ($selected_child_id === null && count($children) === 1) {
        $selected_child_id = $children[0]['id'];
    }

    // Prepare the list to render (selected only), or empty if multiple and none selected
    $children_to_render = [];
    if ($selected_child_id !== null) {
        foreach ($children as $c) { if ($c['id'] === $selected_child_id) { $children_to_render[] = $c; break; } }
    }
?>

<div class="space-y-6">
        <div class="flex items-center justify-between flex-wrap gap-3">
        <div>
            <h2 class="text-xl font-medium">Immunization Tracking</h2>
            <p class="text-sm" style="color: #6b7280;">Digital vaccine records and schedules</p>
        </div>
            <?php
                $active_tab = isset($_GET['tab']) && in_array($_GET['tab'], ['vaccines','supplementation'], true) ? $_GET['tab'] : 'vaccines';
            ?>
            <?php if (count($children) >= 2): ?>
                <form method="get" class="flex items-center gap-2">
                    <input type="hidden" name="view" value="immunization" />
                    <label for="childSelect" class="text-sm" style="color:#6b7280;">Select Child:</label>
                    <select id="childSelect" name="child" class="border rounded px-3 py-2 text-sm">
                        <option value="" <?php echo ($selected_child_id === null ? 'selected' : ''); ?>>-- Choose --</option>
                        <?php foreach ($children as $c): ?>
                            <option value="<?php echo (int)$c['id']; ?>" <?php echo ($c['id'] === $selected_child_id ? 'selected' : ''); ?>>
                                <?php echo htmlspecialchars($c['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <label for="tabSelect" class="text-sm ml-2" style="color:#6b7280;">View:</label>
                    <select id="tabSelect" name="tab" class="border rounded px-3 py-2 text-sm">
                        <option value="vaccines" <?php echo $active_tab==='vaccines'?'selected':''; ?>>Vaccines</option>
                        <option value="supplementation" <?php echo $active_tab==='supplementation'?'selected':''; ?>>Supplementation</option>
                    </select>
                    <button type="submit" class="px-3 py-2 rounded text-white text-sm" style="background-color:#3b82f6;">View</button>
                </form>
            <?php else: ?>
                <?php if (!empty($children)): $onlyChildId = (int)$children[0]['id']; endif; ?>
                <form method="get" class="flex items-center gap-2">
                    <input type="hidden" name="view" value="immunization" />
                    <?php if (!empty($children)): ?>
                        <input type="hidden" name="child" value="<?php echo $selected_child_id!==null?(int)$selected_child_id:$onlyChildId; ?>" />
                    <?php endif; ?>
                    <label for="tabSelect" class="text-sm" style="color:#6b7280;">View:</label>
                    <select id="tabSelect" name="tab" class="border rounded px-3 py-2 text-sm">
                        <option value="vaccines" <?php echo $active_tab==='vaccines'?'selected':''; ?>>Vaccines</option>
                        <option value="supplementation" <?php echo $active_tab==='supplementation'?'selected':''; ?>>Supplementation</option>
                    </select>
                    <button type="submit" class="px-3 py-2 rounded text-white text-sm" style="background-color:#3b82f6;">View</button>
                </form>
            <?php endif; ?>
    </div>

        <?php if (empty($children)): ?>
        <div class="text-red-500">No children found for this parent.</div>
        <?php elseif (count($children) >= 2 && $selected_child_id === null): ?>
            <div class="bg-white rounded-xl shadow-sm p-6" style="border: 1px solid #e5e7eb;">
                <p class="text-sm" style="color:#6b7280;">Please select a child to view immunization details.</p>
            </div>
        <?php else: ?>
        <div class="grid gap-6 md:grid-cols-1">
            <?php foreach ($children_to_render as $child): ?>
        <!-- Child details card: shown in both Vaccines and Supplementation tabs -->
        <div class="bg-white rounded-xl shadow-sm overflow-hidden" style="border: 2px solid rgba(59, 130, 246, 0.2);">
            <div class="p-6" style="background: linear-gradient(to right, rgba(59, 130, 246, 0.1), rgba(16, 185, 129, 0.1));">
                <div class="flex items-start justify-between">
                    <div class="flex items-center gap-4">
                        <div class="text-5xl"><?php echo ($child['sex'] === 'female') ? '👧' : '👶'; ?></div>
                        <div>
                            <h3 class="font-medium"><?php echo htmlspecialchars($child['name']); ?></h3>
                            <p class="text-sm" style="color: #6b7280;">Date of Birth: <?php echo date('F d, Y', strtotime($child['birth_date'])); ?></p>
                            <p class="text-sm mt-1" style="color: #6b7280;">Child ID: CH<?php echo $child['id']; ?></p>
                            <div class="mt-2 flex gap-2 flex-wrap">
                                <span class="px-3 py-1 rounded-full text-white text-xs font-medium" style="background-color:#3b82f6;">Last: <?php echo htmlspecialchars($child['last_vax_date']); ?></span>
                                <span class="px-3 py-1 rounded-full text-white text-xs font-medium" style="background-color:#10b981;">Next: <?php echo htmlspecialchars($child['next_due_date']); ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="px-3 py-1 rounded-full text-white text-sm font-medium" style="background-color: #10b981;">Active</span>
                        <?php if (!empty($child['is_stale'])): ?>
                            <span class="px-3 py-1 rounded-full text-white text-xs font-medium" title="Last measurement is over 90 days ago" style="background-color:#f59e0b;">Visit recommended</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="p-6">
                <div class="grid gap-4 md:grid-cols-4 mb-6">
                    <div>
                        <p class="text-sm" style="color: #6b7280;">Parent/Guardian</p>
                        <p class="font-medium"><?php echo htmlspecialchars($parent_name); ?></p>
                    </div>
                    <div>
                        <p class="text-sm" style="color: #6b7280;">Birthdate</p>
                        <p class="font-medium"><?php echo date('F d, Y', strtotime($child['birth_date'])); ?></p>
                    </div>
                    <div>
                        <p class="text-sm" style="color: #6b7280;">Address</p>
                        <p class="font-medium"><?php echo htmlspecialchars($child['address']); ?></p>
                    </div>
                    <div>
                        <p class="text-sm" style="color: #6b7280;">Assigned Staff</p>
                        <p class="font-medium"><?php echo htmlspecialchars($child['assigned_staff']); ?></p>
                    </div>
                </div>
                <div class="mb-4">
                    <div class="flex items-center justify-between mb-2">
                        <p class="font-medium">Immunization Progress</p>
                        <span class="font-medium"><?php echo (int)$child['progress_percent']; ?>% Complete</span>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-3">
                        <div class="h-3 rounded-full" style="width: <?php echo (int)$child['progress_percent']; ?>%; background-color: #3b82f6;"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Pending and Overdue (only in Vaccines tab) -->
        <?php if ($active_tab === 'vaccines'): ?>
        <?php 
            $pendingList = $pending_by_child[$child['id']] ?? []; 
            $overdueList = $overdue_by_child[$child['id']] ?? []; 
            if (!empty($pendingList) || !empty($overdueList)):
        ?>
        <div class="bg-white rounded-xl shadow-sm p-6" style="border: 1px solid #e5e7eb;">
            <h3 class="font-medium mb-2">Pending and Overdue Vaccines</h3>
            <p class="text-sm mb-4" style="color: #6b7280;">Scheduled doses up to the child's age that are not yet recorded<?php echo ($OVERDUE_GRACE_DAYS > 0 ? ' (with ' . (int)$OVERDUE_GRACE_DAYS . '-day grace)' : ''); ?></p>
            <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <div class="flex items-center justify-between mb-2">
                        <p class="font-medium">Pending</p>
                        <span class="text-sm px-2 py-1 rounded-full" style="background:#eff6ff; color:#1d4ed8;"><?php echo count($pendingList); ?></span>
                    </div>
                    <?php if (!empty($pendingList)): ?>
                    <ul class="list-disc ml-5 space-y-1">
                        <?php foreach ($pendingList as $pl): ?>
                            <li><?php echo htmlspecialchars($pl); ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <?php else: ?>
                    <p class="text-sm" style="color:#6b7280;">None</p>
                    <?php endif; ?>
                </div>
                <div>
                    <div class="flex items-center justify-between mb-2">
                        <p class="font-medium">Overdue</p>
                        <span class="text-sm px-2 py-1 rounded-full" style="background:#fee2e2; color:#b91c1c;"><?php echo count($overdueList); ?></span>
                    </div>
                    <?php if (!empty($overdueList)): ?>
                    <ul class="list-disc ml-5 space-y-1">
                        <?php foreach ($overdueList as $ol): ?>
                            <li class="text-red-600"><?php echo htmlspecialchars($ol); ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <?php else: ?>
                    <p class="text-sm" style="color:#6b7280;">None</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>
    <?php endif; ?>

    <!-- Upcoming Vaccines -->
    <?php $list = $upcoming_by_child[$child['id']] ?? []; if (!empty($list) && $active_tab==='vaccines'): ?>
        <div class="bg-white rounded-xl shadow-sm p-6" style="border: 1px solid #e5e7eb;">
            <h3 class="font-medium mb-2">Upcoming Vaccines</h3>
            <p class="text-sm mb-6" style="color: #6b7280;">Don't miss these important immunization dates</p>
            <div class="space-y-3">
                <?php foreach ($list as $v): 
                    $bg_color = $v['urgency'] === 'urgent' ? 'rgba(239, 68, 68, 0.1)' : ($v['urgency'] === 'soon' ? 'rgba(245, 158, 11, 0.1)' : 'rgba(59, 130, 246, 0.1)');
                    $border_color = $v['urgency'] === 'urgent' ? '#ef4444' : ($v['urgency'] === 'soon' ? '#f59e0b' : '#3b82f6');
                    $badge_bg = $v['urgency'] === 'urgent' ? '#ef4444' : '#10b981';
                ?>
                <div class="p-4 rounded-lg border-l-4" style="background-color: <?php echo $bg_color; ?>; border-left-color: <?php echo $border_color; ?>;">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <div class="p-2 rounded-full" style="background-color: rgba(<?php echo $v['urgency'] === 'urgent' ? '239, 68, 68' : '59, 130, 246'; ?>, 0.2);">
                                <i data-lucide="<?php echo $v['urgency'] === 'urgent' ? 'alert-circle' : 'calendar'; ?>" class="w-5 h-5" style="color: <?php echo $border_color; ?>;"></i>
                            </div>
                            <div>
                                <p class="font-medium"><?php echo htmlspecialchars($v['label']); ?></p>
                                <p class="text-sm" style="color: #6b7280;">For <?php echo htmlspecialchars($child['name']); ?></p>
                            </div>
                        </div>
                        <div class="text-right">
                            <p class="mb-1 font-medium"><?php echo htmlspecialchars($v['due_date_fmt']); ?></p>
                            <span class="px-3 py-1 rounded-full text-white text-sm font-medium" style="background-color: <?php echo $badge_bg; ?>;">
                                <?php echo (int)$v['days_left']; ?> days left
                            </span>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

    

        <!-- Vaccination History Table for this child -->
        <?php if ($active_tab==='vaccines'): ?>
        <div class="bg-white rounded-xl shadow-sm p-6" style="border: 1px solid #e5e7eb;">
            <h3 class="font-medium mb-2">Complete Vaccination History</h3>
            <p class="text-sm mb-6" style="color: #6b7280;">All administered vaccines for <?php echo htmlspecialchars($child['name']); ?></p>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="border-b" style="border-color: #e5e7eb;">
                            <th class="text-left py-3 px-4 font-medium">Vaccine</th>
                            <th class="text-left py-3 px-4 font-medium">Dose Number</th>
                            <th class="text-left py-3 px-4 font-medium">Date Given</th>
                            <th class="text-left py-3 px-4 font-medium">Vaccination Site</th>
                            <th class="text-left py-3 px-4 font-medium">Batch Number</th>
                            <th class="text-left py-3 px-4 font-medium">Expiry Date</th>
                            <th class="text-left py-3 px-4 font-medium">Administered By</th>
                            <th class="text-left py-3 px-4 font-medium">Notes</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (($immunizations_by_child[$child['id']] ?? []) as $record): ?>
                        <tr class="border-b" style="border-color: #e5e7eb;">
                            <td class="py-3 px-4"><?php echo htmlspecialchars($record['vaccine_name'] ?? ''); ?></td>
                            <td class="py-3 px-4"><?php echo htmlspecialchars($record['dose_number']); ?></td>
                            <td class="py-3 px-4"><?php echo htmlspecialchars($record['date']); ?></td>
                            <td class="py-3 px-4"><?php echo htmlspecialchars($record['site']); ?></td>
                            <td class="py-3 px-4"><?php echo htmlspecialchars($record['batch']); ?></td>
                            <td class="py-3 px-4"><?php echo htmlspecialchars($record['expiry']); ?></td>
                            <td class="py-3 px-4"><?php echo htmlspecialchars($record['provider']); ?></td>
                            <td class="py-3 px-4"><?php echo htmlspecialchars($record['notes']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($immunizations_by_child[$child['id']])): ?>
                        <tr>
                            <td colspan="8" class="py-4 px-4 text-center" style="color:#6b7280;">No immunization records found.</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <!-- Supplementation Tab Content -->
        <?php if ($active_tab==='supplementation'): ?>
        <div class="bg-white rounded-xl shadow-sm overflow-hidden" style="border: 2px solid rgba(16, 185, 129, 0.2);">
            <div class="p-6" style="background: linear-gradient(to right, rgba(16, 185, 129, 0.1), rgba(59, 130, 246, 0.1));">
                <div class="flex items-start justify-between">
                    <div>
                        <h3 class="font-medium">Supplementation for <?php echo htmlspecialchars($child['name']); ?></h3>
                        <p class="text-sm" style="color:#6b7280;">Vitamins and supplements administered</p>
                    </div>
                </div>
            </div>
            <div class="p-6">
                <div class="space-y-3">
                    <?php $supList = $supplementation_by_child[$child['id']] ?? []; ?>
                    <?php if (empty($supList)): ?>
                        <div class="p-4 rounded-lg" style="background-color:#f9fafb; border:1px dashed #e5e7eb;">
                            <p class="text-sm" style="color:#6b7280;">No supplementation records found for this child.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($supList as $s): ?>
                            <div class="p-4 rounded-lg border" style="border-color:#e5e7eb;">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="font-medium"><?php echo htmlspecialchars($s['type']); ?></p>
                                        <p class="text-sm" style="color:#6b7280;">Date: <?php echo htmlspecialchars($s['date']); ?><?php if (!empty($s['dosage'])) echo ' • Dosage: '.htmlspecialchars($s['dosage']); ?></p>
                                        <?php if (!empty($s['next_due'])): ?>
                                            <p class="text-sm" style="color:#6b7280;">Next due: <?php echo htmlspecialchars($s['next_due']); ?></p>
                                        <?php endif; ?>
                                    </div>
                                    <?php if (!empty($s['provider'])): ?>
                                        <span class="px-3 py-1 rounded-full text-xs" style="background:#eef2ff; color:#3730a3;">By <?php echo htmlspecialchars($s['provider']); ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>