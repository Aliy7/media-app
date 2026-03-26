<div class="max-w-5xl mx-auto py-8 px-4">

    {{-- ── Header ─────────────────────────────────────────────────────────── --}}
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-semibold text-gray-900">Media Library</h1>
        <a
            href="{{ route('media.upload') }}"
            class="inline-flex items-center gap-1.5 px-4 py-2 bg-emerald-600 text-white text-sm font-medium rounded-lg shadow-sm hover:bg-emerald-700 transition"
        >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            Upload Image
        </a>
    </div>

    {{-- ── Filter tabs + sort control ─────────────────────────────────────── --}}
    <div class="flex flex-wrap items-center justify-between gap-3 mb-6">

        {{-- Status filter tabs --}}
        <div class="flex items-center gap-1 bg-gray-100 rounded-lg p-1">
            @foreach (['all' => 'All', 'pending' => 'Pending', 'processing' => 'Processing', 'completed' => 'Completed', 'failed' => 'Failed'] as $value => $label)
                <button
                    wire:click="$set('statusFilter', '{{ $value }}')"
                    class="px-3 py-1 text-xs font-medium rounded-md transition
                        {{ $statusFilter === $value
                            ? 'bg-white text-gray-900 shadow-sm'
                            : 'text-gray-500 hover:text-gray-700' }}"
                >
                    {{ $label }}
                </button>
            @endforeach
        </div>

        {{-- Sort toggle --}}
        <button
            wire:click="toggleSort"
            class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-gray-600 bg-white border border-gray-200 rounded-lg hover:bg-gray-50 transition"
            title="Toggle sort order"
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

    {{-- ── Empty state ─────────────────────────────────────────────────────── --}}
    @if ($grouped->isEmpty())
        <div class="text-center py-20 text-gray-400">
            <svg class="mx-auto w-12 h-12 mb-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                    d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14M14 8h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
            </svg>
            @if ($statusFilter === 'all')
                <p class="text-base font-medium text-gray-500">No images yet</p>
                <a href="{{ route('media.upload') }}" class="mt-2 inline-block text-sm text-emerald-600 hover:underline">
                    Upload your first image
                </a>
            @else
                <p class="text-base font-medium text-gray-500">No {{ $statusFilter }} images</p>
                <button wire:click="$set('statusFilter', 'all')" class="mt-2 text-sm text-emerald-600 hover:underline">
                    Show all
                </button>
            @endif
        </div>

    @else

        {{-- ── Date-grouped grid ───────────────────────────────────────────── --}}
        @foreach ($grouped as $bucket => $items)

            {{-- Group header --}}
            <div class="flex items-center gap-3 mb-3 {{ ! $loop->first ? 'mt-8' : '' }}">
                <h2 class="text-xs font-semibold uppercase tracking-wider text-gray-400">{{ $bucket }}</h2>
                <div class="flex-1 border-t border-gray-100"></div>
                <span class="text-xs text-gray-300">{{ $items->count() }} {{ Str::plural('item', $items->count()) }}</span>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                @foreach ($items as $media)
                    @php
                        // Merge in-memory broadcast updates with DB record for instant reactivity.
                        $live     = $liveUpdates[$media->uuid] ?? null;
                        $status   = $live['status']   ?? $media->status;
                        $progress = $live['progress'] ?? $media->progress ?? 0;
                        $step     = $live['step']     ?? $media->processing_step;
                        $error    = $live['error']    ?? $media->error_message;

                        $statusClasses = [
                            'pending'    => 'bg-amber-50 text-amber-700 ring-1 ring-amber-200',
                            'processing' => 'bg-blue-50 text-blue-700 ring-1 ring-blue-200',
                            'completed'  => 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200',
                            'failed'     => 'bg-red-50 text-red-700 ring-1 ring-red-200',
                        ];
                    @endphp

                    {{--
                        Alpine subscribes to the media's private channel via Echo.
                        $wire.handleMediaUpdate() triggers a server round-trip so the
                        DB is re-queried and the card re-renders with fresh data.
                    --}}
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
                        class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden flex flex-col"
                    >
                        {{-- Thumbnail (completed items only) --}}
                        @if ($status === 'completed' && !empty($media->outputs['thumbnail']))
                            <div class="border-b border-gray-100">
                                <img
                                    src="{{ route('media.thumbnail', $media->uuid) }}"
                                    alt="{{ $media->original_filename }}"
                                    class="w-full h-36 object-cover"
                                >
                            </div>
                        @elseif ($status !== 'completed')
                            {{-- Placeholder while processing/pending --}}
                            <div class="w-full h-36 bg-gray-50 border-b border-gray-100 flex items-center justify-center">
                                @if ($status === 'processing')
                                    <svg class="animate-spin w-6 h-6 text-blue-400" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                                    </svg>
                                @elseif ($status === 'pending')
                                    <svg class="w-6 h-6 text-gray-300 animate-pulse" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                            d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14M14 8h.01"/>
                                    </svg>
                                @elseif ($status === 'failed')
                                    <svg class="w-6 h-6 text-red-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                            d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                                    </svg>
                                @endif
                            </div>
                        @endif

                        <div class="p-3 flex flex-col gap-2 flex-1">

                            {{-- Filename + file size --}}
                            <div>
                                <p class="text-sm font-medium text-gray-800 truncate" title="{{ $media->original_filename }}">
                                    {{ $media->original_filename }}
                                </p>
                                <p class="text-xs text-gray-400 mt-0.5">
                                    {{ round($media->file_size / 1024) }} KB
                                    &middot;
                                    {{ $media->created_at->diffForHumans() }}
                                </p>
                            </div>

                            {{-- Status badge + processing step --}}
                            <div class="flex items-center gap-2">
                                <span class="px-2 py-0.5 text-xs font-medium rounded-full {{ $statusClasses[$status] ?? 'bg-gray-100 text-gray-600' }}">
                                    {{ ucfirst($status) }}
                                </span>
                                @if ($status === 'processing' && $step)
                                    <span class="text-xs text-gray-400 capitalize">{{ $step }}</span>
                                @endif
                            </div>

                            {{-- Progress bar (processing only) --}}
                            @if ($status === 'processing')
                                <div class="w-full bg-gray-100 rounded-full h-1.5">
                                    <div
                                        class="bg-blue-500 h-1.5 rounded-full transition-all duration-500"
                                        style="width: {{ $progress }}%"
                                    ></div>
                                </div>
                            @endif

                            {{-- Error detail + retry (failed only) --}}
                            @if ($status === 'failed')
                                @if ($error)
                                    <p class="text-xs text-red-500 truncate" title="{{ $error }}">
                                        {{ $error }}
                                    </p>
                                @endif
                                <button
                                    wire:click="retryMedia('{{ $media->uuid }}')"
                                    wire:loading.attr="disabled"
                                    wire:target="retryMedia('{{ $media->uuid }}')"
                                    class="mt-1 w-full text-xs font-medium text-amber-700 bg-amber-50 hover:bg-amber-100 border border-amber-200 rounded-lg py-1.5 transition"
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

</div>
