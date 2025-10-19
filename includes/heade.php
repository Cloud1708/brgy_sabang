<!-- Top Navbar -->
<header class="sticky top-0 z-50 bg-white border-b shadow-sm" style="border-color: #e5e7eb;">
    <div class="flex items-center justify-between h-16 px-4 lg:px-6">
        <div class="flex items-center gap-4">
            <button id="sidebar-toggle" class="lg:hidden p-2 hover:bg-gray-100 rounded-lg">
                <i data-lucide="menu" class="w-6 h-6"></i>
            </button>
            <div class="flex items-center gap-2">
                <div class="h-10 w-10 rounded-lg flex items-center justify-center text-white"
                     style="background: linear-gradient(135deg, #2563eb, #0891b2); box-shadow: 0 6px 14px -6px rgba(37,99,235,.45);">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-5 h-5">
                        <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 1 0-7.78 7.78L12 21.23l8.84-8.84a5.5 5.5 0 0 0 0-7.78z"/>
                        <path d="M7.5 12h2l1.5-3 2 6 1-3h2"/>
                    </svg>
                </div>
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
                <a href="logout.php" class="ml-2 inline-flex items-center gap-2 px-3 py-2 rounded-lg text-sm font-medium"
                   style="background-color:#ef4444; color:white;">
                    <i data-lucide="log-out" class="w-4 h-4"></i>
                    <span class="hidden sm:inline">Logout</span>
                </a>
            </div>
        </div>
    </div>
</header>