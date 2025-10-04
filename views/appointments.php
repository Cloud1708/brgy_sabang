<?php
$vaccination_dates = [
    ['id' => 1, 'child' => 'Noah Johnson', 'vaccine' => '6-month vaccines (DPT, Polio)', 'date' => 'Oct 20, 2025', 'time' => '10:00 AM', 'location' => 'City Health Center', 'status' => 'upcoming'],
    ['id' => 2, 'child' => 'Emma Johnson', 'vaccine' => 'MMR Booster', 'date' => 'Nov 15, 2025', 'time' => '2:00 PM', 'location' => 'Barangay Clinic', 'status' => 'scheduled'],
    ['id' => 3, 'child' => 'Noah Johnson', 'vaccine' => '12-month vaccines', 'date' => 'Apr 15, 2026', 'time' => '9:00 AM', 'location' => 'City Health Center', 'status' => 'scheduled'],
];

$health_checkups = [
    ['id' => 1, 'child' => 'Emma Johnson', 'type' => 'Annual Physical Exam', 'date' => 'Oct 10, 2025', 'time' => '9:00 AM', 'provider' => 'Dr. Santos', 'location' => 'Family Clinic', 'status' => 'confirmed'],
    ['id' => 2, 'child' => 'Noah Johnson', 'type' => '6-month Wellness Check', 'date' => 'Oct 25, 2025', 'time' => '11:00 AM', 'provider' => 'Nurse Maria', 'location' => 'Health Center', 'status' => 'pending'],
    ['id' => 3, 'child' => 'Emma Johnson', 'type' => 'Dental Check-up', 'date' => 'Nov 5, 2025', 'time' => '3:00 PM', 'provider' => 'Dr. Cruz', 'location' => 'Dental Clinic', 'status' => 'pending'],
];

$nutrition_sessions = [
    ['id' => 1, 'child' => 'Emma Johnson', 'program' => 'OPT Plus Feeding Program', 'date' => 'Oct 8, 2025', 'time' => '8:00 AM - 12:00 PM', 'location' => 'Barangay Hall', 'status' => 'enrolled'],
    ['id' => 2, 'child' => 'Noah Johnson', 'program' => 'Monthly Growth Monitoring', 'date' => 'Oct 15, 2025', 'time' => '9:00 AM', 'location' => 'Health Center', 'status' => 'scheduled'],
];

$events = [
    ['id' => 1, 'event' => 'Vitamin A Supplementation Day', 'date' => 'Oct 10, 2025', 'time' => '8:00 AM - 4:00 PM', 'location' => 'Barangay Wide', 'participants' => 'Emma, Noah', 'status' => 'registered'],
    ['id' => 2, 'event' => 'Deworming Campaign', 'date' => 'Nov 20, 2025', 'time' => 'All day', 'location' => 'All Health Centers', 'participants' => 'Emma, Noah', 'status' => 'registered'],
    ['id' => 3, 'event' => 'Health & Nutrition Fair', 'date' => 'Dec 1, 2025', 'time' => '9:00 AM - 3:00 PM', 'location' => 'City Plaza', 'participants' => 'Pending', 'status' => 'available'],
];
?>

