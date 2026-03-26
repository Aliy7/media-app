<?php

namespace Tests\Feature;

use App\Jobs\ProcessImageJob;
use App\Livewire\MediaLibrary;
use App\Livewire\MediaUploader;
use App\Models\Media;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Verifies the manual retry path: failed media can be re-queued from both
 * the HTTP endpoint and the Livewire components without re-uploading the file.
 */
class MediaRetryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
    }

    // -------------------------------------------------------------------------
    // HTTP endpoint — POST /media/{uuid}/retry
    // -------------------------------------------------------------------------

    public function test_owner_can_retry_failed_media(): void
    {
        $user  = User::factory()->create();
        $media = Media::factory()->create(['user_id' => $user->id, 'status' => 'failed', 'error_message' => 'Corrupt file']);

        $response = $this->actingAs($user)->postJson(route('media.retry', $media->uuid));

        $response->assertOk()->assertJsonFragment(['status' => 'pending']);
    }

    public function test_retry_resets_media_record_to_pending(): void
    {
        $user  = User::factory()->create();
        $media = Media::factory()->create([
            'user_id'         => $user->id,
            'status'          => 'failed',
            'processing_step' => 'resize',
            'progress'        => 33,
            'error_message'   => 'Imagick error',
        ]);

        $this->actingAs($user)->postJson(route('media.retry', $media->uuid));

        $media->refresh();
        $this->assertSame('pending', $media->status);
        $this->assertNull($media->processing_step);
        $this->assertSame(0, $media->progress);
        $this->assertNull($media->error_message);
    }

    public function test_retry_dispatches_process_image_job_for_correct_media(): void
    {
        $user  = User::factory()->create();
        $media = Media::factory()->create(['user_id' => $user->id, 'status' => 'failed']);

        $this->actingAs($user)->postJson(route('media.retry', $media->uuid));

        Queue::assertPushed(ProcessImageJob::class, function ($job) use ($media) {
            return $job->media->is($media);
        });
    }

    public function test_other_user_cannot_retry_media(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $media = Media::factory()->create(['user_id' => $owner->id, 'status' => 'failed']);

        $this->actingAs($other)->postJson(route('media.retry', $media->uuid))->assertForbidden();
    }

    public function test_unauthenticated_user_cannot_retry(): void
    {
        $media = Media::factory()->create(['status' => 'failed']);

        $this->postJson(route('media.retry', $media->uuid))->assertUnauthorized();
    }

    public function test_cannot_retry_non_failed_media(): void
    {
        $user  = User::factory()->create();
        $media = Media::factory()->create(['user_id' => $user->id, 'status' => 'completed']);

        $this->actingAs($user)->postJson(route('media.retry', $media->uuid))->assertUnprocessable();
    }

    public function test_retry_does_not_dispatch_job_when_media_is_pending(): void
    {
        $user  = User::factory()->create();
        $media = Media::factory()->create(['user_id' => $user->id, 'status' => 'pending']);

        $this->actingAs($user)->postJson(route('media.retry', $media->uuid))->assertUnprocessable();

        Queue::assertNothingPushed();
    }

    // -------------------------------------------------------------------------
    // Livewire MediaUploader component
    // -------------------------------------------------------------------------

    public function test_uploader_retry_resets_to_pending_state(): void
    {
        $user  = User::factory()->create();
        $media = Media::factory()->create([
            'user_id'         => $user->id,
            'status'          => 'failed',
            'processing_step' => 'resize',
            'progress'        => 33,
            'error_message'   => 'Imagick error',
        ]);

        Livewire::actingAs($user)
            ->test(MediaUploader::class)
            ->set('uploadedUuid', $media->uuid)
            ->set('uploadStatus', 'failed')
            ->set('failureStep', 'resize')
            ->set('failureError', 'Imagick error')
            ->call('retryProcessing')
            ->assertSet('uploadStatus', 'pending')
            ->assertSet('processingStep', null)
            ->assertSet('progress', 0)
            ->assertSet('failureStep', null)
            ->assertSet('failureError', null);
    }

    public function test_uploader_retry_dispatches_job(): void
    {
        $user  = User::factory()->create();
        $media = Media::factory()->create(['user_id' => $user->id, 'status' => 'failed']);

        Livewire::actingAs($user)
            ->test(MediaUploader::class)
            ->set('uploadedUuid', $media->uuid)
            ->set('uploadStatus', 'failed')
            ->call('retryProcessing');

        Queue::assertPushed(ProcessImageJob::class);
    }

    public function test_uploader_retry_is_no_op_when_status_is_not_failed(): void
    {
        $user  = User::factory()->create();
        $media = Media::factory()->create(['user_id' => $user->id, 'status' => 'completed']);

        Livewire::actingAs($user)
            ->test(MediaUploader::class)
            ->set('uploadedUuid', $media->uuid)
            ->set('uploadStatus', 'completed')
            ->call('retryProcessing')
            ->assertSet('uploadStatus', 'completed');

        Queue::assertNothingPushed();
    }

    // -------------------------------------------------------------------------
    // Livewire MediaLibrary component
    // -------------------------------------------------------------------------

    public function test_library_retry_resets_media_record(): void
    {
        $user  = User::factory()->create();
        $media = Media::factory()->create([
            'user_id'       => $user->id,
            'status'        => 'failed',
            'error_message' => 'Imagick error',
        ]);

        Livewire::actingAs($user)
            ->test(MediaLibrary::class)
            ->call('retryMedia', $media->uuid);

        $media->refresh();
        $this->assertSame('pending', $media->status);
        $this->assertNull($media->error_message);
        $this->assertSame(0, $media->progress);
    }

    public function test_library_retry_dispatches_job(): void
    {
        $user  = User::factory()->create();
        $media = Media::factory()->create(['user_id' => $user->id, 'status' => 'failed']);

        Livewire::actingAs($user)
            ->test(MediaLibrary::class)
            ->call('retryMedia', $media->uuid);

        Queue::assertPushed(ProcessImageJob::class);
    }

    public function test_library_retry_ignores_other_users_media(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $media = Media::factory()->create(['user_id' => $owner->id, 'status' => 'failed']);

        Livewire::actingAs($other)
            ->test(MediaLibrary::class)
            ->call('retryMedia', $media->uuid);

        $media->refresh();
        $this->assertSame('failed', $media->status);
        Queue::assertNothingPushed();
    }

    public function test_library_retry_clears_live_updates_for_retried_card(): void
    {
        $user  = User::factory()->create();
        $media = Media::factory()->create(['user_id' => $user->id, 'status' => 'failed']);

        Livewire::actingAs($user)
            ->test(MediaLibrary::class)
            ->set('liveUpdates', [$media->uuid => ['status' => 'failed', 'progress' => 0, 'step' => null, 'error' => 'err']])
            ->call('retryMedia', $media->uuid)
            ->assertSet('liveUpdates', []);
    }
}
