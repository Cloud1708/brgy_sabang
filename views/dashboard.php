<?php
// Ensure session is started for accessing $_SESSION values
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../inc/db.php';

$format_date = function($dateStr) {
    if (empty($dateStr)) return '';
    $ts = strtotime($dateStr);
    return $ts ? date('M d, Y', $ts) : '';
};

// BP category helper based on 2017 ACC/AHA
function bp_category($systolic, $diastolic) {
    $s = is_null($systolic) ? null : (int)$systolic;
    $d = is_null($diastolic) ? null : (int)$diastolic;
    if ($s === null || $d === null) {
        return ['label' => '—', 'color' => '#9ca3af']; // gray
    }
    if ($s >= 180 || $d >= 120) return ['label' => 'Crisis', 'color' => '#991b1b'];
    if ($s >= 140 || $d >= 90)  return ['label' => 'Stage 2', 'color' => '#ef4444'];
    if ($s >= 130 || $d >= 80)  return ['label' => 'Stage 1', 'color' => '#f97316'];
    if ($s >= 120 && $d < 80)   return ['label' => 'Elevated', 'color' => '#f59e0b'];
    return ['label' => 'Normal', 'color' => '#059669'];
}

$parent_user_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
$parent = [
    'name' => '',
    'email' => '',
    'barangay' => '',
    'children' => [],
];

$mother_id = null;
$maternal = null;
$caregiver = null;
$address = 'N/A';
$contact_number = '';
$emergency_contact = '';
// Extra parent detail fields
$blood_type = '';
$dob = null; // YYYY-MM-DD
$age_display = '';
$gravida = null;
$para = null;

// Load parent basic info
if ($parent_user_id) {
    if ($stmt = $mysqli->prepare("SELECT first_name, last_name, email, barangay FROM users WHERE user_id = ?")) {
        $stmt->bind_param('i', $parent_user_id);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($u = $res->fetch_assoc()) {
            $parent['name'] = trim(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? ''));
            $parent['email'] = $u['email'] ?? '';
            $parent['barangay'] = $u['barangay'] ?? '';
        }
        $stmt->close();
    }

    // Get any linked children (names + mother_id hint)
    if ($stmtc = $mysqli->prepare("SELECT c.child_id, c.full_name, c.mother_id FROM parent_child_access pca JOIN children c ON pca.child_id = c.child_id WHERE pca.parent_user_id = ? AND pca.is_active = 1 ORDER BY c.full_name")) {
        $stmtc->bind_param('i', $parent_user_id);
        $stmtc->execute();
        $resc = $stmtc->get_result();
        while ($r = $resc->fetch_assoc()) {
            $parent['children'][] = ['id' => (int)$r['child_id'], 'name' => $r['full_name']];
            if ($mother_id === null && !empty($r['mother_id'])) {
                $mother_id = (int)$r['mother_id'];
            }
        }
        $stmtc->close();
    }

    // Try to map maternal_patients via user_account_id first (direct link)
    if ($mother_id === null) {
        if ($stmtmp = $mysqli->prepare("SELECT * FROM maternal_patients WHERE user_account_id = ? LIMIT 1")) {
            $stmtmp->bind_param('i', $parent_user_id);
            $stmtmp->execute();
            $resmp = $stmtmp->get_result();
            if ($mp = $resmp->fetch_assoc()) {
                $maternal = $mp; $mother_id = (int)$mp['mother_id'];
            }
            $stmtmp->close();
        }
    }
    // If we have mother_id from a child, try to fetch maternal & caregiver records by id
    if ($mother_id !== null) {
        if ($stmtmp2 = $mysqli->prepare("SELECT * FROM maternal_patients WHERE mother_id = ? LIMIT 1")) {
            $stmtmp2->bind_param('i', $mother_id);
            $stmtmp2->execute();
            $resmp2 = $stmtmp2->get_result();
            if ($mp2 = $resmp2->fetch_assoc()) { $maternal = $mp2; }
            $stmtmp2->close();
        }
        if ($stmtcg = $mysqli->prepare("SELECT * FROM mothers_caregivers WHERE mother_id = ? LIMIT 1")) {
            $stmtcg->bind_param('i', $mother_id);
            $stmtcg->execute();
            $rescg = $stmtcg->get_result();
            if ($cg = $rescg->fetch_assoc()) { $caregiver = $cg; }
            $stmtcg->close();
        }
    } else {
        // Fallback: try caregiver link by user_account_id
        if ($stmtcg2 = $mysqli->prepare("SELECT * FROM mothers_caregivers WHERE user_account_id = ? LIMIT 1")) {
            $stmtcg2->bind_param('i', $parent_user_id);
            $stmtcg2->execute();
            $rescg2 = $stmtcg2->get_result();
            if ($cg2 = $rescg2->fetch_assoc()) { $caregiver = $cg2; $mother_id = (int)$cg2['mother_id']; }
            $stmtcg2->close();
        }
    }
}

