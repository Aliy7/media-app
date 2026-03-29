<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'Media App') }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans antialiased bg-gray-100 text-gray-900">

    @auth
        <script>window.location = "{{ route('dashboard') }}";</script>
    @endauth

    <div class="min-h-screen flex flex-col justify-center items-center px-4 py-12">

        {{-- Logo --}}
        <div class="mb-6">
            <x-application-logo class="w-16 h-16 fill-current text-gray-500" />
        </div>

        {{-- Heading --}}
        <h1 class="text-3xl font-semibold text-gray-800 mb-2">Media App</h1>
        <p class="text-gray-500 text-center max-w-sm mb-10">
            Upload images and watch them process in real time — resize, thumbnail, and optimise through a background queue.
        </p>

        {{-- Feature list --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 max-w-lg w-full mb-10">
            @foreach ([
                ['Queue processing', 'Jobs run in the background via Redis and Horizon'],
                ['Real-time updates', 'WebSocket events update the UI without a page reload'],
                ['Queue priorities', 'Three named queues: critical, standard, and low'],
                ['Failure handling', 'Failed jobs surface in Horizon with retry support'],
            ] as [$title, $desc])
            <div class="bg-white rounded-lg px-5 py-4 shadow-sm">
                <p class="text-sm font-medium text-gray-700">{{ $title }}</p>
                <p class="text-xs text-gray-400 mt-0.5">{{ $desc }}</p>
            </div>
            @endforeach
        </div>

        {{-- CTAs --}}
        <div class="flex gap-3">
            <a href="{{ route('login') }}"
               class="px-5 py-2.5 bg-gray-800 text-white text-sm font-medium rounded-md hover:bg-gray-700 transition">
                Log in
            </a>
            <a href="{{ route('register') }}"
               class="px-5 py-2.5 bg-white border border-gray-300 text-gray-700 text-sm font-medium rounded-md hover:bg-gray-50 transition">
                Register
            </a>
        </div>

    </div>

</body>
</html>
