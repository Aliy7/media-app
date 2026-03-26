<?php

namespace App\Http\Controllers;

use App\Exceptions\InvalidMediaException;
use App\Jobs\ProcessImageJob;
use App\Models\Media;
use App\Services\MediaUploadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

class MediaController extends Controller
{
    public function __construct(private MediaUploadService $uploadService) {}

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'file' => [
                'bail',
                'required',
                'file',
                'mimetypes:image/jpeg,image/png,image/gif,image/webp',
                'max:10240',
                'dimensions:min_width=100,min_height=100,max_width=8000,max_height=8000',
            ],
        ]);

        try {
            $media = $this->uploadService->handle($request->file('file'), $request->user());
        } catch (InvalidMediaException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'uuid'   => $media->uuid,
            'status' => $media->status,
        ], 201);
    }

    public function show(string $uuid): JsonResponse
    {
        $media = Media::where('uuid', $uuid)->firstOrFail();

        $this->authorize('view', $media);

        return response()->json($media);
    }

    public function destroy(string $uuid): Response
    {
        $media = Media::where('uuid', $uuid)->firstOrFail();

        $this->authorize('delete', $media);

        $this->deleteMediaFile($media);
        $media->delete();

        return response()->noContent();
    }

    public function retry(string $uuid): JsonResponse
    {
        $media = Media::where('uuid', $uuid)->firstOrFail();

        $this->authorize('retry', $media);

        abort_if($media->status !== Media::STATUS_FAILED, 422, 'Only failed media can be retried.');

        $media->update([
            'status'          => Media::STATUS_PENDING,
            'processing_step' => null,
            'progress'        => 0,
            'error_message'   => null,
        ]);

        ProcessImageJob::dispatch($media)->delay(now()->addSeconds(5));

        return response()->json(['uuid' => $media->uuid, 'status' => $media->status]);
    }

    public function thumbnail(string $uuid): \Illuminate\Http\Response
    {
        $media = Media::where('uuid', $uuid)->firstOrFail();

        $this->authorize('view', $media);

        $path = $media->outputs['thumbnail'] ?? null;
        abort_if(! $path || ! Storage::disk('media')->exists($path), 404);

        return response(
            Storage::disk('media')->get($path),
            200,
            ['Content-Type' => Storage::disk('media')->mimeType($path) ?? 'image/jpeg']
        );
    }

    private function deleteMediaFile(Media $media): void
    {
        Storage::disk('media')->delete($media->stored_filename);
    }
}
