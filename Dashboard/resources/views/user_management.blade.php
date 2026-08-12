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

                        {{-- Top row: Firstname | Lastname | Contact no | email --}}
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
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

                            <!-- Contact Number (11 digits only) -->
                            <div class="flex flex-col">
                                <label for="contact_number" class="block text-xs font-medium text-text-400 mb-1.5 uppercase tracking-wide">
                                    Contact No <span class="text-munti-red-400">*</span>
                                </label>
                                <input type="tel" id="contact_number" name="contact_number" value="{{ old('contact_number') }}" required
                                    maxlength="11"
                                    pattern="[0-9]{11}"
                                    inputmode="numeric"
                                    oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 11)"
                                    class="w-full px-3.5 py-2.5 border border-border-600 rounded-lg bg-surface-900 text-text-100 placeholder-text-500 focus:ring-2 focus:ring-radar-500/40 focus:border-radar-500 text-sm transition @error('contact_number') border-munti-red-500 @enderror"
                                    placeholder="09XXXXXXXXX">
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
                                    pattern="[a-z0-9._%+\-]+@[a-z0-9.\-]+\.[a-z]{2,}$"
                                    title="Please enter a valid email address"
                                    class="w-full px-3.5 py-2.5 border border-border-600 rounded-lg bg-surface-900 text-text-100 placeholder-text-500 focus:ring-2 focus:ring-radar-500/40 focus:border-radar-500 text-sm transition @error('email') border-munti-red-500 @enderror"
                                    placeholder="email@example.com">
                                @error('email')
                                    <p class="mt-1 text-xs text-munti-red-400">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        {{-- Bottom row: username | password | Confirm pass | role --}}
                        <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                            <!-- Username -->
                            <div class="flex flex-col">
                                <label for="username" class="block text-xs font-medium text-text-400 mb-1.5 uppercase tracking-wide">
                                    Username <span class="text-munti-red-400">*</span>
                                </label>
                                <input type="text" id="username" name="username" value="{{ old('username') }}" required
                                    class="w-full px-3.5 py-2.5 border border-border-600 rounded-lg bg-surface-900 text-text-100 placeholder-text-500 focus:ring-2 focus:ring-radar-500/40 focus:border-radar-500 text-sm transition @error('username') border-munti-red-500 @enderror"
                                    placeholder="Enter username">
                                @error('username')
                                    <p class="mt-1 text-xs text-munti-red-400">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Password with show/hide -->
                            <div class="flex flex-col">
                                <label for="password" class="block text-xs font-medium text-text-400 mb-1.5 uppercase tracking-wide">
                                    Password <span class="text-munti-red-400">*</span>
                                </label>
                                <div class="relative">
                                    <input type="password" id="password" name="password" required
                                        class="w-full px-3.5 py-2.5 pr-10 border border-border-600 rounded-lg bg-surface-900 text-text-100 placeholder-text-500 focus:ring-2 focus:ring-radar-500/40 focus:border-radar-500 text-sm transition @error('password') border-munti-red-500 @enderror"
                                        placeholder="Enter password">
                                    <button type="button" onclick="togglePassword('password', this)"
                                        class="absolute inset-y-0 right-0 flex items-center pr-3 text-text-400 hover:text-text-200 transition">
                                        <!-- Eye (show) -->
                                        <svg class="w-4 h-4 eye-open" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                        </svg>
                                        <!-- Eye-slash (hide) - hidden by default -->
                                        <svg class="w-4 h-4 eye-closed hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>
                                        </svg>
                                    </button>
                                </div>
                                @error('password')
                                    <p class="mt-1 text-xs text-munti-red-400">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Confirm Password with show/hide -->
                            <div class="flex flex-col">
                                <label for="password_confirmation" class="block text-xs font-medium text-text-400 mb-1.5 uppercase tracking-wide">
                                    Confirm Password <span class="text-munti-red-400">*</span>
                                </label>
                                <div class="relative">
                                    <input type="password" id="password_confirmation" name="password_confirmation" required
                                        class="w-full px-3.5 py-2.5 pr-10 border border-border-600 rounded-lg bg-surface-900 text-text-100 placeholder-text-500 focus:ring-2 focus:ring-radar-500/40 focus:border-radar-500 text-sm transition"
                                        placeholder="Enter password again">
                                    <button type="button" onclick="togglePassword('password_confirmation', this)"
                                        class="absolute inset-y-0 right-0 flex items-center pr-3 text-text-400 hover:text-text-200 transition">
                                        <!-- Eye (show) -->
                                        <svg class="w-4 h-4 eye-open" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                        </svg>
                                        <!-- Eye-slash (hide) -->
                                        <svg class="w-4 h-4 eye-closed hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>
                                        </svg>
                                    </button>
                                </div>
                            </div>

                            <!-- Role -->
                            <div class="flex flex-col">
                                <label for="role" class="block text-xs font-medium text-text-400 mb-1.5 uppercase tracking-wide">
                                    Role <span class="text-munti-red-400">*</span>
                                </label>
                                <select id="role" name="role" required
                                    class="w-full px-3.5 py-2.5 border border-border-600 rounded-lg bg-surface-900 text-text-100 focus:ring-2 focus:ring-radar-500/40 focus:border-radar-500 text-sm transition @error('role') border-munti-red-500 @enderror">
                                    <option value="">Select role</option>
                                    <option value="superAdmin" {{ old('role') == 'superAdmin' ? 'selected' : '' }}>Super Administrator</option>
                                    <option value="admin" {{ old('role') == 'admin' ? 'selected' : '' }}>Admin</option>
                                    <option value="user" {{ old('role') == 'user' ? 'selected' : '' }}>User</option>
                                </select>
                                @error('role')
                                    <p class="mt-1 text-xs text-munti-red-400">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        {{-- Note + Create User button --}}
                        <div class="mt-4 flex flex-col sm:flex-row gap-3 items-stretch">
                            <div class="flex-1 flex items-center px-4 py-3 bg-surface-800 rounded-lg border border-border-600">
                                <p class="text-xs text-text-300">
                                    <span class="text-munti-red-400 font-semibold">*</span>
                                    Note: Username and Email must be unique across all records.
                                </p>
                            </div>

                            <button type="submit"
                                    class="shrink-0 px-6 py-2.5 bg-munti-green-600 hover:bg-munti-green-500 text-text-100 font-semibold rounded-lg transition border border-munti-green-500/30 flex items-center justify-center gap-2 whitespace-nowrap min-w-[140px]">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                </svg>
                                Create User
                            </button>
                        </div>
                    </form>
                </div>

                <script>
                    function togglePassword(inputId, button) {
                        const input = document.getElementById(inputId);
                        const eyeOpen = button.querySelector('.eye-open');
                        const eyeClosed = button.querySelector('.eye-closed');

                        if (input.type === 'password') {
                            input.type = 'text';
                            eyeOpen.classList.add('hidden');
                            eyeClosed.classList.remove('hidden');
                        } else {
                            input.type = 'password';
                            eyeOpen.classList.remove('hidden');
                            eyeClosed.classList.add('hidden');
                        }
                    }
                </script>

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
                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
        
        <form id="editForm" method="POST" class="p-6">
            @csrf
            @method('PUT')
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <!-- First Name -->
                <div class="flex flex-col">
                    <label for="edit_first_name" class="block text-xs font-medium text-text-400 mb-1.5 uppercase tracking-wide">
                        First Name <span class="text-munti-red-400">*</span>
                    </label>
                    <input type="text" id="edit_first_name" name="first_name" required
                        class="w-full px-3.5 py-2.5 border border-border-600 rounded-lg bg-surface-900 text-text-100 placeholder-text-500 focus:ring-2 focus:ring-radar-500/40 focus:border-radar-500 text-sm transition">
                </div>

                <!-- Last Name -->
                <div class="flex flex-col">
                    <label for="edit_last_name" class="block text-xs font-medium text-text-400 mb-1.5 uppercase tracking-wide">
                        Last Name <span class="text-munti-red-400">*</span>
                    </label>
                    <input type="text" id="edit_last_name" name="last_name" required
                        class="w-full px-3.5 py-2.5 border border-border-600 rounded-lg bg-surface-900 text-text-100 placeholder-text-500 focus:ring-2 focus:ring-radar-500/40 focus:border-radar-500 text-sm transition">
                </div>

                <!-- Contact Number -->
                <div class="flex flex-col">
                    <label for="edit_contact_number" class="block text-xs font-medium text-text-400 mb-1.5 uppercase tracking-wide">
                        Contact Number <span class="text-munti-red-400">*</span>
                    </label>
                    <input type="text" id="edit_contact_number" name="contact_number" required
                        class="w-full px-3.5 py-2.5 border border-border-600 rounded-lg bg-surface-900 text-text-100 placeholder-text-500 focus:ring-2 focus:ring-radar-500/40 focus:border-radar-500 text-sm transition">
                </div>

                <!-- Email -->
                <div class="flex flex-col">
                    <label for="edit_email" class="block text-xs font-medium text-text-400 mb-1.5 uppercase tracking-wide">
                        Email <span class="text-munti-red-400">*</span>
                    </label>
                    <input type="email" id="edit_email" name="email" required
                        class="w-full px-3.5 py-2.5 border border-border-600 rounded-lg bg-surface-900 text-text-100 placeholder-text-500 focus:ring-2 focus:ring-radar-500/40 focus:border-radar-500 text-sm transition">
                </div>

                <!-- Username -->
                <div class="flex flex-col">
                    <label for="edit_username" class="block text-xs font-medium text-text-400 mb-1.5 uppercase tracking-wide">
                        Username <span class="text-munti-red-400">*</span>
                    </label>
                    <input type="text" id="edit_username" name="username" required
                        class="w-full px-3.5 py-2.5 border border-border-600 rounded-lg bg-surface-900 text-text-100 placeholder-text-500 focus:ring-2 focus:ring-radar-500/40 focus:border-radar-500 text-sm transition">
                </div>

                <!-- Role -->
                <div class="flex flex-col">
                    <label for="edit_role" class="block text-xs font-medium text-text-400 mb-1.5 uppercase tracking-wide">
                        Role <span class="text-munti-red-400">*</span>
                    </label>
                    <select id="edit_role" name="role" required
                        class="w-full px-3.5 py-2.5 border border-border-600 rounded-lg bg-surface-900 text-text-100 focus:ring-2 focus:ring-radar-500/40 focus:border-radar-500 text-sm transition">
                        <option value="admin">Admin</option>
                        <option value="manager">Manager</option>
                        <option value="user">User</option>
                    </select>
                </div>

                <!-- Password (optional on edit) -->
                <div class="flex flex-col">
                    <label for="edit_password" class="block text-xs font-medium text-text-400 mb-1.5 uppercase tracking-wide">
                        New Password <span class="text-text-500">(leave blank to keep current)</span>
                    </label>
                    <input type="password" id="edit_password" name="password"
                        class="w-full px-3.5 py-2.5 border border-border-600 rounded-lg bg-surface-900 text-text-100 placeholder-text-500 focus:ring-2 focus:ring-radar-500/40 focus:border-radar-500 text-sm transition"
                        placeholder="Enter new password (optional)">
                </div>

                <!-- Confirm Password -->
                <div class="flex flex-col">
                    <label for="edit_password_confirmation" class="block text-xs font-medium text-text-400 mb-1.5 uppercase tracking-wide">
                        Confirm New Password
                    </label>
                    <input type="password" id="edit_password_confirmation" name="password_confirmation"
                        class="w-full px-3.5 py-2.5 border border-border-600 rounded-lg bg-surface-900 text-text-100 placeholder-text-500 focus:ring-2 focus:ring-radar-500/40 focus:border-radar-500 text-sm transition"
                        placeholder="Confirm new password">
                </div>
            </div>

            <div class="mt-6 pt-4 border-t border-border-700 flex justify-end gap-3">
                <button type="button" onclick="closeEditModal()"
                        class="px-4 py-2.5 text-sm font-medium text-text-300 hover:text-text-100 bg-surface-700 hover:bg-surface-600 rounded-lg transition border border-border-600">
                    Cancel
                </button>
                <button type="submit"
                        class="px-6 py-2.5 bg-radar-500 hover:bg-radar-400 text-text-100 font-semibold rounded-lg transition border border-radar-400/30 flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                    Update User
                </button>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
