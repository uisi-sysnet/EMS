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
                        <span class="text-[10px] text-munti-green-400 bg-munti-green-700/20 px-1.5 py-0.5 rounded-full shrink-0 border border-munti-green-600/30">Beta V1.0</span>
                    </div>
                </div>
            </div>

            {{-- Desktop Navigation (xl+) --}}
            <div class="hidden xl:flex items-center gap-x-5 lg:gap-x-8 text-sm font-medium">
                @if(session('role') === 'administrator')
                    <a href="{{ route('home') }}" class="text-text-400 hover:text-text-100 transition-colors py-1">Dashboard</a>
                    <a href="{{ route('stations.index') }}" class="text-text-400 hover:text-text-100 transition-colors py-1">Stations</a>

                    {{-- ENV Editor Dropdown --}}
                    <div class="relative group" id="env-dropdown-desktop">
                        <button type="button"
                                class="env-dropdown-toggle flex items-center gap-x-1 text-text-400 hover:text-text-100 transition-colors py-1 focus:outline-none"
                                aria-expanded="false">
                            ENV Editor
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 transition-transform duration-200" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>
                        <div class="env-dropdown-menu absolute left-0 mt-2 w-48 bg-surface-800 rounded-xl shadow-2xl border border-border-700 hidden z-20 overflow-hidden">
                            <a href="{{ route('env.editor') }}" class="block px-4 py-2.5 text-sm text-text-400 hover:bg-surface-700 hover:text-radar-400 transition-colors">Database</a>
                            <a href="{{ route('mqtt.editor') }}" class="block px-4 py-2.5 text-sm text-text-400 hover:bg-surface-700 hover:text-radar-400 transition-colors border-t border-border-700">MQTT</a>
                        </div>
                    </div>

                    <a href="{{ route('api.editor') }}" class="text-text-400 hover:text-text-100 transition-colors py-1">API</a>
                    <a href="{{ route('sms.index') }}" class="text-text-400 hover:text-text-100 transition-colors py-1">SMS</a>
                    <a href="{{ route('network.index') }}" class="text-text-400 hover:text-text-100 transition-colors py-1">Network</a>

                    {{-- Logs Dropdown --}}
                    <div class="relative group" id="logs-dropdown-desktop">
                        <button type="button"
                                class="logs-dropdown-toggle flex items-center gap-x-1 text-text-400 hover:text-text-100 transition-colors py-1 focus:outline-none"
                                aria-expanded="false">
                            Logs
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 transition-transform duration-200" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>
                        <div class="logs-dropdown-menu absolute left-0 mt-2 w-48 bg-surface-800 rounded-xl shadow-2xl border border-border-700 hidden z-20 overflow-hidden">
                            <a href="{{ route('api-logs.index') }}" class="block px-4 py-2.5 text-sm text-text-400 hover:bg-surface-700 hover:text-radar-400 transition-colors">API Logs</a>
                            <a href="{{ route('logs.index') }}" class="block px-4 py-2.5 text-sm text-text-400 hover:bg-surface-700 hover:text-radar-400 transition-colors border-t border-border-700">System Logs</a>
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
                                <a href="{{ route('logs.index') }}" class="text-xs text-radar-400 hover:underline">View all</a>
                            </div>
                        </div>
                        <div id="notification-list" class="max-h-72 overflow-y-auto divide-y divide-border-700 thin-scrollbar">
                            <div class="px-4 py-6 text-sm text-text-400 text-center">Loading…</div>
                        </div>
                        <div class="px-4 py-2 border-t border-border-700 text-center">
                            <a href="{{ route('api-logs.index') }}" class="text-xs text-text-400 hover:text-text-100 transition">Also check API logs</a>
                        </div>
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
                <a href="{{ route('home') }}" class="block px-3 py-3 rounded-xl text-base font-medium text-text-300 hover:text-text-100 hover:bg-surface-700 transition">Dashboard</a>

                {{-- ENV Editor submenu --}}
                <div class="relative" id="env-mobile-wrapper">
                    <button type="button"
                            class="env-mobile-toggle w-full flex items-center justify-between px-3 py-3 rounded-xl text-base font-medium text-text-300 hover:text-text-100 hover:bg-surface-700 transition"
                            aria-expanded="false">
                        <span>ENV Editor</span>
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 transition-transform duration-200" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                        </svg>
                    </button>
                    <div class="env-mobile-submenu hidden pl-4 space-y-1 mt-1">
                        <a href="{{ route('env.editor') }}" class="block px-3 py-3 rounded-xl text-base font-medium text-text-400 hover:text-text-100 hover:bg-surface-700 transition">Database</a>
                        <a href="{{ route('mqtt.editor') }}" class="block px-3 py-3 rounded-xl text-base font-medium text-text-400 hover:text-text-100 hover:bg-surface-700 transition">MQTT</a>
                    </div>
                </div>

                <a href="{{ route('api.editor') }}" class="block px-3 py-3 rounded-xl text-base font-medium text-text-400 hover:text-text-100 hover:bg-surface-700 transition">API</a>
                <a href="{{ route('sms.index') }}" class="block px-3 py-3 rounded-xl text-base font-medium text-text-300 hover:text-text-100 hover:bg-surface-700 transition">SMS</a>
                <a href="{{ route('network.index') }}" class="block px-3 py-3 rounded-xl text-base font-medium text-text-300 hover:text-text-100 hover:bg-surface-700 transition">Network</a>

                {{-- Logs submenu --}}
                <div class="relative" id="logs-mobile-wrapper">
                    <button type="button"
                            class="logs-mobile-toggle w-full flex items-center justify-between px-3 py-3 rounded-xl text-base font-medium text-text-300 hover:text-text-100 hover:bg-surface-700 transition"
                            aria-expanded="false">
                        <span>Logs</span>
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 transition-transform duration-200" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                        </svg>
                    </button>
                    <div class="logs-mobile-submenu hidden pl-4 space-y-1 mt-1">
                        <a href="{{ route('api-logs.index') }}" class="block px-3 py-3 rounded-xl text-base font-medium text-text-400 hover:text-text-100 hover:bg-surface-700 transition">API Logs</a>
                        <a href="{{ route('logs.index') }}" class="block px-3 py-3 rounded-xl text-base font-medium text-text-400 hover:text-text-100 hover:bg-surface-700 transition">System Logs</a>
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

        // ----- Desktop ENV dropdown toggle (click) -----
        const desktopToggle = document.querySelector('#env-dropdown-desktop .env-dropdown-toggle');
        const desktopMenu = document.querySelector('#env-dropdown-desktop .env-dropdown-menu');

        if (desktopToggle && desktopMenu) {
            desktopToggle.addEventListener('click', function (e) {
                e.stopPropagation();
                const isOpen = !desktopMenu.classList.contains('hidden');
                desktopMenu.classList.toggle('hidden');
                this.setAttribute('aria-expanded', String(!isOpen));
                const chevron = this.querySelector('svg');
                if (chevron) chevron.classList.toggle('rotate-180');
            });

            document.addEventListener('click', function (e) {
                const wrapper = document.querySelector('#env-dropdown-desktop');
                if (wrapper && !wrapper.contains(e.target)) {
                    desktopMenu.classList.add('hidden');
                    desktopToggle.setAttribute('aria-expanded', 'false');
                    const chevron = desktopToggle.querySelector('svg');
                    if (chevron) chevron.classList.remove('rotate-180');
                }
            });
        }

        // ----- Mobile ENV submenu toggle (accordion) -----
        const mobileToggle = document.querySelector('.env-mobile-toggle');
        const mobileSubmenu = document.querySelector('.env-mobile-submenu');

        if (mobileToggle && mobileSubmenu) {
            mobileToggle.addEventListener('click', function () {
                const isOpen = !mobileSubmenu.classList.contains('hidden');
                mobileSubmenu.classList.toggle('hidden');
                this.setAttribute('aria-expanded', String(!isOpen));
                const chevron = this.querySelector('svg');
                if (chevron) chevron.classList.toggle('rotate-180');
            });
        }

        // ----- Desktop Logs dropdown toggle -----
        const logsDesktopToggle = document.querySelector('#logs-dropdown-desktop .logs-dropdown-toggle');
        const logsDesktopMenu = document.querySelector('#logs-dropdown-desktop .logs-dropdown-menu');

        function closeLogsDesktop() {
            if (logsDesktopMenu && logsDesktopToggle) {
                logsDesktopMenu.classList.add('hidden');
                logsDesktopToggle.setAttribute('aria-expanded', 'false');
                const chevron = logsDesktopToggle.querySelector('svg');
                if (chevron) chevron.classList.remove('rotate-180');
            }
        }

        function closeEnvDesktop() {
            if (desktopMenu && desktopToggle) {
                desktopMenu.classList.add('hidden');
                desktopToggle.setAttribute('aria-expanded', 'false');
                const chevron = desktopToggle.querySelector('svg');
                if (chevron) chevron.classList.remove('rotate-180');
            }
        }

        if (logsDesktopToggle && logsDesktopMenu) {
            logsDesktopToggle.addEventListener('click', function (e) {
                e.stopPropagation();
                closeEnvDesktop();
                const isOpen = !logsDesktopMenu.classList.contains('hidden');
                logsDesktopMenu.classList.toggle('hidden');
                this.setAttribute('aria-expanded', String(!isOpen));
                const chevron = this.querySelector('svg');
                if (chevron) chevron.classList.toggle('rotate-180');
            });

            document.addEventListener('click', function (e) {
                const wrapper = document.querySelector('#logs-dropdown-desktop');
                if (wrapper && !wrapper.contains(e.target)) {
                    closeLogsDesktop();
                }
            });
        }

        // ----- Mobile Logs submenu toggle -----
        const logsMobileToggle = document.querySelector('.logs-mobile-toggle');
        const logsMobileSubmenu = document.querySelector('.logs-mobile-submenu');

        if (logsMobileToggle && logsMobileSubmenu) {
            logsMobileToggle.addEventListener('click', function () {
                const isOpen = !logsMobileSubmenu.classList.contains('hidden');
                logsMobileSubmenu.classList.toggle('hidden');
                this.setAttribute('aria-expanded', String(!isOpen));
                const chevron = this.querySelector('svg');
                if (chevron) chevron.classList.toggle('rotate-180');
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

        // In your getLogStyle function, update to handle the level from the log object
        function getLogStyle(log) {
            const type = log.type || 'system';
            
            // For API logs
            if (type === 'api') {
                return {
                    borderColor: 'border-indigo-500',
                    bgColor: 'bg-indigo-900/10 hover:bg-indigo-900/20',
                    labelColor: 'text-indigo-400',
                    label: 'API',
                    icon: '🔌'
                };
            }
            
            // For system logs - use the level from the log object
            const level = (log.level || 'info').toLowerCase();
            
            switch(level) {
                case 'error':
                    return {
                        borderColor: 'border-red-500',
                        bgColor: 'bg-red-900/20 hover:bg-red-900/30',
                        labelColor: 'text-red-400',
                        label: 'ERROR',
                        icon: `<svg  xmlns="http://www.w3.org/2000/svg" width="24" height="24"  
                                fill="currentColor" viewBox="0 0 24 24" >
                                <!--Boxicons v3.0.8 https://boxicons.com | License  https://docs.boxicons.com/free-->
                                <path d="M4.93 4.93C3.04 6.82 2 9.33 2 12s1.04 5.18 2.93 7.07c1.95 1.95 4.51 2.92 7.07 2.92s5.12-.97 7.07-2.92S22 14.67 22 12s-1.04-5.18-2.93-7.07c-3.9-3.9-10.24-3.9-14.14 0M12 4.01c1.73 0 3.46.56 4.9 1.68l-4.9 4.9-4.9-4.9A7.97 7.97 0 0 1 12 4.01m-8 8c0-1.8.6-3.5 1.68-4.9l4.9 4.9-4.9 4.9A7.92 7.92 0 0 1 4 12.01m3.1 6.32 4.9-4.9 4.9 4.9a8.014 8.014 0 0 1-9.8 0m11.22-1.41-4.9-4.9 4.9-4.9c1.09 1.4 1.68 3.1 1.68 4.9s-.6 3.5-1.68 4.9"></path>
                                </svg>`
                    };
                case 'warning':
                    return {
                        borderColor: 'border-yellow-500',
                        bgColor: 'bg-yellow-900/20 hover:bg-yellow-900/30',
                        labelColor: 'text-yellow-400',
                        label: 'WARN',
                        icon: `<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" viewBox="0 0 24 24" class="text-yellow-400">
                                <path d="M11 9h2v6h-2zm0 8h2v2h-2z"/>
                                <path d="M12.87 2.51c-.35-.63-1.4-.63-1.75 0l-9.99 18c-.17.31-.17.69.01.99.18.31.51.49.86.49h20c.35 0 .68-.19.86-.49a1 1 0 0 0 .01-.99zM3.7 20 12 5.06 20.3 20z"/>
                            </svg>`
                    };
                case 'info':
                default:
                    return {
                        borderColor: 'border-blue-500',
                        bgColor: 'bg-blue-900/20 hover:bg-blue-900/30',
                        labelColor: 'text-blue-400',
                        label: 'INFO',
                        icon: `<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" viewBox="0 0 24 24" class="text-blue-400">
                                <path d="M11 7h2v6h-2zm0 8h2v2h-2z"/>
                                <path d="M12 22c5.51 0 10-4.49 10-10S17.51 2 12 2 2 6.49 2 12s4.49 10 10 10zm0-18c4.41 0 8 3.59 8 8s-3.59 8-8 8-8-3.59-8-8 3.59-8 8-8z"/>
                            </svg>`
                    };
            }
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

        // Render the logs in the dropdown
        function renderLogs(logs) {
            if (logs.length === 0) {
                notificationList.innerHTML = `
                    <div class="px-4 py-6 text-sm text-text-400 text-center">🎉 All caught up! No new logs.</div>
                `;
                notificationDot.classList.add('hidden');
                return;
            }

            let html = '';
            logs.forEach(log => {
                const style = getLogStyle(log);
                const url = log.url;
                // Determine if it's a system or API log for the badge
                const typeBadge = log.type === 'api' ? 'API' : '';
                
                html += `
                    <a href="${url}"
                    data-type="${log.type}"
                    data-id="${log.id}"
                    class="log-item block px-4 py-3 border-l-4 ${style.borderColor} ${style.bgColor} transition-all duration-200 hover:shadow-lg hover:scale-[1.01] transform">
                        <div class="flex items-start gap-3">
                            <span class="text-lg flex-shrink-0 mt-0.5">${style.icon}</span>
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2 mb-0.5">
                                    <span class="text-xs font-bold ${style.labelColor} px-2 py-0.5 rounded-full bg-surface-800/50">${style.label}</span>
                                    ${typeBadge ? `<span class="text-xs font-bold text-indigo-400 px-2 py-0.5 rounded-full bg-indigo-900/30">API</span>` : ''}
                                </div>
                                <div class="text-sm text-text-100 font-medium">${log.summary}</div>
                                <div class="text-xs text-text-400 truncate mt-0.5">${log.detail}</div>
                                <div class="text-xs text-text-500 mt-1 flex items-center gap-1">
                                    <span>🕐</span>
                                    <span>${log.time}</span>
                                </div>
                            </div>
                            <span class="text-xs text-text-500 flex-shrink-0 mt-0.5">●</span>
                        </div>
                    </a>
                `;
            });
            notificationList.innerHTML = html;
            notificationDot.classList.remove('hidden');

            document.querySelectorAll('.log-item').forEach(item => {
                item.addEventListener('click', function(e) {
                    const type = this.dataset.type;
                    const id = this.dataset.id;
                    addSeenId(type, id);
                    this.style.opacity = '0.5';
                    this.style.transform = 'scale(0.98)';
                    setTimeout(() => {
                        this.remove();
                        const remaining = document.querySelectorAll('.log-item');
                        if (remaining.length === 0) {
                            notificationList.innerHTML = `
                                <div class="px-4 py-6 text-sm text-text-400 text-center">🎉 All caught up! No new logs.</div>
                            `;
                            notificationDot.classList.add('hidden');
                        }
                    }, 150);
                });
            });
        }

        // Mark all as seen
        function markAllAsSeen() {
            const seen = getSeenIds();
            const seenParam = seen.join(',');

            fetch(`{{ route('recent-logs') }}?seen=${encodeURIComponent(seenParam)}&all=1`)
                .then(response => response.json())
                .then(logs => {
                    logs.forEach(log => {
                        addSeenId(log.type, log.id);
                    });
                    fetchRecentLogs();
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
                    fetchRecentLogs();
                    // Remove pulse animation when opened
                    this.classList.remove('animate-pulse');
                }
            });

            document.addEventListener('click', function (e) {
                const container = document.getElementById('notification-container');
                if (container && !container.contains(e.target)) {
                    bellDropdown.classList.add('hidden');
                    bellButton.setAttribute('aria-expanded', 'false');
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
    .env-dropdown-toggle svg,
    .env-mobile-toggle svg {
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