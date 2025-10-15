<?php
if (session_status() === PHP_SESSION_NONE) session_start();
// $mysqli provided by parent_portal.php include

// Helpers
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function age_text_from_months(int $months): string {
    if ($months < 12) return $months.' month'.($months===1?'':'s');
    $y = intdiv($months, 12); $m = $months % 12;
    return $y.' year'.($y===1?'':'s').($m>0?" {$m} mo":"");
}
function status_color(string $code): string {
    // Map WFL/HT status codes to colors
    switch (strtoupper($code)) {
        case 'NOR': return '#10b981';    // Normal - green
        case 'UW':  return '#f59e0b';    // Underweight - amber
        case 'OW':  return '#3b82f6';    // Overweight - blue
        case 'OB':  return '#ef4444';    // Obese - red
        case 'MAM': return '#f97316';    // Moderate Acute Malnutrition - orange
        case 'SAM': return '#dc2626';    // Severe Acute Malnutrition - dark red
        case 'ST':  return '#ef4444';    // Stunted - red
        default:    return '#6b7280';    // Unknown - gray
    }
}

$parentId = (int)($_SESSION['user_id'] ?? 0);
$children = [];
if ($parentId > 0) {
    $st = $mysqli->prepare("SELECT c.child_id,c.full_name,c.sex,c.birth_date, TIMESTAMPDIFF(MONTH,c.birth_date,CURDATE()) AS age_months FROM parent_child_access p JOIN children c ON c.child_id=p.child_id WHERE p.parent_user_id=? AND p.is_active=1 ORDER BY c.full_name ASC");
    if ($st) { $st->bind_param('i',$parentId); $st->execute(); $res=$st->get_result(); while($r=$res->fetch_assoc()) $children[]=$r; $st->close(); }
}

// Determine selected child for charts/supplementation
$selected_child_id = 0; $selected_child_name = '';
if ($children) {
    $requested = isset($_GET['child_id']) ? (int)$_GET['child_id'] : 0;
    if ($requested > 0) {
        foreach ($children as $c) { if ((int)$c['child_id'] === $requested) { $selected_child_id=$requested; $selected_child_name=$c['full_name']; break; } }
    }
    if ($selected_child_id === 0) { $selected_child_id = (int)$children[0]['child_id']; $selected_child_name = $children[0]['full_name']; }
}

// Build nutrition status cards (latest per child)
$nutrition_cards = [];
if ($children) {
    $stmt = $mysqli->prepare("SELECT nr.weighing_date,nr.weight_kg,nr.length_height_cm,s.status_code,s.status_description FROM nutrition_records nr LEFT JOIN wfl_ht_status_types s ON s.status_id=nr.wfl_ht_status_id WHERE nr.child_id=? ORDER BY nr.weighing_date DESC, nr.record_id DESC LIMIT 1");
    foreach ($children as $c) {
        $latest = null; $weightTxt='—'; $heightTxt='—'; $code=''; $desc='';
        if ($stmt) { $cid=(int)$c['child_id']; $stmt->bind_param('i',$cid); $stmt->execute(); $res=$stmt->get_result(); if($res && $res->num_rows){ $latest=$res->fetch_assoc(); } $res->free(); }
        if ($latest) {
            if ($latest['weight_kg'] !== null) $weightTxt = number_format((float)$latest['weight_kg'], 2).' kg';
            if ($latest['length_height_cm'] !== null) $heightTxt = number_format((float)$latest['length_height_cm'], 1).' cm';
            $code = (string)($latest['status_code'] ?? '');
            $desc = (string)($latest['status_description'] ?? '');
        }
        $nutrition_cards[] = [
            'child_id' => (int)$c['child_id'],
            'child'    => (string)$c['full_name'],
            'age'      => age_text_from_months((int)$c['age_months']),
            'weight'   => $weightTxt,
            'height'   => $heightTxt,
            'status_code' => $code,
            'status_text' => $desc ?: ($code==='NOR'?'Normal':($code?:'Unknown')),
            'color'    => status_color($code)
        ];
    }
    if ($stmt) $stmt->close();
}

