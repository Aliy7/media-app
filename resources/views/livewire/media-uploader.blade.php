<div class="min-h-screen bg-gray-50 flex items-start justify-center pt-16 px-4">
    <div class="w-full max-w-lg">

        {{-- Page header --}}
        <div class="mb-8">
            <a href="{{ route('dashboard') }}" class="inline-flex items-center text-sm text-gray-500 hover:text-gray-700 mb-4">
                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
                Back to library
            </a>
            <h1 class="text-2xl font-bold text-gray-900">Upload Image</h1>
            <p class="text-sm text-gray-500 mt-1">JPEG, PNG, GIF or WebP &mdash; up to 10 MB, minimum 100&times;100 px</p>
        </div>

        {{-- Upload card --}}
        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
            <form wire:submit.prevent="save" class="p-6 space-y-6">

                {{-- Drop zone / file input --}}
                <div>
                    <label
                        for="file-input"
                        class="flex flex-col items-center justify-center w-full h-48 border-2 border-dashed rounded-xl cursor-pointer transition
                            {{ $file ? 'border-emerald-400 bg-emerald-50' : 'border-gray-300 bg-gray-50 hover:bg-gray-100 hover:border-emerald-300' }}"
                    >
                        @if ($file)
                            {{-- Image preview --}}
                            <div class="relative w-full h-full flex items-center justify-center p-2">
                                <img
                                    src="{{ $file->temporaryUrl() }}"
                                    alt="Preview"
                                    class="max-h-40 max-w-full rounded-lg object-contain shadow-sm"
                                >
                                <span class="absolute top-2 right-2 bg-white border border-gray-200 rounded-full px-2 py-0.5 text-xs text-gray-500 shadow-sm">
                                    {{ $file->getClientOriginalName() }}
                                </span>
                            </div>
                        @else
                            <div class="flex flex-col items-center text-gray-400">
                                <svg class="w-10 h-10 mb-3 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                        d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14M14 8h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                </svg>
                                <p class="text-sm font-medium text-gray-600">Click to select an image</p>
                                <p class="text-xs text-gray-400 mt-1">or drag and drop here</p>
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
                        <p class="mt-2 text-sm text-red-600 flex items-center gap-1">
                            <svg class="w-4 h-4 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                            </svg>
                            {{ $message }}
                        </p>
                    @enderror
                </div>

                {{-- File metadata row --}}
                @if ($file && !$errors->has('file'))
                    <div class="flex items-center justify-between text-sm bg-gray-50 rounded-lg px-3 py-2 border border-gray-100">
                        <span class="text-gray-600 truncate max-w-xs">{{ $file->getClientOriginalName() }}</span>
                        <span class="text-gray-400 flex-shrink-0 ml-3">{{ round($file->getSize() / 1024) }} KB</span>
                    </div>
                @endif

                {{-- Service-layer error (InvalidMediaException) --}}
                @if ($errorMessage)
                    <div class="flex items-start gap-3 rounded-lg bg-red-50 border border-red-200 px-4 py-3">
                        <svg class="w-5 h-5 text-red-500 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                        </svg>
                        <p class="text-sm text-red-700">{{ $errorMessage }}</p>
                    </div>
                @endif

                {{-- Success state --}}
                @if ($uploadStatus === 'pending')
                    <div class="flex items-start gap-3 rounded-lg bg-green-50 border border-green-200 px-4 py-3">
                        <svg class="w-5 h-5 text-green-500 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                        </svg>
                        <div>
                            <p class="text-sm font-medium text-green-800">Upload received — queued for processing</p>
                            <p class="text-xs text-green-600 font-mono mt-0.5">{{ $uploadedUuid }}</p>
                        </div>
                    </div>
                @endif

                {{-- Submit button --}}
                <button
                    type="submit"
                    @disabled(!$file || $uploadStatus === 'uploading' || $uploadStatus === 'pending')
                    class="w-full inline-flex items-center justify-center gap-2 px-4 py-3 bg-emerald-600 text-white text-sm font-semibold rounded-xl shadow-sm
                        hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2
                        disabled:opacity-40 disabled:cursor-not-allowed transition"
                >
                    {{-- Spinner while uploading --}}
                    <span wire:loading wire:target="save">
                        <svg class="animate-spin h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                        </svg>
                    </span>

                    {{-- Upload icon at rest --}}
                    <span wire:loading.remove wire:target="save">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                        </svg>
                    </span>

                    <span wire:loading.remove wire:target="save">Upload Image</span>
                    <span wire:loading wire:target="save">Uploading&hellip;</span>
                </button>

            </form>

            {{-- Footer hint --}}
            @if ($uploadStatus === 'pending')
                <div class="px-6 pb-5">
                    <a href="{{ route('dashboard') }}" class="block text-center text-sm text-emerald-600 hover:text-emerald-800 font-medium">
                        View in library &rarr;
                    </a>
                </div>
            @endif
        </div>

    </div>
</div>
