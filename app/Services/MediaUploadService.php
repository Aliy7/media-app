<?php

namespace App\Services;

use App\Exceptions\InvalidMediaException;
use App\Jobs\ProcessImageJob;
use App\Models\Media;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MediaUploadService
{
    private const ALLOWED_MIME_TYPES = [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
    ];

    private const MAX_FILE_SIZE_BYTES = 10 * 1024 * 1024; // 10MB

    private const MIN_DIMENSION = 100;
    private const MAX_DIMENSION = 8000;

    public function handle(UploadedFile $file, User $user): Media
    {
        $this->validateMimeType($file);
        $this->validateFileSize($file);
        $this->validateDimensions($file);

        $uuid            = Str::uuid()->toString();
        $storedFilename  = $uuid . '.' . $file->extension();

        $storedPath = Storage::disk('media')->putFileAs('', $file, $storedFilename);
        if ($storedPath === false) {
            throw InvalidMediaException::storageFailure();
        }

        $media = new Media([
            'original_filename' => $file->getClientOriginalName(),
            'stored_filename'   => $storedFilename,
            'mime_type'         => $file->getMimeType(),
            'file_size'         => $file->getSize(),
        ]);

        $media->user_id = $user->id;
        $media->uuid    = $uuid;
        $media->status  = Media::STATUS_PENDING;
        $media->save();

        // 5-second delay gives the browser time to establish the Echo WebSocket
        // subscription and authenticate the private channel before the first
        // event fires. Soketi does not buffer past events — without this window,
        // fast workers complete the entire job chain before the subscription is
        // established and all broadcast events evaporate. See DECISIONS.md D-027.
        ProcessImageJob::dispatch($media)->delay(now()->addSeconds(5));

        return $media;
    }

    private function validateMimeType(UploadedFile $file): void
    {
        if (! in_array($file->getMimeType(), self::ALLOWED_MIME_TYPES, true)) {
            throw InvalidMediaException::invalidMimeType($file->getMimeType());
        }
    }

    private function validateFileSize(UploadedFile $file): void
    {
        if ($file->getSize() > self::MAX_FILE_SIZE_BYTES) {
            throw InvalidMediaException::fileTooLarge($file->getSize(), self::MAX_FILE_SIZE_BYTES);
        }
    }

    private function validateDimensions(UploadedFile $file): void
    {
        $imageSize = @getimagesize($file->getRealPath());

        if ($imageSize === false) {
            throw InvalidMediaException::unreadableFile();
        }

        [$width, $height] = $imageSize;

        if ($width < self::MIN_DIMENSION || $height < self::MIN_DIMENSION) {
            throw InvalidMediaException::dimensionsTooSmall($width, $height);
        }

        if ($width > self::MAX_DIMENSION || $height > self::MAX_DIMENSION) {
            throw InvalidMediaException::dimensionsTooLarge($width, $height);
        }
    }
}
