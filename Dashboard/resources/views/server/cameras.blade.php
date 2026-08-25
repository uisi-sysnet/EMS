{{-- resources/views/server/cameras.blade.php --}}
@include('layouts.header')
@include('layouts.topbar')

<div id="main-content"
     class="pt-20 pb-6 px-4 sm:px-6 max-w-7xl mx-auto w-full overflow-hidden flex flex-col h-[calc(100dvh)] max-h-[calc(100dvh)]">
    <div class="bg-surface-900 rounded-2xl shadow-xl border border-border-800 overflow-hidden flex-1 flex flex-col min-h-0">
        {{-- Header --}}
        <div class="px-4 sm:px-6 py-3 sm:py-4 border-b border-border-800 bg-surface-800 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-1">
            <h2 class="text-lg sm:text-xl font-semibold text-text-100 flex items-center gap-2">
                <span class="leading-tight uppercase">CCTV</span>
            </h2>
            <div class="flex items-center gap-3">
                <button type="button" id="add-camera-toggle"
                        class="text-xs font-semibold px-3 py-1.5 rounded-lg border border-border-600 text-text-300 hover:bg-surface-700 transition inline-flex items-center gap-1.5">
                    + Add Camera
                </button>
            </div>
        </div>

        {{-- Scrollable body --}}
        <div class="flex-1 min-h-0 overflow-y-auto thin-scrollbar px-4 sm:px-6 py-4 sm:py-6 space-y-6">

            @if (session('status'))
                <div class="bg-munti-green-700/20 text-munti-green-400 border border-munti-green-600/30 text-sm px-4 py-2 rounded-lg">
                    {{ session('status') }}
                </div>
            @endif

            {{-- ========== ADD CAMERA FORM (hidden by default) ========== --}}
            <div id="add-camera-form" class="hidden bg-surface-800 border border-border-700 rounded-xl p-4">
                <h3 class="text-sm font-semibold text-text-100 mb-3">Add Camera</h3>
                <form method="POST" action="{{ route('cameras.store') }}" class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    @csrf
                    <input type="text" name="name" placeholder="Name (e.g. Front Gate)" required
                           class="bg-surface-900 border border-border-700 rounded-lg px-3 py-2 text-sm text-text-100 placeholder-text-500">
                    <input type="text" name="location" placeholder="Location (optional)"
                           class="bg-surface-900 border border-border-700 rounded-lg px-3 py-2 text-sm text-text-100 placeholder-text-500">
                    <input type="text" name="ip_address" placeholder="IP address" required
                           class="bg-surface-900 border border-border-700 rounded-lg px-3 py-2 text-sm text-text-100 placeholder-text-500">
                    <input type="number" name="onvif_port" placeholder="ONVIF port" value="80" required
                           class="bg-surface-900 border border-border-700 rounded-lg px-3 py-2 text-sm text-text-100 placeholder-text-500">
                    <input type="text" name="username" placeholder="Username" required
                           class="bg-surface-900 border border-border-700 rounded-lg px-3 py-2 text-sm text-text-100 placeholder-text-500">
                    <input type="password" name="password" placeholder="Password" required
                           class="bg-surface-900 border border-border-700 rounded-lg px-3 py-2 text-sm text-text-100 placeholder-text-500">
                    <textarea name="notes" placeholder="Notes (optional)" rows="1"
                              class="sm:col-span-2 bg-surface-900 border border-border-700 rounded-lg px-3 py-2 text-sm text-text-100 placeholder-text-500"></textarea>
                    <div class="sm:col-span-2 flex justify-end gap-2">
                        <button type="submit"
                                class="text-xs font-semibold px-4 py-2 rounded-lg border border-munti-green-600/40 text-munti-green-400 hover:bg-munti-green-700/20 transition">
                            Add &amp; Connect
                        </button>
                    </div>
                </form>
            </div>

            {{-- ========== CAMERA GRID ========== --}}
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                @forelse ($cameras as $cam)
                    <div class="camera-card bg-surface-800 border border-border-700 rounded-xl overflow-hidden" data-slug="{{ $cam->slug }}">
                        <div class="flex items-center justify-between px-4 py-2.5 border-b border-border-700">
                            <div class="min-w-0">
                                <p class="text-sm font-semibold text-text-100 truncate">{{ $cam->name }}</p>
                                @if ($cam->location)
                                    <p class="text-xs text-text-500 truncate">{{ $cam->location }}</p>
                                @endif
                            </div>
                            <span class="status-pill shrink-0 inline-flex items-center gap-1.5 text-xs font-medium px-2 py-1 rounded-full
                                {{ $cam->last_status === 'online'
                                    ? 'bg-munti-green-700/20 text-munti-green-400 border border-munti-green-600/30'
                                    : ($cam->last_status === 'error'
                                        ? 'bg-munti-red-700/20 text-munti-red-400 border border-munti-red-600/30'
                                        : 'bg-surface-700 text-text-400 border border-border-600') }}">
                                {{ ucfirst($cam->last_status ?? 'unknown') }}
                            </span>
                        </div>

                        <div class="bg-black aspect-video flex items-center justify-center relative">
                            <video class="cam-video w-full h-full object-contain" autoplay muted playsinline></video>
                            <span class="cam-overlay-status absolute inset-0 flex items-center justify-center text-xs text-text-500">
                                Connecting…
                            </span>
                        </div>

                        <div class="px-4 py-2.5 flex items-center justify-between gap-2">
                            @if ($cam->last_error)
                                <p class="text-xs text-munti-red-400 truncate" title="{{ $cam->last_error }}">{{ $cam->last_error }}</p>
                            @else
                                <span></span>
                            @endif
                            <div class="flex gap-2 shrink-0">
                                <button type="button" class="cam-reconnect text-xs text-text-400 hover:text-text-100 px-2 py-1 rounded hover:bg-surface-700 transition">
                                    Reconnect
                                </button>
                                <form method="POST" action="{{ route('cameras.refresh', $cam) }}">
                                    @csrf
                                    <button type="submit" class="text-xs text-text-400 hover:text-text-100 px-2 py-1 rounded hover:bg-surface-700 transition">
                                        Refresh
                                    </button>
                                </form>
                                <form method="POST" action="{{ route('cameras.destroy', $cam) }}"
                                      onsubmit="return confirm('Remove {{ $cam->name }}?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-xs text-munti-red-400 hover:text-munti-red-300 px-2 py-1 rounded hover:bg-munti-red-700/20 transition">
                                        Delete
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-text-500">No cameras added yet.</p>
                @endforelse
            </div>

        </div>{{-- /scrollable body --}}
    </div>{{-- /card --}}
