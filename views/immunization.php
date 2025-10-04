<?php
$vaccine_history = [
    ['vaccine' => 'BCG', 'date' => 'Jan 10, 2023', 'age' => 'At birth', 'batch' => 'BCG2023A', 'provider' => 'City Health Center'],
    ['vaccine' => 'Hepatitis B', 'date' => 'Jan 10, 2023', 'age' => 'At birth', 'batch' => 'HEPB2023B', 'provider' => 'City Health Center'],
    ['vaccine' => 'DPT (1st dose)', 'date' => 'Mar 15, 2023', 'age' => '2 months', 'batch' => 'DPT2023C', 'provider' => 'Barangay Clinic'],
    ['vaccine' => 'Polio (1st dose)', 'date' => 'Mar 15, 2023', 'age' => '2 months', 'batch' => 'POLIO2023D', 'provider' => 'Barangay Clinic'],
    ['vaccine' => 'DPT (2nd dose)', 'date' => 'May 20, 2023', 'age' => '4 months', 'batch' => 'DPT2023E', 'provider' => 'Barangay Clinic'],
];

$upcoming_vaccines = [
    ['vaccine' => '6-month vaccines', 'child' => 'Noah Johnson', 'due_date' => 'Oct 20, 2025', 'urgency' => 'urgent', 'days_left' => 17],
    ['vaccine' => 'MMR Booster', 'child' => 'Emma Johnson', 'due_date' => 'Nov 15, 2025', 'urgency' => 'soon', 'days_left' => 43],
    ['vaccine' => '12-month vaccines', 'child' => 'Noah Johnson', 'due_date' => 'Apr 15, 2026', 'urgency' => 'scheduled', 'days_left' => 194],
];
?>

