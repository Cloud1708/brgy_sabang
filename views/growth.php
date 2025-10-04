<?php
$nutrition_status = [
    ['child' => 'Emma Johnson', 'age' => '3 years', 'weight' => '14.5 kg', 'height' => '95 cm', 'status' => 'Normal', 'color' => '#10b981'],
    ['child' => 'Noah Johnson', 'age' => '6 months', 'weight' => '7.8 kg', 'height' => '67 cm', 'status' => 'Normal', 'color' => '#10b981'],
];

$supplement_records = [
    ['supplement' => 'Vitamin A', 'date' => 'Sep 28, 2025', 'dosage' => '100,000 IU', 'status' => 'Administered', 'provider' => 'Barangay Health'],
    ['supplement' => 'Iron', 'date' => 'Aug 15, 2025', 'dosage' => '30mg', 'status' => 'Administered', 'provider' => 'City Clinic'],
    ['supplement' => 'Deworming', 'date' => 'Aug 30, 2025', 'dosage' => '400mg', 'status' => 'Completed', 'provider' => 'Barangay Health'],
    ['supplement' => 'Vitamin A', 'date' => 'Mar 20, 2025', 'dosage' => '100,000 IU', 'status' => 'Administered', 'provider' => 'Health Center'],
];
?>

<div class="space-y-6">
    <!-- Page Header -->
    <div>
        <h2 class="text-xl font-medium">Growth & Nutrition Monitoring</h2>
        <p class="text-sm" style="color: #6b7280;">Track your children's development and nutritional status</p>
    </div>

    <!-- Nutrition Status Cards -->
    <div class="grid gap-6 md:grid-cols-2">
        <?php foreach ($nutrition_status as $child): ?>
        <div class="bg-white rounded-xl shadow-sm" style="border: 1px solid #e5e7eb;">
            <div class="p-6">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h3 class="font-medium"><?php echo $child['child']; ?></h3>
                        <p class="text-sm" style="color: #6b7280;"><?php echo $child['age']; ?></p>
                    </div>
                    <span class="px-3 py-1 rounded-full text-white text-sm font-medium" style="background-color: <?php echo $child['color']; ?>;">
                        <?php echo $child['status']; ?>
                    </span>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div class="flex items-center gap-3 p-3 rounded-lg" style="background-color: rgba(59, 130, 246, 0.1);">
                        <i data-lucide="scale" class="w-8 h-8" style="color: #3b82f6;"></i>
                        <div>
                            <p class="text-sm" style="color: #6b7280;">Weight</p>
                            <p class="font-medium"><?php echo $child['weight']; ?></p>
                        </div>
                    </div>
                    <div class="flex items-center gap-3 p-3 rounded-lg" style="background-color: rgba(16, 185, 129, 0.1);">
                        <i data-lucide="ruler" class="w-8 h-8" style="color: #10b981;"></i>
                        <div>
                            <p class="text-sm" style="color: #6b7280;">Height</p>
                            <p class="font-medium"><?php echo $child['height']; ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Growth Charts -->
    <div class="bg-white rounded-xl shadow-sm p-6" style="border: 1px solid #e5e7eb;">
        <div class="flex items-center justify-between mb-6">
            <div>
                <h3 class="font-medium">Weight Progress Chart</h3>
                <p class="text-sm" style="color: #6b7280;">Emma Johnson - Last 7 months</p>
            </div>
            <i data-lucide="trending-up" class="w-8 h-8" style="color: #10b981;"></i>
        </div>
        <canvas id="weightChart" height="80"></canvas>
        <div class="mt-4 p-4 rounded-lg" style="background-color: rgba(16, 185, 129, 0.1);">
            <p class="flex items-center gap-2">
                <i data-lucide="trending-up" class="w-4 h-4" style="color: #10b981;"></i>
                <span>Great progress! Emma is gaining weight steadily and is within the healthy range.</span>
            </p>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm p-6" style="border: 1px solid #e5e7eb;">
        <div class="flex items-center justify-between mb-6">
            <div>
                <h3 class="font-medium">Height Development Chart</h3>
                <p class="text-sm" style="color: #6b7280;">Emma Johnson - Last 7 months</p>
            </div>
            <i data-lucide="ruler" class="w-8 h-8" style="color: #3b82f6;"></i>
        </div>
        <canvas id="heightChart" height="80"></canvas>
        <div class="mt-4 p-4 rounded-lg" style="background-color: rgba(59, 130, 246, 0.1);">
            <p class="flex items-center gap-2">
                <i data-lucide="trending-up" class="w-4 h-4" style="color: #3b82f6;"></i>
                <span>Excellent growth! Emma's height is developing according to the recommended curve.</span>
            </p>
        </div>
    </div>

    <!-- Supplementation Records -->
    <div class="bg-white rounded-xl shadow-sm p-6" style="border: 1px solid #e5e7eb;">
        <div class="flex items-center justify-between mb-6">
            <div>
                <h3 class="font-medium">Supplementation Records</h3>
                <p class="text-sm" style="color: #6b7280;">Vitamin A, Iron, Deworming, and other supplements</p>
            </div>
            <div class="text-3xl">🍎</div>
        </div>
        <div class="space-y-3">
            <?php foreach ($supplement_records as $record): ?>
            <div class="flex items-center justify-between p-4 rounded-lg" style="background-color: rgba(229, 231, 235, 0.5);">
                <div class="flex items-center gap-4">
                    <div class="p-2 rounded-full" style="background-color: rgba(245, 158, 11, 0.2);">
                        <i data-lucide="apple" class="w-5 h-5" style="color: #f59e0b;"></i>
                    </div>
                    <div>
                        <p class="font-medium"><?php echo $record['supplement']; ?></p>
                        <p class="text-sm" style="color: #6b7280;"><?php echo $record['date']; ?> • <?php echo $record['dosage']; ?></p>
                    </div>
                </div>
                <div class="text-right">
                    <span class="px-3 py-1 rounded-full text-sm font-medium" style="background-color: #10b981; color: white;">
                        <?php echo $record['status']; ?>
                    </span>
                    <p class="text-sm mt-1" style="color: #6b7280;"><?php echo $record['provider']; ?></p>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Nutrition Classification -->
    <div class="bg-white rounded-xl shadow-sm" style="border: 1px solid rgba(16, 185, 129, 0.5);">
        <div class="p-6" style="background-color: rgba(16, 185, 129, 0.1);">
            <h3 class="font-medium mb-2">Nutrition Status Classification</h3>
            <p class="text-sm" style="color: #6b7280;">Understanding your child's nutritional health</p>
        </div>
        <div class="p-6">
            <div class="grid gap-3 md:grid-cols-2 lg:grid-cols-4">
                <div class="p-3 rounded-lg" style="background-color: rgba(16, 185, 129, 0.1); border: 1px solid rgba(16, 185, 129, 0.3);">
                    <span class="px-2 py-1 rounded-full text-white text-xs font-medium mb-2 inline-block" style="background-color: #10b981;">Normal</span>
                    <p class="text-sm" style="color: #6b7280;">Healthy weight and height for age</p>
                </div>
                <div class="p-3 rounded-lg" style="background-color: rgba(245, 158, 11, 0.1); border: 1px solid rgba(245, 158, 11, 0.3);">
                    <span class="px-2 py-1 rounded-full text-white text-xs font-medium mb-2 inline-block" style="background-color: #f59e0b;">Underweight</span>
                    <p class="text-sm" style="color: #6b7280;">Below healthy weight range</p>
                </div>
                <div class="p-3 rounded-lg" style="background-color: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.3);">
                    <span class="px-2 py-1 rounded-full text-white text-xs font-medium mb-2 inline-block" style="background-color: #ef4444;">Stunted</span>
                    <p class="text-sm" style="color: #6b7280;">Low height for age</p>
                </div>
                <div class="p-3 rounded-lg" style="background-color: rgba(59, 130, 246, 0.1); border: 1px solid rgba(59, 130, 246, 0.3);">
                    <span class="px-2 py-1 rounded-full text-white text-xs font-medium mb-2 inline-block" style="background-color: #3b82f6;">Overweight</span>
                    <p class="text-sm" style="color: #6b7280;">Above healthy weight range</p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Weight Chart
