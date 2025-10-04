<?php
$vaccine_reminders = [
    ['id' => 1, 'child' => 'Noah Johnson', 'vaccine' => '6-month vaccines', 'date' => 'Oct 20, 2025', 'time' => '10:00 AM', 'location' => 'City Health Center', 'priority' => 'high'],
    ['id' => 2, 'child' => 'Emma Johnson', 'vaccine' => 'MMR Booster', 'date' => 'Nov 15, 2025', 'time' => '2:00 PM', 'location' => 'Barangay Clinic', 'priority' => 'medium'],
];

$appointments = [
    ['id' => 1, 'child' => 'Emma Johnson', 'type' => 'Growth Monitoring', 'date' => 'Oct 10, 2025', 'time' => '9:00 AM', 'provider' => 'Nurse Maria', 'status' => 'confirmed'],
    ['id' => 2, 'child' => 'Noah Johnson', 'type' => 'Routine Check-up', 'date' => 'Oct 25, 2025', 'time' => '11:00 AM', 'provider' => 'Dr. Santos', 'status' => 'pending'],
];

$announcements = [
    ['id' => 1, 'title' => 'Free Vitamin A Supplementation Day', 'content' => 'Barangay-wide Vitamin A distribution on October 10, 2025. Bring your child\'s health card.', 'date' => 'Oct 2, 2025', 'type' => 'event'],
    ['id' => 2, 'title' => 'Health Center Schedule Update', 'content' => 'City Health Center will be closed on Oct 5, 2025 for facility maintenance.', 'date' => 'Oct 1, 2025', 'type' => 'announcement'],
];

$health_education = [
    ['id' => 1, 'title' => 'Nutrition Tips for Toddlers', 'content' => 'Learn about balanced meals for children aged 1-3 years', 'date' => 'Oct 1, 2025', 'category' => 'Nutrition'],
    ['id' => 2, 'title' => 'Importance of Regular Deworming', 'content' => 'Why deworming is crucial for your child\'s health', 'date' => 'Sep 28, 2025', 'category' => 'Health'],
    ['id' => 3, 'title' => 'Preparing for School Immunizations', 'content' => 'Required vaccines before school entry', 'date' => 'Sep 25, 2025', 'category' => 'Immunization'],
];
?>

