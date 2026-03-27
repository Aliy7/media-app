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
        $this->validateFilename($file);
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

    /**
     * Reset a failed Media record to pending and re-dispatch the processing job.
     * The 5-second delay gives the browser time to re-establish the Echo
     * subscription before the first event fires (see DECISIONS.md D-027).
     */
    public function retry(Media $media): void
    {
        $media->update([
            'status'          => Media::STATUS_PENDING,
            'processing_step' => null,
            'progress'        => 0,
            'error_message'   => null,
        ]);

        ProcessImageJob::dispatch($media)->delay(now()->addSeconds(5));
    }

    /**
     * Remove the stored file from disk and delete the Media record.
     */
    public function delete(Media $media): void
    {
        Storage::disk('media')->delete($media->stored_filename);
        $media->delete();
    }

    private function validateFilename(UploadedFile $file): void
    {
        $length = strlen($file->getClientOriginalName());

        if ($length > 255) {
            throw InvalidMediaException::filenameTooLong($length);
        }
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