const weightCtx = document.getElementById('weightChart');
if (weightCtx) {
    new Chart(weightCtx, {
        type: 'line',
        data: {
            labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul'],
            datasets: [{
                label: 'Actual Weight',
                data: [12.5, 12.8, 13.2, 13.5, 13.9, 14.2, 14.5],
                borderColor: '#3b82f6',
                backgroundColor: 'rgba(59, 130, 246, 0.1)',
                tension: 0.4
            }, {
                label: 'Ideal Weight',
                data: [13.0, 13.2, 13.5, 13.8, 14.0, 14.3, 14.5],
                borderColor: '#10b981',
                backgroundColor: 'rgba(16, 185, 129, 0.1)',
                borderDash: [5, 5],
                tension: 0.4
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'bottom'
                }
            },
            scales: {
                y: {
                    beginAtZero: false,
                    title: {
                        display: true,
                        text: 'Weight (kg)'
                    }
                }
            }
        }
    });
}

// Height Chart
const heightCtx = document.getElementById('heightChart');
if (heightCtx) {
    new Chart(heightCtx, {
        type: 'bar',
        data: {
            labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul'],
            datasets: [{
                label: 'Actual Height',
                data: [88, 89, 90, 92, 93, 94, 95],
                backgroundColor: '#3b82f6'
            }, {
                label: 'Ideal Height',
                data: [89, 90, 91, 92, 93, 94, 95],
                backgroundColor: 'rgba(16, 185, 129, 0.5)'
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'bottom'
                }
            },
            scales: {
                y: {
                    beginAtZero: false,
                    title: {
                        display: true,
                        text: 'Height (cm)'
                    }
                }
            }
        }
    });
}
</script>