// Build display initials for avatar
$initials = 'PP';
if (!empty($parent['name'])) {
    $parts = preg_split('/\s+/', trim($parent['name']));
    $first = $parts[0] ?? '';
    $last  = $parts[count($parts)-1] ?? '';
    $fi = $first !== '' ? mb_substr($first, 0, 1) : '';
    $li = $last !== '' ? mb_substr($last, 0, 1) : '';
    $initials = strtoupper(($fi.$li) ?: 'PP');
}

// Build address, contact, emergency
if ($maternal) {
    $parts = [];
    if (!empty($maternal['house_number'])) $parts[] = '#' . trim($maternal['house_number']);
    if (!empty($maternal['street_name'])) $parts[] = $maternal['street_name'];
    if (!empty($maternal['purok_name'])) $parts[] = $maternal['purok_name'];
    if (!empty($maternal['subdivision_name'])) $parts[] = $maternal['subdivision_name'];
    if (!empty($parts)) $address = implode(', ', $parts);
    $contact_number = $maternal['contact_number'] ?? '';
    $emergency_contact = trim(($maternal['emergency_contact_name'] ?? '') . ' ' . ($maternal['emergency_contact_number'] ? '(' . $maternal['emergency_contact_number'] . ')' : ''));
    // Enrich extra fields from maternal profile
    if (!empty($maternal['blood_type'])) $blood_type = strtoupper(trim($maternal['blood_type']));
    if (!empty($maternal['date_of_birth'])) $dob = $maternal['date_of_birth'];
    if (isset($maternal['gravida'])) $gravida = $maternal['gravida'];
    if (isset($maternal['para'])) $para = $maternal['para'];
} elseif ($caregiver) {
    $parts = [];
    if (!empty($caregiver['house_number'])) $parts[] = '#' . trim($caregiver['house_number']);
    if (!empty($caregiver['street_name'])) $parts[] = $caregiver['street_name'];
    if (!empty($caregiver['subdivision_name'])) $parts[] = $caregiver['subdivision_name'];
    if (!empty($caregiver['purok_id'])) {
        if ($stmtp = $mysqli->prepare("SELECT purok_name FROM puroks WHERE purok_id = ?")) {
            $stmtp->bind_param('i', $caregiver['purok_id']);
            $stmtp->execute();
            $resp = $stmtp->get_result();
            if ($pr = $resp->fetch_assoc()) { $parts[] = $pr['purok_name']; }
            $stmtp->close();
        }
    }
    if (empty($parts) && !empty($caregiver['address_details'])) $parts[] = $caregiver['address_details'];
    if (!empty($parts)) $address = implode(', ', $parts);
    $contact_number = $caregiver['contact_number'] ?? '';
    $emergency_contact = trim(($caregiver['emergency_contact_name'] ?? '') . ' ' . ($caregiver['emergency_contact_number'] ? '(' . $caregiver['emergency_contact_number'] . ')' : ''));
    // Some caregivers table may include date_of_birth
    if (!empty($caregiver['date_of_birth'])) $dob = $caregiver['date_of_birth'];
}

