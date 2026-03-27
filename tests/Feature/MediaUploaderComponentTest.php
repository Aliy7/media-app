<?php

namespace Tests\Feature;

use App\Livewire\MediaUploader;
use App\Models\Media;
use App\Models\User;
use App\Services\MediaUploadService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Covers MediaUploader::save(), checkStatus(), and the retryProcessing()
 * early-return guard — the branches not exercised by the broadcast event tests.
 */
class MediaUploaderComponentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('media');
        Queue::fake();
    }

    // -----------------------------------------------------------------------
    // save() — happy path
    // -----------------------------------------------------------------------

    public function test_save_transitions_to_pending_and_creates_media_record(): void
    {
        $user = User::factory()->create();
        $file = UploadedFile::fake()->image('photo.jpg', 800, 600);

        Livewire::actingAs($user)
            ->test(MediaUploader::class)
            ->set('file', $file)
            ->call('save')
            ->assertSet('uploadStatus', 'pending')
            ->assertSet('file', null);

        $this->assertDatabaseCount('media', 1);
    }

    public function test_save_stores_uuid_on_component_after_successful_upload(): void
    {
        $user = User::factory()->create();
        $file = UploadedFile::fake()->image('photo.jpg', 800, 600);

        $component = Livewire::actingAs($user)
            ->test(MediaUploader::class)
            ->set('file', $file)
            ->call('save');

        $uuid = $component->get('uploadedUuid');
        $this->assertNotEmpty($uuid);
        $this->assertDatabaseHas('media', ['uuid' => $uuid]);
    }

    // -----------------------------------------------------------------------
    // save() — unauthenticated guard (lines 54-57)
    // -----------------------------------------------------------------------

    public function test_save_sets_error_state_when_no_user_is_authenticated(): void
    {
        $file = UploadedFile::fake()->image('photo.jpg', 800, 600);

        Livewire::test(MediaUploader::class)
            ->set('file', $file)
            ->call('save')
            ->assertSet('uploadStatus', 'error')
            ->assertSet('errorMessage', 'You are not authenticated.');
    }

    // -----------------------------------------------------------------------
    // save() — InvalidMediaException catch (lines 78-80)
    // -----------------------------------------------------------------------

    public function test_save_sets_service_error_message_on_invalid_media_exception(): void
    {
        $user = User::factory()->create();
        $file = UploadedFile::fake()->image('photo.jpg', 800, 600);

        $this->mock(MediaUploadService::class, function ($mock) {
            $mock->shouldReceive('handle')
                ->andThrow(\App\Exceptions\InvalidMediaException::invalidMimeType('image/tiff'));
        });

        Livewire::actingAs($user)
            ->test(MediaUploader::class)
            ->set('file', $file)
            ->call('save')
            ->assertSet('uploadStatus', 'error')
            ->assertSet('errorMessage', fn ($msg) => str_contains($msg, 'not allowed'));
    }

    // -----------------------------------------------------------------------
    // save() — Throwable catch (lines 81-90)
    // -----------------------------------------------------------------------

    public function test_save_sets_generic_error_on_unexpected_exception(): void
    {
        $user = User::factory()->create();
        $file = UploadedFile::fake()->image('photo.jpg', 800, 600);

        $this->mock(MediaUploadService::class, function ($mock) {
            $mock->shouldReceive('handle')
                ->andThrow(new \RuntimeException('Disk full'));
        });

        Livewire::actingAs($user)
            ->test(MediaUploader::class)
            ->set('file', $file)
            ->call('save')
            ->assertSet('uploadStatus', 'error')
            ->assertSet('errorMessage', 'Upload failed. Please try again.');
    }

    // -----------------------------------------------------------------------
    // checkStatus() — early returns
    // -----------------------------------------------------------------------

    public function test_check_status_returns_early_when_uuid_is_empty(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(MediaUploader::class)
            ->set('uploadStatus', 'pending')  // right status but no uuid
            ->call('checkStatus')
            ->assertSet('uploadStatus', 'pending');  // unchanged
    }

    public function test_check_status_returns_early_for_non_polling_status(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(MediaUploader::class)
            ->set('uploadedUuid', 'some-uuid')
            ->set('uploadStatus', 'completed')  // not pending or processing
            ->call('checkStatus')
            ->assertSet('uploadStatus', 'completed');  // unchanged
    }

    public function test_check_status_returns_early_when_media_not_found_in_db(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(MediaUploader::class)
            ->set('uploadedUuid', 'nonexistent-uuid')
            ->set('uploadStatus', 'pending')
            ->call('checkStatus')
            ->assertSet('uploadStatus', 'pending');  // unchanged
    }

    // -----------------------------------------------------------------------
    // checkStatus() — status sync branches
    // -----------------------------------------------------------------------

    public function test_check_status_syncs_processing_state_from_db(): void
    {
        $user  = User::factory()->create();
        $media = Media::factory()->create([
            'user_id'         => $user->id,
            'status'          => Media::STATUS_PROCESSING,
            'processing_step' => 'resize',
            'progress'        => 33,
        ]);

        Livewire::actingAs($user)
            ->test(MediaUploader::class)
            ->set('uploadedUuid', $media->uuid)
            ->set('uploadStatus', 'pending')
            ->call('checkStatus')
            ->assertSet('uploadStatus', 'processing')
            ->assertSet('processingStep', 'resize')
            ->assertSet('progress', 33);
    }

    public function test_check_status_syncs_completed_state_from_db(): void
    {
        $user  = User::factory()->create();
        $media = Media::factory()->create([
            'user_id'  => $user->id,
            'status'   => Media::STATUS_COMPLETED,
            'progress' => 100,
        ]);

        Livewire::actingAs($user)
            ->test(MediaUploader::class)
            ->set('uploadedUuid', $media->uuid)
            ->set('uploadStatus', 'processing')
            ->call('checkStatus')
            ->assertSet('uploadStatus', 'completed')
            ->assertSet('progress', 100);
    }

    public function test_check_status_syncs_failed_state_from_db(): void
    {
        $user  = User::factory()->create();
        $media = Media::factory()->create([
            'user_id'         => $user->id,
            'status'          => Media::STATUS_FAILED,
            'processing_step' => 'resize',
            'error_message'   => 'Out of memory',
        ]);

        Livewire::actingAs($user)
            ->test(MediaUploader::class)
            ->set('uploadedUuid', $media->uuid)
            ->set('uploadStatus', 'processing')
            ->call('checkStatus')
            ->assertSet('uploadStatus', 'failed')
            ->assertSet('failureStep', 'resize')
            ->assertSet('failureError', 'Out of memory');
    }

    // -----------------------------------------------------------------------
    // retryProcessing() — media not found early return (line 184)
    // -----------------------------------------------------------------------

    public function test_retry_processing_returns_early_when_media_not_in_db(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(MediaUploader::class)
            ->set('uploadedUuid', 'nonexistent-uuid')
            ->set('uploadStatus', 'failed')
            ->call('retryProcessing')
            ->assertSet('uploadStatus', 'failed');  // not reset to pending
    }
}
