<nav class="bg-background-900/95 backdrop-blur-sm border-b border-border-800 shadow-lg fixed top-0 left-0 right-0 z-50">
    @php
        $systemName = config('app.system_name', 'Environmental Monitoring System Gateway');
        $companyName = config('app.company_name', 'Uplink Integrated Solutions Inc.');
        $appVersion = config('app.version', '7.0');
    @endphp
    <div class="max-w-8xl mx-auto px-3 sm:px-6">
        <div class="h-16 flex items-center justify-between">

            {{-- Logo / Title --}}
            <div class="flex items-center gap-x-3 min-w-0">
                <div class="shrink-0 flex items-center justify-center w-10 h-10">
                    <img src="{{ asset('ems-icon.svg') }}" alt="EMS Gateway icon" class="w-10 h-10">
                </div>

                {{-- System Name + Version --}}
                <div class="min-w-0 leading-none">
                    <div class="text-sm sm:text-base font-bold text-text-100 tracking-tight uppercase truncate">
                        {{ $systemName }}
                    </div>
                    <div class="flex items-center gap-x-1.5 mt-1">
                        <span class="text-[10px] text-text-400 uppercase tracking-wide truncate max-w-[15rem] sm:max-w-none">{{ $companyName }}</span>
                        <span class="text-[10px] text-munti-green-400 bg-munti-green-700/20 px-1.5 py-0.5 rounded-full shrink-0 border border-munti-green-600/30">Version {{ $appVersion }}</span>
                    </div>
                </div>
            </div>

            {{-- Desktop Navigation (xl+) --}}
            <div class="hidden xl:flex items-center gap-x-5 lg:gap-x-20 text-sm font-medium">
                @if(session('role') === 'admin' || session('role') === 'superAdmin')
                    <a href="{{ route('home') }}" class="text-text-400 hover:text-text-100 transition-colors py-1">Dashboards</a>
                    <a href="{{ route('stations.index') }}" class="text-text-400 hover:text-text-100 transition-colors py-1">Stations</a>
                    <a href="{{ route('sms.index') }}" class="text-text-400 hover:text-text-100 transition-colors py-1">SMS</a>

                    {{-- Maintenance Dropdown --}}
                    <div class="relative group" id="maintenance-dropdown-desktop">
                        <button type="button"
                                class="maintenance-dropdown-toggle flex items-center gap-x-1 text-text-400 hover:text-text-100 transition-colors py-1 focus:outline-none"
                                aria-expanded="false">
                            Maintenance
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 transition-transform duration-200" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>
                        <div class="maintenance-dropdown-menu absolute left-0 mt-2 w-52 bg-surface-800 rounded-xl shadow-2xl border border-border-700 hidden z-20 overflow-hidden">
                            <a href="{{ route('maintenance.index') }}" class="block px-4 py-2.5 text-sm text-text-400 hover:bg-surface-700 hover:text-radar-400 transition-colors">Network Diagnostic</a>
                            <a href="{{ route('services.terminal') }}" target="_blank" rel="noopener" class="block px-4 py-2.5 text-sm text-text-400 hover:bg-surface-700 hover:text-radar-400 transition-colors">Terminal</a>
                            <a href="{{ route('services.index') }}" class="block px-4 py-2.5 text-sm text-text-400 hover:bg-surface-700 hover:text-radar-400 transition-colors">Services</a>
                            <!-- API Logs with badge -->
                            <a href="{{ route('api-logs.index') }}" class="block px-4 py-2.5 text-sm text-text-400 hover:bg-surface-700 hover:text-radar-400 transition-colors border-t border-border-700 flex items-center justify-between">
                                <span>API Logs</span>
                                @php
                                    $apiUnseenCount = \App\Models\ApiLog::unseen()->count();
                                @endphp
                                @if($apiUnseenCount > 0)
                                    <span class="inline-flex items-center justify-center px-2 py-0.5 text-xs font-medium rounded-full bg-red-500/20 text-red-400 border border-red-500/30">
                                        {{ $apiUnseenCount }}
                                    </span>
                                @endif
                            </a>
                            
                            <!-- System Logs with badge -->
                            <a href="{{ route('logs.index') }}" class="block px-4 py-2.5 text-sm text-text-400 hover:bg-surface-700 hover:text-radar-400 transition-colors flex items-center justify-between">
                                <span>System Logs</span>
                                @php
                                    $systemUnseenCount = \App\Models\SystemLog::unseen()->count();
                                @endphp
                                @if($systemUnseenCount > 0)
                                    <span class="inline-flex items-center justify-center px-2 py-0.5 text-xs font-medium rounded-full bg-red-500/20 text-red-400 border border-red-500/30">
                                        {{ $systemUnseenCount }}
                                    </span>
                                @endif
                            </a>
                        </div>
                    </div>

                    <a href="{{ route('about') }}" class="text-text-400 hover:text-text-100 transition-colors py-1">About</a>
                @endif
            </div>

            {{-- Right side: Bell + Avatar + Mobile toggle --}}
            <div class="flex items-center gap-x-1 sm:gap-x-3">

                {{-- Notification Bell (Admin only) --}}
                @if(session('role') === 'admin' || session('role') === 'superAdmin')
                <div class="relative" id="notification-container">
                    <button type="button"
                            id="notification-bell"
                            class="relative text-text-300 hover:text-text-100 transition-colors focus:outline-none p-1.5 rounded-lg hover:bg-surface-700"
                            aria-label="Notifications"
                            aria-expanded="false">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" style="fill: currentColor;">
                            <path d="M12 22a2.98 2.98 0 0 0 2.818-2H9.182A2.98 2.98 0 0 0 12 22zm7-7.414V10c0-3.217-2.185-5.927-5.145-6.742C13.562 2.52 12.846 2 12 2s-1.562.52-1.855 1.258C7.185 4.074 5 6.783 5 10v4.586l-1.707 1.707A.996.996 0 0 0 3 17v1a1 1 0 0 0 1 1h16a1 1 0 0 0 1-1v-1a.996.996 0 0 0-.293-.707L19 14.586z"/>
                        </svg>
                        <span id="notification-dot" class="absolute -top-0.5 -right-0.5 w-2.5 h-2.5 bg-munti-red-600 rounded-full border-2 border-background-900 hidden"></span>
                    </button>

                    <div id="notification-dropdown"
                        class="absolute right-0 mt-2 w-[calc(100vw-2rem)] sm:w-96 max-w-[400px] bg-surface-800 rounded-xl shadow-2xl border border-border-700 hidden z-40 overflow-hidden">
                        <div class="flex items-center justify-between px-4 py-3 border-b border-border-700">
                            <h3 class="text-sm font-semibold text-text-100">Recent Logs</h3>
                            <div class="flex items-center gap-3">
                                <button id="mark-all-seen" class="text-xs text-text-400 hover:text-text-100 transition px-2 py-1 rounded hover:bg-surface-700">Mark All as Seen</button>
                            </div>
                        </div>
                        <div id="notification-list" class="max-h-72 overflow-y-auto divide-y divide-border-700 thin-scrollbar">
                            <div class="px-4 py-6 text-sm text-text-400 text-center">Loading…</div>
                        </div>
                        <div class="px-4 py-2 border-t border-border-700 text-right">
                            <a href="{{ route('api-logs.index') }}" class="text-xs text-radar-400 hover:underline">API Logs</a>
                            <span class="mx-1 text-text-500">|</span>
                            <a href="{{ route('logs.index') }}" class="text-xs text-radar-400 hover:underline">System Logs</a>

                        </div>
                    </div>
                </div>
                @endif

                {{-- Settings Gear Icon --}}
                @if(session('role') === 'admin' || session('role') === 'superAdmin')
                <div class="relative" id="settings-gear-container">
                    <button type="button"
                            id="settings-gear-button"
                            class="relative text-text-300 hover:text-text-100 transition-colors focus:outline-none p-1.5 rounded-lg hover:bg-surface-700"
                            aria-label="Settings"
                            aria-expanded="false">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M12 8c-2.21 0-4 1.79-4 4s1.79 4 4 4 4-1.79 4-4-1.79-4-4-4m0 6c-1.08 0-2-.92-2-2s.92-2 2-2 2 .92 2 2-.92 2-2 2"></path>
                            <path d="m20.42 13.4-.51-.29c.05-.37.08-.74.08-1.11s-.03-.74-.08-1.11l.51-.29c.96-.55 1.28-1.78.73-2.73l-1-1.73a2.006 2.006 0 0 0-2.73-.73l-.53.31c-.58-.46-1.22-.83-1.9-1.11v-.6c0-1.1-.9-2-2-2h-2c-1.1 0-2 .9-2 2v.6c-.67.28-1.31.66-1.9 1.11l-.53-.31c-.96-.55-2.18-.22-2.73.73l-1 1.73c-.55.96-.22 2.18.73 2.73l.51.29c-.05.37-.08.74-.08 1.11s.03.74.08 1.11l-.51.29c-.96.55-1.28 1.78-.73 2.73l1 1.73c.55.95 1.77 1.28 2.73.73l.53-.31c.58.46 1.22.83 1.9 1.11v.6c0 1.1.9 2 2 2h2c1.1 0 2-.9 2-2v-.6a8.7 8.7 0 0 0 1.9-1.11l.53.31c.95.55 2.18.22 2.73-.73l1-1.73c.55-.96.22-2.18-.73-2.73m-2.59-2.78c.11.45.17.92.17 1.38s-.06.92-.17 1.38a1 1 0 0 0 .47 1.11l1.12.65-1 1.73-1.14-.66c-.38-.22-.87-.16-1.19.14-.68.65-1.51 1.13-2.38 1.4-.42.13-.71.52-.71.96v1.3h-2v-1.3c0-.44-.29-.83-.71-.96-.88-.27-1.7-.75-2.38-1.4a1.01 1.01 0 0 0-1.19-.15l-1.14.66-1-1.73 1.12-.65c.39-.22.58-.68.47-1.11-.11-.45-.17-.92-.17-1.38s.06-.93.17-1.38A1 1 0 0 0 5.7 9.5l-1.12-.65 1-1.73 1.14.66c.38.22.87.16 1.19-.14.68-.65 1.51-1.13 2.38-1.4.42-.13.71-.52.71-.96v-1.3h2v1.3c0 .44.29.83.71.96.88.27 1.7.75 2.38 1.4.32.31.81.36 1.19.14l1.14-.66 1 1.73-1.12.65c-.39.22-.58.68-.47 1.11Z"></path>
                        </svg>
                    </button>

                    {{-- Settings Gear Dropdown --}}
                    <div id="settings-gear-dropdown"
                        class="absolute right-0 mt-2 w-48 bg-surface-800 rounded-xl shadow-2xl border border-border-700 hidden z-30 overflow-hidden">
                        <a href="{{ route('env.editor') }}" class="flex items-center gap-2 px-4 py-2.5 text-sm text-text-400 hover:bg-surface-700 hover:text-radar-400 transition-colors">
                            Database
                        </a>
                        <a href="{{ route('mqtt.editor') }}" class="flex items-center gap-2 px-4 py-2.5 text-sm text-text-400 hover:bg-surface-700 hover:text-radar-400 transition-colors border-t border-border-700">
                            MQTT
                        </a>
                        <a href="{{ route('network.index') }}" class="flex items-center gap-2 px-4 py-2.5 text-sm text-text-400 hover:bg-surface-700 hover:text-radar-400 transition-colors border-t border-border-700">
                            Network
                        </a>
                        <a href="{{ route('api.editor') }}" class="flex items-center gap-2 px-4 py-2.5 text-sm text-text-400 hover:bg-surface-700 hover:text-radar-400 transition-colors border-t border-border-700">
                            API
                        </a>
                        {{-- <a href="{{ route('about') }}" class="flex items-center gap-2 px-4 py-2.5 text-sm text-text-400 hover:bg-surface-700 hover:text-radar-400 transition-colors border-t border-border-700">
                            About
                        </a> --}}
                    </div>
                </div>
                @endif

                {{-- Avatar --}}
                <div class="relative" id="avatar-dropdown">
                    <button type="button"
                            id="avatar-button"
                            class="w-8 h-8 bg-munti-yellow-500 text-background-950 rounded-full flex items-center justify-center font-bold ring-2 ring-text-100/40 hover:ring-4 hover:ring-text-100/60 transition-all focus:outline-none"
                            aria-expanded="false"
                            aria-haspopup="true">
                        {{ strtoupper(substr(session('username') ?? 'U', 0, 1)) }}
                    </button>

                    <div id="avatar-dropdown-menu"
                         class="absolute right-0 mt-2 w-56 bg-surface-800 rounded-xl shadow-2xl border border-border-700 hidden z-30 overflow-hidden">
                        <div class="px-4 py-3 border-b border-border-700">
                            <p class="text-sm font-semibold text-text-100 uppercase">
                                {{ session('role') === 'superAdmin' || session('role') === 'superAdmin' ? 'Super Administrator' : (session('role') === 'admin' || session('role') === 'administrator' ? 'Administrator' : 'User') }}
                            </p>
                            <p class="text-xs text-text-400 truncate">{{ session('username') ?? 'Guest' }}</p>
                        </div>
                        <a href="#" class="flex items-center gap-2 px-4 py-2.5 text-sm text-text-400 hover:bg-surface-700 hover:text-radar-400 transition-colors">
                            User Management
                        </a>
                        <form method="POST" action="{{ route('logout') }}" class="p-2">
                            @csrf
                            <button type="submit"
                                    class="w-full flex items-center justify-center gap-x-2 bg-munti-red-700/20 hover:bg-munti-red-600/30 text-munti-red-400 text-sm font-bold py-2.5 rounded-lg transition-all border border-munti-red-600/40 hover:border-munti-red-500/60">
                                <svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24">
                                    <path fill="currentColor" d="M16 13v-2H7V8l-5 4l5 4v-3z"/>
                                    <path fill="currentColor" d="M20 3h-9c-1.103 0-2 .897-2 2v4h2V5h9v14h-9v-4H9v4c0 1.103.897 2 2 2h9c1.103 0 2-.897 2-2V5c0-1.103-.897-2-2-2"/>
                                </svg>
                                Logout
                            </button>
                        </form>
                    </div>
                </div>
                

                {{-- Mobile menu button --}}
                <button type="button"
                        id="mobile-menu-button"
                        class="xl:hidden inline-flex items-center justify-center p-2 rounded-lg text-text-200 hover:bg-surface-700 focus:outline-none focus:ring-2 focus:ring-radar-500/50 transition"
                        aria-controls="mobile-menu"
                        aria-expanded="false">
                    <span class="sr-only">Open main menu</span>
                    <svg id="menu-open-icon" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                    </svg>
                    <svg id="menu-close-icon" class="h-6 w-6 hidden" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
        </div>
    </div>

    {{-- ========== MOBILE MENU ========== --}}
    <div id="mobile-menu" class="xl:hidden hidden border-t border-border-800 bg-surface-900/95 backdrop-blur-sm">
        <div class="px-4 pt-2 pb-4 space-y-1">

            @if(session('role') === 'admin' || session('role') === 'superAdmin')
                <a href="{{ route('home') }}" class="block px-3 py-3 rounded-xl text-base font-medium text-text-300 hover:text-text-100 hover:bg-surface-700 transition">Dashboards</a>
                <a href="{{ route('stations.index') }}" class="block px-3 py-3 rounded-xl text-base font-medium text-text-300 hover:text-text-100 hover:bg-surface-700 transition">Stations</a>
                <a href="{{ route('sms.index') }}" class="block px-3 py-3 rounded-xl text-base font-medium text-text-300 hover:text-text-100 hover:bg-surface-700 transition">SMS</a>

                {{-- Maintenance submenu --}}
                <div class="relative" id="maintenance-mobile-wrapper">
                    <button type="button"
                            class="maintenance-mobile-toggle w-full flex items-center justify-between px-3 py-3 rounded-xl text-base font-medium text-text-300 hover:text-text-100 hover:bg-surface-700 transition"
                            aria-expanded="false">
                        <span>Maintenance</span>
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 transition-transform duration-200" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                        </svg>
                    </button>
                    <div class="maintenance-mobile-submenu hidden pl-4 space-y-1 mt-1">
                        <a href="{{ route('maintenance.index') }}" class="block px-3 py-3 rounded-xl text-base font-medium text-text-400 hover:text-text-100 hover:bg-surface-700 transition">Network Diagnostic</a>
                        <a href="{{ route('services.terminal') }}" target="_blank" rel="noopener" class="block px-3 py-3 rounded-xl text-base font-medium text-text-400 hover:text-text-100 hover:bg-surface-700 transition">Terminal</a>
                        <a href="{{ route('api-logs.index') }}" class="block px-3 py-3 rounded-xl text-base font-medium text-text-400 hover:text-text-100 hover:bg-surface-700 transition">API Logs</a>
                        <a href="{{ route('logs.index') }}" class="block px-3 py-3 rounded-xl text-base font-medium text-text-400 hover:text-text-100 hover:bg-surface-700 transition">System Logs</a>
                        <a href="{{ route('services.index') }}" class="block px-3 py-3 rounded-xl text-base font-medium text-text-400 hover:text-text-100 hover:bg-surface-700 transition">Services</a>
                    </div>
                </div>

                {{-- Settings submenu --}}
                <div class="relative" id="settings-mobile-wrapper">
                    <button type="button"
                            class="settings-mobile-toggle w-full flex items-center justify-between px-3 py-3 rounded-xl text-base font-medium text-text-300 hover:text-text-100 hover:bg-surface-700 transition"
                            aria-expanded="false">
                        <span>Settings</span>
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 transition-transform duration-200" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                        </svg>
                    </button>
                    <div class="settings-mobile-submenu hidden pl-4 space-y-1 mt-1">
                        <a href="{{ route('env.editor') }}" class="block px-3 py-3 rounded-xl text-base font-medium text-text-400 hover:text-text-100 hover:bg-surface-700 transition">Database</a>
                        <a href="{{ route('mqtt.editor') }}" class="block px-3 py-3 rounded-xl text-base font-medium text-text-400 hover:text-text-100 hover:bg-surface-700 transition">MQTT</a>
                        <a href="{{ route('network.index') }}" class="block px-3 py-3 rounded-xl text-base font-medium text-text-400 hover:text-text-100 hover:bg-surface-700 transition">Network</a>
                        <a href="{{ route('api.editor') }}" class="block px-3 py-3 rounded-xl text-base font-medium text-text-400 hover:text-text-100 hover:bg-surface-700 transition">API</a>
                    </div>
                </div>

                <a href="{{ route('about') }}" class="block px-3 py-3 rounded-xl text-base font-medium text-text-300 hover:text-text-100 hover:bg-surface-700 transition">About</a>
            @endif

            {{-- Mobile user & logout --}}
            <div class="pt-3 mt-3 border-t border-border-700">
                <div class="px-3 py-2 flex items-center gap-3">
                    <div class="w-10 h-10 bg-munti-yellow-500 text-background-950 rounded-full flex items-center justify-center font-bold ring-2 ring-text-100/30">
                        {{ strtoupper(substr(session('username') ?? 'U', 0, 1)) }}
                    </div>
                    <div>
                        <p class="text-text-100 text-sm font-semibold uppercase">
                            {{ session('role') === 'admin' || session('role') === 'user' || session('role') === 'superAdmin' ? 'admin' || session('role') === 'user' || session('role') === 'superAdmin' : 'User' }}
                        </p>
                        <p class="text-text-400 text-xs">{{ session('username') ?? 'Guest' }}</p>
                    </div>
                </div>

                <form method="POST" action="{{ route('logout') }}" class="mt-2">
                    @csrf
                    <button type="submit"
                            class="w-full flex items-center justify-center gap-x-2 bg-surface-700 hover:bg-surface-600 text-text-100 text-sm font-bold py-3 rounded-xl transition-all border border-border-600">
                        <svg xmlns="http://www.w3.org/2000/svg" width="1.1em" height="1.1em" viewBox="0 0 24 24">
                            <path fill="currentColor" d="M16 13v-2H7V8l-5 4l5 4v-3z"/>
                            <path fill="currentColor" d="M20 3h-9c-1.103 0-2 .897-2 2v4h2V5h9v14h-9v-4H9v4c0 1.103.897 2 2 2h9c1.103 0 2-.897 2-2V5c0-1.103-.897-2-2-2"/>
                        </svg>
                        Logout
                    </button>
                </form>
            </div>
        </div>
    </div>
