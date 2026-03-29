<div
    x-data="{ showUpload: false }"
    @close-uploader.window="showUpload = false; $wire.$refresh()"
    class="max-w-6xl mx-auto py-8 px-4 sm:px-6 lg:px-8"
>

    {{-- ── Upload modal ──────────────────────────────────────────────────── --}}
    <div
        x-show="showUpload"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="fixed inset-0 z-50 overflow-y-auto"
        @keydown.escape.window="showUpload = false"
        style="display: none;"
    >
        <div class="fixed inset-0 bg-black/60 backdrop-blur-sm" @click="showUpload = false"></div>
        <div class="relative flex min-h-full items-center justify-center p-4">
            <div class="relative w-full max-w-lg bg-white dark:bg-gray-900 rounded-2xl shadow-2xl border border-gray-200 dark:border-gray-700 overflow-hidden" @click.stop>
                <button
                    @click="showUpload = false"
                    class="absolute top-3 right-3 z-10 p-1.5 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-800 rounded-lg transition"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
                @livewire('media-uploader', ['embedded' => true])
            </div>
        </div>
    </div>

    {{-- ── Header ────────────────────────────────────────────────────────── --}}
    <div class="flex items-center justify-between mb-8">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Media Library</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">Your uploaded images and their processing status</p>
        </div>
        <button
            @click="showUpload = true"
            class="inline-flex items-center gap-2 px-4 py-2.5 bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-semibold rounded-xl shadow-sm transition"
        >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5"/>
            </svg>
            Upload Image
        </button>
    </div>

    {{-- ── Filters ───────────────────────────────────────────────────────── --}}
    <div class="flex flex-wrap items-center justify-between gap-3 mb-6">

        {{-- Status filter tabs --}}
        <div class="flex items-center gap-1 bg-gray-100 dark:bg-gray-800 rounded-xl p-1">
            @foreach (['all' => 'All', 'pending' => 'Pending', 'processing' => 'Processing', 'completed' => 'Completed', 'failed' => 'Failed'] as $value => $label)
                <button
                    wire:click="$set('statusFilter', '{{ $value }}')"
                    @class([
                        'px-3 py-1.5 text-xs font-medium rounded-lg transition',
                        'bg-white dark:bg-gray-700 text-gray-900 dark:text-white shadow-sm' => $statusFilter === $value,
                        'text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200' => $statusFilter !== $value,
                    ])
                >
                    {{ $label }}
                </button>
            @endforeach
        </div>

        {{-- Sort toggle --}}
        <button
            wire:click="toggleSort"
            class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium
                   text-gray-600 dark:text-gray-400
                   bg-white dark:bg-gray-800
                   border border-gray-200 dark:border-gray-700
                   rounded-xl hover:bg-gray-50 dark:hover:bg-gray-700 transition"
        >
            @if ($sortOrder === 'desc')
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4h13M3 8h9m-9 4h6m4 0l4-4m0 0l4 4m-4-4v12"/>
                </svg>
                Newest first
            @else
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4h13M3 8h9m-9 4h9m5-4v12m0 0l-4-4m4 4l4-4"/>
                </svg>
                Oldest first
            @endif
        </button>
    </div>

    {{-- ── Empty state ───────────────────────────────────────────────────── --}}
    @if ($grouped->isEmpty())
        <div class="text-center py-24">
            <div class="w-16 h-16 mx-auto mb-4 rounded-2xl bg-gray-100 dark:bg-gray-800 flex items-center justify-center">
                <svg class="w-8 h-8 text-gray-300 dark:text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                        d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14M14 8h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                </svg>
            </div>
            @if ($statusFilter === 'all')
                <p class="text-base font-semibold text-gray-700 dark:text-gray-300">No images yet</p>
                <p class="text-sm text-gray-400 dark:text-gray-500 mt-1 mb-4">Upload your first image to get started</p>
                <button @click="showUpload = true" class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-medium rounded-xl transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    Upload now
                </button>
            @else
                <p class="text-base font-semibold text-gray-700 dark:text-gray-300">No {{ $statusFilter }} images</p>
                <button wire:click="$set('statusFilter', 'all')" class="mt-3 text-sm text-indigo-500 hover:text-indigo-400 font-medium">
                    Show all &rarr;
                </button>
            @endif
        </div>

    @else

        {{-- ── Date-grouped grid ────────────────────────────────────────── --}}
        @foreach ($grouped as $bucket => $items)

            <div class="flex items-center gap-3 mb-4 {{ ! $loop->first ? 'mt-10' : '' }}">
                <h2 class="text-xs font-semibold uppercase tracking-wider text-gray-400 dark:text-gray-500 whitespace-nowrap">{{ $bucket }}</h2>
                <div class="flex-1 border-t border-gray-200 dark:border-gray-800"></div>
                <span class="text-xs text-gray-300 dark:text-gray-600">{{ $items->count() }} {{ Str::plural('item', $items->count()) }}</span>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                @foreach ($items as $media)
                    @php
                        $live     = $liveUpdates[$media->uuid] ?? null;
                        $status   = $live['status']   ?? $media->status;
                        $progress = $live['progress'] ?? $media->progress ?? 0;
                        $step     = $live['step']     ?? $media->processing_step;
                        $error    = $live['error']    ?? $media->error_message;

                        $badgeClasses = [
                            'pending'    => 'bg-amber-50 dark:bg-amber-500/10 text-amber-700 dark:text-amber-400 ring-1 ring-amber-200 dark:ring-amber-500/20',
                            'processing' => 'bg-blue-50 dark:bg-blue-500/10 text-blue-700 dark:text-blue-400 ring-1 ring-blue-200 dark:ring-blue-500/20',
                            'completed'  => 'bg-emerald-50 dark:bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 ring-1 ring-emerald-200 dark:ring-emerald-500/20',
                            'failed'     => 'bg-red-50 dark:bg-red-500/10 text-red-700 dark:text-red-400 ring-1 ring-red-200 dark:ring-red-500/20',
                        ];
                    @endphp

                    <div
                        wire:key="{{ $media->uuid }}"
                        x-data
                        x-init="
                            if (window.Echo) {
                                window.Echo.private('media.{{ $media->uuid }}')
                                    .listen('.media.processing.started', () =>
                                        $wire.handleMediaUpdate('{{ $media->uuid }}', 'processing', 0, 'starting', null))
                                    .listen('.media.step.completed', (e) =>
                                        $wire.handleMediaUpdate('{{ $media->uuid }}', 'processing', e.progress, e.step, null))
                                    .listen('.media.processing.completed', () =>
                                        $wire.handleMediaUpdate('{{ $media->uuid }}', 'completed', 100, null, null))
                                    .listen('.media.processing.failed', (e) =>
                                        $wire.handleMediaUpdate('{{ $media->uuid }}', 'failed', 0, e.step, e.error));
                            }
                        "
                        class="group bg-white dark:bg-gray-800/60 rounded-2xl border border-gray-200 dark:border-gray-700 overflow-hidden flex flex-col shadow-sm hover:shadow-md dark:hover:border-gray-600 transition"
                    >
                        {{-- Thumbnail / placeholder --}}
                        @if ($status === 'completed' && !empty($media->outputs['thumbnail']))
                            <div class="border-b border-gray-100 dark:border-gray-700">
                                <img
                                    src="{{ route('media.thumbnail', $media->uuid) }}"
                                    alt="{{ $media->original_filename }}"
                                    class="w-full h-40 object-cover"
                                >
                            </div>
                        @else
                            <div class="w-full h-40 bg-gray-50 dark:bg-gray-800 border-b border-gray-100 dark:border-gray-700 flex items-center justify-center">
                                @if ($status === 'processing')
                                    <svg class="animate-spin w-6 h-6 text-indigo-400" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                                    </svg>
                                @elseif ($status === 'pending')
                                    <svg class="w-6 h-6 text-gray-300 dark:text-gray-600 animate-pulse" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                            d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14M14 8h.01"/>
                                    </svg>
                                @elseif ($status === 'failed')
                                    <svg class="w-6 h-6 text-red-300 dark:text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                            d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                                    </svg>
                                @endif
                            </div>
                        @endif

                        <div class="p-4 flex flex-col gap-2.5 flex-1">

                            {{-- Filename + size --}}
                            <div>
                                <p class="text-sm font-semibold text-gray-800 dark:text-white truncate" title="{{ $media->original_filename }}">
                                    {{ $media->original_filename }}
                                </p>
                                <p class="text-xs text-gray-400 dark:text-gray-500 mt-0.5">
                                    {{ round($media->file_size / 1024) }} KB &middot; {{ $media->created_at->diffForHumans() }}
                                </p>
                            </div>

                            {{-- Status badge --}}
                            <div class="flex items-center gap-2">
                                <span class="px-2 py-0.5 text-xs font-medium rounded-full {{ $badgeClasses[$status] ?? 'bg-gray-100 text-gray-500' }}">
                                    {{ ucfirst($status) }}
                                </span>
                                @if ($status === 'processing' && $step)
                                    <span class="text-xs text-gray-400 dark:text-gray-500 capitalize">{{ $step }}</span>
                                @endif
                            </div>

                            {{-- Progress bar --}}
                            @if ($status === 'processing')
                                <div class="w-full bg-gray-100 dark:bg-gray-700 rounded-full h-1.5 overflow-hidden">
                                    <div
                                        class="bg-indigo-500 h-1.5 rounded-full transition-all duration-500"
                                        style="width: {{ $progress }}%"
                                    ></div>
                                </div>
                            @endif

                            {{-- Error + retry --}}
                            @if ($status === 'failed')
                                @if ($error)
                                    <p class="text-xs text-red-500 dark:text-red-400 truncate" title="{{ $error }}">{{ $error }}</p>
                                @endif
                                <button
                                    wire:click="retryMedia('{{ $media->uuid }}')"
                                    wire:loading.attr="disabled"
                                    wire:target="retryMedia('{{ $media->uuid }}')"
                                    class="mt-auto w-full text-xs font-medium
                                           text-amber-700 dark:text-amber-400
                                           bg-amber-50 dark:bg-amber-500/10
                                           hover:bg-amber-100 dark:hover:bg-amber-500/20
                                           border border-amber-200 dark:border-amber-500/20
                                           rounded-xl py-2 transition"
                                >
                                    <span wire:loading wire:target="retryMedia('{{ $media->uuid }}')">Re-queuing&hellip;</span>
                                    <span wire:loading.remove wire:target="retryMedia('{{ $media->uuid }}')">Retry</span>
                                </button>
                            @endif

                        </div>
                    </div>
                @endforeach
            </div>

        @endforeach

    @endif

    {{-- ── Pagination ────────────────────────────────────────────────────── --}}
    @if ($mediaItems->hasPages())
        <div class="mt-8">
            {{ $mediaItems->links() }}
        </div>
    @endif

</div>