// Compute age display if DOB known
if (!empty($dob)) {
    $dob_dt = date_create($dob);
    if ($dob_dt) {
        $now = new DateTime('now');
        $diff = $dob_dt->diff($now);
        $age_display = $diff->y . ' yrs';
    }
}

// Fetch prenatal health records and upcoming next visit
$prenatal_visits = [];
$postnatal_visits = [];
$next_prenatal = null;
if ($mother_id !== null) {
    if ($stmth = $mysqli->prepare("SELECT 
            health_record_id,
            consultation_date,
            age,
            height_cm,
            last_menstruation_date,
            expected_delivery_date,
            pregnancy_age_weeks,
            vaginal_bleeding,
            urinary_infection,
            weight_kg,
            blood_pressure_systolic,
            blood_pressure_diastolic,
            high_blood_pressure,
            fever_38_celsius,
            pallor,
            abnormal_abdominal_size,
            abnormal_presentation,
            absent_fetal_heartbeat,
            swelling,
            vaginal_infection,
            hgb_result,
            urine_result,
            vdrl_result,
            other_lab_results,
            iron_folate_prescription,
            iron_folate_notes,
            additional_iodine,
            additional_iodine_notes,
            malaria_prophylaxis,
            malaria_prophylaxis_notes,
            breastfeeding_plan,
            breastfeeding_plan_notes,
            dental_checkup,
            dental_checkup_notes,
            emergency_plan,
            emergency_plan_notes,
            general_risk,
            general_risk_notes,
            next_visit_date
        FROM health_records
        WHERE mother_id = ?
        ORDER BY consultation_date DESC, health_record_id DESC
        LIMIT 10")) {
        $stmth->bind_param('i', $mother_id);
        $stmth->execute();
        $resh = $stmth->get_result();
        while ($hr = $resh->fetch_assoc()) {
            $bpCat = bp_category($hr['blood_pressure_systolic'], $hr['blood_pressure_diastolic']);
            $heightCm = isset($hr['height_cm']) ? (float)$hr['height_cm'] : null;
            $weightKg = isset($hr['weight_kg']) ? (float)$hr['weight_kg'] : null;
            $bmi = null;
            if ($heightCm && $heightCm > 0 && $weightKg && $weightKg > 0) {
                $m = $heightCm / 100.0;
                $bmi = $m > 0 ? ($weightKg / ($m*$m)) : null;
            }
            // Aggregate danger signs
            $danger = [];
            if (!empty($hr['vaginal_bleeding'])) $danger[] = 'Vaginal bleeding';
            if (!empty($hr['high_blood_pressure'])) $danger[] = 'High blood pressure';
            if (!empty($hr['fever_38_celsius'])) $danger[] = 'Fever ≥ 38°C';
            if (!empty($hr['pallor'])) $danger[] = 'Pallor';
            if (!empty($hr['abnormal_abdominal_size'])) $danger[] = 'Abnormal abdominal size';
            if (!empty($hr['abnormal_presentation'])) $danger[] = 'Abnormal presentation';
            if (!empty($hr['absent_fetal_heartbeat'])) $danger[] = 'Absent fetal heartbeat';
            if (!empty($hr['swelling'])) $danger[] = 'Swelling';
            if (!empty($hr['vaginal_infection'])) $danger[] = 'Vaginal infection';
            if (!empty($hr['urinary_infection'])) $danger[] = 'Urinary infection';

            $details = [
                'Last Menstruation (LMP)' => ($hr['last_menstruation_date'] ?? null) ? date('M d, Y', strtotime($hr['last_menstruation_date'])) : 'N/A',
                'Estimated Delivery Date (EDD)' => ($hr['expected_delivery_date'] ?? null) ? date('M d, Y', strtotime($hr['expected_delivery_date'])) : 'N/A',
                'Mother Age' => isset($hr['age']) ? (string)$hr['age'] . ' yrs' : 'N/A',
                'Height' => $heightCm ? number_format($heightCm, 1) . ' cm' : 'N/A',
                'BMI' => $bmi ? number_format($bmi, 1) : 'N/A',
                'Lab - Hemoglobin (Hgb)' => isset($hr['hgb_result']) && $hr['hgb_result'] !== '' ? (string)$hr['hgb_result'] : 'N/A',
                'Lab - Urine' => isset($hr['urine_result']) && $hr['urine_result'] !== '' ? (string)$hr['urine_result'] : 'N/A',
                'Lab - VDRL' => isset($hr['vdrl_result']) && $hr['vdrl_result'] !== '' ? (string)$hr['vdrl_result'] : 'N/A',
                'Other Lab Results' => isset($hr['other_lab_results']) && $hr['other_lab_results'] !== '' ? (string)$hr['other_lab_results'] : 'N/A',
                'Danger Signs' => !empty($danger) ? implode(', ', $danger) : 'None',
                'Intervention - Iron/Folate' => !empty($hr['iron_folate_prescription']) ? 'Yes' : 'No',
                'IFA Notes' => isset($hr['iron_folate_notes']) && $hr['iron_folate_notes'] !== '' ? (string)$hr['iron_folate_notes'] : '—',
                'Intervention - Iodine' => !empty($hr['additional_iodine']) ? 'Yes' : 'No',
                'Iodine Notes' => isset($hr['additional_iodine_notes']) && $hr['additional_iodine_notes'] !== '' ? (string)$hr['additional_iodine_notes'] : '—',
                'Intervention - Malaria Prophylaxis' => !empty($hr['malaria_prophylaxis']) ? 'Yes' : 'No',
                'Malaria Notes' => isset($hr['malaria_prophylaxis_notes']) && $hr['malaria_prophylaxis_notes'] !== '' ? (string)$hr['malaria_prophylaxis_notes'] : '—',
                'Counseling - Breastfeeding Plan' => !empty($hr['breastfeeding_plan']) ? 'Yes' : 'No',
                'Breastfeeding Notes' => isset($hr['breastfeeding_plan_notes']) && $hr['breastfeeding_plan_notes'] !== '' ? (string)$hr['breastfeeding_plan_notes'] : '—',
                'Dental Checkup' => !empty($hr['dental_checkup']) ? 'Yes' : 'No',
                'Dental Notes' => isset($hr['dental_checkup_notes']) && $hr['dental_checkup_notes'] !== '' ? (string)$hr['dental_checkup_notes'] : '—',
                'Emergency Plan' => !empty($hr['emergency_plan']) ? 'Yes' : 'No',
                'Emergency Notes' => isset($hr['emergency_plan_notes']) && $hr['emergency_plan_notes'] !== '' ? (string)$hr['emergency_plan_notes'] : '—',
                'General Risk Notes' => isset($hr['general_risk_notes']) && $hr['general_risk_notes'] !== '' ? (string)$hr['general_risk_notes'] : '—',
            ];
            $prenatal_visits[] = [
                'id' => (int)$hr['health_record_id'],
                'date' => date('M d, Y', strtotime($hr['consultation_date'])),
                'weeks' => $hr['pregnancy_age_weeks'],
                'weight' => is_null($hr['weight_kg']) ? '' : number_format((float)$hr['weight_kg'], 1) . ' kg',
                'bp' => (!is_null($hr['blood_pressure_systolic']) && !is_null($hr['blood_pressure_diastolic'])) ? ($hr['blood_pressure_systolic'] . '/' . $hr['blood_pressure_diastolic'] . ' mmHg') : '',
                'bp_cat' => $bpCat,
                'next' => $hr['next_visit_date'] ? date('M d, Y', strtotime($hr['next_visit_date'])) : '',
                'details' => $details,
            ];
        }
        $stmth->close();
    }
    if ($stmtnext = $mysqli->prepare("SELECT MIN(next_visit_date) AS next_visit FROM health_records WHERE mother_id = ? AND next_visit_date IS NOT NULL AND next_visit_date >= CURDATE()")) {
        $stmtnext->bind_param('i', $mother_id);
        $stmtnext->execute();
        $resn = $stmtnext->get_result();
        if ($nv = $resn->fetch_assoc()) { if (!empty($nv['next_visit'])) $next_prenatal = date('M d, Y', strtotime($nv['next_visit'])); }
        $stmtnext->close();
    }
    if ($stmtpn = $mysqli->prepare("SELECT postnatal_visit_id, visit_date, postpartum_day, bp_systolic, bp_diastolic, temperature_c, danger_signs FROM postnatal_visits WHERE mother_id = ? ORDER BY visit_date DESC, postnatal_visit_id DESC LIMIT 10")) {
        $stmtpn->bind_param('i', $mother_id);
        $stmtpn->execute();
        $respn = $stmtpn->get_result();
        while ($pn = $respn->fetch_assoc()) {
            $tempNum = is_null($pn['temperature_c']) ? null : (float)$pn['temperature_c'];
            $postnatal_visits[] = [
                'id' => (int)$pn['postnatal_visit_id'],
                'date' => date('M d, Y', strtotime($pn['visit_date'])),
                'pp_day' => $pn['postpartum_day'],
                'bp' => (!is_null($pn['bp_systolic']) && !is_null($pn['bp_diastolic'])) ? ($pn['bp_systolic'] . '/' . $pn['bp_diastolic'] . ' mmHg') : '',
                'temp' => is_null($pn['temperature_c']) ? '' : number_format((float)$pn['temperature_c'], 1) . ' °C',
                'temp_num' => $tempNum,
                'danger' => $pn['danger_signs'] ?? ''
            ];
        }
        $stmtpn->close();
    }
}

// Build quick stats
$stats = [
    'last_prenatal' => !empty($prenatal_visits) ? ($prenatal_visits[0]['date'] ?? null) : null,
    'next_prenatal' => $next_prenatal,
    'count_prenatal' => count($prenatal_visits),
    'count_postnatal' => count($postnatal_visits),
    'risk' => 'None',
    'risk_color' => '#10b981', // green
];

$riskLevel = 0; // 0 none, 1 monitor, 2 high
foreach ($prenatal_visits as $pvcalc) {
    $lbl = $pvcalc['bp_cat']['label'] ?? '';
    if ($lbl === 'Crisis' || $lbl === 'Stage 2') { $riskLevel = max($riskLevel, 2); }
    elseif ($lbl === 'Stage 1' || $lbl === 'Elevated') { $riskLevel = max($riskLevel, 1); }
}
foreach ($postnatal_visits as $pncalc) {
    if (!empty($pncalc['danger'])) { $riskLevel = max($riskLevel, 2); }
    if (!is_null($pncalc['temp_num']) && $pncalc['temp_num'] >= 38.0) { $riskLevel = max($riskLevel, 1); }
}
if ($riskLevel === 2) { $stats['risk'] = 'High-risk'; $stats['risk_color'] = '#ef4444'; }
elseif ($riskLevel === 1) { $stats['risk'] = 'Monitor'; $stats['risk_color'] = '#f59e0b'; }

// ... further code for children immunization, overdue, upcoming, etc. goes here ...
?>

<div class="space-y-6">
    <div class="flex items-center justify-start">
        <div>
            <h2 class="text-xl font-medium">Parent Profile</h2>
            <p class="text-sm" style="color:#6b7280;">Personal details and checkups</p>
        </div>
    </div>

    <!-- Profile Card -->
    <div class="bg-white rounded-xl shadow-sm overflow-hidden" style="border: 1px solid #e5e7eb;">
        <div class="p-6" style="background: linear-gradient(to right, rgba(59, 130, 246, 0.08), rgba(16, 185, 129, 0.08));">
            <div class="flex items-center gap-4">
                <div class="h-12 w-12 rounded-full bg-blue-500 text-white flex items-center justify-center text-lg font-semibold shadow-sm">
                    <?php echo htmlspecialchars($initials); ?>
                </div>
                <div>
                    <h3 class="font-medium"><?php echo htmlspecialchars($parent['name'] ?: 'Parent'); ?></h3>
                    <p class="text-sm" style="color:#6b7280;">Barangay: <?php echo htmlspecialchars($parent['barangay'] ?: 'N/A'); ?></p>
                    <?php if (!empty($parent['children'])): ?>
                        <p class="text-sm mt-1" style="color:#6b7280;">Children: <?php echo htmlspecialchars(implode(', ', array_map(function($c){ return $c['name']; }, $parent['children']))); ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="p-6">
            <div class="grid gap-4 md:grid-cols-3">
                <div>
                    <p class="text-sm" style="color:#6b7280;">Email</p>
                    <p class="font-medium"><?php echo htmlspecialchars($parent['email'] ?: 'N/A'); ?></p>
                </div>
                <div>
                    <p class="text-sm" style="color:#6b7280;">Contact Number</p>
                    <p class="font-medium"><?php echo htmlspecialchars($contact_number ?: 'N/A'); ?></p>
                </div>
                <div>
                    <p class="text-sm" style="color:#6b7280;">Address</p>
                    <p class="font-medium"><?php echo htmlspecialchars($address); ?></p>
                </div>
                <div>
                    <p class="text-sm" style="color:#6b7280;">Blood Type</p>
                    <p class="font-medium"><?php echo htmlspecialchars($blood_type ?: 'N/A'); ?></p>
                </div>
                <div>
                    <p class="text-sm" style="color:#6b7280;">Date of Birth</p>
                    <p class="font-medium">
                        <?php echo htmlspecialchars(!empty($dob) ? date('M d, Y', strtotime($dob)) : 'N/A'); ?>
                        <?php if (!empty($age_display)): ?>
                            <span class="text-sm" style="color:#6b7280;">(<?php echo htmlspecialchars($age_display); ?>)</span>
                        <?php endif; ?>
                    </p>
                </div>
                <div>
                    <p class="text-sm" style="color:#6b7280;">Gravida / Para</p>
                    <p class="font-medium"><?php
                        $gp = [];
                        if (!is_null($gravida)) { $gp[] = 'G' . (string)$gravida; }
                        if (!is_null($para)) { $gp[] = 'P' . (string)$para; }
                        echo htmlspecialchars(!empty($gp) ? implode(' / ', $gp) : 'N/A');
                    ?></p>
                </div>
            </div>
            <?php if (!empty($emergency_contact)): ?>
            <div class="mt-4">
                <p class="text-sm" style="color:#6b7280;">Emergency Contact</p>
                <p class="font-medium"><?php echo htmlspecialchars($emergency_contact); ?></p>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Quick Stats -->
    <div class="grid gap-4 md:grid-cols-4">
        <div class="bg-white rounded-xl shadow-sm p-4" style="border:1px solid #e5e7eb;">
            <p class="text-sm" style="color:#6b7280;">Last Prenatal</p>
            <p class="mt-1 font-medium"><?php echo htmlspecialchars($stats['last_prenatal'] ?: 'N/A'); ?></p>
        </div>
        <div class="bg-white rounded-xl shadow-sm p-4" style="border:1px solid #e5e7eb;">
            <p class="text-sm" style="color:#6b7280;">Next Prenatal</p>
            <p class="mt-1 font-medium"><?php echo htmlspecialchars($stats['next_prenatal'] ?: 'N/A'); ?></p>
        </div>
        <div class="bg-white rounded-xl shadow-sm p-4" style="border:1px solid #e5e7eb;">
            <p class="text-sm" style="color:#6b7280;">Prenatal Visits</p>
            <p class="mt-1 font-medium"><?php echo (int)$stats['count_prenatal']; ?></p>
        </div>
        <div class="bg-white rounded-xl shadow-sm p-4" style="border:1px solid #e5e7eb;">
            <p class="text-sm" style="color:#6b7280;">Postnatal Visits</p>
            <div class="mt-1 flex items-center gap-2">
                <p class="font-medium" style="min-width:2rem;"><?php echo (int)$stats['count_postnatal']; ?></p>
                <span class="text-xs px-2 py-1 rounded-full" style="background-color: <?php echo $stats['risk_color']; ?>; color:white;"><?php echo htmlspecialchars($stats['risk']); ?></span>
            </div>
        </div>
    </div>

    <!-- Prenatal Checkups -->
    <div class="bg-white rounded-xl shadow-sm p-6" style="border:1px solid #e5e7eb;">
        <h3 class="font-medium mb-2">Prenatal Checkups</h3>
        <?php if ($mother_id === null): ?>
            <p class="text-sm" style="color:#6b7280;">No maternal profile linked to this account yet.</p>
        <?php else: ?>
            <?php if (empty($prenatal_visits)): ?>
                <p class="text-sm" style="color:#6b7280;">No prenatal records found.</p>
            <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="border-b" style="border-color:#e5e7eb;">
                            <th class="text-left py-3 px-4 font-medium">Date</th>
                            <th class="text-left py-3 px-4 font-medium">Pregnancy Age (weeks)</th>
                            <th class="text-left py-3 px-4 font-medium">Weight</th>
                            <th class="text-left py-3 px-4 font-medium">Blood Pressure</th>
                            <th class="text-left py-3 px-4 font-medium">Next Visit</th>
                            <th class="text-left py-3 px-4 font-medium">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($prenatal_visits as $pv): ?>
                        <tr class="border-b" style="border-color:#e5e7eb;">
                            <td class="py-3 px-4"><?php echo htmlspecialchars($pv['date']); ?></td>
                            <td class="py-3 px-4"><?php echo htmlspecialchars((string)$pv['weeks']); ?></td>
                            <td class="py-3 px-4"><?php echo htmlspecialchars($pv['weight']); ?></td>
                            <td class="py-3 px-4">
                                <?php if (!empty($pv['bp'])): ?>
                                    <div><?php echo htmlspecialchars($pv['bp']); ?></div>
                                    <?php if (!empty($pv['bp_cat']['label'])): ?>
                                        <span class="inline-flex items-center mt-1 text-xs px-2 py-0.5 rounded-full" style="background-color: <?php echo $pv['bp_cat']['color']; ?>; color: #fff;"><?php echo htmlspecialchars($pv['bp_cat']['label']); ?></span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span style="color:#6b7280;">N/A</span>
                                <?php endif; ?>
                            </td>
                            <td class="py-3 px-4"><?php echo htmlspecialchars($pv['next']); ?></td>
                            <td class="py-3 px-4">
                                <?php
                                    $payload = array_merge([
                                        'type' => 'Prenatal Checkup',
                                        'Date' => $pv['date'],
                                        'Pregnancy Age (weeks)' => (string)$pv['weeks'],
                                        'Weight' => $pv['weight'] ?: 'N/A',
                                        'Blood Pressure' => $pv['bp'] ?: 'N/A',
                                        'BP Category' => $pv['bp_cat']['label'] ?? '—',
                                        'Next Visit' => $pv['next'] ?: 'N/A',
                                    ], $pv['details'] ?? []);
                                    $json = htmlspecialchars(json_encode($payload), ENT_QUOTES, 'UTF-8');
                                ?>
                                <button class="text-sm px-3 py-1 rounded-md" style="background-color:#eff6ff;color:#1d4ed8;" data-payload="<?php echo $json; ?>" onclick="openRecordModal(this)">View</button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <!-- Postnatal Visits -->
    <div class="bg-white rounded-xl shadow-sm p-6" style="border:1px solid #e5e7eb;">
        <h3 class="font-medium mb-2">Postnatal Visits</h3>
        <?php if ($mother_id === null): ?>
            <p class="text-sm" style="color:#6b7280;">No maternal profile linked to this account yet.</p>
        <?php else: ?>
            <?php if (empty($postnatal_visits)): ?>
                <p class="text-sm" style="color:#6b7280;">No postnatal records found.</p>
            <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="border-b" style="border-color:#e5e7eb;">
                            <th class="text-left py-3 px-4 font-medium">Visit Date</th>
                            <th class="text-left py-3 px-4 font-medium">Postpartum Day</th>
                            <th class="text-left py-3 px-4 font-medium">Blood Pressure</th>
                            <th class="text-left py-3 px-4 font-medium">Temperature</th>
                            <th class="text-left py-3 px-4 font-medium">Danger Signs</th>
                            <th class="text-left py-3 px-4 font-medium">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($postnatal_visits as $pn): ?>
                        <tr class="border-b" style="border-color:#e5e7eb;">
                            <td class="py-3 px-4"><?php echo htmlspecialchars($pn['date']); ?></td>
                            <td class="py-3 px-4"><?php echo htmlspecialchars((string)$pn['pp_day']); ?></td>
                            <td class="py-3 px-4"><?php echo htmlspecialchars($pn['bp']); ?></td>
                            <td class="py-3 px-4"><?php echo htmlspecialchars($pn['temp']); ?></td>
                            <td class="py-3 px-4"><?php echo htmlspecialchars($pn['danger']); ?></td>
                            <td class="py-3 px-4">
                                <?php
                                    $payload = [
                                        'type' => 'Postnatal Visit',
                                        'Visit Date' => $pn['date'],
                                        'Postpartum Day' => (string)$pn['pp_day'],
                                        'Blood Pressure' => $pn['bp'] ?: 'N/A',
                                        'Temperature' => $pn['temp'] ?: 'N/A',
                                        'Danger Signs' => $pn['danger'] ?: 'None',
                                    ];
                                    $json = htmlspecialchars(json_encode($payload), ENT_QUOTES, 'UTF-8');
                                ?>
                                <button class="text-sm px-3 py-1 rounded-md" style="background-color:#eff6ff;color:#1d4ed8;" data-payload="<?php echo $json; ?>" onclick="openRecordModal(this)">View</button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Record Details Modal -->
<div id="recordModal" class="fixed inset-0 hidden" aria-hidden="true">
    <div class="absolute inset-0" style="background: rgba(0,0,0,0.5);"></div>
    <div class="relative max-w-xl w-[90%] mx-auto mt-24 bg-white rounded-xl shadow-lg" style="border:1px solid #e5e7eb; max-height:85vh; display:flex; flex-direction:column; overflow:hidden;">
        <div class="flex items-center justify-between px-4 py-3" style="border-bottom:1px solid #e5e7eb; flex:0 0 auto;">
            <h4 id="recordModalTitle" class="font-medium">Record Details</h4>
            <button onclick="closeRecordModal()" aria-label="Close" class="px-2 py-1 text-sm rounded" style="color:#374151;">✕</button>
        </div>
        <div id="recordModalBody" class="p-4" style="overflow-y:auto; flex:1 1 auto;">
            <!-- dynamic content -->
        </div>
        <div class="px-4 py-3" style="border-top:1px solid #e5e7eb; flex:0 0 auto;">
            <button onclick="closeRecordModal()" class="px-4 py-2 rounded-md" style="background-color:#1d4ed8;color:#fff;">Close</button>
        </div>
    </div>
    <script>
        function openRecordModal(btn) {
            try {
                const payload = JSON.parse(btn.getAttribute('data-payload'));
                const title = payload.type || 'Record Details';
                const body = document.getElementById('recordModalBody');
                const titleEl = document.getElementById('recordModalTitle');
                titleEl.textContent = title;
                // Build details grid
                const entries = Object.entries(payload).filter(([k]) => k !== 'type');
                let html = '<div class="grid gap-3 md:grid-cols-2">';
                for (const [k, v] of entries) {
                    const val = (v === null || v === undefined || v === '') ? 'N/A' : v;
                    html += `\n<div><p class="text-xs" style="color:#6b7280;">${k}</p><p class="font-medium">${val}</p></div>`;
                }
                html += '\n</div>';
                body.innerHTML = html;
                document.getElementById('recordModal').classList.remove('hidden');
            } catch (e) {
                console.error('Failed to open record modal', e);
            }
        }
        function closeRecordModal(){
            document.getElementById('recordModal').classList.add('hidden');
        }
        // Close when clicking backdrop
        (function(){
            const modal = document.getElementById('recordModal');
            modal.addEventListener('click', function(e){
                if (e.target === modal || e.target.classList.contains('absolute')) {
                    closeRecordModal();
                }
            });
        })();
    </script>
</div>