// Chart data for selected child
$labels = []; $weights=[]; $heights=[];
if ($selected_child_id > 0) {
    $s = $mysqli->prepare("SELECT weighing_date, weight_kg, length_height_cm FROM nutrition_records WHERE child_id=? ORDER BY weighing_date ASC, record_id ASC LIMIT 24");
    if ($s) {
        $s->bind_param('i',$selected_child_id); $s->execute(); $r=$s->get_result();
        while($row=$r->fetch_assoc()){
            $dt = $row['weighing_date'] ?? null; $lab='';
            if ($dt && preg_match('/^\d{4}-\d{2}-\d{2}$/',$dt)) { $lab = date('M d', strtotime($dt)); }
            else { $lab = h((string)$dt); }
            $labels[] = $lab;
            $weights[] = isset($row['weight_kg']) && $row['weight_kg']!==null ? (float)$row['weight_kg'] : null;
            $heights[] = isset($row['length_height_cm']) && $row['length_height_cm']!==null ? (float)$row['length_height_cm'] : null;
        }
        $s->close();
    }
}

// Derive selected child's status color to theme the classification box
$selected_status_color = '#10b981'; // default to Normal green
if (!empty($nutrition_cards)) {
    foreach ($nutrition_cards as $nc) {
        if ((int)$nc['child_id'] === (int)$selected_child_id && !empty($nc['color'])) {
            $selected_status_color = $nc['color'];
            break;
        }
    }
}
// Compose semi-transparent variants using 8-digit hex (#RRGGBBAA)
$class_border_color = $selected_status_color.'66'; // ~40% opacity
$class_bg_color     = $selected_status_color.'1A'; // ~10% opacity

?>

