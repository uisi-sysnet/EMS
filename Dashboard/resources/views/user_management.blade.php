@include('layouts.header')
@include('layouts.topbar')

<style>
    .thin-scrollbar::-webkit-scrollbar { width: 5px; height: 5px; }
    .thin-scrollbar::-webkit-scrollbar-track { background: #1A1A1A; border-radius: 10px; }
    .thin-scrollbar::-webkit-scrollbar-thumb { background: #4B5563; border-radius: 10px; }
    .thin-scrollbar::-webkit-scrollbar-thumb:hover { background: #6B7280; }
    .thin-scrollbar { scrollbar-width: thin; scrollbar-color: #4B5563 #1A1A1A; }
</style>

<div id="main-content" class="pt-20 pb-6 px-4 sm:px-6 max-w-8xl mx-auto w-full overflow-hidden flex flex-col h-[calc(100dvh)] max-h-[calc(100dvh)]">
    <div class="bg-surface-900 rounded-2xl shadow-xl border border-border-800 overflow-hidden flex-1 flex flex-col min-h-0">

        <!-- Header -->
        <div class="px-4 sm:px-6 py-3.5 sm:py-4 border-b border-border-800 bg-surface-800 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-1">
            <h2 class="text-lg sm:text-xl font-semibold text-text-100 flex items-center gap-2.5">
                <span class="leading-tight uppercase tracking-wide">User Management</span>
            </h2>
            <span class="text-xs sm:text-sm text-text-400">Create and manage system users</span>
        </div>

        <!-- Content -->
        <div class="flex-1 overflow-y-auto thin-scrollbar min-h-0 bg-background-900 py-6 px-5 sm:px-8">

            @if(session('success'))
                <div class="mb-6 px-4 py-3 rounded-lg border border-munti-green-600/30 bg-munti-green-700/15 text-munti-green-400 text-sm font-medium">
                    {{ session('success') }}
                </div>
            @endif

            @if($errors->any())
                <div class="mb-6 px-4 py-3 rounded-lg border border-munti-red-600/30 bg-munti-red-700/15 text-munti-red-400 text-sm font-medium">
                    <ul class="list-disc list-inside">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="bg-surface-800 rounded-xl border border-border-700 overflow-hidden flex flex-col shadow-sm">

                <!-- Form Section -->
                <div class="p-5 border-b border-border-700">
                    <h3 class="text-sm font-bold text-text-100 uppercase tracking-wider mb-4 flex items-center gap-2">
                        <span class="w-1.5 h-1.5 rounded-full bg-radar-400"></span>
                        Add New User
                    </h3>

                    <form action="#" method="POST">
                        @csrf

                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                            <!-- First Name -->
                            <div class="flex flex-col">
                                <label for="first_name" class="block text-xs font-medium text-text-400 mb-1.5 uppercase tracking-wide">
                                    First Name <span class="text-munti-red-400">*</span>
                                </label>
                                <input type="text" id="first_name" name="first_name" value="{{ old('first_name') }}" required
                                    class="w-full px-3.5 py-2.5 border border-border-600 rounded-lg bg-surface-900 text-text-100 placeholder-text-500 focus:ring-2 focus:ring-radar-500/40 focus:border-radar-500 text-sm transition @error('first_name') border-munti-red-500 @enderror"
                                    placeholder="Enter first name">
                                @error('first_name')
                                    <p class="mt-1 text-xs text-munti-red-400">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Last Name -->
                            <div class="flex flex-col">
                                <label for="last_name" class="block text-xs font-medium text-text-400 mb-1.5 uppercase tracking-wide">
                                    Last Name <span class="text-munti-red-400">*</span>
                                </label>
                                <input type="text" id="last_name" name="last_name" value="{{ old('last_name') }}" required
                                    class="w-full px-3.5 py-2.5 border border-border-600 rounded-lg bg-surface-900 text-text-100 placeholder-text-500 focus:ring-2 focus:ring-radar-500/40 focus:border-radar-500 text-sm transition @error('last_name') border-munti-red-500 @enderror"
                                    placeholder="Enter last name">
                                @error('last_name')
                                    <p class="mt-1 text-xs text-munti-red-400">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Contact Number -->
                            <div class="flex flex-col">
                                <label for="contact_number" class="block text-xs font-medium text-text-400 mb-1.5 uppercase tracking-wide">
                                    Contact Number <span class="text-munti-red-400">*</span>
                                </label>
                                <input type="text" id="contact_number" name="contact_number" value="{{ old('contact_number') }}" required
                                    class="w-full px-3.5 py-2.5 border border-border-600 rounded-lg bg-surface-900 text-text-100 placeholder-text-500 focus:ring-2 focus:ring-radar-500/40 focus:border-radar-500 text-sm transition @error('contact_number') border-munti-red-500 @enderror"
                                    placeholder="Enter contact number">
                                @error('contact_number')
                                    <p class="mt-1 text-xs text-munti-red-400">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Email -->
                            <div class="flex flex-col">
                                <label for="email" class="block text-xs font-medium text-text-400 mb-1.5 uppercase tracking-wide">
                                    Email <span class="text-munti-red-400">*</span>
                                </label>
                                <input type="email" id="email" name="email" value="{{ old('email') }}" required
                                    class="w-full px-3.5 py-2.5 border border-border-600 rounded-lg bg-surface-900 text-text-100 placeholder-text-500 focus:ring-2 focus:ring-radar-500/40 focus:border-radar-500 text-sm transition @error('email') border-munti-red-500 @enderror"
                                    placeholder="Enter email address">
                                @error('email')
                                    <p class="mt-1 text-xs text-munti-red-400">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Username -->
                            <div class="flex flex-col">
                                <label for="username" class="block text-xs font-medium text-text-400 mb-1.5 uppercase tracking-wide">
                                    Username <span class="text-munti-red-400">*</span>
                                </label>
                                <input type="text" id="username" name="username" value="{{ old('username') }}" required
                                    class="w-full px-3.5 py-2.5 border border-border-600 rounded-lg bg-surface-900 text-text-100 placeholder-text-500 focus:ring-2 focus:ring-radar-500/40 focus:border-radar-500 text-sm transition @error('username') border-munti-red-500 @enderror"
                                    placeholder="Enter unique username">
                                @error('username')
                                    <p class="mt-1 text-xs text-munti-red-400">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Password -->
                            <div class="flex flex-col">
                                <label for="password" class="block text-xs font-medium text-text-400 mb-1.5 uppercase tracking-wide">
                                    Password <span class="text-munti-red-400">*</span>
                                </label>
                                <input type="password" id="password" name="password" required
                                    class="w-full px-3.5 py-2.5 border border-border-600 rounded-lg bg-surface-900 text-text-100 placeholder-text-500 focus:ring-2 focus:ring-radar-500/40 focus:border-radar-500 text-sm transition @error('password') border-munti-red-500 @enderror"
                                    placeholder="Enter password (min 8 characters)">
                                @error('password')
                                    <p class="mt-1 text-xs text-munti-red-400">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        <!-- Password confirmation -->
                        <div class="mt-4 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                            <div class="flex flex-col">
                                <label for="password_confirmation" class="block text-xs font-medium text-text-400 mb-1.5 uppercase tracking-wide">
                                    Confirm Password <span class="text-munti-red-400">*</span>
                                </label>
                                <input type="password" id="password_confirmation" name="password_confirmation" required
                                    class="w-full px-3.5 py-2.5 border border-border-600 rounded-lg bg-surface-900 text-text-100 placeholder-text-500 focus:ring-2 focus:ring-radar-500/40 focus:border-radar-500 text-sm transition"
                                    placeholder="Confirm password">
                            </div>

                            <!-- Role Selection -->
                            <div class="flex flex-col">
                                <label for="role" class="block text-xs font-medium text-text-400 mb-1.5 uppercase tracking-wide">
                                    Role <span class="text-munti-red-400">*</span>
                                </label>
                                <select id="role" name="role" required
                                    class="w-full px-3.5 py-2.5 border border-border-600 rounded-lg bg-surface-900 text-text-100 focus:ring-2 focus:ring-radar-500/40 focus:border-radar-500 text-sm transition @error('role') border-munti-red-500 @enderror">
                                    <option value="">Select a role</option>
                                    <option value="admin" {{ old('role') == 'admin' ? 'selected' : '' }}>Admin</option>
                                    <option value="manager" {{ old('role') == 'manager' ? 'selected' : '' }}>Manager</option>
                                    <option value="user" {{ old('role') == 'user' ? 'selected' : '' }}>User</option>
                                </select>
                                @error('role')
                                    <p class="mt-1 text-xs text-munti-red-400">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Submit Button -->
                            <div class="flex flex-col justify-end">
                                <button type="submit"
                                        class="w-full px-6 py-2.5 h-11 bg-munti-green-600 hover:bg-munti-green-500 text-text-100 font-semibold rounded-lg transition border border-munti-green-500/30 flex items-center justify-center gap-2 whitespace-nowrap">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                    </svg>
                                    Create User
                                </button>
                            </div>
                        </div>
                        
                        <!-- Note about uniqueness -->
                        <div class="mt-4 p-3 bg-surface-700/30 rounded-lg border border-border-600">
                            <p class="text-xs text-text-400">
                                <span class="text-munti-red-400">*</span> Note: 
                                <span class="text-text-300">Username and Email must be unique across all records.</span>
                            </p>
                        </div>
                    </form>
                </div>

                <!-- Table Section -->
                <div class="flex-1 flex flex-col min-h-0">
                    <div class="px-5 py-3 border-b border-border-700 bg-surface-900/40 flex items-center justify-between">
                        <h4 class="text-xs font-semibold text-text-400 uppercase tracking-wider flex items-center gap-2">
                            <span class="w-1.5 h-1.5 rounded-full bg-munti-green-400"></span>
                            Existing Users
                        </h4>
                        <span class="text-xs text-text-500">06 User(s)</span>
                    </div>
                    
                    <div class="overflow-x-auto thin-scrollbar flex-1">
                        <!-- Hardcoded Data -->
                        <table class="min-w-full divide-y divide-border-700">
                            <thead class="bg-surface-900/60 text-[11px] uppercase tracking-wider text-text-500 sticky top-0 z-10">
                                <tr>
                                    <th scope="col" class="px-4 py-3 text-left font-medium">Name</th>
                                    <th scope="col" class="px-4 py-3 text-left font-medium">Contact</th>
                                    <th scope="col" class="px-4 py-3 text-left font-medium">Email</th>
                                    <th scope="col" class="px-4 py-3 text-left font-medium">Username</th>
                                    <th scope="col" class="px-4 py-3 text-left font-medium">Role</th>
                                    <th scope="col" class="px-4 py-3 text-left font-medium">Created At</th>
                                    <th scope="col" class="px-4 py-3 text-center font-medium">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border-800">
                                <!-- User 1 -->
                                <tr class="hover:bg-surface-700/50 transition" data-user-id="1">
                                    <td class="px-4 py-2.5 whitespace-nowrap text-xs text-text-200">
                                        John Smith
                                    </td>
                                    <td class="px-4 py-2.5 whitespace-nowrap text-xs text-text-300">
                                        +1 (555) 123-4567
                                    </td>
                                    <td class="px-4 py-2.5 whitespace-nowrap text-xs text-text-300">
                                        john.smith@company.com
                                    </td>
                                    <td class="px-4 py-2.5 whitespace-nowrap font-mono text-xs text-radar-400">
                                        jsmith
                                    </td>
                                    <td class="px-4 py-2.5 whitespace-nowrap">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium border bg-munti-red-700/15 text-munti-red-400 border-munti-red-600/30">
                                            Admin
                                        </span>
                                    </td>
                                    <td class="px-4 py-2.5 whitespace-nowrap text-xs text-text-500">
                                        2024-12-01 14:30
                                    </td>
                                    <td class="px-4 py-2.5 whitespace-nowrap text-center">
                                        <div class="flex items-center justify-center gap-1.5">
                                            <button type="button" 
                                                    onclick="editUser('1')"
                                                    class="p-1.5 rounded-lg text-text-400 hover:text-radar-400 hover:bg-surface-700/70 transition-all duration-200 group"
                                                    title="Edit User">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                                </svg>
                                            </button>
                                            <button type="button" 
                                                    onclick="deleteUser('1', 'John Smith')"
                                                    class="p-1.5 rounded-lg text-text-400 hover:text-munti-red-400 hover:bg-surface-700/70 transition-all duration-200 group"
                                                    title="Delete User">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="1.2em" height="1.2em" viewBox="0 0 24 24" class="text-red-400">
                                                    <path fill="currentColor" d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/>
                                                </svg>
                                            </button>
                                        </div>
                                    </td>
                                </tr>

                                <!-- User 2 -->
                                <tr class="hover:bg-surface-700/50 transition" data-user-id="2">
                                    <td class="px-4 py-2.5 whitespace-nowrap text-xs text-text-200">
                                        Sarah Johnson
                                    </td>
                                    <td class="px-4 py-2.5 whitespace-nowrap text-xs text-text-300">
                                        +1 (555) 234-5678
                                    </td>
                                    <td class="px-4 py-2.5 whitespace-nowrap text-xs text-text-300">
                                        sarah.johnson@company.com
                                    </td>
                                    <td class="px-4 py-2.5 whitespace-nowrap font-mono text-xs text-radar-400">
                                        sjohnson
                                    </td>
                                    <td class="px-4 py-2.5 whitespace-nowrap">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium border bg-radar-700/15 text-radar-400 border-radar-600/30">
                                            Manager
                                        </span>
                                    </td>
                                    <td class="px-4 py-2.5 whitespace-nowrap text-xs text-text-500">
                                        2024-12-03 09:15
                                    </td>
                                    <td class="px-4 py-2.5 whitespace-nowrap text-center">
                                        <div class="flex items-center justify-center gap-1.5">
                                            <button type="button" 
                                                    onclick="editUser('2')"
                                                    class="p-1.5 rounded-lg text-text-400 hover:text-radar-400 hover:bg-surface-700/70 transition-all duration-200 group"
                                                    title="Edit User">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                                </svg>
                                            </button>
                                            <button type="button" 
                                                    onclick="deleteUser('2', 'Sarah Johnson')"
                                                    class="p-1.5 rounded-lg text-text-400 hover:text-munti-red-400 hover:bg-surface-700/70 transition-all duration-200 group"
                                                    title="Delete User">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="1.2em" height="1.2em" viewBox="0 0 24 24" class="text-red-400">
                                                    <path fill="currentColor" d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/>
                                                </svg>
                                            </button>
                                        </div>
                                    </td>
                                </tr>

                                <!-- User 3 -->
                                <tr class="hover:bg-surface-700/50 transition" data-user-id="3">
                                    <td class="px-4 py-2.5 whitespace-nowrap text-xs text-text-200">
                                        Michael Chen
                                    </td>
                                    <td class="px-4 py-2.5 whitespace-nowrap text-xs text-text-300">
                                        +1 (555) 345-6789
                                    </td>
                                    <td class="px-4 py-2.5 whitespace-nowrap text-xs text-text-300">
                                        michael.chen@company.com
                                    </td>
                                    <td class="px-4 py-2.5 whitespace-nowrap font-mono text-xs text-radar-400">
                                        mchen
                                    </td>
                                    <td class="px-4 py-2.5 whitespace-nowrap">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium border bg-munti-green-700/15 text-munti-green-400 border-munti-green-600/30">
                                            User
                                        </span>
                                    </td>
                                    <td class="px-4 py-2.5 whitespace-nowrap text-xs text-text-500">
                                        2024-12-05 11:20
                                    </td>
                                    <td class="px-4 py-2.5 whitespace-nowrap text-center">
                                        <div class="flex items-center justify-center gap-1.5">
                                            <button type="button" 
                                                    onclick="editUser('3')"
                                                    class="p-1.5 rounded-lg text-text-400 hover:text-radar-400 hover:bg-surface-700/70 transition-all duration-200 group"
                                                    title="Edit User">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                                </svg>
                                            </button>
                                            <button type="button" 
                                                    onclick="deleteUser('3', 'Michael Chen')"
                                                    class="p-1.5 rounded-lg text-text-400 hover:text-munti-red-400 hover:bg-surface-700/70 transition-all duration-200 group"
                                                    title="Delete User">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="1.2em" height="1.2em" viewBox="0 0 24 24" class="text-red-400">
                                                    <path fill="currentColor" d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/>
                                                </svg>
                                            </button>
                                        </div>
                                    </td>
                                </tr>

                                <!-- User 4 -->
                                <tr class="hover:bg-surface-700/50 transition" data-user-id="4">
                                    <td class="px-4 py-2.5 whitespace-nowrap text-xs text-text-200">
                                        Emily Rodriguez
                                    </td>
                                    <td class="px-4 py-2.5 whitespace-nowrap text-xs text-text-300">
                                        +1 (555) 456-7890
                                    </td>
                                    <td class="px-4 py-2.5 whitespace-nowrap text-xs text-text-300">
                                        emily.r@company.com
                                    </td>
                                    <td class="px-4 py-2.5 whitespace-nowrap font-mono text-xs text-radar-400">
                                        erodriguez
                                    </td>
                                    <td class="px-4 py-2.5 whitespace-nowrap">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium border bg-radar-700/15 text-radar-400 border-radar-600/30">
                                            Manager
                                        </span>
                                    </td>
                                    <td class="px-4 py-2.5 whitespace-nowrap text-xs text-text-500">
                                        2024-12-07 16:45
                                    </td>
                                    <td class="px-4 py-2.5 whitespace-nowrap text-center">
                                        <div class="flex items-center justify-center gap-1.5">
                                            <button type="button" 
                                                    onclick="editUser('4')"
                                                    class="p-1.5 rounded-lg text-text-400 hover:text-radar-400 hover:bg-surface-700/70 transition-all duration-200 group"
                                                    title="Edit User">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                                </svg>
                                            </button>
                                            <button type="button" 
                                                    onclick="deleteUser('4', 'Emily Rodriguez')"
                                                    class="p-1.5 rounded-lg text-text-400 hover:text-munti-red-400 hover:bg-surface-700/70 transition-all duration-200 group"
                                                    title="Delete User">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="1.2em" height="1.2em" viewBox="0 0 24 24" class="text-red-400">
                                                    <path fill="currentColor" d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/>
                                                </svg>
                                            </button>
                                        </div>
                                    </td>
                                </tr>

                                <!-- User 5 -->
                                <tr class="hover:bg-surface-700/50 transition" data-user-id="5">
                                    <td class="px-4 py-2.5 whitespace-nowrap text-xs text-text-200">
                                        David Kim
                                    </td>
                                    <td class="px-4 py-2.5 whitespace-nowrap text-xs text-text-300">
                                        +1 (555) 567-8901
                                    </td>
                                    <td class="px-4 py-2.5 whitespace-nowrap text-xs text-text-300">
                                        david.kim@company.com
                                    </td>
                                    <td class="px-4 py-2.5 whitespace-nowrap font-mono text-xs text-radar-400">
                                        dkim
                                    </td>
                                    <td class="px-4 py-2.5 whitespace-nowrap">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium border bg-munti-green-700/15 text-munti-green-400 border-munti-green-600/30">
                                            User
                                        </span>
                                    </td>
                                    <td class="px-4 py-2.5 whitespace-nowrap text-xs text-text-500">
                                        2024-12-08 13:10
                                    </td>
                                    <td class="px-4 py-2.5 whitespace-nowrap text-center">
                                        <div class="flex items-center justify-center gap-1.5">
                                            <button type="button" 
                                                    onclick="editUser('5')"
                                                    class="p-1.5 rounded-lg text-text-400 hover:text-radar-400 hover:bg-surface-700/70 transition-all duration-200 group"
                                                    title="Edit User">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                                </svg>
                                            </button>
                                            <button type="button" 
                                                    onclick="deleteUser('5', 'David Kim')"
                                                    class="p-1.5 rounded-lg text-text-400 hover:text-munti-red-400 hover:bg-surface-700/70 transition-all duration-200 group"
                                                    title="Delete User">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="1.2em" height="1.2em" viewBox="0 0 24 24" class="text-red-400">
                                                    <path fill="currentColor" d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/>
                                                </svg>
                                            </button>
                                        </div>
                                    </td>
                                </tr>

                                <!-- User 6 -->
                                <tr class="hover:bg-surface-700/50 transition" data-user-id="6">
                                    <td class="px-4 py-2.5 whitespace-nowrap text-xs text-text-200">
                                        Lisa Thompson
                                    </td>
                                    <td class="px-4 py-2.5 whitespace-nowrap text-xs text-text-300">
                                        +1 (555) 678-9012
                                    </td>
                                    <td class="px-4 py-2.5 whitespace-nowrap text-xs text-text-300">
                                        lisa.t@company.com
                                    </td>
                                    <td class="px-4 py-2.5 whitespace-nowrap font-mono text-xs text-radar-400">
                                        lthompson
                                    </td>
                                    <td class="px-4 py-2.5 whitespace-nowrap">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium border bg-munti-red-700/15 text-munti-red-400 border-munti-red-600/30">
                                            Admin
                                        </span>
                                    </td>
                                    <td class="px-4 py-2.5 whitespace-nowrap text-xs text-text-500">
                                        2024-12-10 08:30
                                    </td>
                                    <td class="px-4 py-2.5 whitespace-nowrap text-center">
                                        <div class="flex items-center justify-center gap-1.5">
                                            <button type="button" 
                                                    onclick="editUser('6')"
                                                    class="p-1.5 rounded-lg text-text-400 hover:text-radar-400 hover:bg-surface-700/70 transition-all duration-200 group"
                                                    title="Edit User">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                                </svg>
                                            </button>
                                            <button type="button" 
                                                    onclick="deleteUser('6', 'Lisa Thompson')"
                                                    class="p-1.5 rounded-lg text-text-400 hover:text-munti-red-400 hover:bg-surface-700/70 transition-all duration-200 group"
                                                    title="Delete User">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="1.2em" height="1.2em" viewBox="0 0 24 24" class="text-red-400">
                                                    <path fill="currentColor" d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/>
                                                </svg>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Edit User Modal -->
<div id="editModal" class="fixed inset-0 bg-black/70 backdrop-blur-sm z-50 hidden items-center justify-center p-4" style="display: none;">
    <div class="bg-surface-800 rounded-2xl border border-border-700 shadow-2xl w-full max-w-3xl max-h-[90vh] overflow-y-auto thin-scrollbar">
        <div class="sticky top-0 bg-surface-800/95 backdrop-blur-sm px-6 py-4 border-b border-border-700 flex items-center justify-between">
            <h3 class="text-lg font-semibold text-text-100 flex items-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-radar-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                </svg>
                Edit User
            </h3>
            <button type="button" onclick="closeEditModal()" class="p-2 rounded-lg hover:bg-surface-700 text-text-400 hover:text-text-100 transition">
                <svg xmlns="http://www.w3.org