</nav>

{{-- All dropdown toggles and notification logic --}}
<script>
    document.addEventListener('DOMContentLoaded', function () {
        // ========== Helper: close everything ==========
        function closeAllDropdowns(except = null) {
            // Desktop Maintenance
            const maintMenu = document.querySelector('#maintenance-dropdown-desktop .maintenance-dropdown-menu');
            const maintToggle = document.querySelector('#maintenance-dropdown-desktop .maintenance-dropdown-toggle');
            if (maintMenu && except !== 'maintenance') {
                maintMenu.classList.add('hidden');
                if (maintToggle) {
                    maintToggle.setAttribute('aria-expanded', 'false');
                    const chevron = maintToggle.querySelector('svg');
                    if (chevron) chevron.classList.remove('rotate-180');
                }
            }

            // Notification
            const notifDropdown = document.getElementById('notification-dropdown');
            const notifBell = document.getElementById('notification-bell');
            if (notifDropdown && except !== 'notification') {
                notifDropdown.classList.add('hidden');
                if (notifBell) notifBell.setAttribute('aria-expanded', 'false');
            }

            // Settings Gear
            const settingsDropdown = document.getElementById('settings-gear-dropdown');
            const settingsBtn = document.getElementById('settings-gear-button');
            if (settingsDropdown && except !== 'settings') {
                settingsDropdown.classList.add('hidden');
                if (settingsBtn) settingsBtn.setAttribute('aria-expanded', 'false');
            }

            // Avatar
            const avatarMenu = document.getElementById('avatar-dropdown-menu');
            const avatarBtn = document.getElementById('avatar-button');
            if (avatarMenu && except !== 'avatar') {
                avatarMenu.classList.add('hidden');
                if (avatarBtn) avatarBtn.setAttribute('aria-expanded', 'false');
            }
        }

        // ========== Mobile menu toggle ==========
        const btn = document.getElementById('mobile-menu-button');
        const menu = document.getElementById('mobile-menu');
        const openIcon = document.getElementById('menu-open-icon');
        const closeIcon = document.getElementById('menu-close-icon');

        if (btn && menu) {
            btn.addEventListener('click', () => {
                const isOpen = !menu.classList.contains('hidden');
                menu.classList.toggle('hidden');
                openIcon.classList.toggle('hidden');
                closeIcon.classList.toggle('hidden');
                btn.setAttribute('aria-expanded', String(!isOpen));
                // Close all desktop dropdowns when opening mobile menu
                if (!isOpen) closeAllDropdowns();
            });
        }

        // ========== Desktop Maintenance Dropdown ==========
        const maintWrapper = document.getElementById('maintenance-dropdown-desktop');
        const maintToggle = maintWrapper?.querySelector('.maintenance-dropdown-toggle');
        const maintMenu = maintWrapper?.querySelector('.maintenance-dropdown-menu');

        if (maintToggle && maintMenu) {
            maintToggle.addEventListener('click', function (e) {
                e.stopPropagation();
                const isOpen = !maintMenu.classList.contains('hidden');
                closeAllDropdowns('maintenance');
                maintMenu.classList.toggle('hidden');
                this.setAttribute('aria-expanded', String(!isOpen));
                const chevron = this.querySelector('svg');
                if (chevron) chevron.classList.toggle('rotate-180');
            });
        }

        // ========== Settings Gear ==========
        const settingsGearButton = document.getElementById('settings-gear-button');
        const settingsGearDropdown = document.getElementById('settings-gear-dropdown');

        if (settingsGearButton && settingsGearDropdown) {
            settingsGearButton.addEventListener('click', function (e) {
                e.stopPropagation();
                const isOpen = !settingsGearDropdown.classList.contains('hidden');
                closeAllDropdowns('settings');
                settingsGearDropdown.classList.toggle('hidden');
                this.setAttribute('aria-expanded', String(!isOpen));
            });
        }

        // ========== Avatar ==========
        const avatarButton = document.getElementById('avatar-button');
        const avatarMenu = document.getElementById('avatar-dropdown-menu');

        if (avatarButton && avatarMenu) {
            avatarButton.addEventListener('click', function (e) {
                e.stopPropagation();
                const isOpen = !avatarMenu.classList.contains('hidden');
                closeAllDropdowns('avatar');
                avatarMenu.classList.toggle('hidden');
                this.setAttribute('aria-expanded', String(!isOpen));
            });
        }

        // ========== Notification Bell ==========
        const bellButton = document.getElementById('notification-bell');
        const bellDropdown = document.getElementById('notification-dropdown');
        const notificationList = document.getElementById('notification-list');
        const notificationDot = document.getElementById('notification-dot');
        const markAllBtn = document.getElementById('mark-all-seen');

        function getSeenIds() {
            try {
                return JSON.parse(localStorage.getItem('seen_logs') || '[]');
            } catch {
                return [];
            }
        }

        function setSeenIds(ids) {
            localStorage.setItem('seen_logs', JSON.stringify(ids));
        }

        function addSeenId(type, id) {
            const composite = type + '-' + id;
            let seen = getSeenIds();
            if (!seen.includes(composite)) {
                seen.push(composite);
                setSeenIds(seen);
            }
        }

        function renderLogs(logs) {
            if (!logs.length) {
                notificationList.innerHTML = `
                    <div class="px-4 py-8 text-center text-text-500 text-sm">
                        No recent logs
                    </div>`;
                notificationDot.classList.add('hidden');
                bellButton.classList.remove('animate-pulse');
                return;
            }

            const hasUnseen = logs.some(log => !log.is_seen);

            notificationList.innerHTML = logs.map(log => {
                const isNew = !log.is_seen;
                const type = log.type || 'api';
                const badgeText = log.badge_text || (type === 'api' ? 'API' : 'SYS');
                const badgeColor = log.badge_color || (type === 'api' ? 'text-blue-400' : 'text-purple-400');
                
                let bgClass = 'hover:bg-surface-700/60';
                let borderClass = 'border-transparent';
                let textClass = 'text-text-400';
                
                if (isNew) {
                    if (type === 'api') {
                        bgClass = 'bg-blue-500/10 hover:bg-blue-500/20';
                        borderClass = 'border-blue-500/60';
                        textClass = 'text-text-100';
                    } else {
                        const level = (log.level || '').toLowerCase();
                        if (['emergency', 'alert', 'critical', 'error'].includes(level)) {
                            bgClass = 'bg-red-500/10 hover:bg-red-500/20';
                            borderClass = 'border-red-500/60';
                            textClass = 'text-text-100';
                        } else if (['warning'].includes(level)) {
                            bgClass = 'bg-yellow-500/10 hover:bg-yellow-500/20';
                            borderClass = 'border-yellow-500/60';
                            textClass = 'text-text-100';
                        } else {
                            bgClass = 'bg-purple-500/10 hover:bg-purple-500/20';
                            borderClass = 'border-purple-500/60';
                            textClass = 'text-text-100';
                        }
                    }
                }

                return `
                    <a href="${log.url}"
                        data-type="${log.type}"
                        data-seen="${log.is_seen}"
                        data-log-data='${JSON.stringify(log._log_data || {})}'
                        class="log-item block px-4 py-3 transition-colors border-l-[3px] ${bgClass} ${borderClass}">
                        <div class="flex items-center justify-between gap-2 mb-1">
                            <div class="flex items-center gap-2">
                                <span class="text-[10px] font-semibold uppercase tracking-wide ${badgeColor}">${log.time}</span>
                                ${isNew ? '<span class="text-[10px] font-bold text-blue-400">NEW</span>' : ''}
                            </div>
                            <span class="text-xs font-mono ${log.status_color}">${log.status_code || '--'}</span>
                        </div>
                        <div class="text-sm ${textClass} font-medium truncate">
                            ${log.summary}
                        </div>
                        <div class="text-xs text-text-500 truncate mt-0.5">
                            ${log.detail}
                        </div>
                    </a>`;
            }).join('');

            if (hasUnseen) {
                notificationDot.classList.remove('hidden');
                bellButton.classList.add('animate-pulse');
            } else {
                notificationDot.classList.add('hidden');
                bellButton.classList.remove('animate-pulse');
            }

            // Click handler for individual log items
            document.querySelectorAll('.log-item').forEach(item => {
                item.addEventListener('click', function(e) {
                    if (this.dataset.seen === 'true' || this.dataset.seen === '1') return;

                    const type = this.dataset.type;
                    const logData = JSON.parse(this.dataset.logData || '{}');

                    fetch('/mark-log-seen', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '{{ csrf_token() }}'
                        },
                        body: JSON.stringify({ 
                            type: type, 
                            log_data: logData 
                        })
                    })
                    .then(r => r.json())
                    .then(data => {
                        if (data.success) {
                            this.dataset.seen = 'true';
                            
                            this.classList.remove(
                                'bg-blue-500/10', 'bg-red-500/10', 'bg-yellow-500/10', 'bg-purple-500/10',
                                'hover:bg-blue-500/20', 'hover:bg-red-500/20', 'hover:bg-yellow-500/20', 'hover:bg-purple-500/20',
                                'border-blue-500/60', 'border-red-500/60', 'border-yellow-500/60', 'border-purple-500/60'
                            );
                            this.classList.add('hover:bg-surface-700/60', 'border-transparent', 'opacity-70');
                            
                            const newBadge = this.querySelector('.text-blue-400');
                            if (newBadge && newBadge.textContent === 'NEW') newBadge.remove();
                            
                            const summary = this.querySelector('.text-text-100');
                            if (summary) {
                                summary.classList.remove('text-text-100');
                                summary.classList.add('text-text-400');
                            }
                            
                            if (!document.querySelector('.log-item:not([data-seen="true"])')) {
                                notificationDot.classList.add('hidden');
                                bellButton.classList.remove('animate-pulse');
                            }
                        }
                    })
                    .catch(err => console.error('Error marking log as seen:', err));
                });
            });
        }

        function markAllAsSeen() {
            const unseenItems = document.querySelectorAll('.log-item:not([data-seen="true"])');
            if (!unseenItems.length) return;

            const typesNeeded = new Set();
            unseenItems.forEach(item => typesNeeded.add(item.dataset.type));

            markAllBtn.textContent = 'Processing…';
            markAllBtn.disabled = true;

            const promises = Array.from(typesNeeded).map(type => {
                return fetch('/mark-all-logs-seen', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({ type: type })
                })
                .then(r => r.json())
                .then(data => ({ type, success: !!data.success }))
                .catch(() => ({ type, success: false }));
            });

            Promise.all(promises)
                .then(results => {
                    markAllBtn.textContent = 'Mark All as Seen';
                    markAllBtn.disabled = false;

                    if (results.every(r => r.success)) {
                        unseenItems.forEach(item => {
                            item.dataset.seen = 'true';
                            item.classList.remove(
                                'bg-blue-500/10', 'bg-red-500/10', 'bg-yellow-500/10', 'bg-purple-500/10',
                                'hover:bg-blue-500/20', 'hover:bg-red-500/20', 'hover:bg-yellow-500/20', 'hover:bg-purple-500/20',
                                'border-blue-500/60', 'border-red-500/60', 'border-yellow-500/60', 'border-purple-500/60'
                            );
                            item.classList.add('hover:bg-surface-700/60', 'border-transparent', 'opacity-70');

                            const badge = item.querySelector('.text-blue-400');
                            if (badge && badge.textContent === 'NEW') badge.remove();
                            const summary = item.querySelector('.text-text-100');
                            if (summary) {
                                summary.classList.remove('text-text-100');
                                summary.classList.add('text-text-400');
                            }
                        });
                        notificationDot.classList.add('hidden');
                        bellButton.classList.remove('animate-pulse');
                    }
                })
                .catch(() => {
                    markAllBtn.textContent = 'Mark All as Seen';
                    markAllBtn.disabled = false;
                });
        }

        function updateDot() {
            const seenParam = getSeenIds().join(',');
            fetch(`{{ route('recent-logs.count') }}?seen=${encodeURIComponent(seenParam)}`)
                .then(r => r.json())
                .then(data => {
                    if (data.count > 0) {
                        notificationDot.classList.remove('hidden');
                        bellButton.classList.add('animate-pulse');
                    } else {
                        notificationDot.classList.add('hidden');
                        bellButton.classList.remove('animate-pulse');
                    }
                })
                .catch(() => {
                    notificationDot.classList.add('hidden');
                    bellButton.classList.remove('animate-pulse');
                });
        }

        if (bellButton && bellDropdown) {
            bellButton.addEventListener('click', function (e) {
                e.stopPropagation();
                const isOpen = !bellDropdown.classList.contains('hidden');
                closeAllDropdowns('notification');
                bellDropdown.classList.toggle('hidden');
                this.setAttribute('aria-expanded', String(!isOpen));

                if (!isOpen) {
                    notificationList.innerHTML = `
                        <div class="px-4 py-6 text-sm text-text-400 text-center">
                            Loading…
                        </div>`;
                    const seenParam = getSeenIds().join(',');
                    fetch(`{{ route('recent-logs') }}?seen=${encodeURIComponent(seenParam)}`)
                        .then(r => r.json())
                        .then(renderLogs)
                        .catch(() => {
                            notificationList.innerHTML = `
                                <div class="px-4 py-6 text-sm text-red-400 text-center">
                                    Failed to load logs
                                </div>`;
                        });
                }
            });
        }

        if (markAllBtn) {
            markAllBtn.addEventListener('click', e => {
                e.preventDefault();
                markAllAsSeen();
            });
        }

        // ========== Global click outside ==========
        document.addEventListener('click', function (e) {
            // Close all if click is outside every dropdown container
            const isInside =
                e.target.closest('#maintenance-dropdown-desktop') ||
                e.target.closest('#notification-container') ||
                e.target.closest('#settings-gear-container') ||
                e.target.closest('#avatar-dropdown');

            if (!isInside) {
                closeAllDropdowns();
            }
        });

        // ========== Mobile submenus (independent) ==========
        const mobileSubmenus = [
            { toggleClass: '.maintenance-mobile-toggle', submenuClass: '.maintenance-mobile-submenu' },
            { toggleClass: '.settings-mobile-toggle', submenuClass: '.settings-mobile-submenu' },
        ];

        mobileSubmenus.forEach(({ toggleClass, submenuClass }) => {
            const toggle = document.querySelector(toggleClass);
            const submenu = document.querySelector(submenuClass);
            if (toggle && submenu) {
                toggle.addEventListener('click', function () {
                    const isOpen = !submenu.classList.contains('hidden');
                    submenu.classList.toggle('hidden');
                    this.setAttribute('aria-expanded', String(!isOpen));
                    const chevron = this.querySelector('svg');
                    if (chevron) chevron.classList.toggle('rotate-180');
                });
            }
        });

        // Initial notification dot check
        updateDot();
    });