<div class="space-y-6">
    <!-- Page Header + Child Selector -->
    <div class="flex flex-col md:flex-row md:items-end md:justify-between gap-3">
        <div>
            <h2 class="text-xl font-medium">Growth & Nutrition Monitoring</h2>
            <p class="text-sm" style="color: #6b7280;">Track your children's development and nutritional status</p>
        </div>
        <?php if (!empty($children)): ?>
        <form method="get" class="flex items-center gap-2">
            <input type="hidden" name="view" value="growth" />
            <label for="child_id" class="text-sm" style="color:#6b7280;">Select Child:</label>
            <select id="child_id" name="child_id" class="border rounded-lg px-3 py-2 text-sm">
                <?php foreach ($children as $c): ?>
                    <option value="<?php echo (int)$c['child_id']; ?>" <?php echo ((int)$c['child_id'] === $selected_child_id)?'selected':''; ?>>
                        <?php echo h($c['full_name']); ?> (<?php echo h(age_text_from_months((int)$c['age_months'])); ?>)
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="px-3 py-2 rounded-lg text-white text-sm" style="background-color:#3b82f6;">View</button>
        </form>
        <?php endif; ?>
    </div>

    <?php if (empty($children)): ?>
        <div class="bg-white rounded-xl shadow-sm p-6" style="border:1px solid #e5e7eb;">
            <p class="text-sm" style="color:#6b7280;">No linked children yet. Please contact your BNS/BHW to link your child account.</p>
        </div>
    <?php endif; ?>

    <!-- Nutrition Status Cards -->
    <?php if (!empty($nutrition_cards)): ?>
    <div class="grid gap-6 md:grid-cols-2">
        <?php foreach ($nutrition_cards as $child): ?>
        <div class="bg-white rounded-xl shadow-sm" style="border: 1px solid #e5e7eb;">
            <div class="p-6">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h3 class="font-medium"><?php echo h($child['child']); ?></h3>
                        <p class="text-sm" style="color: #6b7280;"><?php echo h($child['age']); ?></p>
                    </div>
                    <span class="px-3 py-1 rounded-full text-white text-sm font-medium" style="background-color: <?php echo h($child['color']); ?>;">
                        <?php echo h($child['status_text']); ?>
                    </span>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div class="flex items-center gap-3 p-3 rounded-lg" style="background-color: rgba(59, 130, 246, 0.1);">
                        <i data-lucide="scale" class="w-8 h-8" style="color: #3b82f6;"></i>
                        <div>
                            <p class="text-sm" style="color: #6b7280;">Weight</p>
                            <p class="font-medium"><?php echo h($child['weight']); ?></p>
                        </div>
                    </div>
                    <div class="flex items-center gap-3 p-3 rounded-lg" style="background-color: rgba(16, 185, 129, 0.1);">
                        <i data-lucide="ruler" class="w-8 h-8" style="color: #10b981;"></i>
                        <div>
                            <p class="text-sm" style="color: #6b7280;">Height</p>
                            <p class="font-medium"><?php echo h($child['height']); ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Growth Charts -->
    <div class="grid gap-6 md:grid-cols-2">
        <div class="bg-white rounded-xl shadow-sm p-6" style="border: 1px solid #e5e7eb;">
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h3 class="font-medium">Weight Progress</h3>
                    <p class="text-sm" style="color: #6b7280;">
                        <?php echo $selected_child_id>0 ? h($selected_child_name) : 'Select a child'; ?>
                    </p>
                </div>
                <i data-lucide="trending-up" class="w-8 h-8" style="color: #10b981;"></i>
            </div>
            <?php if (!empty($labels) && array_filter($weights, fn($v)=>$v!==null)): ?>
                <canvas id="weightChart" height="80"></canvas>
            <?php else: ?>
                <div class="p-4 rounded-lg" style="background-color:#f9fafb; border:1px dashed #e5e7eb;">
                    <p class="text-sm" style="color:#6b7280;">No weight records yet for this child.</p>
                </div>
            <?php endif; ?>
        </div>

        <div class="bg-white rounded-xl shadow-sm p-6" style="border: 1px solid #e5e7eb;">
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h3 class="font-medium">Height Development</h3>
                    <p class="text-sm" style="color: #6b7280;">
                        <?php echo $selected_child_id>0 ? h($selected_child_name) : 'Select a child'; ?>
                    </p>
                </div>
                <i data-lucide="ruler" class="w-8 h-8" style="color: #3b82f6;"></i>
            </div>
            <?php if (!empty($labels) && array_filter($heights, fn($v)=>$v!==null)): ?>
                <canvas id="heightChart" height="80"></canvas>
            <?php else: ?>
                <div class="p-4 rounded-lg" style="background-color:#f9fafb; border:1px dashed #e5e7eb;">
                    <p class="text-sm" style="color:#6b7280;">No height/length records yet for this child.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Nutrition Classification -->
    <div class="bg-white rounded-xl shadow-sm" style="border: 1px solid <?php echo h($class_border_color); ?>;">
        <div class="p-6" style="background-color: <?php echo h($class_bg_color); ?>;">
            <h3 class="font-medium mb-2">Nutrition Status Classification</h3>
            <p class="text-sm" style="color: #6b7280;">Understanding your child's nutritional health</p>
        </div>
        <div class="p-6">
            <div class="grid gap-3 md:grid-cols-2 lg:grid-cols-4">
                <!-- NOR: Normal -->
                <div class="p-3 rounded-lg" style="background-color: rgba(16, 185, 129, 0.08); border: 1px solid rgba(16, 185, 129, 0.3);">
                    <span class="px-2 py-1 rounded-full text-white text-xs font-medium mb-2 inline-block" style="background-color: #10b981;">Normal</span>
                    <p class="text-sm" style="color: #6b7280;">Healthy weight-for-length/height</p>
                </div>

                <!-- MAM: Moderate Acute Malnutrition -->
                <div class="p-3 rounded-lg" style="background-color: rgba(249, 115, 22, 0.08); border: 1px solid rgba(249, 115, 22, 0.3);">
                    <span class="px-2 py-1 rounded-full text-white text-xs font-medium mb-2 inline-block" style="background-color: #f97316;">Moderate Acute Malnutrition</span>
                    <p class="text-sm" style="color: #6b7280;">Moderate wasting; requires nutrition support</p>
                </div>

                <!-- SAM: Severe Acute Malnutrition -->
                <div class="p-3 rounded-lg" style="background-color: rgba(220, 38, 38, 0.08); border: 1px solid rgba(220, 38, 38, 0.3);">
                    <span class="px-2 py-1 rounded-full text-white text-xs font-medium mb-2 inline-block" style="background-color: #dc2626;">Severe Acute Malnutrition</span>
                    <p class="text-sm" style="color: #6b7280;">Severe wasting; needs urgent care</p>
                </div>

                <!-- UW: Underweight -->
                <div class="p-3 rounded-lg" style="background-color: rgba(245, 158, 11, 0.08); border: 1px solid rgba(245, 158, 11, 0.3);">
                    <span class="px-2 py-1 rounded-full text-white text-xs font-medium mb-2 inline-block" style="background-color: #f59e0b;">Underweight</span>
                    <p class="text-sm" style="color: #6b7280;">Below healthy weight range</p>
                </div>

                <!-- OW: Overweight -->
                <div class="p-3 rounded-lg" style="background-color: rgba(59, 130, 246, 0.08); border: 1px solid rgba(59, 130, 246, 0.3);">
                    <span class="px-2 py-1 rounded-full text-white text-xs font-medium mb-2 inline-block" style="background-color: #3b82f6;">Overweight</span>
                    <p class="text-sm" style="color: #6b7280;">Above healthy weight range</p>
                </div>

                <!-- OB: Obese -->
                <div class="p-3 rounded-lg" style="background-color: rgba(239, 68, 68, 0.08); border: 1px solid rgba(239, 68, 68, 0.3);">
                    <span class="px-2 py-1 rounded-full text-white text-xs font-medium mb-2 inline-block" style="background-color: #ef4444;">Obese</span>
                    <p class="text-sm" style="color: #6b7280;">Excessive weight for length/height</p>
                </div>

                <!-- ST: Stunted -->
                <div class="p-3 rounded-lg" style="background-color: rgba(239, 68, 68, 0.08); border: 1px solid rgba(239, 68, 68, 0.3);">
                    <span class="px-2 py-1 rounded-full text-white text-xs font-medium mb-2 inline-block" style="background-color: #ef4444;">Stunted</span>
                    <p class="text-sm" style="color: #6b7280;">Low height-for-age (chronic)</p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Data from PHP
