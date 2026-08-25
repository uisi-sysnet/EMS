{{-- resources/views/server/cameras-live.blade.php --}}
@include('layouts.header')
@include('layouts.topbar')

<div id="main-content" class="pt-20 pb-0 px-0 w-full overflow-hidden flex flex-col h-[calc(100dvh)] max-h-[calc(100dvh)]">
    <div class="flex flex-col lg:flex-row flex-1 min-h-0">

        {{-- LEFT: Camera list (30%) --}}
        <div class="w-full lg:w-[30%] lg:min-w-[280px] border-b lg:border-b-0 lg:border-r border-border-800 bg-surface-900/50 lg:overflow-y-auto thin-scrollbar shrink-0">
            <div class="px-4 py-3 border-b border-border-800">
                <h2 class="text-sm font-semibold text-text-100 uppercase tracking-wide">Cameras</h2>
                <p class="text-xs text-text-400 mt-0.5">{{ $cameras->count() }} connected</p>
            </div>
            <div id="camera-list" class="divide-y divide-border-800 max-h-64 lg:max-h-none overflow-y-auto lg:overflow-visible thin-scrollbar">
                @forelse ($cameras as $cam)
                    <button type="button"
                        class="camera-item w-full text-left px-4 py-3 hover:bg-surface-800 transition flex items-center justify-between gap-2"
                        data-slug="{{ $cam->slug }}"
                        data-name="{{ $cam->name }}"
                        data-location="{{ $cam->location }}">
                        <div class="min-w-0">
                            <div class="text-sm font-medium text-text-100 truncate">{{ $cam->name }}</div>
                            @if ($cam->location)
                                <div class="text-xs text-text-500 truncate">{{ $cam->location }}</div>
                            @endif
                        </div>
                        <span class="shrink-0 w-2 h-2 rounded-full {{ $cam->last_status === 'online' ? 'bg-munti-green-400' : ($cam->last_status === 'error' ? 'bg-munti-red-400' : 'bg-text-500') }}"></span>
                    </button>
                @empty
                    <div class="px-4 py-8 text-sm text-text-500 text-center">No cameras added yet.</div>
                @endforelse
            </div>
        </div>

        {{-- RIGHT: Viewer (70%) --}}
        <div class="w-full lg:w-[70%] flex-1 flex flex-col bg-black min-h-[50vh] lg:min-h-0">
            <div class="px-4 py-3 border-b border-border-800 bg-surface-900/50 flex items-center justify-between shrink-0">
                <div>
                    <div id="viewer-name" class="text-sm font-semibold text-text-100">Select a camera</div>
                    <div id="viewer-location" class="text-xs text-text-500"></div>
                </div>
                <span id="viewer-status" class="text-xs px-2 py-0.5 rounded-full border border-border-600 text-text-400">Idle</span>
            </div>
            <div class="flex-1 relative flex items-center justify-center">
                <video id="cctv-player" class="w-full h-full object-contain hidden" autoplay playsinline muted controls></video>
                <div id="viewer-placeholder" class="text-text-500 text-sm px-4 text-center">Choose a camera from the list to start streaming</div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const video = document.getElementById('cctv-player');
    const placeholder = document.getElementById('viewer-placeholder');
    const nameEl = document.getElementById('viewer-name');
    const locEl = document.getElementById('viewer-location');
    const statusEl = document.getElementById('viewer-status');

    // Same basic-auth credential mediamtx requires to read a path,
    // scoped to this page the same way as the Maintenance cameras page
    // ('auth' + 'role:administrator').
    const mediamtxAuth = btoa('{{ $mediamtxReadUser }}:{{ $mediamtxReadPass }}');

    let pc = null;

    function setStatus(text, colorClass) {
        statusEl.textContent = text;
        statusEl.className = 'text-xs px-2 py-0.5 rounded-full border ' + colorClass;
    }

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

    async function playCamera(slug, name, location) {
        if (pc) { try { pc.close(); } catch (e) {} pc = null; }
        video.classList.add('hidden');
        video.srcObject = null;
        placeholder.classList.remove('hidden');
        placeholder.textContent = 'Connecting…';
        nameEl.textContent = name;
        locEl.textContent = location || '';
        setStatus('Connecting', 'border-border-600 text-text-400');

        pc = new RTCPeerConnection({ iceServers: [{ urls: 'stun:stun.l.google.com:19302' }] });

        pc.addTransceiver('video', { direction: 'recvonly' });
        pc.addTransceiver('audio', { direction: 'recvonly' });

        pc.ontrack = (event) => {
            if (video.srcObject !== event.streams[0]) {
                video.srcObject = event.streams[0];
                video.classList.remove('hidden');
                placeholder.classList.add('hidden');
                setStatus('Live', 'border-munti-green-600/40 text-munti-green-400');
            }
        };
        pc.onconnectionstatechange = () => {
            if (['failed', 'disconnected', 'closed'].includes(pc.connectionState)) {
                setStatus('Offline', 'border-munti-red-600/40 text-munti-red-400');
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
        } catch (err) {
            console.error('CCTV stream error:', err);
            placeholder.textContent = 'Unable to connect to this camera.';
            placeholder.classList.remove('hidden');
            video.classList.add('hidden');
            setStatus('Error', 'border-munti-red-600/40 text-munti-red-400');
        }
    }

    document.querySelectorAll('.camera-item').forEach((btn) => {
        btn.addEventListener('click', function () {
            document.querySelectorAll('.camera-item').forEach((b) => b.classList.remove('bg-surface-800'));
            this.classList.add('bg-surface-800');
            playCamera(this.dataset.slug, this.dataset.name, this.dataset.location);
        });
    });

    window.addEventListener('beforeunload', () => {
        if (pc) { try { pc.close(); } catch (e) {} }
    });
});
</script>

@include('layouts.footer')