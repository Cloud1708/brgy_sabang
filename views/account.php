<div class="space-y-6">
    <!-- Page Header -->
    <div>
        <h2 class="text-xl font-medium">Account Settings</h2>
        <p class="text-sm" style="color: #6b7280;">Manage your profile and preferences</p>
    </div>

    <!-- Profile Management -->
    <div class="bg-white rounded-xl shadow-sm p-6" style="border: 1px solid #e5e7eb;">
        <div class="flex items-center gap-3 mb-6">
            <i data-lucide="user" class="w-6 h-6" style="color: #3b82f6;"></i>
            <div>
                <h3 class="font-medium">Profile Management</h3>
                <p class="text-sm" style="color: #6b7280;">Update your personal information</p>
            </div>
        </div>
        <div class="space-y-6">
            <div class="flex items-center gap-6">
                <div class="h-20 w-20 rounded-full flex items-center justify-center text-3xl" style="background-color: rgba(59, 130, 246, 0.1);">
                    👤
                </div>
                <div class="space-y-2">
                    <button class="px-4 py-2 rounded-lg border text-sm" style="border-color: #e5e7eb;">
                        Change Photo
                    </button>
                    <p class="text-sm" style="color: #6b7280;">JPG, PNG or GIF, max 5MB</p>
                </div>
            </div>
            <div class="border-t" style="border-color: #e5e7eb;"></div>
            <div class="grid gap-4 md:grid-cols-2">
                <div class="space-y-2">
                    <label class="font-medium">First Name</label>
                    <input type="text" value="Sarah" class="w-full px-4 py-2 rounded-lg border" style="border-color: #e5e7eb;">
                </div>
                <div class="space-y-2">
                    <label class="font-medium">Last Name</label>
                    <input type="text" value="Johnson" class="w-full px-4 py-2 rounded-lg border" style="border-color: #e5e7eb;">
                </div>
                <div class="space-y-2">
                    <label class="font-medium">Relationship to Child</label>
                    <input type="text" value="Mother" class="w-full px-4 py-2 rounded-lg border" style="border-color: #e5e7eb;">
                </div>
                <div class="space-y-2">
                    <label class="font-medium">Date of Birth</label>
                    <input type="date" value="1990-05-15" class="w-full px-4 py-2 rounded-lg border" style="border-color: #e5e7eb;">
                </div>
            </div>
            <button class="px-4 py-2 rounded-lg text-white flex items-center gap-2" style="background-color: #3b82f6;">
                <i data-lucide="save" class="w-4 h-4"></i>
                Save Changes
            </button>
        </div>
    </div>

    <!-- Contact Information -->
    <div class="bg-white rounded-xl shadow-sm p-6" style="border: 1px solid #e5e7eb;">
        <div class="flex items-center gap-3 mb-6">
            <i data-lucide="phone" class="w-6 h-6" style="color: #10b981;"></i>
            <div>
                <h3 class="font-medium">Contact Information</h3>
                <p class="text-sm" style="color: #6b7280;">Update your contact details</p>
            </div>
        </div>
        <div class="space-y-4">
            <div class="space-y-2">
                <label class="font-medium flex items-center gap-2">
                    <i data-lucide="phone" class="w-4 h-4"></i>
                    Phone Number
                </label>
                <input type="tel" value="+63 912 345 6789" class="w-full px-4 py-2 rounded-lg border" style="border-color: #e5e7eb;">
            </div>
            <div class="space-y-2">
                <label class="font-medium flex items-center gap-2">
                    <i data-lucide="mail" class="w-4 h-4"></i>
                    Email Address
                </label>
                <input type="email" value="sarah.johnson@email.com" class="w-full px-4 py-2 rounded-lg border" style="border-color: #e5e7eb;">
            </div>
            <div class="space-y-2">
                <label class="font-medium flex items-center gap-2">
                    <i data-lucide="map-pin" class="w-4 h-4"></i>
                    Home Address
                </label>
                <input type="text" value="123 Main Street, Barangay Health" class="w-full px-4 py-2 rounded-lg border" style="border-color: #e5e7eb;">
            </div>
            <div class="space-y-2">
                <label class="font-medium">City/Municipality</label>
                <input type="text" value="Sample City" class="w-full px-4 py-2 rounded-lg border" style="border-color: #e5e7eb;">
            </div>
            <div class="grid gap-4 md:grid-cols-2">
                <div class="space-y-2">
                    <label class="font-medium">Province</label>
                    <input type="text" value="Metro Manila" class="w-full px-4 py-2 rounded-lg border" style="border-color: #e5e7eb;">
                </div>
                <div class="space-y-2">
                    <label class="font-medium">Zip Code</label>
                    <input type="text" value="1000" class="w-full px-4 py-2 rounded-lg border" style="border-color: #e5e7eb;">
                </div>
            </div>
            <button class="px-4 py-2 rounded-lg text-white flex items-center gap-2" style="background-color: #10b981;">
                <i data-lucide="save" class="w-4 h-4"></i>
                Update Contact Info
            </button>
        </div>
    </div>

    <!-- Password Management -->
    <div class="bg-white rounded-xl shadow-sm p-6" style="border: 1px solid #e5e7eb;">
        <div class="flex items-center gap-3 mb-6">
            <i data-lucide="lock" class="w-6 h-6" style="color: #f59e0b;"></i>
            <div>
                <h3 class="font-medium">Password Management</h3>
                <p class="text-sm" style="color: #6b7280;">Change your password to keep your account secure</p>
            </div>
        </div>
        <div class="space-y-4">
            <div class="space-y-2">
                <label class="font-medium">Current Password</label>
                <input type="password" placeholder="Enter current password" class="w-full px-4 py-2 rounded-lg border" style="border-color: #e5e7eb;">
            </div>
            <div class="space-y-2">
                <label class="font-medium">New Password</label>
                <input type="password" placeholder="Enter new password" class="w-full px-4 py-2 rounded-lg border" style="border-color: #e5e7eb;">
            </div>
            <div class="space-y-2">
                <label class="font-medium">Confirm New Password</label>
                <input type="password" placeholder="Confirm new password" class="w-full px-4 py-2 rounded-lg border" style="border-color: #e5e7eb;">
            </div>
            <div class="p-4 rounded-lg" style="background-color: rgba(229, 231, 235, 0.5);">
                <p style="color: #6b7280;">Password Requirements:</p>
                <ul class="list-disc list-inside mt-2 space-y-1" style="color: #6b7280;">
                    <li>At least 8 characters long</li>
                    <li>Include uppercase and lowercase letters</li>
                    <li>Include at least one number</li>
                    <li>Include at least one special character</li>
                </ul>
            </div>
            <button class="px-4 py-2 rounded-lg text-white flex items-center gap-2" style="background-color: #f59e0b;">
                <i data-lucide="lock" class="w-4 h-4"></i>
                Change Password
            </button>
        </div>
    </div>

    <!-- Notification Preferences -->
    <div class="bg-white rounded-xl shadow-sm p-6" style="border: 1px solid #e5e7eb;">
        <div class="flex items-center gap-3 mb-6">
            <i data-lucide="bell" class="w-6 h-6" style="color: #3b82f6;"></i>
            <div>
                <h3 class="font-medium">Notification Preferences</h3>
                <p class="text-sm" style="color: #6b7280;">Choose how you want to receive updates</p>
            </div>
        </div>
        <div class="space-y-6">
            <div class="flex items-center justify-between">
                <div class="space-y-0.5">
                    <label class="font-medium">Email Notifications</label>
                    <p class="text-sm" style="color: #6b7280;">Receive updates via email</p>
                </div>
                <label class="relative inline-flex items-center cursor-pointer">
                    <input type="checkbox" checked class="sr-only peer">
                    <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                </label>
            </div>
            <div class="border-t" style="border-color: #e5e7eb;"></div>
            <div class="flex items-center justify-between">
                <div class="space-y-0.5">
                    <label class="font-medium">SMS Notifications</label>
                    <p class="text-sm" style="color: #6b7280;">Receive text message alerts</p>
                </div>
                <label class="relative inline-flex items-center cursor-pointer">
                    <input type="checkbox" checked class="sr-only peer">
                    <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                </label>
            </div>
            <div class="border-t" style="border-color: #e5e7eb;"></div>
            <div class="flex items-center justify-between">
                <div class="space-y-0.5">
                    <label class="font-medium">Push Notifications</label>
                    <p class="text-sm" style="color: #6b7280;">Receive push alerts on your device</p>
                </div>
                <label class="relative inline-flex items-center cursor-pointer">
                    <input type="checkbox" checked class="sr-only peer">
                    <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                </label>
            </div>
            <div class="border-t" style="border-color: #e5e7eb;"></div>
            <div class="space-y-4">
                <h4 class="font-medium">Notification Types</h4>
                <div class="space-y-4">
                    <div class="flex items-center justify-between">
                        <div class="space-y-0.5">
                            <label class="font-medium">Vaccine Reminders</label>
                            <p class="text-sm" style="color: #6b7280;">Upcoming immunization dates</p>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" checked class="sr-only peer">
                            <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                        </label>
                    </div>
                    <div class="flex items-center justify-between">
                        <div class="space-y-0.5">
                            <label class="font-medium">Appointment Reminders</label>
                            <p class="text-sm" style="color: #6b7280;">Health check-up notifications</p>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" checked class="sr-only peer">
                            <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                        </label>
                    </div>
                    <div class="flex items-center justify-between">
                        <div class="space-y-0.5">
                            <label class="font-medium">Growth Monitoring</label>
                            <p class="text-sm" style="color: #6b7280;">Weighing session alerts</p>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" checked class="sr-only peer">
                            <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                        </label>
                    </div>
                    <div class="flex items-center justify-between">
                        <div class="space-y-0.5">
                            <label class="font-medium">Health Education</label>
                            <p class="text-sm" style="color: #6b7280;">Tips and information updates</p>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" checked class="sr-only peer">
                            <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                        </label>
                    </div>
                    <div class="flex items-center justify-between">
                        <div class="space-y-0.5">
                            <label class="font-medium">Community Announcements</label>
                            <p class="text-sm" style="color: #6b7280;">Events and system updates</p>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" checked class="sr-only peer">
                            <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                        </label>
                    </div>
                </div>
            </div>
            <button class="w-full px-4 py-2 rounded-lg text-white flex items-center justify-center gap-2" style="background-color: #3b82f6;">
                <i data-lucide="save" class="w-4 h-4"></i>
                Save Preferences
            </button>
        </div>
    </div>
</div>