const chartLabels = <?php echo json_encode($labels, JSON_UNESCAPED_SLASHES); ?>;
const weightData  = <?php echo json_encode($weights, JSON_UNESCAPED_SLASHES); ?>;
const heightData  = <?php echo json_encode($heights, JSON_UNESCAPED_SLASHES); ?>;

// Weight Chart
const weightCtx = document.getElementById('weightChart');
if (weightCtx && chartLabels.length && weightData.some(v => v !== null)) {
    new Chart(weightCtx, {
        type: 'line',
        data: {
            labels: chartLabels,
            datasets: [{
                label: 'Weight (kg)',
                data: weightData,
                borderColor: '#3b82f6',
                backgroundColor: 'rgba(59, 130, 246, 0.1)',
                spanGaps: false,
                tension: 0.35
            }]
        },
        options: {
            responsive: true,
            plugins: { legend: { position: 'bottom' } },
            scales: {
                y: { beginAtZero: false, title: { display: true, text: 'kg' } }
            }
        }
    });
}

// Height Chart
const heightCtx = document.getElementById('heightChart');
if (heightCtx && chartLabels.length && heightData.some(v => v !== null)) {
    new Chart(heightCtx, {
        type: 'bar',
        data: {
            labels: chartLabels,
            datasets: [{
                label: 'Height/Length (cm)',
                data: heightData,
                backgroundColor: '#3b82f6'
            }]
        },
        options: {
            responsive: true,
            plugins: { legend: { position: 'bottom' } },
            scales: {
                y: { beginAtZero: false, title: { display: true, text: 'cm' } }
            }
        }
    });
}
</script>