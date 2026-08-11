@include('layouts.header')

<div class="fixed inset-0 bg-background-950 flex items-center justify-center px-6 overflow-hidden"
     x-data="{ mode: '{{ old('mode', request()->get('mode', 'login')) }}' }">

    {{-- Decorative background elements --}}
    <div class="absolute inset-0 pointer-events-none">
        <div class="absolute top-[-10%] left-[-10%] w-96 h-96 bg-radar-600/20 rounded-full blur-3xl"></div>
        <div class="absolute bottom-[-10%] right-[-10%] w-96 h-96 bg-munti-yellow-500/10 rounded-full blur-3xl"></div>
        <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[600px] h-[600px] bg-radar-700/5 rounded-full blur-2xl"></div>
    </div>

    <div class="w-full max-w-5xl rounded-[40px] overflow-hidden shadow-2xl border border-border-800
                flex flex-col lg:flex-row bg-surface-900/90 backdrop-blur-sm relative z-10">

        <!-- LEFT PANEL -->
        <div
            class="lg:w-1/2 bg-gradient-to-br from-radar-700 via-radar-600 to-radar-500
                   flex flex-col justify-center items-center px-5 py-16 relative">

            {{-- Decorative pattern overlay --}}
            <div class="absolute inset-0 opacity-10 bg-[url('data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSI2MCIgaGVpZ2h0PSI2MCIgdmlld0JveD0iMCAwIDYwIDYwIj48cGF0aCBkPSJNMzAgMTBMMTAgMzBsMjAgMjAgMjAtMjB6IiBmaWxsPSIjZmZmIiBmaWxsLW9wYWNpdHk9IjAuMSIvPjwvc3ZnPg==')]"></div>

            <div class="mb-8 flex justify-center">
                <img
                    src="https://pmms.uplinkph.net/uisi_pmms/assets/images/UPLINK%20LOGO.png"
                    alt="Uplink Integrated Solutions Inc."
                    class="w-48 h-auto object-contain">
            </div>

            <h1 class="text-3xl font-bold text-munti-yellow-500 text-center leading-tight drop-shadow-sm uppercase tracking-wide">
                {{ config('app.system_name', 'Environmental Monitoring System Gateway') }}
            </h1>

            <p class="mt-6 text-text-300 text-lg text-center font-light tracking-wide">
                {{ config('app.company_name', 'Uplink Integrated Solutions Inc.') }}
            </p>

            <div class="mt-16 text-text-400 text-sm bg-white/5 px-4 py-1 rounded-full border border-white/10">
                Beta Version 1.0
            </div>

        </div>

        <!-- RIGHT PANEL -->
        <div class="lg:w-1/2 bg-surface-800 flex items-center justify-center px-10 py-12">

            <div class="w-full max-w-md">

                {{-- Title --}}
                <h2 class="text-3xl font-bold text-text-100 text-center mb-1"
                    x-text="mode === 'login' ? 'Welcome Back' : 'Create Account'">
                </h2>
                <p class="text-text-400 text-center mb-6 text-sm"
                   x-text="mode === 'login' ? 'Sign in to your account' : 'Register a new account'">
                </p>

                {{-- Error --}}
                @if ($errors->any())
                    <div class="mb-4 rounded-xl border border-munti-red-600 bg-munti-red-700/20 px-4 py-2.5 text-munti-red-400 flex items-center gap-3 text-sm">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 shrink-0" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                        </svg>
                        {{ $errors->first() }}
                    </div>
                @endif

                {{-- ==================== LOGIN FORM ==================== --}}
                <form method="POST" action="{{ route('login') }}"
                      class="space-y-5"
                      x-show="mode === 'login'"
                      x-cloak>
                    @csrf

                    <div>
                        <label class="block text-sm font-medium text-text-300 mb-1.5">Username</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-text-500" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd" />
                                </svg>
                            </div>
                            <input
                                type="text"
                                name="username"
                                value="{{ old('username') }}"
                                required
                                class="w-full rounded-xl bg-background-900 border border-border-700
                                       pl-10 pr-4 py-2.5 text-text-100 placeholder-text-500
                                       focus:border-radar-400 focus:ring-2 focus:ring-radar-400/50 outline-none transition"
                                placeholder="Enter your username">
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-text-300 mb-1.5">Password</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-text-500" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd" />
                                </svg>
                            </div>
                            <input
                                type="password"
                                name="password"
                                required
                                class="w-full rounded-xl bg-background-900 border border-border-700
                                       pl-10 pr-4 py-2.5 text-text-100 placeholder-text-500
                                       focus:border-radar-400 focus:ring-2 focus:ring-radar-400/50 outline-none transition"
                                placeholder="••••••••">
                        </div>
                    </div>

                    <button
                        type="submit"
                        class="w-full rounded-xl bg-munti-yellow-500 hover:bg-munti-yellow-600
                               text-black font-bold py-3 transition duration-200 transform hover:scale-[1.02] active:scale-[0.98]
                               flex items-center justify-center gap-2 shadow-lg shadow-munti-yellow-500/20">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-8.707l-3-3a1 1 0 00-1.414 1.414L10.586 9H7a1 1 0 100 2h3.586l-1.293 1.293a1 1 0 101.414 1.414l3-3a1 1 0 000-1.414z" clip-rule="evenodd" />
                        </svg>
                        LOGIN
                    </button>
                </form>

                {{-- ==================== REGISTER FORM (COMPRESSED) ==================== --}}
                <form method="POST" action="{{ route('register') }}"
                      class="space-y-3.5"
                      x-show="mode === 'register'"
                      x-cloak>
                    @csrf

                    {{-- Username --}}
                    <div>
                        <label class="block text-sm font-medium text-text-300 mb-1">Username</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-text-500" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd" />
                                </svg>
                            </div>
                            <input
                                type="text"
                                name="username"
                                value="{{ old('username') }}"
                                required
                                class="w-full rounded-xl bg-background-900 border border-border-700
                                       pl-9 pr-4 py-2 text-text-100 placeholder-text-500 text-sm
                                       focus:border-radar-400 focus:ring-2 focus:ring-radar-400/50 outline-none transition"
                                placeholder="Choose a username">
                        </div>
                    </div>

                    {{-- Email --}}
                    <div>
                        <label class="block text-sm font-medium text-text-300 mb-1">Email</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-text-500" viewBox="0 0 20 20" fill="currentColor">
                                    <path d="M2.003 5.884L10 9.882l7.997-3.998A2 2 0 0016 4H4a2 2 0 00-1.997 1.884z" />
                                    <path d="M18 8.118l-8 4-8-4V14a2 2 0 002 2h12a2 2 0 002-2V8.118z" />
                                </svg>
                            </div>
                            <input
                                type="email"
                                name="email"
                                value="{{ old('email') }}"
                                required
                                class="w-full rounded-xl bg-background-900 border border-border-700
                                       pl-9 pr-4 py-2 text-text-100 placeholder-text-500 text-sm
                                       focus:border-radar-400 focus:ring-2 focus:ring-radar-400/50 outline-none transition"
                                placeholder="Enter your email">
                        </div>
                    </div>

                    {{-- Password + Confirm Password (side by side) --}}
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-sm font-medium text-text-300 mb-1">Password</label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-2.5 flex items-center pointer-events-none">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-text-500" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd" />
                                    </svg>
                                </div>
                                <input
                                    type="password"
                                    name="password"
                                    required
                                    class="w-full rounded-xl bg-background-900 border border-border-700
                                           pl-8 pr-3 py-2 text-text-100 placeholder-text-500 text-sm
                                           focus:border-radar-400 focus:ring-2 focus:ring-radar-400/50 outline-none transition"
                                    placeholder="••••••••">
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-text-300 mb-1">Confirm</label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-2.5 flex items-center pointer-events-none">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-text-500" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd" />
                                    </svg>
                                </div>
                                <input
                                    type="password"
                                    name="password_confirmation"
                                    required
                                    class="w-full rounded-xl bg-background-900 border border-border-700
                                           pl-8 pr-3 py-2 text-text-100 placeholder-text-500 text-sm
                                           focus:border-radar-400 focus:ring-2 focus:ring-radar-400/50 outline-none transition"
                                    placeholder="••••••••">
                            </div>
                        </div>
                    </div>

                    <button
                        type="submit"
                        class="w-full rounded-xl bg-munti-yellow-500 hover:bg-munti-yellow-600
                               text-black font-bold py-2.5 transition duration-200 transform hover:scale-[1.02] active:scale-[0.98]
                               flex items-center justify-center gap-2 shadow-lg shadow-munti-yellow-500/20 text-sm">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd" />
                        </svg>
                        REGISTER
                    </button>
                </form>

                {{-- Toggle link --}}
                <div class="mt-6 text-center text-sm text-text-400 border-t border-border-700 pt-5">
                    <template x-if="mode === 'login'">
                        <span>
                            Don't have an account?
                            <button type="button"
                                    @click="mode = 'register'"
                                    class="text-munti-yellow-500 hover:text-munti-yellow-400 transition font-medium">
                                Register
                            </button>
                        </span>
                    </template>

                    <template x-if="mode === 'register'">
                        <span>
                            Already have an account?
                            <button type="button"
                                    @click="mode = 'login'"
                                    class="text-munti-yellow-500 hover:text-munti-yellow-400 transition font-medium">
                                Sign in
                            </button>
                        </span>
                    </template>
                </div>

                {{-- Version info --}}
                <div class="mt-4 text-center text-xs text-text-500">
                    &copy; {{ date('Y') }} Uplink Integrated Solutions Inc.
                </div>

            </div>
        </div>
    </div>
</div>

@include('layouts.footer')
