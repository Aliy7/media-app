<?php

namespace Tests\Unit\Services;

use App\Exceptions\InvalidMediaException;
use App\Models\Media;
use App\Models\User;
use App\Services\MediaUploadService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MediaUploadServiceTest extends TestCase
{
    use RefreshDatabase;

    private MediaUploadService $service;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('media');
        Queue::fake();
        $this->service = new MediaUploadService();
    }

    public function test_valid_image_creates_media_record(): void
    {
        $user  = User::factory()->create();
        $file  = UploadedFile::fake()->image('photo.jpg', 800, 600);

        $media = $this->service->handle($file, $user);

        $this->assertInstanceOf(Media::class, $media);
        $this->assertDatabaseHas('media', [
            'user_id'           => $user->id,
            'original_filename' => 'photo.jpg',
            'mime_type'         => 'image/jpeg',
            'status'            => Media::STATUS_PENDING,
        ]);
    }

    public function test_valid_image_stores_file_on_disk(): void
    {
        $user  = User::factory()->create();
        $file  = UploadedFile::fake()->image('photo.png', 800, 600);

        $media = $this->service->handle($file, $user);

        Storage::disk('media')->assertExists($media->stored_filename);
    }

    public function test_returned_media_has_uuid(): void
    {
        $user  = User::factory()->create();
        $file  = UploadedFile::fake()->image('photo.jpg', 800, 600);

        $media = $this->service->handle($file, $user);

        $this->assertNotNull($media->uuid);
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/',
            $media->uuid
        );
    }

    public function test_invalid_mime_type_throws_exception(): void
    {
        $user = User::factory()->create();
        $file = UploadedFile::fake()->create('document.pdf', 500, 'application/pdf');

        $this->expectException(InvalidMediaException::class);
        $this->expectExceptionMessageMatches('/not allowed/');

        $this->service->handle($file, $user);
    }

    public function test_file_exceeding_size_limit_throws_exception(): void
    {
        $user = User::factory()->create();
        // 11MB — exceeds 10MB limit
        $file = UploadedFile::fake()->image('big.jpg', 800, 600)->size(11264);

        $this->expectException(InvalidMediaException::class);
        $this->expectExceptionMessageMatches('/exceeds/');

        $this->service->handle($file, $user);
    }

    public function test_image_below_minimum_dimensions_throws_exception(): void
    {
        $user = User::factory()->create();
        $file = UploadedFile::fake()->image('tiny.jpg', 50, 50);

        $this->expectException(InvalidMediaException::class);
        $this->expectExceptionMessageMatches('/below the minimum/');

        $this->service->handle($file, $user);
    }

    public function test_image_exceeding_maximum_dimensions_throws_exception(): void
    {
        $user = User::factory()->create();
        // 8001×100 exceeds the 8000px max width without exhausting GD memory
        $file = UploadedFile::fake()->image('huge.jpg', 8001, 100);

        $this->expectException(InvalidMediaException::class);
        $this->expectExceptionMessageMatches('/exceed the maximum/');

        $this->service->handle($file, $user);
    }

    public function test_no_database_record_created_on_invalid_file(): void
    {
        $user = User::factory()->create();
        $file = UploadedFile::fake()->create('document.pdf', 500, 'application/pdf');

        try {
            $this->service->handle($file, $user);
        } catch (InvalidMediaException) {
        }

        $this->assertDatabaseCount('media', 0);
    }
}