<div class="space-y-6">
    <!-- Page Header -->
    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-xl font-medium">Appointment Management</h2>
            <p class="text-sm" style="color: #6b7280;">Manage all health appointments and events for your children</p>
        </div>
        <button class="px-4 py-2 rounded-lg text-white flex items-center gap-2" style="background-color: #3b82f6;">
            <i data-lucide="plus" class="w-4 h-4"></i>
            Schedule Appointment
        </button>
    </div>

    <!-- Next Vaccination Dates -->
    <div class="bg-white rounded-xl shadow-sm p-6" style="border: 1px solid #e5e7eb;">
        <div class="flex items-center justify-between mb-6">
            <div>
                <h3 class="font-medium">Next Vaccination Dates</h3>
                <p class="text-sm" style="color: #6b7280;">Upcoming immunization schedule</p>
            </div>
            <i data-lucide="calendar" class="w-8 h-8" style="color: #3b82f6;"></i>
        </div>
        <div class="space-y-4">
            <?php foreach ($vaccination_dates as $appointment): 
                $bg_color = $appointment['status'] === 'upcoming' ? 'rgba(59, 130, 246, 0.1)' : 'rgba(229, 231, 235, 0.5)';
                $border_color = $appointment['status'] === 'upcoming' ? 'rgba(59, 130, 246, 0.3)' : '#e5e7eb';
            ?>
            <div class="p-4 rounded-lg border" style="background-color: <?php echo $bg_color; ?>; border-color: <?php echo $border_color; ?>;">
                <div class="flex items-start justify-between mb-3">
                    <div>
                        <p class="font-medium mb-1"><?php echo $appointment['vaccine']; ?></p>
                        <p class="text-sm" style="color: #6b7280;"><?php echo $appointment['child']; ?></p>
                    </div>
                    <span class="px-3 py-1 rounded-full text-white text-sm font-medium" 
                          style="background-color: <?php echo $appointment['status'] === 'upcoming' ? '#3b82f6' : '#10b981'; ?>;">
                        <?php echo $appointment['status']; ?>
                    </span>
                </div>
                <div class="grid gap-2 mb-3">
                    <div class="flex items-center gap-2 text-sm" style="color: #6b7280;">
                        <i data-lucide="calendar" class="w-4 h-4"></i>
                        <span><?php echo $appointment['date']; ?></span>
                    </div>
                    <div class="flex items-center gap-2 text-sm" style="color: #6b7280;">
                        <i data-lucide="clock" class="w-4 h-4"></i>
                        <span><?php echo $appointment['time']; ?></span>
                    </div>
                    <div class="flex items-center gap-2 text-sm" style="color: #6b7280;">
                        <i data-lucide="map-pin" class="w-4 h-4"></i>
                        <span><?php echo $appointment['location']; ?></span>
                    </div>
                </div>
                <div class="flex gap-2">
                    <button class="px-3 py-1 rounded-lg border text-sm" style="border-color: #e5e7eb;">View Details</button>
                    <button class="px-3 py-1 rounded-lg border text-sm" style="border-color: #e5e7eb;">Add to Calendar</button>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Health Check-up Calendar -->
    <div class="bg-white rounded-xl shadow-sm p-6" style="border: 1px solid #e5e7eb;">
        <div class="flex items-center justify-between mb-6">
            <div>
                <h3 class="font-medium">Health Check-up Calendar</h3>
                <p class="text-sm" style="color: #6b7280;">Doctor and nurse visit schedule</p>
            </div>
            <i data-lucide="user" class="w-8 h-8" style="color: #10b981;"></i>
        </div>
        <div class="space-y-4">
            <?php foreach ($health_checkups as $checkup): ?>
            <div class="p-4 rounded-lg border" style="background-color: rgba(16, 185, 129, 0.1); border-color: rgba(16, 185, 129, 0.3);">
                <div class="flex items-start justify-between mb-3">
                    <div>
                        <p class="font-medium mb-1"><?php echo $checkup['type']; ?></p>
                        <p class="text-sm" style="color: #6b7280;"><?php echo $checkup['child']; ?></p>
                    </div>
                    <span class="px-3 py-1 rounded-full text-white text-sm font-medium" 
                          style="background-color: <?php echo $checkup['status'] === 'confirmed' ? '#10b981' : '#f59e0b'; ?>;">
                        <?php echo $checkup['status']; ?>
                    </span>
                </div>
                <div class="grid md:grid-cols-2 gap-3 mb-3">
                    <div>
                        <p class="text-sm" style="color: #6b7280;">Date & Time</p>
                        <p><?php echo $checkup['date']; ?></p>
                        <p><?php echo $checkup['time']; ?></p>
                    </div>
                    <div>
                        <p class="text-sm" style="color: #6b7280;">Provider</p>
                        <p><?php echo $checkup['provider']; ?></p>
                        <p class="text-sm" style="color: #6b7280;"><?php echo $checkup['location']; ?></p>
                    </div>
                </div>
                <div class="flex gap-2">
                    <button class="px-3 py-1 rounded-lg text-white text-sm" style="background-color: #10b981;">Confirm</button>
                    <button class="px-3 py-1 rounded-lg border text-sm" style="border-color: #e5e7eb;">Reschedule</button>
                    <button class="px-3 py-1 rounded-lg border text-sm" style="border-color: #e5e7eb;">Cancel</button>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Nutrition Session Schedule -->
    <div class="bg-white rounded-xl shadow-sm p-6" style="border: 1px solid #e5e7eb;">
        <div class="flex items-center justify-between mb-6">
            <div>
                <h3 class="font-medium">Nutrition Session Schedule</h3>
                <p class="text-sm" style="color: #6b7280;">OPT Plus and feeding programs</p>
            </div>
            <div class="text-3xl">🍎</div>
        </div>
        <div class="space-y-4">
            <?php foreach ($nutrition_sessions as $session): ?>
            <div class="p-4 rounded-lg border" style="background-color: rgba(245, 158, 11, 0.1); border-color: rgba(245, 158, 11, 0.3);">
                <div class="flex items-start justify-between mb-3">
                    <div>
                        <p class="font-medium mb-1"><?php echo $session['program']; ?></p>
                        <p class="text-sm" style="color: #6b7280;"><?php echo $session['child']; ?></p>
                    </div>
                    <span class="px-3 py-1 rounded-full text-white text-sm font-medium" style="background-color: #f59e0b;">
                        <?php echo $session['status']; ?>
                    </span>
                </div>
                <div class="grid gap-2 mb-3">
                    <div class="flex items-center gap-2 text-sm" style="color: #6b7280;">
                        <i data-lucide="calendar" class="w-4 h-4"></i>
                        <span><?php echo $session['date']; ?></span>
                    </div>
                    <div class="flex items-center gap-2 text-sm" style="color: #6b7280;">
                        <i data-lucide="clock" class="w-4 h-4"></i>
                        <span><?php echo $session['time']; ?></span>
                    </div>
                    <div class="flex items-center gap-2 text-sm" style="color: #6b7280;">
                        <i data-lucide="map-pin" class="w-4 h-4"></i>
                        <span><?php echo $session['location']; ?></span>
                    </div>
                </div>
                <button class="w-full px-3 py-1 rounded-lg border text-sm" style="border-color: #e5e7eb;">
                    View Program Details
                </button>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Event Participation -->
    <div class="bg-white rounded-xl shadow-sm p-6" style="border: 1px solid #e5e7eb;">
        <div class="flex items-center justify-between mb-6">
            <div>
                <h3 class="font-medium">Event Participation</h3>
                <p class="text-sm" style="color: #6b7280;">Community health events and campaigns</p>
            </div>
            <div class="text-3xl">🎉</div>
        </div>
        <div class="space-y-4">
            <?php foreach ($events as $event): 
                $bg_color = $event['status'] === 'registered' ? 'rgba(16, 185, 129, 0.1)' : 'rgba(229, 231, 235, 0.5)';
                $border_color = $event['status'] === 'registered' ? 'rgba(16, 185, 129, 0.3)' : '#e5e7eb';
            ?>
            <div class="p-4 rounded-lg border" style="background-color: <?php echo $bg_color; ?>; border-color: <?php echo $border_color; ?>;">
                <div class="flex items-start justify-between mb-3">
                    <div>
                        <p class="font-medium mb-1"><?php echo $event['event']; ?></p>
                        <p class="text-sm" style="color: #6b7280;">Participants: <?php echo $event['participants']; ?></p>
                    </div>
                    <span class="px-3 py-1 rounded-full text-white text-sm font-medium" 
                          style="background-color: <?php echo $event['status'] === 'registered' ? '#10b981' : '#3b82f6'; ?>;">
                        <?php echo $event['status']; ?>
                    </span>
                </div>
                <div class="grid gap-2 mb-3">
                    <div class="flex items-center gap-2 text-sm" style="color: #6b7280;">
                        <i data-lucide="calendar" class="w-4 h-4"></i>
                        <span><?php echo $event['date']; ?></span>
                    </div>
                    <div class="flex items-center gap-2 text-sm" style="color: #6b7280;">
                        <i data-lucide="clock" class="w-4 h-4"></i>
                        <span><?php echo $event['time']; ?></span>
                    </div>
                    <div class="flex items-center gap-2 text-sm" style="color: #6b7280;">
                        <i data-lucide="map-pin" class="w-4 h-4"></i>
                        <span><?php echo $event['location']; ?></span>
                    </div>
                </div>
                <?php if ($event['status'] === 'available'): ?>
                <button class="w-full px-3 py-2 rounded-lg text-white" style="background-color: #3b82f6;">
                    Register for Event
                </button>
                <?php else: ?>
                <button class="w-full px-3 py-2 rounded-lg border" style="border-color: #e5e7eb;">
                    View Registration
                </button>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>