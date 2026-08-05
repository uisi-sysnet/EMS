@include('layouts.header')
@include('layouts.topbar')

<style>
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

    /* Transition for the inbox drawer */
    .inbox-drawer {
        transition: transform 0.3s ease-in-out, opacity 0.3s ease-in-out;
    }
    .inbox-drawer.open {
        transform: translateX(0);
        opacity: 1;
    }
    .inbox-overlay {
        transition: opacity 0.3s ease-in-out;
    }
</style>

<main class="pt-20 pb-6 px-4 sm:px-6 max-w-8xl mx-auto w-full overflow-hidden flex flex-col h-[calc(100dvh)] max-h-[calc(100dvh)]">
    <div class="bg-surface-900 rounded-2xl shadow-xl border border-border-800 overflow-hidden flex-1 flex min-h-0 relative">

        {{-- ════════════ OVERLAY (mobile) – starts BELOW topbar ════════════ --}}
        <div id="inbox-overlay"
             class="inbox-overlay fixed top-20 left-0 right-0 bottom-0 bg-black/60 z-40 opacity-0 pointer-events-none lg:hidden">
        </div>

        {{-- ════════════ LEFT PANEL (Inbox) ════════════ --}}
        {{-- Mobile: sidebar width, starts below topbar --}}
        {{-- Desktop: normal relative sidebar --}}
        <div id="inbox-panel"
             class="inbox-drawer fixed top-20 left-0 bottom-0 z-50 w-80 bg-surface-900 border-r border-border-800 flex flex-col
                    transform -translate-x-full opacity-0
                    lg:relative lg:top-auto lg:bottom-auto lg:translate-x-0 lg:opacity-100 lg:flex lg:w-80 lg:inset-auto lg:border-r lg:z-auto
                    shrink-0 min-h-0">

            {{-- Close button (mobile) --}}
            <div class="flex items-center justify-between px-4 py-3 border-b border-border-800 bg-surface-800 lg:hidden">
                <h2 class="text-base font-semibold text-text-100 flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" viewBox="0 0 24 24" class="text-radar-400">
                        <path d="M20 2H4C2.9 2 2 2.9 2 4V22L6 18H20C21.1 18 22 17.1 22 16V4C22 2.9 21.1 2 20 2Z"/>
                    </svg>
                    Inbox
                </h2>
                <button id="close-inbox" class="text-text-400 hover:text-text-100 p-1">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            {{-- Inbox header + search (visible on all sizes) --}}
            <div class="px-4 py-3 border-b border-border-800 bg-surface-800 shrink-0">
                <div class="flex items-center justify-between mb-2">
                    <h2 class="text-base font-semibold text-text-100 hidden lg:flex items-center gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" viewBox="0 0 24 24" class="text-radar-400">
                            <path d="M20 2H4C2.9 2 2 2.9 2 4V22L6 18H20C21.1 18 22 17.1 22 16V4C22 2.9 21.1 2 20 2Z"/>
                        </svg>
                        Inbox
                    </h2>
                    <span class="text-xs font-medium text-munti-green-400 bg-munti-green-700/20 border border-munti-green-600/30 px-2 py-0.5 rounded-full">
                        {{ $senders->count() }}
                    </span>
                </div>
                <input type="text"
                       id="inbox-search"
                       placeholder="Search sender..."
                       class="w-full rounded-xl border border-border-600 bg-surface-900 text-text-100 placeholder-text-500
                              px-3 py-2 text-sm focus:ring-2 focus:ring-radar-500/50 focus:border-radar-500 outline-none transition">
            </div>

            {{-- Sender list --}}
            <div class="flex-1 overflow-y-auto thin-scrollbar min-h-0" id="inbox-list">
                @forelse($senders as $item)
                    @php
                        $latest = $item->latest_message;
                        $isActive = ($selectedSender == $item->sender);
                    @endphp
                    <a href="{{ route('sms.index', ['sender' => $item->sender]) }}"
                       data-sender="{{ strtolower($item->sender) }}"
                       class="inbox-item block px-4 py-3 border-b border-border-800 hover:bg-surface-700/60 cursor-pointer transition
                              {{ $isActive ? 'bg-surface-700/40 border-l-2 border-l-radar-500' : 'border-l-2 border-l-transparent' }}">
                        <div class="flex justify-between gap-2">
                            <div class="min-w-0 flex-1">
                                <h3 class="font-medium {{ $isActive ? 'text-text-100' : 'text-text-200' }} text-sm truncate">
                                    {{ $item->sender }}
                                </h3>
                                <p class="text-xs {{ $isActive ? 'text-text-400' : 'text-text-500' }} mt-0.5 truncate">
                                    {{ $latest ? \Illuminate\Support\Str::limit($latest->raw_body, 42) : 'No messages' }}
                                </p>
                            </div>
                            <div class="flex flex-col items-end gap-1 shrink-0">
                                <span class="text-[10px] text-text-500">
                                    {{ $latest ? $latest->received_at->format('g:i A') : '' }}
                                </span>
                                @if($item->count > 0)
                                    <span class="text-[10px] font-medium text-munti-green-400 bg-munti-green-700/20 border border-munti-green-600/30 px-1.5 py-0.5 rounded-full leading-none">
                                        {{ $item->count }}
                                    </span>
                                @endif
                            </div>
                        </div>
                    </a>
                @empty
                    <div class="p-8 text-text-500 text-sm text-center">
                        No conversations yet.
                    </div>
                @endforelse
            </div>
        </div>

        {{-- ════════════ RIGHT PANEL (Conversation) ════════════ --}}
        <div class="flex-1 flex flex-col min-w-0 min-h-0 relative">

            {{-- Conversation header --}}
            <div class="px-4 py-3 border-b border-border-800 bg-surface-800 flex justify-between items-center shrink-0 gap-3">
                <div class="min-w-0 flex items-center gap-2">
                    {{-- Toggle inbox button (mobile) --}}
                    <button id="toggle-inbox" class="lg:hidden text-text-400 hover:text-text-100 p-1 -ml-1">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                    </button>
                    <div class="min-w-0">
                        @if($selectedSender)
                            <h2 class="font-semibold text-text-100 text-sm truncate">{{ $selectedSender }}</h2>
                            <p class="text-xs text-text-400 font-mono mt-0.5">{{ $selectedSender }}</p>
                        @else
                            <h2 class="font-semibold text-text-400 text-sm">Select a conversation</h2>
                            <p class="text-xs text-text-500 mt-0.5">Choose a sender from the inbox</p>
                        @endif
                    </div>
                </div>
                <a href="{{ route('sms.index', $selectedSender ? ['sender' => $selectedSender] : []) }}"
                   class="bg-radar-600 hover:bg-radar-500 text-text-100 text-sm px-4 py-1.5 rounded-xl transition border border-radar-500/40 shrink-0 flex items-center gap-1.5">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                    </svg>
                    Refresh
                </a>
            </div>

            {{-- Messages --}}
            <div class="flex-1 overflow-y-auto thin-scrollbar p-4 space-y-3 bg-background-900 min-h-0" id="conversation-scroll">
                @forelse($conversation as $msg)
                    <div class="flex">
                        <div class="bg-surface-800 border border-border-700 text-text-200 rounded-2xl rounded-tl-md px-4 py-2.5 shadow-sm max-w-[85%] sm:max-w-md text-sm">
                            <div class="whitespace-pre-wrap break-words">{{ $msg->raw_body }}</div>
                            <div class="flex items-center gap-2 mt-1.5 text-[10px] text-text-500">
                                <span>{{ $msg->received_at->format('M j g:i A') }}</span>
                                @if(!$msg->parsed_ok)
                                    <span class="text-munti-red-400 flex items-center gap-0.5">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                                        </svg>
                                        Parse error
                                    </span>
                                @endif
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="flex flex-col items-center justify-center h-full text-text-500 py-16">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10 mb-3 text-text-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                        </svg>
                        <p class="text-sm">
                            {{ $selectedSender ? 'No messages in this conversation.' : 'Select a sender to view messages.' }}
                        </p>
                    </div>
                @endforelse
            </div>

            {{-- Composer --}}
            <div class="border-t border-border-800 bg-surface-800 p-3 shrink-0">
                <form class="flex gap-3" onsubmit="return false;">
                    <textarea rows="1"
                              id="reply-input"
                              placeholder="{{ $selectedSender ? 'Type your reply…' : 'Select a conversation first' }}"
                              {{ $selectedSender ? '' : 'disabled' }}
                              class="flex-1 border border-border-600 rounded-xl px-4 py-2.5 resize-none outline-none
                                     bg-surface-900 text-text-100 placeholder-text-500
                                     focus:ring-2 focus:ring-radar-500/50 focus:border-radar-500 text-sm transition
                                     disabled:opacity-50 disabled:cursor-not-allowed"></textarea>
                    <button type="button"
                            {{ $selectedSender ? '' : 'disabled' }}
                            class="bg-munti-green-600 hover:bg-munti-green-500 disabled:opacity-50 disabled:cursor-not-allowed
                                   text-text-100 px-6 rounded-xl font-medium text-sm transition border border-munti-green-500/30 shrink-0">
                        Send
                    </button>
                </form>
            </div>
        </div>
    </div>
