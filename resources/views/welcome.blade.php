<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'Media App') }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700,800&display=swap" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        .gradient-hero {
            background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 50%, #312e81 100%);
        }
        .gradient-text {
            background: linear-gradient(90deg, #a5b4fc, #e879f9);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        .step-glow {
            box-shadow: 0 0 0 1px rgba(165,180,252,0.15), 0 8px 32px rgba(99,102,241,0.15);
        }
        .card-hover {
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .card-hover:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 40px rgba(99,102,241,0.18);
        }
        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-6px); }
        }
        .float { animation: float 4s ease-in-out infinite; }
        .float-delay { animation: float 4s ease-in-out 1.5s infinite; }
    </style>
</head>
<body class="font-sans antialiased bg-gray-950 text-white">

    @auth
        <script>window.location = "{{ route('dashboard') }}";</script>
    @endauth

    {{-- ─── NAV ─────────────────────────────────────────────── --}}
    <header class="absolute top-0 inset-x-0 z-10">
        <div class="max-w-6xl mx-auto px-6 py-5 flex items-center justify-between">
            <div class="flex items-center gap-2.5">
                <div class="w-7 h-7 bg-indigo-500 rounded-md flex items-center justify-center">
                    <svg class="w-4 h-4 text-white" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909M3 21h18M3.75 3h16.5M4.5 3v18M19.5 3v18" />
                    </svg>
                </div>
                <span class="font-semibold text-white text-sm tracking-tight">Media App</span>
            </div>
            <div class="flex items-center gap-3">
                <a href="{{ route('login') }}" class="text-sm text-gray-300 hover:text-white transition">Log in</a>
                <a href="{{ route('register') }}" class="text-sm bg-indigo-600 hover:bg-indigo-500 text-white px-4 py-2 rounded-lg font-medium transition">
                    Get started
                </a>
            </div>
        </div>
    </header>

    {{-- ─── HERO ────────────────────────────────────────────── --}}
    <section class="gradient-hero min-h-screen flex items-center relative overflow-hidden">

        {{-- Background decoration --}}
        <div class="absolute inset-0 overflow-hidden pointer-events-none">
            <div class="absolute -top-40 -right-40 w-96 h-96 bg-indigo-600 rounded-full opacity-10 blur-3xl"></div>
            <div class="absolute -bottom-40 -left-20 w-80 h-80 bg-purple-600 rounded-full opacity-10 blur-3xl"></div>
            <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[600px] h-[600px] bg-indigo-900 rounded-full opacity-20 blur-3xl"></div>
        </div>

        <div class="relative max-w-6xl mx-auto px-6 py-32 w-full">
            <div class="grid lg:grid-cols-2 gap-16 items-center">

                {{-- Left: copy --}}
                <div>
                    <div class="inline-flex items-center gap-2 bg-white/10 backdrop-blur-sm border border-white/10 rounded-full px-4 py-1.5 mb-6">
                        <span class="w-2 h-2 bg-green-400 rounded-full"></span>
                        <span class="text-xs text-gray-300 font-medium">Instant results, no waiting</span>
                    </div>

                    <h1 class="text-5xl lg:text-6xl font-extrabold leading-tight mb-6 tracking-tight">
                        Upload an image.<br>
                        <span class="gradient-text">We handle the rest.</span>
                    </h1>

                    <p class="text-lg text-gray-300 leading-relaxed mb-8 max-w-md">
                        Drop in your photo and get back a resized version, a thumbnail, and an optimised copy — all done automatically while you watch the progress live.
                    </p>

                    <div class="flex flex-wrap gap-3">
                        <a href="{{ route('register') }}"
                           class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-500 text-white font-semibold px-6 py-3 rounded-xl transition text-sm">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5" />
                            </svg>
                            Start uploading
                        </a>
                        <a href="{{ route('login') }}"
                           class="inline-flex items-center gap-2 bg-white/10 hover:bg-white/20 backdrop-blur-sm border border-white/10 text-white font-medium px-6 py-3 rounded-xl transition text-sm">
                            Log in
                        </a>
                    </div>
                </div>

                {{-- Right: visual pipeline --}}
                <div class="hidden lg:block">
                    <div class="relative flex flex-col gap-3">

                        {{-- Step 1: upload --}}
                        <div class="float step-glow bg-white/5 backdrop-blur-sm border border-white/10 rounded-2xl p-5 flex items-center gap-4">
                            <div class="w-10 h-10 rounded-xl bg-indigo-500/20 border border-indigo-400/30 flex items-center justify-center flex-shrink-0">
                                <svg class="w-5 h-5 text-indigo-300" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5" />
                                </svg>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-semibold text-white">photo-holiday.jpg</p>
                                <p class="text-xs text-gray-400 mt-0.5">5.2 MB · JPEG · uploading…</p>
                            </div>
                            <div class="w-2 h-2 bg-indigo-400 rounded-full"></div>
                        </div>

                        {{-- Connector --}}
                        <div class="flex justify-center">
                            <div class="w-px h-4 bg-gradient-to-b from-indigo-500/40 to-purple-500/40"></div>
                        </div>

                        {{-- Step 2: processing --}}
                        <div class="float-delay step-glow bg-white/5 backdrop-blur-sm border border-white/10 rounded-2xl p-5">
                            <div class="flex items-center gap-4 mb-3">
                                <div class="w-10 h-10 rounded-xl bg-purple-500/20 border border-purple-400/30 flex items-center justify-center flex-shrink-0">
                                    <svg class="w-5 h-5 text-purple-300" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.325.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 0 1 1.37.49l1.296 2.247a1.125 1.125 0 0 1-.26 1.431l-1.003.827c-.293.241-.438.613-.43.992a7.723 7.723 0 0 1 0 .255c-.008.378.137.75.43.991l1.004.827c.424.35.534.955.26 1.43l-1.298 2.247a1.125 1.125 0 0 1-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.47 6.47 0 0 1-.22.128c-.331.183-.581.495-.644.869l-.213 1.281c-.09.543-.56.94-1.11.94h-2.594c-.55 0-1.019-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 0 1-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 0 1-1.369-.49l-1.297-2.247a1.125 1.125 0 0 1 .26-1.431l1.004-.827c.292-.24.437-.613.43-.991a6.932 6.932 0 0 1 0-.255c.007-.38-.138-.751-.43-.992l-1.004-.827a1.125 1.125 0 0 1-.26-1.43l1.297-2.247a1.125 1.125 0 0 1 1.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.086.22-.128.332-.183.582-.495.644-.869l.214-1.28Z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                    </svg>
                                </div>
                                <div>
                                    <p class="text-sm font-semibold text-white">Processing</p>
                                    <p class="text-xs text-gray-400">Generating outputs…</p>
                                </div>
                            </div>
                            <div class="space-y-2">
                                @foreach (['Resizing to web dimensions', 'Creating thumbnail', 'Optimising file size'] as $i => $step)
                                <div class="flex items-center gap-2.5">
                                    <div @class([
                                        'w-1.5 h-1.5 rounded-full flex-shrink-0',
                                        'bg-green-400' => $i < 2,
                                        'bg-purple-400 animate-pulse' => $i === 2,
                                    ])></div>
                                    <p class="text-xs text-gray-300">{{ $step }}</p>
                                </div>
                                @endforeach
                            </div>
                        </div>

                        {{-- Connector --}}
                        <div class="flex justify-center">
                            <div class="w-px h-4 bg-gradient-to-b from-purple-500/40 to-green-500/40"></div>
                        </div>

                        {{-- Step 3: done --}}
                        <div class="step-glow bg-white/5 backdrop-blur-sm border border-white/10 rounded-2xl p-5 flex items-center gap-4">
                            <div class="w-10 h-10 rounded-xl bg-green-500/20 border border-green-400/30 flex items-center justify-center flex-shrink-0">
                                <svg class="w-5 h-5 text-green-300" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                </svg>
                            </div>
                            <div class="flex-1">
                                <p class="text-sm font-semibold text-white">3 files ready</p>
                                <p class="text-xs text-gray-400">Resized · Thumbnail · Optimised</p>
                            </div>
                            <span class="text-xs font-medium bg-green-500/20 text-green-300 border border-green-500/20 px-2.5 py-1 rounded-full">Done</span>
                        </div>

                    </div>
                </div>

            </div>
        </div>
    </section>

    {{-- ─── HOW IT WORKS ────────────────────────────────────── --}}
    <section class="bg-gray-950 py-24 border-t border-white/5">
        <div class="max-w-6xl mx-auto px-6">

            <div class="text-center mb-16">
                <p class="text-indigo-400 text-sm font-semibold uppercase tracking-widest mb-3">How it works</p>
                <h2 class="text-3xl font-bold text-white">Three steps, zero effort</h2>
            </div>

            <div class="grid sm:grid-cols-3 gap-6 relative">

                {{-- connector line (desktop) --}}
                <div class="hidden sm:block absolute top-10 left-[calc(16.67%+1.5rem)] right-[calc(16.67%+1.5rem)] h-px bg-gradient-to-r from-indigo-500/30 via-purple-500/30 to-green-500/30"></div>

                @foreach ([
                    [
                        'number' => '01',
                        'color'  => 'indigo',
                        'icon'   => '<path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5" />',
                        'title'  => 'Choose your image',
                        'desc'   => 'Pick any photo from your device. JPEG, PNG, GIF, or WebP — up to 10 MB.',
                    ],
                    [
                        'number' => '02',
                        'color'  => 'purple',
                        'icon'   => '<path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09Z" />',
                        'title'  => 'We process it automatically',
                        'desc'   => 'As soon as you upload, the app gets to work — no button to click, nothing to wait for.',
                    ],
                    [
                        'number' => '03',
                        'color'  => 'green',
                        'icon'   => '<path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909M3 21h18M3.75 3h16.5M4.5 3v18M19.5 3v18" />',
                        'title'  => 'See your results live',
                        'desc'   => 'The page updates in real time as each output completes. No refreshing required.',
                    ],
                ] as $step)
                <div class="relative flex flex-col items-center text-center card-hover bg-white/[0.03] border border-white/5 rounded-2xl p-8">
                    <div @class([
                        'w-14 h-14 rounded-2xl flex items-center justify-center mb-6 relative z-10',
                        'bg-indigo-500/15 border border-indigo-500/20' => $step['color'] === 'indigo',
                        'bg-purple-500/15 border border-purple-500/20' => $step['color'] === 'purple',
                        'bg-green-500/15 border border-green-500/20'   => $step['color'] === 'green',
                    ])>
                        <svg @class([
                            'w-6 h-6',
                            'text-indigo-400' => $step['color'] === 'indigo',
                            'text-purple-400' => $step['color'] === 'purple',
                            'text-green-400'  => $step['color'] === 'green',
                        ]) fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                            {!! $step['icon'] !!}
                        </svg>
                    </div>
                    <p @class([
                        'text-xs font-bold tracking-widest uppercase mb-2',
                        'text-indigo-400' => $step['color'] === 'indigo',
                        'text-purple-400' => $step['color'] === 'purple',
                        'text-green-400'  => $step['color'] === 'green',
                    ])>{{ $step['number'] }}</p>
                    <h3 class="text-base font-semibold text-white mb-2">{{ $step['title'] }}</h3>
                    <p class="text-sm text-gray-400 leading-relaxed">{{ $step['desc'] }}</p>
                </div>
                @endforeach

            </div>
        </div>
    </section>

    {{-- ─── FEATURES ────────────────────────────────────────── --}}
    <section class="bg-gray-900/50 py-24 border-t border-white/5">
        <div class="max-w-6xl mx-auto px-6">

            <div class="text-center mb-16">
                <p class="text-purple-400 text-sm font-semibold uppercase tracking-widest mb-3">What you get</p>
                <h2 class="text-3xl font-bold text-white">Everything handled for you</h2>
            </div>

            <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-5">
                @foreach ([
                    [
                        'icon' => '<path stroke-linecap="round" stroke-linejoin="round" d="M3.75 3.75v4.5m0-4.5h4.5m-4.5 0L9 9M3.75 20.25v-4.5m0 4.5h4.5m-4.5 0L9 15M20.25 3.75h-4.5m4.5 0v4.5m0-4.5L15 9m5.25 11.25h-4.5m4.5 0v-4.5m0 4.5L15 15" />',
                        'title' => 'Resized for the web',
                        'desc'  => 'Your image is automatically scaled down to sensible web dimensions without losing quality.',
                    ],
                    [
                        'icon' => '<path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909M3 21h18M3.75 3h16.5M4.5 3v18M19.5 3v18" />',
                        'title' => 'Thumbnail generated',
                        'desc'  => 'A small preview image is created for fast loading anywhere you need a compact view.',
                    ],
                    [
                        'icon' => '<path stroke-linecap="round" stroke-linejoin="round" d="M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z" />',
                        'title' => 'File size reduced',
                        'desc'  => 'The final image is compressed so it loads faster and takes up less storage — no visible difference.',
                    ],
                    [
                        'icon' => '<path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />',
                        'title' => 'Live progress updates',
                        'desc'  => 'Watch each step complete in real time directly on the page — no refreshing, no guessing.',
                    ],
                    [
                        'icon' => '<path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z" />',
                        'title' => 'Only your images, always',
                        'desc'  => 'Your uploads are private to your account. Nobody else can see or access your files.',
                    ],
                    [
                        'icon' => '<path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" />',
                        'title' => 'Automatic retry on failure',
                        'desc'  => 'If something goes wrong, the app retries automatically. You can also trigger a retry yourself.',
                    ],
                ] as $feature)
                <div class="card-hover bg-white/[0.03] border border-white/5 rounded-2xl p-6">
                    <div class="w-10 h-10 rounded-xl bg-white/5 border border-white/10 flex items-center justify-center mb-4">
                        <svg class="w-5 h-5 text-indigo-300" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                            {!! $feature['icon'] !!}
                        </svg>
                    </div>
                    <h3 class="text-sm font-semibold text-white mb-1.5">{{ $feature['title'] }}</h3>
                    <p class="text-sm text-gray-400 leading-relaxed">{{ $feature['desc'] }}</p>
                </div>
                @endforeach
            </div>

        </div>
    </section>

    {{-- ─── BOTTOM CTA ──────────────────────────────────────── --}}
    <section class="bg-gray-950 py-24 border-t border-white/5">
        <div class="max-w-xl mx-auto px-6 text-center">
            <h2 class="text-3xl font-bold text-white mb-4">Ready to try it?</h2>
            <p class="text-gray-400 mb-8">Create a free account and upload your first image in under a minute.</p>
            <a href="{{ route('register') }}"
               class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-500 text-white font-semibold px-8 py-3.5 rounded-xl transition text-sm">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5" />
                </svg>
                Get started free
            </a>
        </div>
    </section>

    {{-- ─── FOOTER ──────────────────────────────────────────── --}}
    <footer class="bg-gray-950 border-t border-white/5 py-8">
        <div class="max-w-6xl mx-auto px-6 flex flex-col sm:flex-row items-center justify-between gap-4">
            <div class="flex items-center gap-2">
                <div class="w-5 h-5 bg-indigo-500 rounded flex items-center justify-center">
                    <svg class="w-3 h-3 text-white" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909M3 21h18M3.75 3h16.5M4.5 3v18M19.5 3v18" />
                    </svg>
                </div>
                <span class="text-xs text-gray-500 font-medium">Media App</span>
            </div>
            <p class="text-xs text-gray-600">Built with Laravel, Livewire, and Tailwind CSS</p>
        </div>
    </footer>

</body>
</html>
