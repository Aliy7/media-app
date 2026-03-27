<?php

namespace App\Livewire;

use App\Exceptions\InvalidMediaException;
use App\Models\Media;
use App\Services\MediaUploadService;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\On;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithFileUploads;
use Throwable;

class MediaUploader extends Component
{
    use WithFileUploads;

    #[Validate([
        'file' => [
            'bail',
            'required',
            'file',
            'mimetypes:image/jpeg,image/png,image/gif,image/webp',
            'max:10240',
            'dimensions:min_width=100,min_height=100,max_width=8000,max_height=8000',
        ],
    ])]
    public $file = null;

    // Empty string (not null) so Livewire's echo-private placeholder resolver can
    // substitute it before a file has been uploaded. An empty channel name never
    // matches a real broadcast, so no spurious events fire.
    public string $uploadedUuid = '';

    /** idle | uploading | pending | processing | completed | failed | error */
    public string $uploadStatus = 'idle';

    public ?string $errorMessage  = null;
    public int     $progress      = 0;
    public ?string $processingStep = null;
    public ?string $failureStep   = null;
    public ?string $failureError  = null;

    public function save(): void
    {
        $this->validate();

        $this->uploadStatus = 'uploading';
        $this->errorMessage = null;

        try {
            $user = auth()->user();
            if (! $user) {
                $this->uploadStatus = 'error';
                $this->errorMessage = 'You are not authenticated.';
                return;
            }

            Log::info('media_upload_started', [
                'user_id'       => $user->id,
                'original_name' => $this->file?->getClientOriginalName(),
                'size'          => $this->file?->getSize(),
            ]);

            $uploadService = app(MediaUploadService::class);
            $media = $uploadService->handle($this->file, $user);

            $this->uploadedUuid = $media->uuid;
            $this->uploadStatus = 'pending';
            $this->file         = null;

            Log::info('media_upload_saved', [
                'user_id'         => $media->user_id,
                'uuid'            => $media->uuid,
                'stored_filename' => $media->stored_filename,
            ]);
        } catch (InvalidMediaException $e) {
            $this->uploadStatus = 'error';
            $this->errorMessage = $e->getMessage();
        } catch (Throwable $e) {
            Log::error('media_upload_failed', [
                'message' => $e->getMessage(),
                'class'   => $e::class,
            ]);
            report($e);

            $this->uploadStatus = 'error';
            $this->errorMessage = 'Upload failed. Please try again.';
        }
    }

    // -------------------------------------------------------------------------
    // Broadcast event handlers — Livewire 3 subscribes via Laravel Echo under
    // the hood. The {uploadedUuid} token is interpolated from the public prop.
    // The leading dot in the event name bypasses Echo's namespace prefix, which
    // is required when the event uses broadcastAs() (custom name).
    // -------------------------------------------------------------------------

    #[On('echo-private:media.{uploadedUuid},.media.processing.started')]
    public function onProcessingStarted(array $event): void
    {
        $this->uploadStatus   = 'processing';
        $this->processingStep = 'starting';
        $this->progress       = 0;
    }

    #[On('echo-private:media.{uploadedUuid},.media.step.completed')]
    public function onStepCompleted(array $event): void
    {
        $this->uploadStatus   = 'processing';
        $this->processingStep = $event['step'];
        $this->progress       = $event['progress'];
    }

    #[On('echo-private:media.{uploadedUuid},.media.processing.completed')]
    public function onProcessingCompleted(array $event): void
    {
        $this->uploadStatus = 'completed';
        $this->progress     = 100;
    }

    #[On('echo-private:media.{uploadedUuid},.media.processing.failed')]
    public function onProcessingFailed(array $event): void
    {
        $this->uploadStatus = 'failed';
        $this->failureStep  = $event['step'];
        $this->failureError = $event['error'];
    }

    /**
     * Polling fallback called by wire:poll when status is pending/processing.
     * Soketi does not buffer past events — if the job completed before the
     * Echo subscription was established (race condition on fast workers),
     * this ensures the UI catches up by reading DB state directly.
     * The poll directive is only rendered while status is pending|processing,
     * so it removes itself automatically once we reach a terminal state.
     */
    public function checkStatus(): void
    {
        if (! $this->uploadedUuid || ! in_array($this->uploadStatus, ['pending', 'processing'])) {
            return;
        }

        $media = Media::where('uuid', $this->uploadedUuid)
            ->where('user_id', auth()->id())
            ->first();

        if (! $media) {
            return;
        }

        if ($media->status === Media::STATUS_PROCESSING) {
            $this->uploadStatus   = 'processing';
            $this->processingStep = $media->processing_step;
            $this->progress       = $media->progress ?? 0;
        } elseif ($media->status === Media::STATUS_COMPLETED) {
            $this->uploadStatus = 'completed';
            $this->progress     = 100;
        } elseif ($media->status === Media::STATUS_FAILED) {
            $this->uploadStatus = 'failed';
            $this->failureStep  = $media->processing_step;
            $this->failureError = $media->error_message;
        }
    }

    /**
     * Re-queues a failed media item without requiring re-upload.
     * Resets the Media record to pending and re-dispatches the job with the
     * standard 5s delay so the Echo subscription can be re-established first.
     */
    public function retryProcessing(): void
    {
        if (! $this->uploadedUuid || $this->uploadStatus !== 'failed') {
            return;
        }

        $media = Media::where('uuid', $this->uploadedUuid)
            ->where('user_id', auth()->id())
            ->where('status', Media::STATUS_FAILED)
            ->first();

        if (! $media) {
            return;
        }

        app(MediaUploadService::class)->retry($media);

        $this->uploadStatus   = 'pending';
        $this->processingStep = null;
        $this->progress       = 0;
        $this->failureStep    = null;
        $this->failureError   = null;
    }

    public function resetForm(): void
    {
        $this->reset([
            'uploadedUuid', 'uploadStatus', 'errorMessage',
            'progress', 'processingStep', 'failureStep', 'failureError',
        ]);
    }

    public function render()
    {
        return view('livewire.media-uploader')
            ->layout('layouts.app');
    }
}