</div>{{-- /main-content --}}

<script>
document.addEventListener('DOMContentLoaded', function () {
    document.getElementById('add-camera-toggle').addEventListener('click', function () {
        document.getElementById('add-camera-form').classList.toggle('hidden');
    });

    // Basic-auth credential mediamtx requires to read a path — injected
    // here because this page itself only renders for authenticated
    // administrators (same 'auth' + 'role:administrator' gate as the
    // rest of Maintenance), matching how the terminal token is scoped.
    const mediamtxAuth = btoa('{{ $mediamtxReadUser }}:{{ $mediamtxReadPass }}');

    function waitIceGatheringComplete(pc) {
        return new Promise((resolve) => {
            if (pc.iceGatheringState === 'complete') return resolve();
            function check() {
                if (pc.iceGatheringState === 'complete') {
                    pc.removeEventListener('icegatheringstatechange', check);
                    resolve();
                }
            }
            pc.addEventListener('icegatheringstatechange', check);
            setTimeout(resolve, 3000); // safety timeout — proceed with whatever candidates we have
        });
    }

    async function connectCamera(card) {
        const slug = card.dataset.slug;
        const video = card.querySelector('.cam-video');
        const overlay = card.querySelector('.cam-overlay-status');
        overlay.textContent = 'Connecting…';
        overlay.classList.remove('hidden');

        if (card._pc) {
            try { card._pc.close(); } catch (e) {}
        }

        const pc = new RTCPeerConnection({ iceServers: [{ urls: 'stun:stun.l.google.com:19302' }] });
        card._pc = pc;

        pc.addTransceiver('video', { direction: 'recvonly' });
        pc.addTransceiver('audio', { direction: 'recvonly' });

        pc.ontrack = (event) => {
            if (video.srcObject !== event.streams[0]) {
                video.srcObject = event.streams[0];
                overlay.classList.add('hidden');
            }
        };

        pc.onconnectionstatechange = () => {
            if (['failed', 'disconnected', 'closed'].includes(pc.connectionState)) {
                overlay.textContent = 'Disconnected';
                overlay.classList.remove('hidden');
            }
        };

        try {
            const offer = await pc.createOffer();
            await pc.setLocalDescription(offer);
            await waitIceGatheringComplete(pc);

            const res = await fetch(`/cctv-stream/${slug}/whep`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/sdp',
                    'Authorization': 'Basic ' + mediamtxAuth,
                },
                body: pc.localDescription.sdp,
            });

            if (!res.ok) throw new Error('WHEP request failed: ' + res.status);

            const answerSdp = await res.text();
            await pc.setRemoteDescription({ type: 'answer', sdp: answerSdp });
        } catch (e) {
            console.error('Camera connect failed:', e);
            overlay.textContent = 'Connection failed';
            overlay.classList.remove('hidden');
        }
    }

    document.querySelectorAll('.camera-card').forEach((card) => {
        connectCamera(card);
        card.querySelector('.cam-reconnect').addEventListener('click', () => connectCamera(card));
    });

    window.addEventListener('beforeunload', () => {
        document.querySelectorAll('.camera-card').forEach((card) => {
            if (card._pc) { try { card._pc.close(); } catch (e) {} }
        });
    });
});
</script>

@include('layouts.footer')