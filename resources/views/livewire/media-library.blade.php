<div class="max-w-4xl mx-auto py-8 px-4">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-semibold text-gray-900">Media Library</h1>
        <a
            href="{{ route('media.upload') }}"
            class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded shadow-sm hover:bg-indigo-700"
        >
            Upload Image
        </a>
    </div>

    @if ($mediaItems->isEmpty())
        <div class="text-center py-16 text-gray-400">
            <p class="text-lg">No images yet.</p>
            <a href="{{ route('media.upload') }}" class="mt-2 inline-block text-indigo-600 hover:underline">
                Upload your first image
            </a>
        </div>
    @else
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            @foreach ($mediaItems as $media)
                <div class="bg-white rounded-lg border border-gray-200 shadow-sm p-4">
                    <p class="text-sm font-medium text-gray-800 truncate" title="{{ $media->original_filename }}">
                        {{ $media->original_filename }}
                    </p>
                    <p class="text-xs text-gray-400 mt-1">{{ round($media->file_size / 1024) }} KB</p>

                    <div class="mt-3 flex items-center gap-2">
                        @php
                            $statusClasses = [
                                'pending'    => 'bg-yellow-100 text-yellow-700',
                                'processing' => 'bg-blue-100 text-blue-700',
                                'completed'  => 'bg-green-100 text-green-700',
                                'failed'     => 'bg-red-100 text-red-700',
                            ];
                        @endphp
                        <span class="px-2 py-0.5 text-xs font-medium rounded {{ $statusClasses[$media->status] ?? 'bg-gray-100 text-gray-600' }}">
                            {{ ucfirst($media->status) }}
                        </span>

                        @if ($media->status === 'processing' && $media->processing_step)
                            <span class="text-xs text-gray-400">{{ $media->processing_step }}</span>
                        @endif
                    </div>

                    @if ($media->status === 'processing')
                        <div class="mt-2 w-full bg-gray-200 rounded-full h-1.5">
                            <div
                                class="bg-indigo-500 h-1.5 rounded-full transition-all"
                                style="width: {{ $media->progress }}%"
                            ></div>
                        </div>
                    @endif

                    @if ($media->error_message)
                        <p class="mt-2 text-xs text-red-500 truncate" title="{{ $media->error_message }}">
                            {{ $media->error_message }}
                        </p>
                    @endif
                </div>
            @endforeach
        </div>
    @endif
</div>
