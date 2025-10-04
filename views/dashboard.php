<?php
$children = [
    [
        'id' => 1,
        'name' => 'Emma Johnson',
        'age' => '3 years',
        'photo' => '👧',
        'health_status' => 'Excellent',
        'status_color' => '#10b981',
        'next_vaccine' => 'MMR Booster - Nov 15, 2025',
        'weight' => '14.5 kg',
        'height' => '95 cm'
    ],
    [
        'id' => 2,
        'name' => 'Noah Johnson',
        'age' => '6 months',
        'photo' => '👶',
        'health_status' => 'Good',
        'status_color' => '#3b82f6',
        'next_vaccine' => '6-month vaccines - Oct 20, 2025',
        'weight' => '7.8 kg',
        'height' => '67 cm'
    ]
];

$milestones = [
    ['child' => 'Emma Johnson', 'milestone' => 'Complete BCG Vaccination', 'date' => 'Jan 2023', 'icon' => '💉'],
    ['child' => 'Emma Johnson', 'milestone' => 'First Growth Check', 'date' => 'Feb 2023', 'icon' => '📏'],
    ['child' => 'Noah Johnson', 'milestone' => 'Birth Registration', 'date' => 'Apr 2025', 'icon' => '📋'],
    ['child' => 'Noah Johnson', 'milestone' => '2-month Vaccines', 'date' => 'Jun 2025', 'icon' => '💉'],
];

$recent_activities = [
    ['date' => 'Oct 1, 2025', 'activity' => 'Weight & Height Check - Noah', 'status' => 'Normal growth', 'icon' => 'activity'],
    ['date' => 'Sep 28, 2025', 'activity' => 'Vitamin A Supplement - Emma', 'status' => 'Administered', 'icon' => 'heart'],
    ['date' => 'Sep 15, 2025', 'activity' => 'Doctor Consultation - Emma', 'status' => 'Healthy', 'icon' => 'calendar'],
    ['date' => 'Aug 30, 2025', 'activity' => 'Deworming - Emma', 'status' => 'Completed', 'icon' => 'activity'],
];
?>

<div class="space-y-6">
    <!-- Quick Actions -->
    <div class="flex flex-wrap gap-3">
        <button class="px-4 py-2 rounded-lg text-white flex items-center gap-2" style="background-color: #3b82f6;">
            <i data-lucide="plus" class="w-4 h-4"></i>
            Add Child
        </button>
        <button class="px-4 py-2 rounded-lg border flex items-center gap-2 hover:bg-gray-50" style="border-color: #e5e7eb;">
            <i data-lucide="calendar" class="w-4 h-4"></i>
            Update Records
        </button>
        <button class="px-4 py-2 rounded-lg border flex items-center gap-2 hover:bg-gray-50" style="border-color: #e5e7eb;">
            <i data-lucide="download" class="w-4 h-4"></i>
            Download Reports
        </button>
    </div>

    <!-- Child Health Overview -->
    <div>
        <h2 class="mb-4 text-xl font-medium">My Children</h2>
        <div class="grid gap-6 md:grid-cols-2">
            <?php foreach ($children as $child): ?>
            <div class="bg-white rounded-xl shadow-sm overflow-hidden" style="border: 1px solid #e5e7eb;">
                <div class="p-6" style="background: linear-gradient(to right, rgba(59, 130, 246, 0.1), rgba(16, 185, 129, 0.1));">
                    <div class="flex items-start justify-between">
                        <div class="flex items-center gap-3">
                            <div class="text-4xl"><?php echo $child['photo']; ?></div>
                            <div>
                                <h3 class="font-medium"><?php echo $child['name']; ?></h3>
                                <p class="text-sm" style="color: #6b7280;"><?php echo $child['age']; ?></p>
                            </div>
                        </div>
                        <span class="px-3 py-1 rounded-full text-white text-sm font-medium" style="background-color: <?php echo $child['status_color']; ?>;">
                            <?php echo $child['health_status']; ?>
                        </span>
                    </div>
                </div>
                <div class="p-6">
                    <div class="space-y-4">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <p class="text-sm" style="color: #6b7280;">Weight</p>
                                <p class="font-medium"><?php echo $child['weight']; ?></p>
                            </div>
                            <div>
                                <p class="text-sm" style="color: #6b7280;">Height</p>
                                <p class="font-medium"><?php echo $child['height']; ?></p>
                            </div>
                        </div>
                        <div>
                            <p class="text-sm mb-1" style="color: #6b7280;">Next Vaccine</p>
                            <p class="flex items-center gap-2">
                                <i data-lucide="calendar" class="w-4 h-4" style="color: #f59e0b;"></i>
                                <?php echo $child['next_vaccine']; ?>
                            </p>
                        </div>
                        <button class="w-full px-4 py-2 rounded-lg border hover:bg-gray-50" style="border-color: #e5e7eb;">
                            View Full Profile
                        </button>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Health Milestones -->
    <div class="bg-white rounded-xl shadow-sm p-6" style="border: 1px solid #e5e7eb;">
        <div class="flex items-center justify-between mb-6">
            <div>
                <h3 class="font-medium">Health Milestones</h3>
                <p class="text-sm" style="color: #6b7280;">Track your children's health achievements</p>
            </div>
            <i data-lucide="trending-up" class="w-8 h-8" style="color: #10b981;"></i>
        </div>
        <div class="space-y-4">
            <?php foreach ($milestones as $milestone): ?>
            <div class="flex items-center gap-4 p-3 rounded-lg" style="background-color: rgba(16, 185, 129, 0.1);">
                <div class="text-2xl"><?php echo $milestone['icon']; ?></div>
                <div class="flex-1">
                    <p class="font-medium"><?php echo $milestone['milestone']; ?></p>
                    <p class="text-sm" style="color: #6b7280;"><?php echo $milestone['child']; ?> • <?php echo $milestone['date']; ?></p>
                </div>
                <span class="px-3 py-1 rounded-full text-sm font-medium" style="background-color: rgba(16, 185, 129, 0.2); color: #10b981; border: 1px solid #10b981;">
                    ✓ Completed
                </span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Recent Activities -->
    <div class="bg-white rounded-xl shadow-sm p-6" style="border: 1px solid #e5e7eb;">
        <h3 class="font-medium mb-2">Recent Activities</h3>
        <p class="text-sm mb-6" style="color: #6b7280;">Latest health interventions and check-ups</p>
        <div class="space-y-4">
            <?php foreach ($recent_activities as $activity): ?>
            <div class="flex items-center gap-4 pb-4 border-b last:border-0" style="border-color: #e5e7eb;">
                <div class="p-2 rounded-full" style="background-color: rgba(59, 130, 246, 0.1);">
                    <i data-lucide="<?php echo $activity['icon']; ?>" class="w-5 h-5" style="color: #3b82f6;"></i>
                </div>
                <div class="flex-1">
                    <p class="font-medium"><?php echo $activity['activity']; ?></p>
                    <p class="text-sm" style="color: #6b7280;"><?php echo $activity['date']; ?></p>
                </div>
                <span class="px-3 py-1 rounded-full text-sm font-medium" style="background-color: #10b981; color: white;">
                    <?php echo $activity['status']; ?>
                </span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>