</script>

{{-- Extra style for smooth rotation and scrollbar --}}
<style>
    .logs-dropdown-toggle svg,
    .logs-mobile-toggle svg,
    .maintenance-dropdown-toggle svg,
    .maintenance-mobile-toggle svg,
    .settings-dropdown-toggle svg,
    .settings-mobile-toggle svg {
        transition: transform 0.2s ease;
    }
    .rotate-180 {
        transform: rotate(180deg);
    }
    .thin-scrollbar::-webkit-scrollbar {
        width: 5px;
        height: 5px;
    }
    .thin-scrollbar::-webkit-scrollbar-track {
        background: #1A1A1A;
        border-radius: 10px;
    }
    .thin-scrollbar::-webkit-scrollbar-thumb {
        background: #4B5563;
        border-radius: 10px;
    }
    .thin-scrollbar::-webkit-scrollbar-thumb:hover {
        background: #6B7280;
    }
    .thin-scrollbar {
        scrollbar-width: thin;
        scrollbar-color: #4B5563 #1A1A1A;
    }
    
    /* Enhanced notification styles */
    .log-item {
        transition: all 0.2s ease;
        position: relative;
    }
    
    .log-item::after {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        pointer-events: none;
        border-radius: inherit;
        background: linear-gradient(135deg, rgba(255,255,255,0.03) 0%, transparent 100%);
    }
    
    /* Pulse animation for notification bell */
    @keyframes gentlePulse {
        0%, 100% { transform: scale(1); }
        50% { transform: scale(1.05); }
    }
    
    .animate-pulse {
        animation: gentlePulse 2s ease-in-out infinite;
    }
</style>