<?php

namespace Tests\Unit\Services;

use App\Services\ImageProcessingService;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ImageProcessingServiceTest extends TestCase
{
    private ImageProcessingService $service;
    private string $tmpDir;
    private string $fixturePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new ImageProcessingService();

        // Create a real temp directory for I/O — Intervention Image needs real FS paths
        $this->tmpDir = sys_get_temp_dir() . '/mediaflow_tests_' . uniqid();
        mkdir($this->tmpDir . '/outputs', 0775, true);

        // Generate a 400×300 JPEG fixture using GD
        $this->fixturePath = $this->tmpDir . '/fixture.jpg';
        $img = imagecreatetruecolor(400, 300);
        $bg  = imagecolorallocate($img, 100, 149, 237); // cornflower blue
        imagefill($img, 0, 0, $bg);
        imagejpeg($img, $this->fixturePath, 90);
        imagedestroy($img);
    }

    protected function tearDown(): void
    {
        // Clean up temp directory
        $this->deleteDirectory($this->tmpDir);
        parent::tearDown();
    }

    // -----------------------------------------------------------------------
    // resize()
    // -----------------------------------------------------------------------

    public function test_resize_creates_output_file(): void
    {
        $outputPath = $this->service->resize($this->fixturePath, 200, 150, $this->tmpDir . '/outputs');

        $this->assertFileExists($outputPath);
    }

    public function test_resize_output_has_correct_dimensions(): void
    {
        $outputPath = $this->service->resize($this->fixturePath, 200, 150, $this->tmpDir . '/outputs');

        [$width, $height] = getimagesize($outputPath);
        $this->assertEquals(200, $width);
        $this->assertEquals(150, $height);
    }

    public function test_resize_returns_output_path_string(): void
    {
        $outputPath = $this->service->resize($this->fixturePath, 200, 150, $this->tmpDir . '/outputs');

        $this->assertIsString($outputPath);
        $this->assertStringContainsString('outputs', $outputPath);
    }

    // -----------------------------------------------------------------------
    // thumbnail()
    // -----------------------------------------------------------------------

    public function test_thumbnail_creates_output_file(): void
    {
        $outputPath = $this->service->thumbnail($this->fixturePath, 100, $this->tmpDir . '/outputs');

        $this->assertFileExists($outputPath);
    }

    public function test_thumbnail_is_square(): void
    {
        $outputPath = $this->service->thumbnail($this->fixturePath, 100, $this->tmpDir . '/outputs');

        [$width, $height] = getimagesize($outputPath);
        $this->assertEquals(100, $width);
        $this->assertEquals(100, $height);
    }

    public function test_thumbnail_returns_output_path_string(): void
    {
        $outputPath = $this->service->thumbnail($this->fixturePath, 100, $this->tmpDir . '/outputs');

        $this->assertIsString($outputPath);
        $this->assertStringContainsString('thumbnail', $outputPath);
    }

    // -----------------------------------------------------------------------
    // optimize()
    // -----------------------------------------------------------------------

    public function test_optimize_creates_output_file(): void
    {
        $outputPath = $this->service->optimize($this->fixturePath, $this->tmpDir . '/outputs');

        $this->assertFileExists($outputPath);
    }

    public function test_optimize_output_is_smaller_than_input(): void
    {
        // Generate a high-quality large image for a meaningful size comparison
        $bigFixture = $this->tmpDir . '/big.jpg';
        $img = imagecreatetruecolor(1200, 900);
        for ($x = 0; $x < 1200; $x++) {
            for ($y = 0; $y < 900; $y++) {
                $c = imagecolorallocate($img, rand(0, 255), rand(0, 255), rand(0, 255));
                imagesetpixel($img, $x, $y, $c);
            }
        }
        imagejpeg($img, $bigFixture, 100);
        imagedestroy($img);

        $outputPath = $this->service->optimize($bigFixture, $this->tmpDir . '/outputs');

        $this->assertLessThan(filesize($bigFixture), filesize($outputPath));
    }

    public function test_optimize_returns_output_path_string(): void
    {
        $outputPath = $this->service->optimize($this->fixturePath, $this->tmpDir . '/outputs');

        $this->assertIsString($outputPath);
        $this->assertStringContainsString('optimized', $outputPath);
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function deleteDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . '/' . $item;
            is_dir($path) ? $this->deleteDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }
}