// Edit User
function editUser(userId) {
    const modal = document.getElementById('editModal');
    modal.style.display = 'flex';
    
    // Hardcoded user data
    const users = {
        '1': {
            first_name: 'John',
            last_name: 'Smith',
            contact_number: '+1 (555) 123-4567',
            email: 'john.smith@company.com',
            username: 'jsmith',
            role: 'admin'
        },
        '2': {
            first_name: 'Sarah',
            last_name: 'Johnson',
            contact_number: '+1 (555) 234-5678',
            email: 'sarah.johnson@company.com',
            username: 'sjohnson',
            role: 'manager'
        },
        '3': {
            first_name: 'Michael',
            last_name: 'Chen',
            contact_number: '+1 (555) 345-6789',
            email: 'michael.chen@company.com',
            username: 'mchen',
            role: 'user'
        },
        '4': {
            first_name: 'Emily',
            last_name: 'Rodriguez',
            contact_number: '+1 (555) 456-7890',
            email: 'emily.r@company.com',
            username: 'erodriguez',
            role: 'manager'
        },
        '5': {
            first_name: 'David',
            last_name: 'Kim',
            contact_number: '+1 (555) 567-8901',
            email: 'david.kim@company.com',
            username: 'dkim',
            role: 'user'
        },
        '6': {
            first_name: 'Lisa',
            last_name: 'Thompson',
            contact_number: '+1 (555) 678-9012',
            email: 'lisa.t@company.com',
            username: 'lthompson',
            role: 'admin'
        }
    };
    
    const user = users[userId];
    if (user) {
        document.getElementById('edit_first_name').value = user.first_name || '';
        document.getElementById('edit_last_name').value = user.last_name || '';
        document.getElementById('edit_contact_number').value = user.contact_number || '';
        document.getElementById('edit_email').value = user.email || '';
        document.getElementById('edit_username').value = user.username || '';
        document.getElementById('edit_role').value = user.role || 'user';
        
        // Clear password fields
        document.getElementById('edit_password').value = '';
        document.getElementById('edit_password_confirmation').value = '';
        
        // Set form action (will not actually submit in hardcoded version)
        document.getElementById('editForm').action = '#';
    } else {
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'User not found.',
            background: '#1f2937',
            color: '#f3f4f6'
        });
    }
}

