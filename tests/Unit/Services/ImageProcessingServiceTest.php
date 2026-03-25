<?php

namespace Tests\Unit\Services;

use App\Exceptions\ImageProcessingException;
use App\Services\ImageProcessingService;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\ImageManager;
use Tests\TestCase;

class ImageProcessingServiceTest extends TestCase
{
    private ImageProcessingService $service;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('media');

        // Inject ImageManager directly — GD is lighter for unit tests
        $this->service = new ImageProcessingService(new ImageManager(new GdDriver()));

        // Generate a 400×300 JPEG fixture and put it on the fake disk
        $tmp = $this->generateJpeg(400, 300, quality: 90);
        Storage::disk('media')->put('fixture.jpg', file_get_contents($tmp));
        unlink($tmp);

        // PNG fixture
        $tmp = $this->generatePng(400, 300);
        Storage::disk('media')->put('fixture.png', file_get_contents($tmp));
        unlink($tmp);
    }

    // -----------------------------------------------------------------------
    // resize()
    // -----------------------------------------------------------------------

    public function test_resize_writes_output_file_to_storage(): void
    {
        $outputPath = $this->service->resize('fixture.jpg', 200, 150, 'media');

        Storage::disk('media')->assertExists($outputPath);
    }

    public function test_resize_output_has_correct_dimensions(): void
    {
        $outputPath = $this->service->resize('fixture.jpg', 200, 150, 'media');

        [$width, $height] = getimagesize($this->absolutePath($outputPath));
        $this->assertEquals(200, $width);
        $this->assertEquals(150, $height);
    }

    public function test_resize_output_path_contains_resized_suffix(): void
    {
        $outputPath = $this->service->resize('fixture.jpg', 200, 150, 'media');

        $this->assertStringContainsString('resized', $outputPath);
    }

    public function test_resize_preserves_format_for_png(): void
    {
        $outputPath = $this->service->resize('fixture.png', 200, 150, 'media');

        $this->assertStringEndsWith('.png', $outputPath);
        Storage::disk('media')->assertExists($outputPath);
    }

    // -----------------------------------------------------------------------
    // thumbnail()
    // -----------------------------------------------------------------------

    public function test_thumbnail_writes_output_file_to_storage(): void
    {
        $outputPath = $this->service->thumbnail('fixture.jpg', 100, 'media');

        Storage::disk('media')->assertExists($outputPath);
    }

    public function test_thumbnail_produces_square_output(): void
    {
        $outputPath = $this->service->thumbnail('fixture.jpg', 100, 'media');

        [$width, $height] = getimagesize($this->absolutePath($outputPath));
        $this->assertEquals(100, $width);
        $this->assertEquals(100, $height);
    }

    public function test_thumbnail_output_path_contains_thumbnail_suffix(): void
    {
        $outputPath = $this->service->thumbnail('fixture.jpg', 100, 'media');

        $this->assertStringContainsString('thumbnail', $outputPath);
    }

    // -----------------------------------------------------------------------
    // optimize()
    // -----------------------------------------------------------------------

    public function test_optimize_writes_output_file_to_storage(): void
    {
        $outputPath = $this->service->optimize('fixture.jpg', 'media');

        Storage::disk('media')->assertExists($outputPath);
    }

    public function test_optimize_output_path_contains_optimized_suffix(): void
    {
        $outputPath = $this->service->optimize('fixture.jpg', 'media');

        $this->assertStringContainsString('optimized', $outputPath);
    }

    public function test_optimize_reduces_filesize_for_high_quality_jpeg(): void
    {
        // Generate a large, noisy, high-quality JPEG for a meaningful size comparison
        $tmp = $this->generateNoisyJpeg(1000, 800, quality: 100);
        Storage::disk('media')->put('noisy.jpg', file_get_contents($tmp));
        unlink($tmp);

        $outputPath = $this->service->optimize('noisy.jpg', 'media');

        $inputSize  = strlen(Storage::disk('media')->get('noisy.jpg'));
        $outputSize = strlen(Storage::disk('media')->get($outputPath));

        $this->assertLessThan($inputSize, $outputSize);
    }

    public function test_optimize_preserves_format_for_png(): void
    {
        $outputPath = $this->service->optimize('fixture.png', 'media');

        $this->assertStringEndsWith('.png', $outputPath);
        Storage::disk('media')->assertExists($outputPath);
    }

    // -----------------------------------------------------------------------
    // Exception handling
    // -----------------------------------------------------------------------

    public function test_resize_throws_image_processing_exception_on_invalid_file(): void
    {
        Storage::disk('media')->put('corrupt.jpg', 'this is not an image');

        $this->expectException(ImageProcessingException::class);
        $this->expectExceptionMessageMatches('/Resize failed/');

        $this->service->resize('corrupt.jpg', 200, 150, 'media');
    }

    public function test_thumbnail_throws_image_processing_exception_on_invalid_file(): void
    {
        Storage::disk('media')->put('corrupt.jpg', 'this is not an image');

        $this->expectException(ImageProcessingException::class);
        $this->expectExceptionMessageMatches('/Thumbnail/');

        $this->service->thumbnail('corrupt.jpg', 100, 'media');
    }

    public function test_optimize_throws_image_processing_exception_on_invalid_file(): void
    {
        Storage::disk('media')->put('corrupt.jpg', 'this is not an image');

        $this->expectException(ImageProcessingException::class);
        $this->expectExceptionMessageMatches('/Optimiz/');

        $this->service->optimize('corrupt.jpg', 'media');
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function absolutePath(string $relativePath): string
    {
        return Storage::disk('media')->path($relativePath);
    }

    private function generateJpeg(int $width, int $height, int $quality = 90): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'mediaflow_');
        $img = imagecreatetruecolor($width, $height);
        $bg  = imagecolorallocate($img, 100, 149, 237);
        imagefill($img, 0, 0, $bg);
        imagejpeg($img, $tmp, $quality);
        imagedestroy($img);
        return $tmp;
    }

    private function generatePng(int $width, int $height): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'mediaflow_') . '.png';
        $img = imagecreatetruecolor($width, $height);
        $bg  = imagecolorallocate($img, 80, 200, 120);
        imagefill($img, 0, 0, $bg);
        imagepng($img, $tmp);
        imagedestroy($img);
        return $tmp;
    }

    private function generateNoisyJpeg(int $width, int $height, int $quality = 100): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'mediaflow_');
        $img = imagecreatetruecolor($width, $height);
        for ($x = 0; $x < $width; $x += 4) {
            for ($y = 0; $y < $height; $y += 4) {
                $c = imagecolorallocate($img, rand(0, 255), rand(0, 255), rand(0, 255));
                imagefilledrectangle($img, $x, $y, $x + 3, $y + 3, $c);
            }
        }
        imagejpeg($img, $tmp, $quality);
        imagedestroy($img);
        return $tmp;
    }
}