<div class="space-y-6">
    <!-- Page Header -->
    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-xl font-medium">Immunization Tracking</h2>
            <p class="text-sm" style="color: #6b7280;">Digital vaccine records and schedules</p>
        </div>
        <div class="flex gap-2">
            <button class="px-4 py-2 rounded-lg border flex items-center gap-2 hover:bg-gray-50" style="border-color: #e5e7eb;">
                <i data-lucide="printer" class="w-4 h-4"></i>
                Print Card
            </button>
            <button class="px-4 py-2 rounded-lg text-white flex items-center gap-2" style="background-color: #3b82f6;">
                <i data-lucide="download" class="w-4 h-4"></i>
                Download PDF
            </button>
        </div>
    </div>

    <!-- Digital Immunization Card -->
    <div class="bg-white rounded-xl shadow-sm overflow-hidden" style="border: 2px solid rgba(59, 130, 246, 0.2);">
        <div class="p-6" style="background: linear-gradient(to right, rgba(59, 130, 246, 0.1), rgba(16, 185, 129, 0.1));">
            <div class="flex items-start justify-between">
                <div class="flex items-center gap-4">
                    <div class="text-5xl">👧</div>
                    <div>
                        <h3 class="font-medium">Emma Johnson</h3>
                        <p class="text-sm" style="color: #6b7280;">Date of Birth: January 5, 2022</p>
                        <p class="text-sm mt-1" style="color: #6b7280;">Child ID: CH2022-0105</p>
                    </div>
                </div>
                <span class="px-3 py-1 rounded-full text-white text-sm font-medium" style="background-color: #10b981;">
                    Active
                </span>
            </div>
        </div>
        <div class="p-6">
            <div class="grid gap-4 md:grid-cols-3 mb-6">
                <div>
                    <p class="text-sm" style="color: #6b7280;">Parent/Guardian</p>
                    <p class="font-medium">Sarah Johnson</p>
                </div>
                <div>
                    <p class="text-sm" style="color: #6b7280;">Contact</p>
                    <p class="font-medium">+63 912 345 6789</p>
                </div>
                <div>
                    <p class="text-sm" style="color: #6b7280;">Address</p>
                    <p class="font-medium">Barangay Health, City</p>
                </div>
            </div>
            <div class="mb-4">
                <div class="flex items-center justify-between mb-2">
                    <p class="font-medium">Immunization Progress</p>
                    <span class="font-medium">75% Complete</span>
                </div>
                <div class="w-full bg-gray-200 rounded-full h-3">
                    <div class="h-3 rounded-full" style="width: 75%; background-color: #3b82f6;"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Upcoming Vaccines -->
    <div class="bg-white rounded-xl shadow-sm p-6" style="border: 1px solid #e5e7eb;">
        <h3 class="font-medium mb-2">Upcoming Vaccines</h3>
        <p class="text-sm mb-6" style="color: #6b7280;">Don't miss these important immunization dates</p>
        <div class="space-y-3">
            <?php foreach ($upcoming_vaccines as $vaccine): 
                $bg_color = $vaccine['urgency'] === 'urgent' ? 'rgba(239, 68, 68, 0.1)' : 
                           ($vaccine['urgency'] === 'soon' ? 'rgba(245, 158, 11, 0.1)' : 'rgba(59, 130, 246, 0.1)');
                $border_color = $vaccine['urgency'] === 'urgent' ? '#ef4444' : 
                               ($vaccine['urgency'] === 'soon' ? '#f59e0b' : '#3b82f6');
                $badge_bg = $vaccine['urgency'] === 'urgent' ? '#ef4444' : '#10b981';
            ?>
            <div class="p-4 rounded-lg border-l-4" style="background-color: <?php echo $bg_color; ?>; border-left-color: <?php echo $border_color; ?>;">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="p-2 rounded-full" style="background-color: rgba(<?php echo $vaccine['urgency'] === 'urgent' ? '239, 68, 68' : '59, 130, 246'; ?>, 0.2);">
                            <i data-lucide="<?php echo $vaccine['urgency'] === 'urgent' ? 'alert-circle' : 'calendar'; ?>" 
                               class="w-5 h-5" style="color: <?php echo $border_color; ?>;"></i>
                        </div>
                        <div>
                            <p class="font-medium"><?php echo $vaccine['vaccine']; ?></p>
                            <p class="text-sm" style="color: #6b7280;"><?php echo $vaccine['child']; ?></p>
                        </div>
                    </div>
                    <div class="text-right">
                        <p class="mb-1 font-medium"><?php echo $vaccine['due_date']; ?></p>
                        <span class="px-3 py-1 rounded-full text-white text-sm font-medium" style="background-color: <?php echo $badge_bg; ?>;">
                            <?php echo $vaccine['days_left']; ?> days left
                        </span>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Vaccination History Table -->
    <div class="bg-white rounded-xl shadow-sm p-6" style="border: 1px solid #e5e7eb;">
        <h3 class="font-medium mb-2">Complete Vaccination History</h3>
        <p class="text-sm mb-6" style="color: #6b7280;">All administered vaccines for Emma Johnson</p>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="border-b" style="border-color: #e5e7eb;">
                        <th class="text-left py-3 px-4 font-medium">Vaccine</th>
                        <th class="text-left py-3 px-4 font-medium">Date Given</th>
                        <th class="text-left py-3 px-4 font-medium">Age at Vaccination</th>
                        <th class="text-left py-3 px-4 font-medium">Batch Number</th>
                        <th class="text-left py-3 px-4 font-medium">Provider</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($vaccine_history as $record): ?>
                    <tr class="border-b" style="border-color: #e5e7eb;">
                        <td class="py-3 px-4">
                            <div class="flex items-center gap-2">
                                <i data-lucide="check-circle" class="w-4 h-4" style="color: #10b981;"></i>
                                <?php echo $record['vaccine']; ?>
                            </div>
                        </td>
                        <td class="py-3 px-4"><?php echo $record['date']; ?></td>
                        <td class="py-3 px-4"><?php echo $record['age']; ?></td>
                        <td class="py-3 px-4"><?php echo $record['batch']; ?></td>
                        <td class="py-3 px-4"><?php echo $record['provider']; ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>