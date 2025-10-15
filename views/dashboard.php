<?php
// Ensure session is started for accessing $_SESSION values
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../inc/db.php';

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
    if ($stmth = $mysqli->prepare("SELECT health_record_id, consultation_date, pregnancy_age_weeks, weight_kg, blood_pressure_systolic, blood_pressure_diastolic, next_visit_date FROM health_records WHERE mother_id = ? ORDER BY consultation_date DESC, health_record_id DESC LIMIT 10")) {
        $stmth->bind_param('i', $mother_id);
        $stmth->execute();
        $resh = $stmth->get_result();
        while ($hr = $resh->fetch_assoc()) {
            $prenatal_visits[] = [
                'date' => date('M d, Y', strtotime($hr['consultation_date'])),
                'weeks' => $hr['pregnancy_age_weeks'],
                'weight' => is_null($hr['weight_kg']) ? '' : number_format((float)$hr['weight_kg'], 1) . ' kg',
                'bp' => (!is_null($hr['blood_pressure_systolic']) && !is_null($hr['blood_pressure_diastolic'])) ? ($hr['blood_pressure_systolic'] . '/' . $hr['blood_pressure_diastolic'] . ' mmHg') : '',
                'next' => $hr['next_visit_date'] ? date('M d, Y', strtotime($hr['next_visit_date'])) : ''
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
            $postnatal_visits[] = [
                'date' => date('M d, Y', strtotime($pn['visit_date'])),
                'pp_day' => $pn['postpartum_day'],
                'bp' => (!is_null($pn['bp_systolic']) && !is_null($pn['bp_diastolic'])) ? ($pn['bp_systolic'] . '/' . $pn['bp_diastolic'] . ' mmHg') : '',
                'temp' => is_null($pn['temperature_c']) ? '' : number_format((float)$pn['temperature_c'], 1) . ' °C',
                'danger' => $pn['danger_signs'] ?? ''
            ];
        }
        $stmtpn->close();
    }
}

// ... further code for children immunization, overdue, upcoming, etc. goes here ...
?>

<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-xl font-medium">Parent Profile</h2>
            <p class="text-sm" style="color:#6b7280;">Personal details and checkups</p>
        </div>
        <?php if ($next_prenatal): ?>
        <div class="px-4 py-2 rounded-lg text-white" style="background-color:#3b82f6;">
            Next prenatal visit: <strong><?php echo htmlspecialchars($next_prenatal); ?></strong>
        </div>
        <?php endif; ?>
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
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($prenatal_visits as $pv): ?>
                        <tr class="border-b" style="border-color:#e5e7eb;">
                            <td class="py-3 px-4"><?php echo htmlspecialchars($pv['date']); ?></td>
                            <td class="py-3 px-4"><?php echo htmlspecialchars((string)$pv['weeks']); ?></td>
                            <td class="py-3 px-4"><?php echo htmlspecialchars($pv['weight']); ?></td>
                            <td class="py-3 px-4"><?php echo htmlspecialchars($pv['bp']); ?></td>
                            <td class="py-3 px-4"><?php echo htmlspecialchars($pv['next']); ?></td>
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
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>