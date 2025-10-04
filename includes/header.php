<!-- Top Navbar -->
<header class="sticky top-0 z-50 bg-white border-b shadow-sm" style="border-color: #e5e7eb;">
    <div class="flex items-center justify-between h-16 px-4 lg:px-6">
        <div class="flex items-center gap-4">
            <button id="sidebar-toggle" class="lg:hidden p-2 hover:bg-gray-100 rounded-lg">
                <i data-lucide="menu" class="w-6 h-6"></i>
            </button>
            <div class="flex items-center gap-2">
                <div class="h-10 w-10 rounded-lg flex items-center justify-center text-white text-xl" style="background-color: #3b82f6;">
                    👶
                </div>
                <div class="hidden sm:block">
                    <h1 class="text-lg font-medium">HealthKids Portal</h1>
                    <p class="text-sm" style="color: #6b7280;">Child Health Dashboard</p>
                </div>
            </div>
        </div>
        <div class="flex items-center gap-3">
            <button class="p-2 hover:bg-gray-100 rounded-lg relative">
                <i data-lucide="bell" class="w-5 h-5"></i>
                <span class="absolute top-1 right-1 h-2 w-2 rounded-full" style="background-color: #ef4444;"></span>
            </button>
            <button class="p-2 hover:bg-gray-100 rounded-lg">
                <i data-lucide="settings" class="w-5 h-5"></i>
            </button>
            <div class="flex items-center gap-3 pl-3 border-l" style="border-color: #e5e7eb;">
                <div class="h-10 w-10 rounded-full flex items-center justify-center text-white" style="background-color: #3b82f6;">
                    <?php echo $user['initials']; ?>
                </div>
                <div class="hidden md:block">
                    <p class="font-medium"><?php echo $user['first_name'] . ' ' . $user['last_name']; ?></p>
                    <p class="text-sm" style="color: #6b7280;"><?php echo $user['role']; ?></p>
                </div>
            </div>
        </div>
    </div>
</header>