<div class="space-y-6">
    <!-- Page Header -->
    <div>
        <h2 class="text-xl font-medium">Notification Center</h2>
        <p class="text-sm" style="color: #6b7280;">Stay updated on your children's health appointments and reminders</p>
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
                <?php foreach ($announcements as $announcement): ?>
                <div class="p-4 bg-white rounded-lg border" style="border-color: #e5e7eb;">
                    <div class="flex items-start justify-between mb-2">
                        <h4 class="font-medium"><?php echo $announcement['title']; ?></h4>
                        <span class="px-3 py-1 rounded-full text-sm" style="border: 1px solid #e5e7eb;">
                            <?php echo $announcement['type']; ?>
                        </span>
                    </div>
                    <p class="text-sm mb-2" style="color: #6b7280;"><?php echo $announcement['content']; ?></p>
                    <p class="text-sm" style="color: #6b7280;"><?php echo $announcement['date']; ?></p>
                </div>
                <?php endforeach; ?>
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
            <?php foreach ($vaccine_reminders as $reminder): 
                $bg_color = $reminder['priority'] === 'high' ? 'rgba(239, 68, 68, 0.1)' : 'rgba(245, 158, 11, 0.1)';
                $border_color = $reminder['priority'] === 'high' ? '#ef4444' : '#f59e0b';
            ?>
            <div class="p-4 rounded-lg border-l-4" style="background-color: <?php echo $bg_color; ?>; border-left-color: <?php echo $border_color; ?>;">
                <div class="flex items-start justify-between mb-3">
                    <div>
                        <p class="font-medium mb-1"><?php echo $reminder['vaccine']; ?></p>
                        <p class="text-sm" style="color: #6b7280;"><?php echo $reminder['child']; ?></p>
                    </div>
                    <span class="px-3 py-1 rounded-full text-white text-sm font-medium" style="background-color: <?php echo $border_color; ?>;">
                        <?php echo $reminder['priority'] === 'high' ? 'Urgent' : 'Soon'; ?>
                    </span>
                </div>
                <div class="grid grid-cols-2 gap-2 mb-3">
                    <div class="flex items-center gap-2">
                        <i data-lucide="calendar" class="w-4 h-4"></i>
                        <span><?php echo $reminder['date']; ?></span>
                    </div>
                    <div>
                        <span><?php echo $reminder['time']; ?></span>
                    </div>
                </div>
                <p class="text-sm mb-3" style="color: #6b7280;"><?php echo $reminder['location']; ?></p>
                <div class="flex gap-2">
                    <button class="px-3 py-1 rounded-lg text-white text-sm flex items-center gap-2" style="background-color: #10b981;">
                        <i data-lucide="check" class="w-4 h-4"></i>
                        Confirm
                    </button>
                    <button class="px-3 py-1 rounded-lg border text-sm" style="border-color: #e5e7eb;">
                        Reschedule
                    </button>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Health Appointments -->
    <div class="bg-white rounded-xl shadow-sm p-6" style="border: 1px solid #e5e7eb;">
        <div class="flex items-center gap-3 mb-6">
            <i data-lucide="calendar" class="w-6 h-6" style="color: #3b82f6;"></i>
            <div>
                <h3 class="font-medium">Health Appointment Notifications</h3>
                <p class="text-sm" style="color: #6b7280;">Scheduled consultation reminders</p>
            </div>
        </div>
        <div class="space-y-3">
            <?php foreach ($appointments as $appointment): ?>
            <div class="p-4 rounded-lg border" style="background-color: rgba(59, 130, 246, 0.05); border-color: #e5e7eb;">
                <div class="flex items-start justify-between mb-3">
                    <div>
                        <p class="font-medium mb-1"><?php echo $appointment['type']; ?></p>
                        <p class="text-sm" style="color: #6b7280;"><?php echo $appointment['child']; ?></p>
                    </div>
                    <span class="px-3 py-1 rounded-full text-white text-sm font-medium" 
                          style="background-color: <?php echo $appointment['status'] === 'confirmed' ? '#10b981' : '#f59e0b'; ?>;">
                        <?php echo $appointment['status']; ?>
                    </span>
                </div>
                <div class="grid grid-cols-2 gap-4 mb-2">
                    <div>
                        <p class="text-sm" style="color: #6b7280;">Date & Time</p>
                        <p><?php echo $appointment['date']; ?> at <?php echo $appointment['time']; ?></p>
                    </div>
                    <div>
                        <p class="text-sm" style="color: #6b7280;">Provider</p>
                        <p><?php echo $appointment['provider']; ?></p>
                    </div>
                </div>
                <div class="flex gap-2 mt-3">
                    <button class="px-3 py-1 rounded-lg border text-sm" style="border-color: #e5e7eb;">
                        View Details
                    </button>
                    <button class="px-3 py-1 rounded-lg border text-sm flex items-center gap-2" style="border-color: #e5e7eb;">
                        <i data-lucide="x" class="w-4 h-4"></i>
                        Cancel
                    </button>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Health Education Updates -->
    <div class="bg-white rounded-xl shadow-sm p-6" style="border: 1px solid #e5e7eb;">
        <div class="flex items-center justify-between mb-6">
            <div>
                <h3 class="font-medium">Health Education Updates</h3>
                <p class="text-sm" style="color: #6b7280;">Tips and information to keep your children healthy</p>
            </div>
            <span class="px-3 py-1 rounded-full text-sm" style="border: 1px solid #e5e7eb;">
                <?php echo count($health_education); ?> New
            </span>
        </div>
        <div class="space-y-3">
            <?php foreach ($health_education as $update): ?>
            <div class="p-4 rounded-lg hover:bg-gray-50 transition-colors cursor-pointer" style="background-color: rgba(229, 231, 235, 0.5);">
                <div class="flex items-start justify-between mb-2">
                    <div class="flex-1">
                        <h4 class="font-medium mb-1"><?php echo $update['title']; ?></h4>
                        <p class="text-sm" style="color: #6b7280;"><?php echo $update['content']; ?></p>
                    </div>
                    <span class="px-3 py-1 rounded-full text-sm ml-3" style="background-color: #10b981; color: white;">
                        <?php echo $update['category']; ?>
                    </span>
                </div>
                <p class="text-sm" style="color: #6b7280;"><?php echo $update['date']; ?></p>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>