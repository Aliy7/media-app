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

    /** When true the component is embedded inside a modal — layout is skipped and navigation links become close-modal dispatches. */
    public bool $embedded = false;

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

    /** Incremented on every reset — causes Livewire to destroy and recreate the form DOM subtree, which clears the browser file input. Alternates between 0/1 for max visibility in wire:key. */
    public int $formKey = 0;

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
        // Guard: ignore events for empty/invalid UUID (after reset)
        if (!$this->uploadedUuid) {
            return;
        }
        
        $this->uploadStatus   = 'processing';
        $this->processingStep = 'starting';
        $this->progress       = 0;
    }

    #[On('echo-private:media.{uploadedUuid},.media.step.completed')]
    public function onStepCompleted(array $event): void
    {
        // Guard: ignore events for empty/invalid UUID (after reset)
        if (!$this->uploadedUuid) {
            return;
        }
        
        $this->uploadStatus   = 'processing';
        $this->processingStep = $event['step'];
        $this->progress       = $event['progress'];
    }

    #[On('echo-private:media.{uploadedUuid},.media.processing.completed')]
    public function onProcessingCompleted(array $event): void
    {
        // Guard: ignore events for empty/invalid UUID (after reset)
        if (!$this->uploadedUuid) {
            return;
        }
        
        $this->uploadStatus = 'completed';
        $this->progress     = 100;
    }

    #[On('echo-private:media.{uploadedUuid},.media.processing.failed')]
    public function onProcessingFailed(array $event): void
    {
        // Guard: ignore events for empty/invalid UUID (after reset)
        if (!$this->uploadedUuid) {
            return;
        }
        
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
            // Show "Optimising…" for one poll cycle before marking complete,
            // so the step is visible even if the broadcast event was missed.
            if ($this->processingStep !== 'optimize') {
                $this->uploadStatus   = 'processing';
                $this->processingStep = 'optimize';
                $this->progress       = 90;
            } else {
                $this->uploadStatus = 'completed';
                $this->progress     = 100;
            }
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
        // Immediately clear the uploaded UUID so the image route breaks
        // This ensures the image won't load even if DOM isn't immediately updated
        $this->uploadedUuid = '';
        
        // Toggle formKey between 0 and 1 for maximum visibility of key change
        $this->formKey = $this->formKey === 0 ? 1 : 0;
        
        // Reset all remaining state to initial values
        $this->file               = null;
        $this->uploadStatus       = 'idle';
        $this->errorMessage       = null;
        $this->progress           = 0;
        $this->processingStep     = null;
        $this->failureStep        = null;
        $this->failureError       = null;
        
        // Dispatch event to clear the browser file input's displayed value.
        // Alpine catches this on @clear-file-input.window and sets input.value = ''.
        // Also, x-init on the input runs after the DOM is settled,
        // so the input is cleared both ways — reliable regardless of morphdom behavior.
        $this->dispatch('clear-file-input');
    }

    public function render()
    {
        return view('livewire.media-uploader')
            ->layout('layouts.app');
    }
}
