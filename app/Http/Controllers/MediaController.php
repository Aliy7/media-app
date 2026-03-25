<?php

namespace App\Http\Controllers;

use App\Exceptions\InvalidMediaException;
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

    private function deleteMediaFile(Media $media): void
    {
        Storage::disk('media')->delete($media->stored_filename);
    }
}
