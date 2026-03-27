<?php

namespace Tests\Feature;

use App\Events\MediaProcessingFailed;
use App\Jobs\GenerateThumbnailJob;
use App\Jobs\OptimizeImageJob;
use App\Jobs\ProcessImageJob;
use App\Jobs\ResizeImageJob;
use App\Models\Media;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Queue\MaxAttemptsExceededException;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Phase 5.1 — Edge Case Handling
 *
 * Verifies that every out-of-spec input is rejected gracefully, that job
 * timeout / exhaustion triggers the correct failure path, that every job
 * carries the retry configuration required to survive transient worker
 * failures, and that concurrent uploads from the same user are independent.
 */
class MediaEdgeCaseTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('media');
        Queue::fake();
    }

    // -----------------------------------------------------------------------
    // Non-image / forbidden MIME types
    // -----------------------------------------------------------------------

    public function test_executable_file_returns_422_and_no_record_created(): void
    {
        $user = User::factory()->create();
        $file = UploadedFile::fake()->create('malware.exe', 500, 'application/octet-stream');

        $response = $this->actingAs($user)->postJson('/media', ['file' => $file]);

        $response->assertStatus(422);
        $this->assertDatabaseCount('media', 0);
        Queue::assertNotPushed(ProcessImageJob::class);
    }

    public function test_text_file_disguised_as_image_returns_422(): void
    {
        $user = User::factory()->create();
        $file = UploadedFile::fake()->create('script.txt', 100, 'text/plain');

        $response = $this->actingAs($user)->postJson('/media', ['file' => $file]);

        $response->assertStatus(422);
        $this->assertDatabaseCount('media', 0);
    }

    // -----------------------------------------------------------------------
    // MIME spoofing — PDF with .jpg extension claiming image/jpeg
    //
    // Uses a real PDF fixture (tests/fixtures/pdf-disguised-as-jpg.jpg) whose
    // magic bytes finfo detects as application/pdf regardless of the filename
    // extension or the client-declared Content-Type. This directly exercises
    // the mimetypes validator rule and MediaUploadService::validateMimeType(),
    // both of which call getMimeType() — which reads actual file content.
    // -----------------------------------------------------------------------

    public function test_pdf_with_jpg_extension_is_rejected_by_content_detection(): void
    {
        $user = User::factory()->create();

        // Real PDF bytes, .jpg extension, client claims image/jpeg — spoofed upload
        $file = new \Illuminate\Http\UploadedFile(
            base_path('tests/fixtures/pdf-disguised-as-jpg.jpg'),
            'somefile.jpg',
            'image/jpeg',
            null,
            true // test mode — bypass move_uploaded_file check
        );

        $response = $this->actingAs($user)->postJson('/media', ['file' => $file]);

        $response->assertStatus(422);
        $this->assertDatabaseCount('media', 0);
        Queue::assertNotPushed(ProcessImageJob::class);
    }

    public function test_pdf_disguised_as_jpg_detected_mime_is_not_image(): void
    {
        // Prove finfo reads application/pdf from the fixture — not image/jpeg from the name.
        // If this assertion fails, the fixture is broken and the spoofing test above is meaningless.
        $file = new \Illuminate\Http\UploadedFile(
            base_path('tests/fixtures/pdf-disguised-as-jpg.jpg'),
            'somefile.jpg',
            'image/jpeg',
            null,
            true
        );

        $this->assertSame('application/pdf', $file->getMimeType(),
            'Fixture must be a real PDF so finfo detects application/pdf from its bytes.');
        $this->assertSame('image/jpeg', $file->getClientMimeType(),
            'Client MIME must remain image/jpeg to simulate a spoofed upload.');
    }

    // -----------------------------------------------------------------------
    // Dimension validation (HTTP feature-level — complements unit tests)
    // -----------------------------------------------------------------------

    public function test_image_below_minimum_dimensions_returns_422_and_no_record_created(): void
    {
        $user = User::factory()->create();
        $file = UploadedFile::fake()->image('tiny.jpg', 50, 50);

        $response = $this->actingAs($user)->postJson('/media', ['file' => $file]);

        $response->assertStatus(422);
        $this->assertDatabaseCount('media', 0);
        Queue::assertNotPushed(ProcessImageJob::class);
    }

    public function test_image_below_minimum_dimensions_contains_error_message(): void
    {
        $user = User::factory()->create();
        $file = UploadedFile::fake()->image('tiny.jpg', 50, 50);

        $response = $this->actingAs($user)->postJson('/media', ['file' => $file]);

        // Laravel's dimensions rule returns a validation error in the 'file' key
        $response->assertJsonValidationErrors('file');
    }

    // -----------------------------------------------------------------------
    // Job timeout — failed() is called with a timeout exception
    // -----------------------------------------------------------------------

    public function test_process_image_job_timeout_marks_media_failed_and_fires_event(): void
    {
        Event::fake();

        $media     = Media::factory()->create(['status' => Media::STATUS_PROCESSING]);
        $exception = new MaxAttemptsExceededException(
            ProcessImageJob::class . ' has been attempted too many times or has been running too long.'
        );

        (new ProcessImageJob($media))->failed($exception);

        $this->assertEquals(Media::STATUS_FAILED, $media->fresh()->status);
        $this->assertNotNull($media->fresh()->error_message);
        Event::assertDispatched(MediaProcessingFailed::class, fn ($e) => $e->media->is($media));
    }

    public function test_resize_job_timeout_marks_media_failed_and_fires_event(): void
    {
        Event::fake();

        $media     = Media::factory()->create(['status' => Media::STATUS_PROCESSING]);
        $exception = new MaxAttemptsExceededException('ResizeImageJob timed out.');

        (new ResizeImageJob($media))->failed($exception);

        $this->assertEquals(Media::STATUS_FAILED, $media->fresh()->status);
        Event::assertDispatched(MediaProcessingFailed::class, fn ($e) =>
            $e->media->is($media) && $e->step === 'resize'
        );
    }

    public function test_thumbnail_job_timeout_marks_media_failed_and_fires_event(): void
    {
        Event::fake();

        $media     = Media::factory()->create(['status' => Media::STATUS_PROCESSING]);
        $exception = new MaxAttemptsExceededException('GenerateThumbnailJob timed out.');

        (new GenerateThumbnailJob($media))->failed($exception);

        $this->assertEquals(Media::STATUS_FAILED, $media->fresh()->status);
        Event::assertDispatched(MediaProcessingFailed::class, fn ($e) =>
            $e->media->is($media) && $e->step === 'thumbnail'
        );
    }

    public function test_optimize_job_timeout_marks_media_failed_and_fires_event(): void
    {
        Event::fake();

        $media     = Media::factory()->create(['status' => Media::STATUS_PROCESSING]);
        $exception = new MaxAttemptsExceededException('OptimizeImageJob timed out.');

        (new OptimizeImageJob($media))->failed($exception);

        $this->assertEquals(Media::STATUS_FAILED, $media->fresh()->status);
        Event::assertDispatched(MediaProcessingFailed::class, fn ($e) =>
            $e->media->is($media) && $e->step === 'optimize'
        );
    }

    // -----------------------------------------------------------------------
    // Retry configuration — jobs survive transient worker failures
    // -----------------------------------------------------------------------

    /**
     * Verifies that each job carries the retry configuration defined in the
     * spec (3 attempts, exponential back-off, 120 s timeout). This is the
     * test-level equivalent of "kill worker mid-processing → job returns to
     * queue and is retried": if these properties are wrong, Laravel will not
     * re-attempt the job and media will be silently stuck in processing.
     */
    public function test_all_jobs_have_correct_retry_configuration(): void
    {
        $media = Media::factory()->create();

        $jobs = [
            new ProcessImageJob($media),
            new ResizeImageJob($media),
            new GenerateThumbnailJob($media),
            new OptimizeImageJob($media),
        ];

        foreach ($jobs as $job) {
            $class = get_class($job);

            $this->assertEquals(3, $job->tries,
                "{$class} must have \$tries = 3 so transient failures are retried.");

            $this->assertEquals([10, 30, 60], $job->backoff,
                "{$class} must use exponential back-off [10, 30, 60] seconds.");

            $this->assertEquals(120, $job->timeout,
                "{$class} must timeout after 120 s to unblock the worker.");
        }
    }

    // -----------------------------------------------------------------------
    // Concurrent uploads from the same user
    // -----------------------------------------------------------------------

    public function test_concurrent_uploads_from_same_user_are_processed_independently(): void
    {
        $user  = User::factory()->create();
        $file1 = UploadedFile::fake()->image('first.jpg', 800, 600);
        $file2 = UploadedFile::fake()->image('second.jpg', 1024, 768);

        $response1 = $this->actingAs($user)->postJson('/media', ['file' => $file1]);
        $response2 = $this->actingAs($user)->postJson('/media', ['file' => $file2]);

        $response1->assertStatus(201);
        $response2->assertStatus(201);

        // Two independent records exist
        $this->assertDatabaseCount('media', 2);

        // Each record has a unique UUID
        $uuid1 = $response1->json('uuid');
        $uuid2 = $response2->json('uuid');
        $this->assertNotEquals($uuid1, $uuid2);

        // A separate job was dispatched for each upload
        Queue::assertPushed(ProcessImageJob::class, 2);

        // Each job targets its own media record
        Queue::assertPushed(ProcessImageJob::class, fn ($job) => $job->media->uuid === $uuid1);
        Queue::assertPushed(ProcessImageJob::class, fn ($job) => $job->media->uuid === $uuid2);
    }

    public function test_concurrent_uploads_each_have_independent_pending_status(): void
    {
        $user  = User::factory()->create();
        $file1 = UploadedFile::fake()->image('a.jpg', 800, 600);
        $file2 = UploadedFile::fake()->image('b.jpg', 800, 600);

        $this->actingAs($user)->postJson('/media', ['file' => $file1]);
        $this->actingAs($user)->postJson('/media', ['file' => $file2]);

        $statuses = Media::where('user_id', $user->id)->pluck('status')->all();
        $this->assertEquals(['pending', 'pending'], $statuses);
    }

    // -----------------------------------------------------------------------
    // Multi-user isolation — files must never mix between users' libraries
    // -----------------------------------------------------------------------

    public function test_multiple_users_uploading_simultaneously_get_separate_records(): void
    {
        [$userA, $userB, $userC] = User::factory()->count(3)->create()->all();

        foreach ([$userA, $userB, $userC] as $user) {
            $this->actingAs($user)
                ->postJson('/media', ['file' => UploadedFile::fake()->image('photo.jpg', 800, 600)])
                ->assertStatus(201);
        }

        $this->assertDatabaseCount('media', 3);

        // Every record belongs to exactly the right user
        $this->assertEquals(1, Media::where('user_id', $userA->id)->count());
        $this->assertEquals(1, Media::where('user_id', $userB->id)->count());
        $this->assertEquals(1, Media::where('user_id', $userC->id)->count());
    }

    public function test_all_stored_filenames_are_unique_across_concurrent_users(): void
    {
        // Filenames are UUID-based — collisions would indicate shared storage paths
        [$userA, $userB, $userC] = User::factory()->count(3)->create()->all();

        foreach ([$userA, $userB, $userC] as $user) {
            $this->actingAs($user)
                ->postJson('/media', ['file' => UploadedFile::fake()->image('photo.jpg', 800, 600)]);
        }

        $filenames = Media::pluck('stored_filename')->all();
        $this->assertCount(3, array_unique($filenames),
            'Every upload must receive a unique UUID-based stored filename.');
    }

    public function test_user_cannot_view_another_users_media_after_concurrent_uploads(): void
    {
        [$userA, $userB, $userC] = User::factory()->count(3)->create()->all();

        // All three upload
        foreach ([$userA, $userB, $userC] as $user) {
            $this->actingAs($user)
                ->postJson('/media', ['file' => UploadedFile::fake()->image('photo.jpg', 800, 600)]);
        }

        $mediaA = Media::where('user_id', $userA->id)->first();

        // User B and C are forbidden from User A's media
        $this->actingAs($userB)->getJson("/media/{$mediaA->uuid}")->assertForbidden();
        $this->actingAs($userC)->getJson("/media/{$mediaA->uuid}")->assertForbidden();

        // User A can access their own
        $this->actingAs($userA)->getJson("/media/{$mediaA->uuid}")->assertOk();
    }

    public function test_user_cannot_delete_another_users_media_after_concurrent_uploads(): void
    {
        [$userA, $userB] = User::factory()->count(2)->create()->all();

        $this->actingAs($userA)
            ->postJson('/media', ['file' => UploadedFile::fake()->image('photo.jpg', 800, 600)]);
        $this->actingAs($userB)
            ->postJson('/media', ['file' => UploadedFile::fake()->image('photo.jpg', 800, 600)]);

        $mediaA = Media::where('user_id', $userA->id)->first();

        $this->actingAs($userB)->deleteJson("/media/{$mediaA->uuid}")->assertForbidden();

        // Record must still be in User A's library
        $this->assertDatabaseHas('media', ['id' => $mediaA->id, 'user_id' => $userA->id]);
    }

    public function test_each_users_job_carries_only_their_own_media(): void
    {
        [$userA, $userB] = User::factory()->count(2)->create()->all();

        $this->actingAs($userA)
            ->postJson('/media', ['file' => UploadedFile::fake()->image('a.jpg', 800, 600)]);
        $this->actingAs($userB)
            ->postJson('/media', ['file' => UploadedFile::fake()->image('b.jpg', 800, 600)]);

        $mediaA = Media::where('user_id', $userA->id)->first();
        $mediaB = Media::where('user_id', $userB->id)->first();

        // Each queued job is bound to the correct media record — no cross-linking
        Queue::assertPushed(ProcessImageJob::class, fn ($job) =>
            $job->media->is($mediaA) && $job->media->user_id === $userA->id
        );
        Queue::assertPushed(ProcessImageJob::class, fn ($job) =>
            $job->media->is($mediaB) && $job->media->user_id === $userB->id
        );
    }

    public function test_stored_filename_contains_no_user_controlled_components(): void
    {
        // Original filenames submitted by users must not appear in stored paths —
        // stored_filename must be purely system-generated (UUID + extension).
        $user = User::factory()->create();
        $this->actingAs($user)->postJson('/media', [
            'file' => UploadedFile::fake()->image('../../etc/passwd.jpg', 800, 600),
        ]);

        $media = Media::where('user_id', $user->id)->first();

        $this->assertNotNull($media);
        $this->assertStringNotContainsString('passwd', $media->stored_filename,
            'Stored filename must not contain user-supplied filename components.');
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f\-]{36}\.[a-z]+$/',
            $media->stored_filename,
            'Stored filename must be UUID.extension format only.'
        );
    }
}
