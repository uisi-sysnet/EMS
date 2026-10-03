{{--
    Station Map — satellite view of every located station with live status.
    Fills its parent's height; used by the CityWatch page (citywatch.blade.php).

    Data: $mapStations from DashboardController::buildMapData(), refreshed
    every 20s by citywatch.blade.php via window.updateStationMap().

    Hover a pin for a quick status card; click to pin it open.
    Satellite tiles come from Esri World Imagery and need internet access —
    on an offline gateway the pins still work on a plain dark background.
--}}
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js"></script>

<style>
    .sm-shell { --sm-cyan: #5EEAD4; --sm-teal: #14B8A6; --sm-ok: #84CC16; --sm-warn: #FFB702; --sm-bad: #EF4444; --sm-off: #6B7280; }
    .sm-shell .leaflet-container { background: #04120E; font-family: inherit; }
    .sm-shell .leaflet-control-attribution { background: rgba(4,18,14,.7); color: #6B7280; font-size: 9px; }
    .sm-shell .leaflet-control-attribution a { color: #9CA3AF; }
    .sm-shell .leaflet-bar a { background: rgba(6,34,26,.85); color: var(--sm-cyan); border-color: rgba(20,184,166,.3); }
    .sm-shell .leaflet-bar a:hover { background: rgba(11,79,58,.95); }

    /* Vignette + scanline so the imagery reads as a HUD, like a control-room wall */
    .sm-vignette { pointer-events: none; position: absolute; inset: 0; z-index: 401;
        background:
            radial-gradient(ellipse at center, transparent 55%, rgba(2,11,8,.75) 100%),
            linear-gradient(180deg, rgba(2,11,8,.55) 0%, transparent 18%, transparent 82%, rgba(2,11,8,.6) 100%); }
    .sm-hud { z-index: 450; }
    .sm-panel { background: linear-gradient(135deg, rgba(6,34,26,.88), rgba(4,18,14,.78)); border: 1px solid rgba(20,184,166,.35);
        box-shadow: 0 0 18px rgba(20,184,166,.12), inset 0 0 12px rgba(94,234,212,.05); backdrop-filter: blur(6px); }
    .sm-panel-accent { position: relative; }
    .sm-panel-accent::before { content: ""; position: absolute; left: 0; top: 10px; bottom: 10px; width: 3px; border-radius: 2px; background: var(--sm-cyan); box-shadow: 0 0 8px var(--sm-cyan); }
    .sm-title { clip-path: polygon(0 0, 100% 0, 92% 100%, 8% 100%); background: linear-gradient(180deg, rgba(11,79,58,.95), rgba(6,34,26,.9));
        border-bottom: 1px solid rgba(94,234,212,.5); text-shadow: 0 0 10px rgba(94,234,212,.8); letter-spacing: .35em; }
    .sm-big { text-shadow: 0 0 12px rgba(94,234,212,.45); }

    /* Pins */
    .sm-marker { background: none; border: none; }
    .sm-pin { position: relative; width: 34px; height: 34px; }
    .sm-pin-body { position: absolute; inset: 0; border-radius: 50% 50% 50% 0; transform: rotate(-45deg);
        background: var(--pin); border: 2px solid rgba(255,255,255,.85); box-shadow: 0 0 0 3px rgba(0,0,0,.25), 0 0 16px var(--pin);
        display: flex; align-items: center; justify-content: center; transition: transform .15s ease; }
    .sm-pin-body svg { transform: rotate(45deg); width: 16px; height: 16px; color: #fff; filter: drop-shadow(0 1px 1px rgba(0,0,0,.5)); }
    .sm-marker:hover .sm-pin-body, .sm-pin.is-active .sm-pin-body { transform: rotate(-45deg) scale(1.15); }
    .sm-pin-pulse { position: absolute; left: 50%; bottom: -6px; width: 14px; height: 6px; margin-left: -7px; border-radius: 50%; background: var(--pin); opacity: .6; }
    .sm-pin.is-alert .sm-pin-pulse::after { content: ""; position: absolute; inset: -10px -14px; border-radius: 50%; border: 2px solid var(--pin); animation: sm-ping 1.6s ease-out infinite; }
    .sm-pin.is-disabled .sm-pin-body { filter: grayscale(1) brightness(.7); }
    @keyframes sm-ping { 0% { transform: scale(.4); opacity: .9; } 100% { transform: scale(1.6); opacity: 0; } }

    /* Hover card + click popup share the same card markup */
    .sm-tip.leaflet-tooltip, .sm-popup .leaflet-popup-content-wrapper { background: rgba(4,18,14,.94); border: 1px solid rgba(94,234,212,.45);
        border-radius: 10px; box-shadow: 0 0 22px rgba(20,184,166,.25); color: #E5E7EB; padding: 0; }
    .sm-tip.leaflet-tooltip::before { border-top-color: rgba(94,234,212,.45); }
    .sm-popup .leaflet-popup-content { margin: 0; width: 260px !important; }
    .sm-popup .leaflet-popup-tip { background: rgba(4,18,14,.94); border: 1px solid rgba(94,234,212,.45); }
    .sm-popup a.leaflet-popup-close-button { color: #9CA3AF; top: 6px; right: 6px; }
    .sm-card { width: 260px; font-size: 12px; line-height: 1.35; white-space: normal; }
    .sm-card-head { padding: 10px 28px 8px 12px; border-bottom: 1px solid rgba(94,234,212,.18); }
    .sm-card-row { display: flex; align-items: center; gap: 10px; padding: 7px 12px; }
    .sm-card-row + .sm-card-row { border-top: 1px solid rgba(255,255,255,.05); }
    .sm-ico { width: 26px; height: 26px; border-radius: 7px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; background: rgba(20,184,166,.12); color: var(--sm-cyan); }
    .sm-ico svg { width: 15px; height: 15px; }
    .sm-badge { font-size: 10px; font-weight: 600; padding: 2px 7px; border-radius: 999px; white-space: nowrap; }
    .sm-b-online  { color: #A3E635; background: rgba(132,204,22,.14); border: 1px solid rgba(132,204,22,.35); }
    .sm-b-idle, .sm-b-warning { color: #FCCC3D; background: rgba(255,183,2,.12); border: 1px solid rgba(255,183,2,.35); }
    .sm-b-offline { color: #F87171; background: rgba(239,68,68,.14); border: 1px solid rgba(239,68,68,.35); }
    .sm-b-none    { color: #9CA3AF; background: rgba(107,114,128,.14); border: 1px solid rgba(107,114,128,.3); }
    .sm-dot { width: 8px; height: 8px; border-radius: 50%; display: inline-block; flex-shrink: 0; }

    .sm-filter button[aria-pressed="true"] { background: rgba(20,184,166,.25); color: #fff; box-shadow: inset 0 0 0 1px rgba(94,234,212,.6); }
    /* Camera buttons in the pinned card */
    .sm-cam-btn { display: inline-flex; align-items: center; gap: 6px; font-size: 11px; font-weight: 600; color: #fff; padding: 5px 10px; border-radius: 7px;
        background: linear-gradient(180deg, rgba(20,184,166,.45), rgba(15,118,110,.45)); border: 1px solid rgba(94,234,212,.55); transition: filter .15s; }
    .sm-cam-btn:hover { filter: brightness(1.25); }
    .sm-cam-btn svg { width: 13px; height: 13px; }
    .sm-ptz-tag { font-size: 9px; padding: 0 4px; border-radius: 4px; background: rgba(0,0,0,.35); color: var(--sm-cyan); letter-spacing: .05em; }

    /* Camera pop-up */
    .sm-cam-modal { z-index: 2000; }
    .sm-ptz-pad button { aspect-ratio: 1; display: flex; align-items: center; justify-content: center; border-radius: 10px; color: #E5E7EB;
        background: rgba(6,34,26,.9); border: 1px solid rgba(20,184,166,.35); user-select: none; touch-action: none; transition: background .1s, box-shadow .1s; }
    .sm-ptz-pad button:hover { background: rgba(11,79,58,.95); }
    .sm-ptz-pad button.is-pressed { background: rgba(20,184,166,.45); box-shadow: 0 0 12px rgba(94,234,212,.6); color: #fff; }
    .sm-ptz-pad svg { width: 18px; height: 18px; }
    .sm-ptz-zoom button { aspect-ratio: auto; padding: 6px 0; gap: 4px; font-size: 12px; font-weight: 600; }
    .sm-cam-tab[aria-selected="true"] { background: rgba(20,184,166,.25); color: #fff; box-shadow: inset 0 0 0 1px rgba(94,234,212,.6); }
    .sm-shell:fullscreen { border-radius: 0; }
    .sm-shell:fullscreen .sm-map-frame { height: 100vh !important; }
</style>

<div id="station-map-shell" class="sm-shell h-full flex flex-col bg-surface-800 rounded-xl shadow border border-border-700 overflow-hidden">
    <div class="sm-map-frame relative flex-1 min-h-[420px]">
        <div id="station-map" class="absolute inset-0" role="region" aria-label="Station map"></div>
        <div class="sm-vignette"></div>

        {{-- Title plate --}}
        <div class="sm-hud absolute top-0 left-1/2 -translate-x-1/2 pointer-events-none hidden sm:block">
            <div class="sm-title px-10 sm:px-16 py-1.5 sm:py-2 text-xs sm:text-base font-bold text-radar-300 uppercase whitespace-nowrap">
                Station Watchtower
            </div>
        </div>

        {{-- Top-left: filters --}}
        <div class="sm-hud absolute top-3 left-3 flex flex-col gap-2">
            <div class="sm-panel sm-filter rounded-lg p-1 flex gap-0.5 text-[11px] font-medium text-text-300" role="group" aria-label="Filter stations">
                <button type="button" data-filter="all" aria-pressed="true" class="px-2.5 py-1 rounded-md transition hover:text-text-100">All</button>
                <button type="button" data-filter="aq" aria-pressed="false" class="px-2.5 py-1 rounded-md transition hover:text-text-100">Air Quality</button>
                <button type="button" data-filter="seismic" aria-pressed="false" class="px-2.5 py-1 rounded-md transition hover:text-text-100">Seismic</button>
                <button type="button" data-filter="water" aria-pressed="false" class="px-2.5 py-1 rounded-md transition hover:text-text-100">Water Level</button>
                <button type="button" data-filter="camera" aria-pressed="false" class="hidden sm:block px-2.5 py-1 rounded-md transition hover:text-text-100">CCTV</button>
            </div>
        </div>

        {{-- Top-right: clock + tools --}}
        <div class="sm-hud absolute top-3 right-3 flex items-center gap-2">
            <div class="sm-panel rounded-lg px-2.5 py-1.5 text-[11px] text-radar-300 tabular-nums hidden sm:flex items-center gap-1.5">
                <span class="relative flex h-2 w-2"><span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-radar-400 opacity-60"></span><span class="relative inline-flex rounded-full h-2 w-2 bg-radar-400"></span></span>
                <span id="sm-clock">--:--:--</span>
            </div>
            <button type="button" id="sm-fit" title="Show all stations" aria-label="Show all stations" class="sm-panel rounded-lg p-1.5 text-radar-300 hover:text-white transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 8V4h4M20 8V4h-4M4 16v4h4M20 16v4h-4"/></svg>
            </button>
            <button type="button" id="sm-fullscreen" title="Full screen" aria-label="Full screen" class="sm-panel rounded-lg p-1.5 text-radar-300 hover:text-white transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 3H5a2 2 0 00-2 2v3m18 0V5a2 2 0 00-2-2h-3m0 18h3a2 2 0 002-2v-3M3 16v3a2 2 0 002 2h3"/></svg>
            </button>
        </div>

        {{-- Left: summary stack --}}
        <div class="sm-hud absolute left-3 top-16 hidden md:flex flex-col gap-2.5 w-52">
            <div class="sm-panel sm-panel-accent rounded-lg pl-4 pr-3 py-2.5">
                <div class="text-[10px] uppercase tracking-wider text-text-400">Stations online</div>
                <div class="flex items-baseline gap-1 mt-0.5">
                    <span id="sm-stat-stations" class="sm-big text-3xl font-bold text-white tabular-nums">0</span>
                    <span id="sm-stat-stations-total" class="text-sm text-text-400 tabular-nums">/0</span>
                </div>
            </div>
            <div class="sm-panel sm-panel-accent rounded-lg pl-4 pr-3 py-2.5">
                <div class="text-[10px] uppercase tracking-wider text-text-400">Sensors reporting</div>
                <div class="flex items-baseline gap-1 mt-0.5">
                    <span id="sm-stat-sensors" class="sm-big text-3xl font-bold text-white tabular-nums">0</span>
                    <span id="sm-stat-sensors-total" class="text-sm text-text-400 tabular-nums">/0</span>
                </div>
            </div>
            <div class="sm-panel sm-panel-accent rounded-lg pl-4 pr-3 py-2.5">
                <div class="text-[10px] uppercase tracking-wider text-text-400">Cameras online</div>
                <div class="flex items-baseline gap-1 mt-0.5">
                    <span id="sm-stat-cameras" class="sm-big text-3xl font-bold text-white tabular-nums">0</span>
                    <span id="sm-stat-cameras-total" class="text-sm text-text-400 tabular-nums">/0</span>
                </div>
            </div>
            <div class="sm-panel sm-panel-accent rounded-lg pl-4 pr-3 py-2.5">
                <div class="text-[10px] uppercase tracking-wider text-text-400">Network reachable</div>
                <div class="flex items-baseline gap-1 mt-0.5">
                    <span id="sm-stat-network" class="sm-big text-3xl font-bold text-white tabular-nums">0</span>
                    <span id="sm-stat-network-total" class="text-sm text-text-400 tabular-nums">/0</span>
                </div>
            </div>
        </div>

        {{-- Right: needs attention --}}
        <div class="sm-hud absolute right-3 top-16 hidden lg:block w-64">
            <div class="sm-panel rounded-lg overflow-hidden">
                <div class="px-3 py-2 flex items-center justify-between border-b border-radar-500/20">
                    <span class="text-[11px] font-semibold uppercase tracking-wider text-radar-300">Needs attention</span>
                    <span id="sm-alert-count" class="sm-badge sm-b-none">0</span>
                </div>
                <ul id="sm-alert-list" class="max-h-64 overflow-y-auto thin-scrollbar divide-y divide-white/5"></ul>
            </div>
        </div>

        {{-- Bottom-left: legend --}}
        <div class="sm-hud absolute left-3 bottom-3">
            <div class="sm-panel rounded-lg px-3 py-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-[11px] text-text-300">
                <span class="flex items-center gap-1.5"><span class="sm-dot" style="background:#84CC16;box-shadow:0 0 6px #84CC16"></span>Online</span>
                <span class="flex items-center gap-1.5"><span class="sm-dot" style="background:#FFB702;box-shadow:0 0 6px #FFB702"></span>Partial</span>
                <span class="flex items-center gap-1.5"><span class="sm-dot" style="background:#EF4444;box-shadow:0 0 6px #EF4444"></span>Offline</span>
            </div>
        </div>

        {{-- Bottom-center notices --}}
        <div class="sm-hud absolute bottom-14 sm:bottom-3 left-1/2 -translate-x-1/2 flex flex-col items-center gap-1.5 pointer-events-none w-[80%] sm:w-auto sm:max-w-[50%]">
            <div id="sm-tiles-offline" class="hidden sm-panel rounded-md px-3 py-1 text-[11px] text-munti-yellow-400 text-center">
                Satellite imagery unavailable (no internet) — showing stations only
            </div>
            <div id="sm-unlocated" class="hidden sm-panel rounded-md px-3 py-1 text-[11px] text-text-300 text-center"></div>
        </div>

        {{-- Empty state --}}
        <div id="sm-empty" class="hidden sm-hud absolute inset-0 flex items-center justify-center pointer-events-none">
            <div class="sm-panel rounded-xl px-5 py-4 text-center max-w-xs">
                <div class="text-sm font-semibold text-text-100">No stations on the map yet</div>
                <div class="text-xs text-text-400 mt-1">Add latitude and longitude to stations in Inventory › Stations to place them here.</div>
            </div>
        </div>
    </div>

    {{-- Camera live view pop-up (inside the shell so it also shows in full screen) --}}
    @if($canViewCameras ?? false)
    <div id="sm-cam-modal" class="sm-cam-modal hidden fixed inset-0 bg-black/75 backdrop-blur-sm p-3 sm:p-6 flex items-center justify-center"
         role="dialog" aria-modal="true" aria-labelledby="sm-cam-title">
        <div class="sm-panel rounded-xl w-full max-w-5xl max-h-full flex flex-col overflow-hidden">
            <div class="px-4 py-2.5 flex items-center gap-3 border-b border-radar-500/25">
                <span class="sm-ico"><svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10l5-3v10l-5-3M4 6h11v12H4z"/></svg></span>
                <div class="min-w-0 flex-1">
                    <div id="sm-cam-title" class="text-sm font-semibold text-white truncate">Camera</div>
                    <div id="sm-cam-sub" class="text-[11px] text-text-400 truncate"></div>
                </div>
                <span id="sm-cam-status" class="sm-badge sm-b-none">Idle</span>
                <button type="button" id="sm-cam-close" class="p-1.5 rounded-lg text-text-400 hover:text-white hover:bg-white/10 transition" aria-label="Close camera">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <div id="sm-cam-tabs" class="hidden px-3 py-2 gap-1.5 overflow-x-auto border-b border-radar-500/15" role="tablist" aria-label="Cameras at this station"></div>

            <div class="flex flex-col lg:flex-row min-h-0">
                <div class="relative flex-1 bg-black aspect-video lg:aspect-auto lg:min-h-[440px] flex items-center justify-center">
                    <video id="sm-cam-video" class="absolute inset-0 w-full h-full object-contain hidden" autoplay playsinline muted></video>
                    <div id="sm-cam-placeholder" class="text-text-400 text-sm px-4 text-center">Connecting…</div>
                    <div id="sm-cam-live" class="hidden absolute top-2 left-2 flex items-center gap-1.5 text-[10px] font-bold text-white bg-munti-red-600/85 px-2 py-0.5 rounded">
                        <span class="w-1.5 h-1.5 rounded-full bg-white animate-pulse"></span>LIVE
                    </div>
                </div>

                <div id="sm-ptz" class="hidden lg:w-56 shrink-0 p-4 border-t lg:border-t-0 lg:border-l border-radar-500/20 flex-col items-center gap-3">
                    <div class="text-[11px] font-semibold uppercase tracking-wider text-radar-300 self-start">PTZ control</div>
                    <div class="sm-ptz-pad grid grid-cols-3 gap-1.5 w-40">
                        <button type="button" data-pan="-1" data-tilt="1" aria-label="Pan up-left"><svg fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 17L7 7m0 0v8m0-8h8"/></svg></button>
                        <button type="button" data-pan="0" data-tilt="1" aria-label="Tilt up"><svg fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 15l7-7 7 7"/></svg></button>
                        <button type="button" data-pan="1" data-tilt="1" aria-label="Pan up-right"><svg fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M7 17L17 7m0 0H9m8 0v8"/></svg></button>
                        <button type="button" data-pan="-1" data-tilt="0" aria-label="Pan left"><svg fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg></button>
                        <span class="flex items-center justify-center"><span class="w-2.5 h-2.5 rounded-full bg-radar-400 shadow-[0_0_8px_#14B8A6]"></span></span>
                        <button type="button" data-pan="1" data-tilt="0" aria-label="Pan right"><svg fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg></button>
                        <button type="button" data-pan="-1" data-tilt="-1" aria-label="Pan down-left"><svg fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 7L7 17m0 0h8m-8 0V9"/></svg></button>
                        <button type="button" data-pan="0" data-tilt="-1" aria-label="Tilt down"><svg fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg></button>
                        <button type="button" data-pan="1" data-tilt="-1" aria-label="Pan down-right"><svg fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M7 7l10 10m0 0V9m0 8H9"/></svg></button>
                    </div>
                    <div class="sm-ptz-pad sm-ptz-zoom grid grid-cols-2 gap-1.5 w-40">
                        <button type="button" data-zoom="-1" aria-label="Zoom out"><svg fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M8 11h6m3 0a6 6 0 11-12 0 6 6 0 0112 0z"/></svg>Out</button>
                        <button type="button" data-zoom="1" aria-label="Zoom in"><svg fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M11 8v6m-3-3h6m3 0a6 6 0 11-12 0 6 6 0 0112 0z"/></svg>In</button>
                    </div>
                    <label class="w-40 text-[11px] text-text-400">
                        <span class="flex justify-between"><span>Speed</span><span id="sm-ptz-speed-val" class="tabular-nums text-text-200">50%</span></span>
                        <input id="sm-ptz-speed" type="range" min="10" max="100" step="10" value="50" class="w-full accent-teal-400">
                    </label>
                    <p class="text-[10px] text-text-500 text-center leading-snug">Hold a button to move.<br>Keyboard: arrow keys, + and &minus;</p>
                    <p id="sm-ptz-error" class="hidden text-[11px] text-munti-red-400 text-center" role="alert"></p>
                </div>
            </div>
        </div>
    </div>
    @endif

    {{-- Small screens: summary below the map --}}
    <div class="md:hidden shrink-0 grid grid-cols-2 gap-px bg-border-800 border-t border-border-800 text-center">
        <div class="bg-surface-800 py-2"><div class="text-[10px] uppercase text-text-400">Stations</div><div class="text-sm font-semibold text-text-100 tabular-nums"><span id="sm-m-stations">0/0</span></div></div>
        <div class="bg-surface-800 py-2"><div class="text-[10px] uppercase text-text-400">Sensors</div><div class="text-sm font-semibold text-text-100 tabular-nums"><span id="sm-m-sensors">0/0</span></div></div>
        <div class="bg-surface-800 py-2"><div class="text-[10px] uppercase text-text-400">Cameras</div><div class="text-sm font-semibold text-text-100 tabular-nums"><span id="sm-m-cameras">0/0</span></div></div>
        <div class="bg-surface-800 py-2"><div class="text-[10px] uppercase text-text-400">Network</div><div class="text-sm font-semibold text-text-100 tabular-nums"><span id="sm-m-network">0/0</span></div></div>
    </div>
</div>

<script>
(function () {
    const STATUS_COLOR = { online: '#84CC16', warning: '#FFB702', offline: '#EF4444' };
    // Live view + PTZ are admin-only; see DashboardController::citywatch().
    const CAN_VIEW_CAMERAS = @json($canViewCameras ?? false);
    const STATUS_LABEL = { online: 'Online', idle: 'Idle', warning: 'Partial', offline: 'Offline' };
    const TYPE_LABEL   = { aq: 'Air Quality', seismic: 'Seismic', water: 'Water Level', camera: 'CCTV' };
    // Muntinlupa — used only until the first located station appears.
    const DEFAULT_VIEW = { center: [14.4081, 121.0415], zoom: 13 };

    const ICONS = {
        water: '<svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3s-5 5.5-5 9a5 5 0 0010 0c0-3.5-5-9-5-9zM3 20c1.5 0 1.5-1 3-1s1.5 1 3 1 1.5-1 3-1 1.5 1 3 1 1.5-1 3-1 1.5 1 3 1"/></svg>',
        aq: '<svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 8h11a3 3 0 10-3-3M3 12h15a3 3 0 11-3 3M3 16h7"/></svg>',
        seismic: '<svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2 12h4l3-8 4 16 3-8h6"/></svg>',
        camera: '<svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10l5-3v10l-5-3M4 6h11v12H4z"/></svg>',
        sensors: '<svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 3v2m6-2v2M9 19v2m6-2v2M3 9h2m-2 6h2m14-6h2m-2 6h2M7 7h10v10H7z"/></svg>',
        network: '<svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12.55a11 11 0 0114 0M8.5 16.43a6 6 0 017 0M12 20h.01M2 8.82a15 15 0 0120 0"/></svg>',
    };

    const el = (id) => document.getElementById(id);
    const esc = (v) => String(v ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

    // latest_at is Asia/Manila local time ("Y-m-d H:i:s"), see DashboardController::toManila().
    function timeAgo(manila) {
        if (!manila) return 'No data received yet';
        const t = Date.parse(manila.replace(' ', 'T') + '+08:00');
        if (isNaN(t)) return manila;
        const s = Math.max(0, Math.round((Date.now() - t) / 1000));
        if (s < 60) return `Last data ${s}s ago`;
        if (s < 3600) return `Last data ${Math.round(s / 60)} min ago`;
        if (s < 86400) return `Last data ${Math.round(s / 3600)} h ago`;
        return `Last data ${manila.slice(0, 16)}`;
    }

    const badge = (status) => status
        ? `<span class="sm-badge sm-b-${esc(status)}">${esc(STATUS_LABEL[status] || status)}</span>`
        : '<span class="sm-badge sm-b-none">Not installed</span>';

    function row(icon, title, detail, status) {
        return `<div class="sm-card-row">
            <span class="sm-ico">${ICONS[icon]}</span>
            <div class="min-w-0 flex-1">
                <div class="font-semibold text-text-100">${esc(title)}</div>
                <div class="text-[11px] text-text-400 truncate">${esc(detail)}</div>
            </div>
            ${badge(status)}
        </div>`;
    }

    // Buttons to open a camera's live view; only in the clicked (pinned) card,
    // since the hover card can't be clicked.
    function cameraActions(s) {
        const cams = (s.camera?.cameras || []).filter((c) => c.viewable);
        if (!CAN_VIEW_CAMERAS || !cams.length) return '';
        return `<div class="px-3 pb-2.5 -mt-0.5 flex flex-wrap gap-1.5">${cams.map((c) => `
            <button type="button" class="sm-cam-btn" data-cam-station="${esc(s.id)}" data-cam-slug="${esc(c.slug)}">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10l5-3v10l-5-3M4 6h11v12H4z"/></svg>
                ${cams.length > 1 ? esc(c.name) : 'View camera'}${c.ptz ? '<span class="sm-ptz-tag">PTZ</span>' : ''}
            </button>`).join('')}
        </div>`;
    }

    function cardHtml(s, withActions = false) {
        const rows = [];
        if (s.type !== 'camera') {
            const level = s.type === 'water' && s.sensors?.water_level != null ? `Level ${Number(s.sensors.water_level).toFixed(2)} m · ` : '';
            rows.push(row('sensors', s.type === 'water' ? 'Water level sensor' : 'Sensors', s.sensors ? level + timeAgo(s.sensors.latest_at) : 'No sensor registered', s.sensors?.status));
        }
        rows.push(row('camera', 'Camera',
            s.camera ? (s.camera.count > 1 ? `${s.camera.online}/${s.camera.count} online · ${s.camera.name}` : s.camera.name) : 'No camera linked to this station',
            s.camera?.status) + (withActions ? cameraActions(s) : ''));
        if (s.type === 'water' && !s.network) {
            // GSM sensor: no IP to ping; if its SMS readings arrive, the link is up.
            rows.push(row('network', 'Network', 'GSM / SMS link (from data)', s.sensors?.status));
        } else if (s.type === 'aq' || s.type === 'water') {
            rows.push(row('network', 'Network', s.network ? `Ping ${s.network.ip}` : 'No lead IP configured', s.network ? (s.network.status === 'online' ? 'online' : 'offline') : null));
        } else if (s.type === 'seismic') {
            // No pingable address; if MQTT/SMS data is arriving, the link is up.
            rows.push(row('network', 'Network', 'MQTT / SMS link (from data)', s.sensors?.status));
        }

        const sub = [TYPE_LABEL[s.type], s.code, s.location].filter(Boolean).map(esc).join(' · ');
        return `<div class="sm-card">
            <div class="sm-card-head">
                <div class="flex items-center gap-2">
                    <span class="sm-dot" style="background:${STATUS_COLOR[s.status]};box-shadow:0 0 6px ${STATUS_COLOR[s.status]}"></span>
                    <span class="font-semibold text-sm text-white truncate">${esc(s.name)}</span>
                </div>
                <div class="text-[11px] text-text-400 mt-0.5 truncate">${sub}</div>
                ${s.enabled === false ? '<div class="text-[11px] text-munti-yellow-400 mt-1">Station disabled</div>' : ''}
            </div>
            ${rows.join('')}
            <div class="px-3 py-1.5 text-[10px] text-text-500 border-t border-white/5 tabular-nums">${s.lat.toFixed(5)}, ${s.lng.toFixed(5)}</div>
        </div>`;
    }

    function iconFor(s) {
        const alert = s.status !== 'online';
        const cls = ['sm-pin', alert ? 'is-alert' : '', s.enabled === false ? 'is-disabled' : ''].join(' ');
        return L.divIcon({
            className: 'sm-marker',
            html: `<div class="${cls}" style="--pin:${STATUS_COLOR[s.status]}"><span class="sm-pin-pulse"></span><div class="sm-pin-body">${ICONS[s.type]}</div></div>`,
            iconSize: [34, 34],
            iconAnchor: [17, 40],
            popupAnchor: [0, -38],
            tooltipAnchor: [0, -38],
        });
    }

    if (!window.L || !el('station-map')) return;

    const map = L.map('station-map', { zoomControl: false, attributionControl: true, worldCopyJump: true })
        .setView(DEFAULT_VIEW.center, DEFAULT_VIEW.zoom);
    L.control.zoom({ position: 'bottomright' }).addTo(map);

    const imagery = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
        maxZoom: 19, attribution: 'Imagery &copy; Esri, Maxar, Earthstar Geographics',
    }).addTo(map);
    L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/Reference/World_Boundaries_and_Places/MapServer/tile/{z}/{y}/{x}', {
        maxZoom: 19, opacity: 0.8,
    }).addTo(map);

    let tileErrors = 0, tileLoads = 0;
    imagery.on('tileerror', () => { if (++tileErrors > 3 && tileLoads === 0) el('sm-tiles-offline').classList.remove('hidden'); });
    imagery.on('tileload', () => { tileLoads++; el('sm-tiles-offline').classList.add('hidden'); });

    const markers = new Map();   // id -> { marker, data }
    let stations = [];
    let filter = 'all';
    let fitted = false;

    const visible = (s) => filter === 'all' || s.type === filter;

    function fitAll() {
        const pts = stations.filter(visible).map((s) => [s.lat, s.lng]);
        if (pts.length === 1) map.setView(pts[0], 17);
        else if (pts.length > 1) map.fitBounds(pts, { padding: [70, 70], maxZoom: 17 });
    }

    function focus(id) {
        const m = markers.get(id);
        if (!m) return;
        map.flyTo(m.marker.getLatLng(), Math.max(map.getZoom(), 17), { duration: 0.8 });
        map.once('moveend', () => m.marker.openPopup());
    }

    function renderMarkers() {
        const seen = new Set();
        for (const s of stations) {
            seen.add(s.id);
            const html = cardHtml(s);
            const popupHtml = cardHtml(s, true);
            let entry = markers.get(s.id);
            if (!entry) {
                const marker = L.marker([s.lat, s.lng], { icon: iconFor(s), riseOnHover: true, keyboard: true, title: s.name })
                    .bindTooltip(html, { className: 'sm-tip', direction: 'top', opacity: 1 })
                    // Pan far enough that the card clears the title plate and HUD panels.
                    .bindPopup(popupHtml, { className: 'sm-popup', maxWidth: 280, minWidth: 260,
                        autoPanPaddingTopLeft: [24, 64], autoPanPaddingBottomRight: [24, 56] });
                // While the clicked card is open, the hover card would just duplicate it.
                marker.on('popupopen', () => { marker.closeTooltip(); marker.unbindTooltip(); });
                marker.on('popupclose', () => marker.bindTooltip(markers.get(s.id).html, { className: 'sm-tip', direction: 'top', opacity: 1 }));
                entry = { marker };
                markers.set(s.id, entry);
            } else {
                entry.marker.setLatLng([s.lat, s.lng]).setIcon(iconFor(s));
                entry.marker.setPopupContent(popupHtml);
                if (entry.marker.getTooltip()) entry.marker.setTooltipContent(html);
            }
            entry.html = html;
            entry.data = s;
            if (visible(s)) entry.marker.addTo(map); else entry.marker.remove();
        }
        for (const [id, entry] of markers) {
            if (!seen.has(id)) { entry.marker.remove(); markers.delete(id); }
        }
    }

    function setStat(key, up, total) {
        el(`sm-stat-${key}`).textContent = up;
        el(`sm-stat-${key}-total`).textContent = `/${total}`;
        el(`sm-m-${key}`).textContent = `${up}/${total}`;
    }

    function renderSummary(unlocated) {
        const sites = stations.filter((s) => s.type !== 'camera');
        setStat('stations', sites.filter((s) => s.status === 'online').length, sites.length);

        const sensors = sites.filter((s) => s.sensors);
        setStat('sensors', sensors.filter((s) => s.sensors.status === 'online').length, sensors.length);

        let camUp = 0, camTotal = 0;
        stations.forEach((s) => { if (s.camera) { camUp += s.camera.online; camTotal += s.camera.count; } });
        setStat('cameras', camUp, camTotal);

        const nets = stations.filter((s) => s.network);
        setStat('network', nets.filter((s) => s.network.status === 'online').length, nets.length);

        const order = { offline: 0, warning: 1 };
        const alerts = stations.filter((s) => s.status !== 'online').sort((a, b) => order[a.status] - order[b.status] || a.name.localeCompare(b.name));
        const countEl = el('sm-alert-count');
        countEl.textContent = alerts.length;
        countEl.className = `sm-badge ${alerts.some((a) => a.status === 'offline') ? 'sm-b-offline' : alerts.length ? 'sm-b-warning' : 'sm-b-online'}`;

        const problems = (s) => {
            const p = [];
            if (s.sensors && s.sensors.status !== 'online') p.push(`Sensors ${STATUS_LABEL[s.sensors.status].toLowerCase()}`);
            if (s.camera && s.camera.status !== 'online') p.push(s.camera.count > 1 ? `${s.camera.count - s.camera.online} camera(s) offline` : 'Camera offline');
            if (s.network && s.network.status !== 'online') p.push('Network unreachable');
            return p.join(' · ') || STATUS_LABEL[s.status];
        };

        el('sm-alert-list').innerHTML = alerts.length
            ? alerts.map((s) => `<li>
                    <button type="button" data-focus="${esc(s.id)}" class="w-full text-left px-3 py-2 flex items-start gap-2 hover:bg-radar-500/10 transition">
                        <span class="sm-dot mt-1" style="background:${STATUS_COLOR[s.status]};box-shadow:0 0 6px ${STATUS_COLOR[s.status]}"></span>
                        <span class="min-w-0">
                            <span class="block text-xs font-medium text-text-100 truncate">${esc(s.name)}</span>
                            <span class="block text-[11px] text-text-400 truncate">${esc(problems(s))}</span>
                        </span>
                    </button>
                </li>`).join('')
            : '<li class="px-3 py-3 text-xs text-munti-green-400">All stations operational</li>';

        const un = el('sm-unlocated');
        if (unlocated > 0) {
            un.textContent = `${unlocated} station${unlocated > 1 ? 's have' : ' has'} no coordinates and ${unlocated > 1 ? 'are' : 'is'} not shown`;
            un.classList.remove('hidden');
        } else {
            un.classList.add('hidden');
        }
        el('sm-empty').classList.toggle('hidden', stations.length > 0);
    }

    window.updateStationMap = function (payload) {
        if (!payload) return;
        stations = Array.isArray(payload.markers) ? payload.markers : [];
        renderMarkers();
        renderSummary(payload.unlocated || 0);
        if (!fitted && stations.length) { fitAll(); fitted = true; }
    };

    // Filters
    document.querySelectorAll('#station-map-shell [data-filter]').forEach((btn) => {
        btn.addEventListener('click', () => {
            filter = btn.dataset.filter;
            document.querySelectorAll('#station-map-shell [data-filter]').forEach((b) => b.setAttribute('aria-pressed', String(b === btn)));
            renderMarkers();
            fitAll();
        });
    });

    el('sm-alert-list').addEventListener('click', (e) => {
        const btn = e.target.closest('[data-focus]');
        if (!btn) return;
        const s = stations.find((x) => x.id === btn.dataset.focus);
        if (s && !visible(s)) el('station-map-shell').querySelector('[data-filter="all"]').click();
        focus(btn.dataset.focus);
    });

    el('sm-fit').addEventListener('click', () => { userMoved = false; fitAll(); });

    el('sm-fullscreen').addEventListener('click', () => {
        const shell = el('station-map-shell');
        if (document.fullscreenElement) document.exitFullscreen();
        else shell.requestFullscreen?.();
    });
    document.addEventListener('fullscreenchange', () => setTimeout(() => map.invalidateSize(), 150));

    const clock = el('sm-clock');
    const tick = () => { clock.textContent = new Date().toLocaleTimeString('en-PH', { timeZone: 'Asia/Manila', hour12: false }); };
    tick();
    setInterval(tick, 1000);

    // The map's box changes size as the dashboard lays out, on window resize
    // and in full screen. Keep Leaflet in sync, and keep every station in view
    // until the user takes over by dragging or zooming.
    let userMoved = false;
    map.on('dragstart', () => { userMoved = true; });
    map.getContainer().addEventListener('wheel', () => { userMoved = true; }, { passive: true });
    map.on('zoomstart', (e) => { if (e.originalEvent) userMoved = true; });
    el('station-map-shell').querySelectorAll('.leaflet-control-zoom a').forEach((a) => a.addEventListener('click', () => { userMoved = true; }));

    if (window.ResizeObserver) {
        new ResizeObserver(() => {
            map.invalidateSize();
            if (!userMoved) fitAll();
        }).observe(map.getContainer());
    }

    window.updateStationMap(@json($mapStations));

    // ------------------------------------------------------------------
    // Camera live view + PTZ pop-up (admins only — the modal markup is
    // only rendered for them). Streams over WebRTC from MediaMTX via WHEP
    // and drives PTZ through CameraController::ptz(), exactly like the
    // Live View page (server/cameras-live.blade.php).
    // ------------------------------------------------------------------
    const modal = el('sm-cam-modal');
    if (!CAN_VIEW_CAMERAS || !modal) return;

    // MediaMTX read credential (basic auth on the WHEP endpoint).
    const MEDIAMTX_AUTH = btoa(@json(($mediamtxReadUser ?? '') . ':' . ($mediamtxReadPass ?? '')));
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
    const video = el('sm-cam-video');
    const placeholder = el('sm-cam-placeholder');
    const ptzPanel = el('sm-ptz');
    const ptzError = el('sm-ptz-error');
    const speedInput = el('sm-ptz-speed');

    let pc = null;
    let current = null;       // { station, cam }
    let moving = false;
    let lastFocus = null;

    function setCamStatus(text, cls) {
        const b = el('sm-cam-status');
        b.textContent = text;
        b.className = `sm-badge ${cls}`;
    }

    function showPlaceholder(text) {
        video.classList.add('hidden');
        el('sm-cam-live').classList.add('hidden');
        placeholder.textContent = text;
        placeholder.classList.remove('hidden');
    }

    function closeStream() {
        if (pc) { try { pc.close(); } catch (e) {} pc = null; }
        video.srcObject = null;
    }

    function waitIceGatheringComplete(peer) {
        return new Promise((resolve) => {
            if (peer.iceGatheringState === 'complete') return resolve();
            const check = () => {
                if (peer.iceGatheringState === 'complete') {
                    peer.removeEventListener('icegatheringstatechange', check);
                    resolve();
                }
            };
            peer.addEventListener('icegatheringstatechange', check);
            setTimeout(resolve, 3000);   // proceed with whatever candidates we have
        });
    }

    async function play(cam) {
        closeStream();
        showPlaceholder(cam.status === 'online' ? 'Connecting…' : 'Camera reported offline — trying to connect…');
        setCamStatus('Connecting', 'sm-b-none');

        const peer = new RTCPeerConnection({ iceServers: [{ urls: 'stun:stun.l.google.com:19302' }] });
        pc = peer;
        peer.addTransceiver('video', { direction: 'recvonly' });
        peer.addTransceiver('audio', { direction: 'recvonly' });

        peer.ontrack = (event) => {
            if (pc !== peer || video.srcObject === event.streams[0]) return;
            video.srcObject = event.streams[0];
            video.classList.remove('hidden');
            placeholder.classList.add('hidden');
            el('sm-cam-live').classList.remove('hidden');
            setCamStatus('Live', 'sm-b-online');
        };
        peer.onconnectionstatechange = () => {
            if (pc === peer && ['failed', 'disconnected'].includes(peer.connectionState)) {
                showPlaceholder('Stream interrupted.');
                setCamStatus('Offline', 'sm-b-offline');
            }
        };

        try {
            await peer.setLocalDescription(await peer.createOffer());
            await waitIceGatheringComplete(peer);
            const res = await fetch(`/cctv-stream/${encodeURIComponent(cam.slug)}/whep`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/sdp', 'Authorization': 'Basic ' + MEDIAMTX_AUTH },
                body: peer.localDescription.sdp,
            });
            if (!res.ok) throw new Error(`WHEP request failed: ${res.status}`);
            if (pc !== peer) return;   // user switched camera meanwhile
            await peer.setRemoteDescription({ type: 'answer', sdp: await res.text() });
        } catch (err) {
            if (pc !== peer) return;
            console.error('CityWatch camera stream error:', err);
            showPlaceholder('Unable to connect to this camera.');
            setCamStatus('Error', 'sm-b-offline');
        }
    }

    // ---- PTZ ----
    function ptzSend(body) {
        if (!current) return Promise.resolve();
        return fetch(`/cctv-stream/${encodeURIComponent(current.cam.slug)}/ptz`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
            body: JSON.stringify(body),
        }).then(async (res) => {
            if (res.ok) { ptzError.classList.add('hidden'); return; }
            const data = await res.json().catch(() => ({}));
            ptzError.textContent = data.error || data.message || `PTZ failed (${res.status})`;
            ptzError.classList.remove('hidden');
        }).catch(() => {
            ptzError.textContent = 'PTZ request failed — check the connection.';
            ptzError.classList.remove('hidden');
        });
    }

    function ptzMove(pan, tilt, zoom) {
        if (!current?.cam.ptz) return;
        const speed = Number(speedInput.value) / 100;
        moving = true;
        ptzSend({ pan: pan * speed, tilt: tilt * speed, zoom: zoom * speed });
    }

    function ptzStop() {
        ptzPanel.querySelectorAll('.is-pressed').forEach((b) => b.classList.remove('is-pressed'));
        if (!moving) return;
        moving = false;
        ptzSend({ stop: true });
    }

    ptzPanel.querySelectorAll('button').forEach((btn) => {
        btn.addEventListener('pointerdown', (e) => {
            e.preventDefault();
            btn.setPointerCapture?.(e.pointerId);
            btn.classList.add('is-pressed');
            ptzMove(Number(btn.dataset.pan || 0), Number(btn.dataset.tilt || 0), Number(btn.dataset.zoom || 0));
        });
        ['pointerup', 'pointercancel', 'lostpointercapture'].forEach((ev) => btn.addEventListener(ev, ptzStop));
    });
    speedInput.addEventListener('input', () => { el('sm-ptz-speed-val').textContent = `${speedInput.value}%`; });

    const KEY_MOVES = { ArrowUp: [0, 1, 0], ArrowDown: [0, -1, 0], ArrowLeft: [-1, 0, 0], ArrowRight: [1, 0, 0], '+': [0, 0, 1], '=': [0, 0, 1], '-': [0, 0, -1], '_': [0, 0, -1] };
    // The held key is tracked by e.code (the physical key), not e.key: "+" is
    // Shift+"=" on most layouts, so the same key can go down as "=" and come
    // up as "+" (or the reverse) — matching on e.key would leave it "held"
    // forever and lock out every later key.
    let heldKey = null;

    document.addEventListener('keydown', (e) => {
        if (modal.classList.contains('hidden')) return;
        if (e.key === 'Escape') { closeCamera(); return; }
        const move = KEY_MOVES[e.key];
        if (!move || !current?.cam.ptz || e.target.closest?.('input, textarea, select')) return;
        e.preventDefault();
        if (e.repeat || heldKey) return;
        heldKey = e.code || e.key;
        ptzMove(...move);
    });
    document.addEventListener('keyup', (e) => {
        if (heldKey && (e.code || e.key) === heldKey) { heldKey = null; ptzStop(); }
    });
    // Never leave a camera spinning if focus or the page goes away mid-move.
    window.addEventListener('blur', () => { heldKey = null; ptzStop(); });
    document.addEventListener('visibilitychange', () => { if (document.hidden) { heldKey = null; ptzStop(); } });
    window.addEventListener('beforeunload', () => { ptzStop(); closeStream(); });

    // ---- Open / switch / close ----
    function selectCamera(station, cam) {
        ptzStop();
        current = { station, cam };
        el('sm-cam-title').textContent = cam.name;
        el('sm-cam-sub').textContent = [station.name, TYPE_LABEL[station.type], station.location].filter(Boolean).join(' · ');
        el('sm-cam-tabs').querySelectorAll('[data-tab-slug]').forEach((t) => t.setAttribute('aria-selected', String(t.dataset.tabSlug === cam.slug)));

        ptzPanel.classList.toggle('hidden', !cam.ptz);
        ptzPanel.classList.toggle('flex', !!cam.ptz);
        ptzError.classList.add('hidden');
        play(cam);
    }

    function openCamera(stationId, slug) {
        const station = stations.find((x) => x.id === stationId);
        const cams = (station?.camera?.cameras || []).filter((c) => c.viewable);
        const cam = cams.find((c) => c.slug === slug) || cams[0];
        if (!station || !cam) return;

        const tabs = el('sm-cam-tabs');
        tabs.innerHTML = cams.length > 1 ? cams.map((c) => `
            <button type="button" role="tab" data-tab-slug="${esc(c.slug)}" aria-selected="false"
                class="sm-cam-tab shrink-0 px-2.5 py-1 rounded-md text-[11px] font-medium text-text-300 hover:text-white flex items-center gap-1.5">
                <span class="sm-dot" style="background:${c.status === 'online' ? '#84CC16' : '#EF4444'}"></span>${esc(c.name)}${c.ptz ? '<span class="sm-ptz-tag">PTZ</span>' : ''}
            </button>`).join('') : '';
        tabs.classList.toggle('hidden', cams.length < 2);
        tabs.classList.toggle('flex', cams.length > 1);

        lastFocus = document.activeElement;
        modal.classList.remove('hidden');
        map.closePopup();
        selectCamera(station, cam);
        el('sm-cam-close').focus();
    }

    function closeCamera() {
        heldKey = null;
        ptzStop();
        closeStream();
        current = null;
        modal.classList.add('hidden');
        lastFocus?.focus?.();
    }

    el('sm-cam-tabs').addEventListener('click', (e) => {
        const tab = e.target.closest('[data-tab-slug]');
        if (!tab || !current) return;
        const cam = current.station.camera.cameras.find((c) => c.slug === tab.dataset.tabSlug);
        if (cam && cam.slug !== current.cam.slug) selectCamera(current.station, cam);
    });
    el('sm-cam-close').addEventListener('click', closeCamera);
    modal.addEventListener('click', (e) => { if (e.target === modal) closeCamera(); });

    // "View camera" buttons live inside Leaflet popups, which are re-rendered
    // on every refresh — so listen on the map container. Capture phase,
    // because Leaflet stops click propagation out of popups.
    map.getContainer().addEventListener('click', (e) => {
        const btn = e.target.closest('[data-cam-slug]');
        if (!btn) return;
        e.stopPropagation();
        openCamera(btn.dataset.camStation, btn.dataset.camSlug);
    }, true);
})();
</script>
