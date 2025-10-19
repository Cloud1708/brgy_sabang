<!-- Top Navbar -->
<header class="sticky top-0 z-50 bg-white border-b shadow-sm" style="border-color: #e5e7eb;">
    <div class="flex items-center justify-between h-16 px-4 lg:px-6">
        <div class="flex items-center gap-4">
            <button id="sidebar-toggle" class="lg:hidden p-2 hover:bg-gray-100 rounded-lg">
                <i data-lucide="menu" class="w-6 h-6"></i>
            </button>
            <div class="flex items-center gap-2">
                <img src="assets/img/sabang.jpg" alt="Barangay Sabang Logo" class="h-10 w-10 rounded-lg object-cover border-2" style="border-color: rgba(4, 120, 87, 0.2); box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);">
                <div class="hidden sm:block">
                    <h1 class="text-lg font-semibold">Sabang Health Portal</h1>
                    <p class="text-sm" style="color: #6b7280;">Parent &amp; Child Health</p>
                </div>
            </div>
        </div>
        <div class="flex items-center gap-3">
            <div class="flex items-center gap-3 pl-3 border-l" style="border-color: #e5e7eb;">
                <div class="h-10 w-10 rounded-full flex items-center justify-center text-white" style="background-color: #3b82f6;">
                    <?php echo $user['initials']; ?>
                </div>
                <div class="hidden md:block">
                    <p class="font-medium"><?php echo $user['first_name'] . ' ' . $user['last_name']; ?></p>
                    <p class="text-sm" style="color: #6b7280;"><?php echo $user['role']; ?></p>
                </div>
                <a href="logout" class="ml-2 inline-flex items-center gap-2 px-3 py-2 rounded-lg text-sm font-medium"
                   style="background-color:#ef4444; color:white;">
                    <i data-lucide="log-out" class="w-4 h-4"></i>
                    <span class="hidden sm:inline">Logout</span>
                </a>
            </div>
        </div>
    </div>
</header>