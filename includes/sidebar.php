<?php
$navigation = [
    ['name' => 'My Children', 'icon' => 'home', 'view' => 'dashboard'],
    ['name' => 'Immunization', 'icon' => 'syringe', 'view' => 'immunization'],
    ['name' => 'Growth & Nutrition', 'icon' => 'trending-up', 'view' => 'growth'],
    ['name' => 'Notifications', 'icon' => 'bell', 'view' => 'notifications', 'badge' => 5],
    ['name' => 'Appointments', 'icon' => 'calendar', 'view' => 'appointments'],
    ['name' => 'Account Settings', 'icon' => 'settings', 'view' => 'account'],
];
?>

<!-- Sidebar -->
<aside id="sidebar" class="fixed inset-y-0 left-0 z-40 w-64 bg-white border-r transform transition-transform duration-200 ease-in-out lg:translate-x-0 lg:static -translate-x-full mt-16 lg:mt-0" style="border-color: #e5e7eb;">
    <nav class="h-full overflow-y-auto p-4 space-y-2">
        <?php foreach ($navigation as $item): 
            $isActive = $current_view === $item['view'];
            $activeClass = $isActive ? 'text-white' : '';
            $hoverClass = $isActive ? '' : 'hover:bg-gray-100';
        ?>
        <a href="?view=<?php echo $item['view']; ?>" 
           class="w-full flex items-center justify-between px-4 py-3 rounded-lg transition-colors <?php echo $activeClass . ' ' . $hoverClass; ?>"
           style="<?php echo $isActive ? 'background-color: #3b82f6;' : ''; ?>">
            <div class="flex items-center gap-3">
                <i data-lucide="<?php echo $item['icon']; ?>" class="w-5 h-5"></i>
                <span><?php echo $item['name']; ?></span>
            </div>
            <div class="flex items-center gap-2">
                <?php if (isset($item['badge'])): ?>
                <span class="px-2 py-0.5 rounded-full text-xs font-medium" 
                      style="<?php echo $isActive ? 'background-color: white; color: #3b82f6;' : 'background-color: #ef4444; color: white;'; ?>">
                    <?php echo $item['badge']; ?>
                </span>
                <?php endif; ?>
                <?php if ($isActive): ?>
                <i data-lucide="chevron-right" class="w-4 h-4"></i>
                <?php endif; ?>
            </div>
        </a>
        <?php endforeach; ?>
    </nav>
</aside>