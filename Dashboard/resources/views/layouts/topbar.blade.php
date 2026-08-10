<nav class="bg-background-900/95 backdrop-blur-sm border-b border-border-800 shadow-lg fixed top-0 left-0 right-0 z-50">
    <div class="max-w-8xl mx-auto px-3 sm:px-6">
        <div class="h-16 flex items-center justify-between">

            {{-- Logo / Title --}}
            <div class="flex items-center gap-x-3 min-w-0">
                <div class="shrink-0 flex items-center justify-center w-9 h-9 rounded-full text-munti-green-400 bg-munti-green-700/20 border border-munti-green-600/30">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M19.38 7.25C18.79 4.25 16.12 2 13 2c-2.04 0-3.92.94-5.15 2.54-.21-.03-.4-.04-.6-.04a5.25 5.25 0 1 0 0 10.5H18c2.21 0 4-1.79 4-4 0-1.71-1.07-3.19-2.62-3.75M18 13H7.25C5.46 13 4 11.54 4 9.75S5.46 6.5 7.25 6.5c.24 0 .5.04.81.12l.72.18.39-.63A4.47 4.47 0 0 1 13.01 4c2.32 0 4.29 1.81 4.48 4.13l.06.78.77.12c.97.16 1.68.98 1.68 1.96 0 1.1-.9 2-2 2ZM5.85 22h2.3l3.43-6h-2.3zm4.57 0h2.3l3.43-6h-2.3zm-9.14 0h2.3l3.43-6h-2.3zm19.44-6h-2.3l-3.43 6h2.3z"></path>
                    </svg>
                </div>

                {{-- System Name + Version --}}
                <div class="min-w-0 leading-none">
                    <div class="text-sm sm:text-base font-bold text-text-100 tracking-tight uppercase truncate">
                        Environmental Monitoring System Gateway
                    </div>
                    <div class="flex items-center gap-x-1.5 mt-1">
                        <span class="text-[10px] text-munti-green-400 bg-munti-green-700/20 px-1.5 py-0.5 rounded-full shrink-0 border border-munti-green-600/30">Version 7.0</span>
                    </div>
                </div>
            </div>

            {{-- Desktop Navigation (xl+) --}}
            <div class="hidden xl:flex items-center gap-x-5 lg:gap-x-8 text-sm font-medium">
                @if(session('role') === 'administrator')
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
                @if(session('role') === 'administrator')
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
                                <button id="mark-all-seen" class="text-xs text-text-400 hover:text-text-100 transition px-2 py-1 rounded hover:bg-surface-700">Mark all as seen</button>
                                <a href="{{ route('api-logs.index') }}" class="text-xs text-radar-400 hover:underline">View all</a>
                            </div>
                        </div>
                        <div id="notification-list" class="max-h-72 overflow-y-auto divide-y divide-border-700 thin-scrollbar">
                            <div class="px-4 py-6 text-sm text-text-400 text-center">Loading…</div>
                        </div>
                        <div class="px-4 py-2 border-t border-border-700 text-center">
                            <a href="{{ route('api-logs.index') }}" class="text-xs text-radar-400 hover:underline">View all API logs</a>
                        </div>
                    </div>
                </div>
                @endif

                {{-- Settings Gear Icon --}}
                @if(session('role') === 'administrator')
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
                                {{ session('role') === 'administrator' ? 'Administrator' : 'User' }}
                            </p>
                            <p class="text-xs text-text-400 truncate">{{ session('username') ?? 'Guest' }}</p>
                        </div>
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

            @if(session('role') === 'administrator')
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
                            {{ session('role') === 'administrator' ? 'Administrator' : 'User' }}
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
        // ----- Main mobile menu toggle -----
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
            });
        }

        // ----- Desktop dropdowns: Logs, Maintenance, Settings (mutually exclusive) -----
        const desktopDropdowns = [
            { id: 'logs-dropdown-desktop', toggleClass: '.logs-dropdown-toggle', menuClass: '.logs-dropdown-menu' },
            { id: 'maintenance-dropdown-desktop', toggleClass: '.maintenance-dropdown-toggle', menuClass: '.maintenance-dropdown-menu' },
            { id: 'settings-dropdown-desktop', toggleClass: '.settings-dropdown-toggle', menuClass: '.settings-dropdown-menu' },
        ];

        const desktopDropdownState = desktopDropdowns.map(d => {
            const wrapper = document.getElementById(d.id);
            return {
                wrapper,
                toggle: wrapper ? wrapper.querySelector(d.toggleClass) : null,
                menu: wrapper ? wrapper.querySelector(d.menuClass) : null,
            };
        }).filter(d => d.wrapper && d.toggle && d.menu);

        function closeAllDesktopDropdowns(except) {
            desktopDropdownState.forEach(d => {
                if (d === except) return;
                d.menu.classList.add('hidden');
                d.toggle.setAttribute('aria-expanded', 'false');
                const chevron = d.toggle.querySelector('svg');
                if (chevron) chevron.classList.remove('rotate-180');
            });
        }

        desktopDropdownState.forEach(d => {
            d.toggle.addEventListener('click', function (e) {
                e.stopPropagation();
                const isOpen = !d.menu.classList.contains('hidden');
                closeAllDesktopDropdowns(d);
                d.menu.classList.toggle('hidden');
                this.setAttribute('aria-expanded', String(!isOpen));
                const chevron = this.querySelector('svg');
                if (chevron) chevron.classList.toggle('rotate-180');
            });
        });

        document.addEventListener('click', function (e) {
            desktopDropdownState.forEach(d => {
                if (!d.wrapper.contains(e.target)) {
                    d.menu.classList.add('hidden');
                    d.toggle.setAttribute('aria-expanded', 'false');
                    const chevron = d.toggle.querySelector('svg');
                    if (chevron) chevron.classList.remove('rotate-180');
                }
            });
        });

        // ----- Mobile submenu accordions: Logs, Maintenance, Settings -----
        const mobileSubmenus = [
            { toggleClass: '.logs-mobile-toggle', submenuClass: '.logs-mobile-submenu' },
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

        // ----- Settings Gear Dropdown -----
        const settingsGearButton = document.getElementById('settings-gear-button');
        const settingsGearDropdown = document.getElementById('settings-gear-dropdown');

        if (settingsGearButton && settingsGearDropdown) {
            settingsGearButton.addEventListener('click', function (e) {
                e.stopPropagation();
                const isOpen = !settingsGearDropdown.classList.contains('hidden');
                settingsGearDropdown.classList.toggle('hidden');
                this.setAttribute('aria-expanded', String(!isOpen));
                
                // Close avatar dropdown if open
                if (avatarMenu && !avatarMenu.classList.contains('hidden')) {
                    avatarMenu.classList.add('hidden');
                    avatarButton.setAttribute('aria-expanded', 'false');
                }
            });

            document.addEventListener('click', function (e) {
                const container = document.getElementById('settings-gear-container');
                if (container && !container.contains(e.target)) {
                    settingsGearDropdown.classList.add('hidden');
                    settingsGearButton.setAttribute('aria-expanded', 'false');
                }
            });
        }

        // ----- Avatar dropdown toggle -----
        const avatarButton = document.getElementById('avatar-button');
        const avatarMenu = document.getElementById('avatar-dropdown-menu');

        if (avatarButton && avatarMenu) {
            avatarButton.addEventListener('click', function (e) {
                e.stopPropagation();
                const isOpen = !avatarMenu.classList.contains('hidden');
                avatarMenu.classList.toggle('hidden');
                this.setAttribute('aria-expanded', String(!isOpen));
            });

            document.addEventListener('click', function (e) {
                const wrapper = document.getElementById('avatar-dropdown');
                if (wrapper && !wrapper.contains(e.target)) {
                    avatarMenu.classList.add('hidden');
                    avatarButton.setAttribute('aria-expanded', 'false');
                }
            });
        }

        // ----- Notification Bell with local storage tracking -----
        const bellButton = document.getElementById('notification-bell');
        const bellDropdown = document.getElementById('notification-dropdown');
        const notificationList = document.getElementById('notification-list');
        const notificationDot = document.getElementById('notification-dot');
        const markAllBtn = document.getElementById('mark-all-seen');

        // Helper: get seen IDs from localStorage
        function getSeenIds() {
            try {
                return JSON.parse(localStorage.getItem('seen_logs') || '[]');
            } catch {
                return [];
            }
        }

        // Helper: save seen IDs
        function setSeenIds(ids) {
            localStorage.setItem('seen_logs', JSON.stringify(ids));
        }

        // Helper: add a single log ID to seen list
        function addSeenId(type, id) {
            const composite = type + '-' + id;
            let seen = getSeenIds();
            if (!seen.includes(composite)) {
                seen.push(composite);
                setSeenIds(seen);
            }
        }

        function getLogStyle(log) {
            // For API logs only
            const isSeen = log.is_seen || false;
            
            return {
                borderColor: isSeen ? 'border-border-700' : 'border-indigo-500',
                bgColor: isSeen ? 'bg-surface-800/50' : 'bg-indigo-900/20',
                hoverBg: isSeen ? 'hover:bg-surface-700/50' : 'hover:bg-indigo-900/30',
                labelColor: 'text-indigo-400',
                label: 'API',
                opacityClass: isSeen ? 'opacity-60' : '',
                icon: `<svg  xmlns="http://www.w3.org/2000/svg" width="24" height="24"  
                        fill="currentColor" viewBox="0 0 24 24" class="text-indigo-400">
                        <path d="M15.7 2h-.18c-2.19 0-4.26 1.21-5.53 3.25-.81 1.3-1.12 2.62-.93 4.03L2.9 15.37c-.57.56-.89 1.34-.89 2.13V19c0 1.65 1.35 3 3 3H6.6c.8 0 1.55-.31 2.12-.88l.56-.56c.26-.26.46-.58.58-.92.34-.12.65-.32.92-.58l.5-.5c.26-.26.46-.58.58-.92.34-.12.65-.32.92-.58l.5-.5c.29-.29.51-.65.62-1.03.35-.11.66-.31.93-.57.23.03.45.04.68.04 1.14 0 2.25-.35 3.3-1.03 2.13-1.38 3.32-3.56 3.18-5.85-.2-3.38-2.9-6.02-6.29-6.12m2.02 10.29c-.8.52-1.54.71-2.22.71-.49 0-.95-.1-1.39-.24l-.68.76c-.08.09-.19.13-.31.13-.15 0-.31-.06-.48-.19L12 13v1.79c0 .13-.05.26-.15.35l-.5.5a.485.485 0 0 1-.7 0l-.65-.65v1.79c0 .13-.05.26-.15.35l-.5.5a.485.485 0 0 1-.7 0L8 16.98v1.79c0 .13-.05.26-.15.35l-.56.56a1 1 0 0 1-.71.29H4.99c-.55 0-1-.45-1-1v-1.5a1 1 0 0 1 .3-.71l6.95-6.88c-.35-1.06-.43-2.24.43-3.61.85-1.35 2.25-2.31 3.84-2.31h.12c2.33.07 4.22 1.92 4.35 4.23.1 1.66-.88 3.15-2.28 4.05Z"></path><path d="M14 6.69 17.31 10c.92-.92.92-2.4 0-3.31s-2.4-.91-3.31 0"></path>
                    </svg>`
            };
        }

        // Helper: get all unseen log entries from the server
        function fetchRecentLogs() {
            const seen = getSeenIds();
            const seenParam = seen.join(',');

            notificationList.innerHTML = `
                <div class="px-4 py-6 text-sm text-text-400 text-center">
                    <svg class="animate-spin h-5 w-5 mx-auto text-radar-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    Loading logs…
                </div>
            `;

            fetch(`{{ route('recent-logs') }}?seen=${encodeURIComponent(seenParam)}`)
                .then(response => response.json())
                .then(logs => {
                    renderLogs(logs);
                })
                .catch(error => {
                    notificationList.innerHTML = `
                        <div class="px-4 py-6 text-sm text-red-400 text-center">Failed to load logs.</div>
                    `;
                    console.error('Error fetching recent logs:', error);
                });
        }

        function renderLogs(logs) {
            if (logs.length === 0) {
                notificationList.innerHTML = `
                    <div class="px-4 py-6 text-sm text-text-400 text-center">📭 No API logs available.</div>
                `;
                notificationDot.classList.add('hidden');
                bellButton.classList.remove('animate-pulse');
                return;
            }

            // Check if there are any unseen logs
            const hasUnseen = logs.some(log => !log.is_seen);
            
            let html = '';
            logs.forEach(log => {
                const style = getLogStyle(log);
                const url = log.url;
                // Determine if it's a new log (not seen)
                const isNew = !log.is_seen;
                const newBadge = isNew ? `
                    <span class="text-xs font-bold text-green-400 px-2 py-0.5 rounded-full bg-green-900/30 border border-green-500/30 animate-pulse ml-1">
                        NEW
                    </span>
                ` : '';
                
                // Different styling for seen vs unseen
                const bgClass = isNew ? 'bg-indigo-900/20 hover:bg-indigo-900/30' : 'bg-surface-800/50 hover:bg-surface-700/50';
                const borderClass = isNew ? 'border-indigo-500' : 'border-border-700';
                const opacityClass = isNew ? '' : 'opacity-60';
                const hoverScale = isNew ? 'hover:scale-[1.01]' : '';
                
                html += `
                    <a href="${url}"
                    data-type="${log.type}"
                    data-id="${log.id}"
                    data-seen="${log.is_seen}"
                    class="log-item block px-4 py-3 border-l-4 ${borderClass} ${bgClass} ${opacityClass} transition-all duration-200 hover:shadow-lg ${hoverScale} transform">
                        <div class="flex items-start gap-3">
                            <span class="text-lg flex-shrink-0 mt-0.5">${style.icon}</span>
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2 mb-0.5 flex-wrap">
                                    <span class="text-xs font-bold ${style.labelColor} px-2 py-0.5 rounded-full bg-surface-800/50">${style.label}</span>
                                    ${newBadge}
                                    <span class="text-xs font-mono ${log.status_color} px-2 py-0.5 rounded-full bg-surface-800/50">${log.status_code}</span>
                                </div>
                                <div class="text-sm ${isNew ? 'text-text-100' : 'text-text-400'} font-medium">${log.summary}</div>
                                <div class="text-xs ${isNew ? 'text-text-400' : 'text-text-500'} truncate mt-0.5">${log.detail}</div>
                                <div class="text-xs ${isNew ? 'text-text-500' : 'text-text-600'} mt-1 flex items-center gap-1">
                                    <span>${log.time}</span>
                                    ${isNew ? '<span class="text-green-400">●</span>' : '<span class="text-text-600">●</span>'}
                                </div>
                            </div>
                            ${isNew ? '<span class="text-xs text-green-400 flex-shrink-0 mt-0.5 animate-pulse">✦</span>' : '<span class="text-xs text-text-600 flex-shrink-0 mt-0.5">●</span>'}
                        </div>
                    </a>
                `;
            });
            notificationList.innerHTML = html;
            
            // Show dot and pulse if there are any unseen logs
            if (hasUnseen) {
                notificationDot.classList.remove('hidden');
                bellButton.classList.add('animate-pulse');
            } else {
                notificationDot.classList.add('hidden');
                bellButton.classList.remove('animate-pulse');
            }

            document.querySelectorAll('.log-item').forEach(item => {
                item.addEventListener('click', function(e) {
                    const type = this.dataset.type;
                    const id = this.dataset.id;
                    const isSeen = this.dataset.seen === 'true' || this.dataset.seen === '1';
                    
                    // Only mark as seen if it wasn't already
                    if (!isSeen) {
                        addSeenId(type, id);
                        
                        // Update the UI to show it as seen
                        this.dataset.seen = 'true';
                        this.classList.remove('bg-indigo-900/20', 'hover:bg-indigo-900/30', 'border-indigo-500', 'hover:scale-[1.01]');
                        this.classList.add('bg-surface-800/50', 'hover:bg-surface-700/50', 'border-border-700', 'opacity-60');
                        
                        // Remove NEW badge and indicators
                        const newBadge = this.querySelector('.animate-pulse');
                        if (newBadge) newBadge.remove();
                        
                        const indicators = this.querySelectorAll('.text-green-400');
                        indicators.forEach(el => {
                            if (el.textContent === '●' || el.textContent === '✦') {
                                el.className = 'text-text-600';
                                el.textContent = '●';
                            }
                        });
                        
                        // Update text colors
                        const summary = this.querySelector('.text-text-100');
                        if (summary) summary.className = 'text-sm text-text-400 font-medium';
                        
                        const detail = this.querySelector('.text-text-400');
                        if (detail) detail.className = 'text-xs text-text-500 truncate mt-0.5';
                        
                        const time = this.querySelector('.text-text-500');
                        if (time) time.className = 'text-xs text-text-600 mt-1 flex items-center gap-1';
                        
                        // Check if any unseen logs remain
                        const remainingUnseen = document.querySelectorAll('.log-item:not([data-seen="true"])');
                        if (remainingUnseen.length === 0) {
                            notificationDot.classList.add('hidden');
                            bellButton.classList.remove('animate-pulse');
                        }
                    }
                });
            });
        }

        function markAllAsSeen() {
            // Get all unseen log items
            const unseenItems = document.querySelectorAll('.log-item:not([data-seen="true"])');
            
            if (unseenItems.length === 0) {
                return;
            }
            
            // Collect IDs
            const ids = [];
            unseenItems.forEach(item => {
                const type = item.dataset.type;
                const id = item.dataset.id;
                ids.push({ type, id });
            });
            
            // Send to server to mark as seen
            fetch('{{ route("api-logs.mark-as-seen") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({ ids: ids })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Also mark in local storage
                    ids.forEach(({ type, id }) => {
                        addSeenId(type, id);
                    });
                    // Remove all unseen items from UI (mark them as seen)
                    unseenItems.forEach(item => {
                        item.dataset.seen = 'true';
                        item.classList.remove('bg-indigo-900/20', 'hover:bg-indigo-900/30', 'border-indigo-500', 'hover:scale-[1.01]');
                        item.classList.add('bg-surface-800/50', 'hover:bg-surface-700/50', 'border-border-700', 'opacity-60');
                        
                        // Remove NEW badge and indicators
                        const newBadge = item.querySelector('.animate-pulse');
                        if (newBadge) newBadge.remove();
                        
                        const indicators = item.querySelectorAll('.text-green-400');
                        indicators.forEach(el => {
                            if (el.textContent === '●' || el.textContent === '✦') {
                                el.className = 'text-text-600';
                                el.textContent = '●';
                            }
                        });
                        
                        // Update text colors
                        const summary = item.querySelector('.text-text-100');
                        if (summary) summary.className = 'text-sm text-text-400 font-medium';
                        
                        const detail = item.querySelector('.text-text-400');
                        if (detail) detail.className = 'text-xs text-text-500 truncate mt-0.5';
                        
                        const time = item.querySelector('.text-text-500');
                        if (time) time.className = 'text-xs text-text-600 mt-1 flex items-center gap-1';
                    });
                    
                    notificationDot.classList.add('hidden');
                    bellButton.classList.remove('animate-pulse');
                }
            })
            .catch(error => {
                console.error('Error marking all as seen:', error);
            });
        }

        // Update the dot visibility on page load
        function updateDot() {
            const seen = getSeenIds();
            const seenParam = seen.join(',');

            fetch(`{{ route('recent-logs.count') }}?seen=${encodeURIComponent(seenParam)}`)
                .then(response => response.json())
                .then(data => {
                    if (data.count > 0) {
                        notificationDot.classList.remove('hidden');
                        // Add animation to the bell
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

        // Bell click toggle and fetch
        if (bellButton && bellDropdown) {
            bellButton.addEventListener('click', function (e) {
                e.stopPropagation();
                const isOpen = !bellDropdown.classList.contains('hidden');
                bellDropdown.classList.toggle('hidden');
                this.setAttribute('aria-expanded', String(!isOpen));

                if (!isOpen) {
                    // Fetch all 20 most recent API logs
                    notificationList.innerHTML = `
                        <div class="px-4 py-6 text-sm text-text-400 text-center">
                            <svg class="animate-spin h-5 w-5 mx-auto text-radar-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            Loading API logs…
                        </div>
                    `;
                    
                    // Get seen IDs from localStorage
                    const seen = getSeenIds();
                    const seenParam = seen.join(',');
                    
                    fetch(`{{ route('recent-logs') }}?seen=${encodeURIComponent(seenParam)}`)
                        .then(response => response.json())
                        .then(logs => {
                            renderLogs(logs);
                        })
                        .catch(error => {
                            notificationList.innerHTML = `
                                <div class="px-4 py-6 text-sm text-red-400 text-center">Failed to load API logs.</div>
                            `;
                            console.error('Error fetching recent logs:', error);
                        });
                }
            });
        }

        if (markAllBtn) {
            markAllBtn.addEventListener('click', function(e) {
                e.preventDefault();
                markAllAsSeen();
            });
        }

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