function closeEditModal() {
    document.getElementById('editModal').style.display = 'none';
}

// Delete User
function deleteUser(userId, userName) {
    Swal.fire({
        title: 'Delete User?',
        html: `Are you sure you want to delete user <strong>"${userName}"</strong>?<br><span style="color: #ef4444;">This action cannot be undone!</span>`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc2626',
        cancelButtonColor: '#6b7280',
        confirmButtonText: 'Yes, delete it!',
        cancelButtonText: 'Cancel',
        background: '#1f2937',
        color: '#f3f4f6',
        iconColor: '#f59e0b'
    }).then((result) => {
        if (result.isConfirmed) {
            // In hardcoded version, just show a success message
            Swal.fire({
                icon: 'success',
                title: 'Deleted!',
                text: `${userName} has been deleted. (Demo)`,
                background: '#1f2937',
                color: '#f3f4f6',
                iconColor: '#22c55e'
            });
        }
    });
}

// Close modal on ESC key
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closeEditModal();
    }
});

// Close modal on backdrop click
document.getElementById('editModal').addEventListener('click', function(event) {
    if (event.target === this) {
        closeEditModal();
    }
});

// Prevent actual form submission for demo
document.getElementById('editForm').addEventListener('submit', function(e) {
    e.preventDefault();
    Swal.fire({
        icon: 'success',
        title: 'Updated!',
        text: 'User information has been updated. (Demo)',
        background: '#1f2937',
        color: '#f3f4f6',
        iconColor: '#22c55e'
    });
    closeEditModal();
});
</script>

@include('layouts.footer')