</main>

<script>
    // ---------- Responsive Inbox Drawer ----------
    const panel = document.getElementById('inbox-panel');
    const overlay = document.getElementById('inbox-overlay');
    const toggleBtn = document.getElementById('toggle-inbox');
    const closeBtn = document.getElementById('close-inbox');

    function openInbox() {
        panel.classList.add('open');
        overlay.classList.add('open');
        overlay.style.pointerEvents = 'auto';
        overlay.style.opacity = '1';
    }

    function closeInbox() {
        panel.classList.remove('open');
        overlay.classList.remove('open');
        overlay.style.pointerEvents = 'none';
        overlay.style.opacity = '0';
    }

    if (toggleBtn) {
        toggleBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            if (panel.classList.contains('open')) {
                closeInbox();
            } else {
                openInbox();
            }
        });
    }

    if (closeBtn) {
        closeBtn.addEventListener('click', closeInbox);
    }

    if (overlay) {
        overlay.addEventListener('click', closeInbox);
    }

    // Auto-close drawer when a sender is clicked on mobile
    document.querySelectorAll('.inbox-item').forEach(item => {
        item.addEventListener('click', () => {
            if (window.innerWidth < 1024) {
                closeInbox();
            }
        });
    });

    // Close drawer on window resize to desktop
    window.addEventListener('resize', () => {
        if (window.innerWidth >= 1024) {
            closeInbox();
        }
    });

    // ---------- Search filter ----------
    const searchInput = document.getElementById('inbox-search');
    if (searchInput) {
        searchInput.addEventListener('input', function () {
            const q = this.value.toLowerCase().trim();
            document.querySelectorAll('.inbox-item').forEach(el => {
                const sender = el.dataset.sender || '';
                el.style.display = (!q || sender.includes(q)) ? '' : 'none';
            });
        });
    }

    // ---------- Auto-scroll conversation to bottom ----------
    const conv = document.getElementById('conversation-scroll');
    if (conv) {
        conv.scrollTop = conv.scrollHeight;
    }

    // ---------- Auto-grow textarea ----------
    const reply = document.getElementById('reply-input');
    if (reply) {
        reply.addEventListener('input', function () {
            this.style.height = 'auto';
            this.style.height = Math.min(this.scrollHeight, 120) + 'px';
        });
    }
</script>

@include('layouts.footer')