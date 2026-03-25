<?php

namespace App\Livewire;

use App\Exceptions\InvalidMediaException;
use App\Services\MediaUploadService;
use Illuminate\Support\Facades\Log;
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

    public ?string $uploadedUuid = null;

    public string $uploadStatus = 'idle'; // idle | uploading | pending | error

    public ?string $errorMessage = null;

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
                'user_id' => $user->id,
                'original_name' => $this->file?->getClientOriginalName(),
                'size' => $this->file?->getSize(),
            ]);

            $uploadService = app(MediaUploadService::class);
            $media = $uploadService->handle($this->file, $user);

            $this->uploadedUuid = $media->uuid;
            $this->uploadStatus = 'pending';
            $this->file         = null;

            Log::info('media_upload_saved', [
                'user_id' => $media->user_id,
                'uuid' => $media->uuid,
                'stored_filename' => $media->stored_filename,
            ]);
        } catch (InvalidMediaException $e) {
            $this->uploadStatus = 'error';
            $this->errorMessage = $e->getMessage();
        } catch (Throwable $e) {
            Log::error('media_upload_failed', [
                'message' => $e->getMessage(),
                'class' => $e::class,
            ]);
            report($e);

            $this->uploadStatus = 'error';
            $this->errorMessage = 'Upload failed. Please try again.';
        }
    }

    public function render()
    {
        return view('livewire.media-uploader')
            ->layout('layouts.app');
    }
}
