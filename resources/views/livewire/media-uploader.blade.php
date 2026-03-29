<div @class(['min-h-screen bg-gray-50 dark:bg-gray-950 flex items-start justify-center pt-16 px-4 transition-colors duration-200' => !$embedded])>
    <div class="{{ $embedded ? 'w-full' : 'w-full max-w-lg' }}">

        {{-- Page header — standalone only --}}
        @unless ($embedded)
        <div class="mb-8">
            <a href="{{ route('dashboard') }}" wire:navigate class="inline-flex items-center gap-1.5 text-sm text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 mb-4 transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
                Back to library
            </a>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Upload Image</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">JPEG, PNG, GIF or WebP &mdash; up to 10 MB, minimum 100&times;100 px</p>
        </div>
        @endunless

        {{-- Modal header --}}
        @if ($embedded)
        <div class="px-6 pt-6 pb-2">
            <h2 class="text-lg font-bold text-gray-900 dark:text-white">Upload Image</h2>
            <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">JPEG, PNG, GIF or WebP &mdash; up to 10 MB, minimum 100&times;100 px</p>
        </div>
        @endif

        <div @class(['bg-white dark:bg-gray-900 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden' => !$embedded])>

            {{-- ── Upload form ──────────────────────────────────────────────── --}}
            @if (in_array($uploadStatus, ['idle', 'uploading', 'error']))
                <form wire:submit.prevent="save" class="p-6 space-y-5">

                    {{-- Drop zone --}}
                    <div>
                        <label
                            for="file-input"
                            class="flex flex-col items-center justify-center w-full h-48 border-2 border-dashed rounded-2xl cursor-pointer transition
                                {{ $file
                                    ? 'border-indigo-400 bg-indigo-50 dark:bg-indigo-500/10 dark:border-indigo-500/50'
                                    : 'border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 hover:bg-gray-100 dark:hover:bg-gray-750 hover:border-indigo-300 dark:hover:border-indigo-600' }}"
                        >
                            @if ($file)
                                <div class="relative w-full h-full flex items-center justify-center p-2">
                                    <img
                                        src="{{ $file->temporaryUrl() }}"
                                        alt="Preview"
                                        class="max-h-40 max-w-full rounded-xl object-contain shadow-sm"
                                    >
                                    <span class="absolute top-2 right-2 bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-full px-2 py-0.5 text-xs text-gray-600 dark:text-gray-300 shadow-sm">
                                        {{ $file->getClientOriginalName() }}
                                    </span>
                                </div>
                            @else
                                <div class="flex flex-col items-center text-gray-400 dark:text-gray-500">
                                    <div class="w-12 h-12 rounded-2xl bg-gray-100 dark:bg-gray-700 flex items-center justify-center mb-3">
                                        <svg class="w-6 h-6 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                                d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5"/>
                                        </svg>
                                    </div>
                                    <p class="text-sm font-medium text-gray-600 dark:text-gray-300">Click to select an image</p>
                                    <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">or drag and drop here</p>
                                </div>
                            @endif
                        </label>

                        <input
                            id="file-input"
                            name="file"
                            type="file"
                            wire:key="file-input"
                            wire:model.live="file"
                            accept="image/jpeg,image/png,image/gif,image/webp"
                            class="sr-only"
                        >

                        @error('file')
                            <p class="mt-2 text-sm text-red-600 dark:text-red-400 flex items-center gap-1.5">
                                <svg class="w-4 h-4 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                                </svg>
                                {{ $message }}
                            </p>
                        @enderror
                    </div>

                    {{-- File metadata row --}}
                    @if ($file && !$errors->has('file'))
                        <div class="flex items-center justify-between text-sm bg-gray-50 dark:bg-gray-800 rounded-xl px-3 py-2 border border-gray-100 dark:border-gray-700">
                            <span class="text-gray-600 dark:text-gray-300 truncate max-w-xs">{{ $file->getClientOriginalName() }}</span>
                            <span class="text-gray-400 dark:text-gray-500 flex-shrink-0 ml-3">{{ round($file->getSize() / 1024) }} KB</span>
                        </div>
                    @endif

                    {{-- Service error --}}
                    @if ($errorMessage)
                        <div class="flex items-start gap-3 rounded-xl bg-red-50 dark:bg-red-500/10 border border-red-200 dark:border-red-500/20 px-4 py-3">
                            <svg class="w-5 h-5 text-red-500 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                            </svg>
                            <p class="text-sm text-red-700 dark:text-red-400">{{ $errorMessage }}</p>
                        </div>
                    @endif

                    {{-- Submit --}}
                    <button
                        type="submit"
                        @disabled(!$file || $uploadStatus === 'uploading')
                        class="w-full inline-flex items-center justify-center gap-2 px-4 py-3 bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-semibold rounded-xl shadow-sm
                            focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-gray-900
                            disabled:opacity-40 disabled:cursor-not-allowed transition"
                    >
                        <span wire:loading wire:target="save">
                            <svg class="animate-spin h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                            </svg>
                        </span>
                        <span wire:loading.remove wire:target="save">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5"/>
                            </svg>
                        </span>
                        <span wire:loading.remove wire:target="save">Upload Image</span>
                        <span wire:loading wire:target="save">Uploading&hellip;</span>
                    </button>

                </form>

            {{-- ── Post-upload status panel ──────────────────────────────────── --}}
            @else
                <div class="p-6 space-y-5"
                    @if (in_array($uploadStatus, ['pending', 'processing']))
                        wire:poll.1000ms="checkStatus"
                    @endif
                >

                    @if ($uploadedUuid)
                        <p class="text-xs font-mono text-gray-300 dark:text-gray-600 truncate">{{ $uploadedUuid }}</p>
                    @endif

                    {{-- Pending --}}
                    @if ($uploadStatus === 'pending')
                        <div class="flex items-center gap-3">
                            <svg class="animate-spin h-5 w-5 text-indigo-400 flex-shrink-0" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                            </svg>
                            <p class="text-sm text-gray-600 dark:text-gray-400">In the queue &mdash; processing will begin shortly&hellip;</p>
                        </div>
                    @endif

                    {{-- Processing --}}
                    @if ($uploadStatus === 'processing')
                        @php
                            $stepLabel = match ($processingStep) {
                                'resize'    => 'Resizing image…',
                                'thumbnail' => 'Generating thumbnail…',
                                'optimize'  => 'Optimising…',
                                default     => 'Starting…',
                            };
                        @endphp
                        <div class="space-y-2">
                            <div class="flex items-center justify-between text-sm">
                                <span class="text-gray-700 dark:text-gray-300 font-medium">{{ $stepLabel }}</span>
                                <span class="text-gray-400 dark:text-gray-500 tabular-nums">{{ $progress }}%</span>
                            </div>
                            <div class="w-full bg-gray-100 dark:bg-gray-700 rounded-full h-2 overflow-hidden">
                                <div
                                    class="bg-indigo-500 h-2 rounded-full transition-all duration-500"
                                    style="width: {{ $progress }}%"
                                ></div>
                            </div>
                        </div>
                    @endif

                    {{-- Completed --}}
                    @if ($uploadStatus === 'completed')
                        <div class="space-y-4">
                            <div class="flex items-center gap-2 text-emerald-600 dark:text-emerald-400">
                                <svg class="w-5 h-5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                </svg>
                                <p class="text-sm font-semibold">Processing complete</p>
                            </div>
                            <div class="w-full bg-gray-100 dark:bg-gray-700 rounded-full h-2">
                                <div class="bg-emerald-500 h-2 rounded-full w-full"></div>
                            </div>
                            <div class="rounded-xl overflow-hidden border border-gray-200 dark:border-gray-700">
                                <img
                                    src="{{ route('media.thumbnail', $uploadedUuid) }}"
                                    alt="Processed thumbnail"
                                    class="w-full object-cover max-h-48"
                                >
                            </div>
                        </div>
                    @endif

                    {{-- Failed --}}
                    @if ($uploadStatus === 'failed')
                        <div class="flex items-start gap-3 rounded-xl bg-red-50 dark:bg-red-500/10 border border-red-200 dark:border-red-500/20 px-4 py-3">
                            <svg class="w-5 h-5 text-red-500 dark:text-red-400 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                            </svg>
                            <div>
                                <p class="text-sm font-medium text-red-700 dark:text-red-400">Processing failed</p>
                                @if ($failureStep)
                                    <p class="text-xs text-red-500 dark:text-red-400/70 mt-0.5">Failed at: {{ $failureStep }}</p>
                                @endif
                                @if ($failureError)
                                    <p class="text-xs text-red-500 dark:text-red-400/70 mt-0.5">{{ $failureError }}</p>
                                @endif
                            </div>
                        </div>
                    @endif

                    {{-- Actions --}}
                    <div class="flex gap-3 pt-1">
                        @if ($uploadStatus === 'failed')
                            <button
                                wire:click="retryProcessing"
                                wire:loading.attr="disabled"
                                class="flex-1 text-center text-sm font-medium
                                       text-amber-700 dark:text-amber-400
                                       bg-amber-50 dark:bg-amber-500/10
                                       hover:bg-amber-100 dark:hover:bg-amber-500/20
                                       border border-amber-200 dark:border-amber-500/20
                                       rounded-xl py-2.5 transition"
                            >
                                <span wire:loading wire:target="retryProcessing">Re-queuing&hellip;</span>
                                <span wire:loading.remove wire:target="retryProcessing">Retry</span>
                            </button>
                        @endif
                        @if (in_array($uploadStatus, ['completed', 'failed']))
                            <button
                                wire:click="resetForm"
                                class="flex-1 text-center text-sm font-medium
                                       text-gray-600 dark:text-gray-400
                                       hover:text-gray-800 dark:hover:text-gray-200
                                       bg-gray-50 dark:bg-gray-800
                                       hover:bg-gray-100 dark:hover:bg-gray-700
                                       border border-gray-200 dark:border-gray-700
                                       rounded-xl py-2.5 transition"
                            >
                                Upload another
                            </button>
                        @endif
                        @if ($embedded)
                            <button
                                @click="$dispatch('close-uploader')"
                                class="flex-1 text-center text-sm font-medium
                                       text-indigo-600 dark:text-indigo-400
                                       hover:text-indigo-800 dark:hover:text-indigo-300
                                       bg-indigo-50 dark:bg-indigo-500/10
                                       hover:bg-indigo-100 dark:hover:bg-indigo-500/20
                                       border border-indigo-200 dark:border-indigo-500/20
                                       rounded-xl py-2.5 transition"
                            >
                                View library &rarr;
                            </button>
                        @else
                            <a
                                href="{{ route('dashboard') }}"
                                wire:navigate
                                class="flex-1 text-center text-sm font-medium
                                       text-indigo-600 dark:text-indigo-400
                                       hover:text-indigo-800 dark:hover:text-indigo-300
                                       bg-indigo-50 dark:bg-indigo-500/10
                                       hover:bg-indigo-100 dark:hover:bg-indigo-500/20
                                       border border-indigo-200 dark:border-indigo-500/20
                                       rounded-xl py-2.5 transition"
                            >
                                View library &rarr;
                            </a>
                        @endif
                    </div>

                </div>
            @endif

        </div>
    </div>
</div>
