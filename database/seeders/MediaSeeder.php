<?php

namespace Database\Seeders;

use App\Models\Media;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Seeds demo media records across all seeded users.
 *
 * Each user receives 5 items:
 *   • 3 completed  — real GD-generated images on disk (thumbnails render in UI)
 *   • 1 pending    — source file on disk, pipeline not yet run
 *   • 1 failed     — source file on disk, pipeline error recorded
 *
 * Formats cycle: JPEG → PNG → GIF → WebP → JPEG → …
 * Outputs (resized / thumbnail / optimised) are always JPEG, matching the
 * real pipeline behaviour in ImageProcessingService.
 */
class MediaSeeder extends Seeder
{
    /** MIME types and their GD save function + file extension. */
    private const FORMATS = [
        ['mime' => 'image/jpeg', 'ext' => 'jpg',  'gd' => 'imagejpeg'],
        ['mime' => 'image/png',  'ext' => 'png',  'gd' => 'imagepng'],
        ['mime' => 'image/gif',  'ext' => 'gif',  'gd' => 'imagegif'],
        ['mime' => 'image/webp', 'ext' => 'webp', 'gd' => 'imagewebp'],
    ];

    private const FAILURE_REASONS = [
        'resize'    => 'Image dimensions exceed processing limit.',
        'thumbnail' => 'Thumbnail generation failed: out of memory.',
        'optimize'  => 'Optimisation failed: unsupported colour profile.',
    ];

    private int $formatIndex = 0;

    public function run(): void
    {
        Storage::disk('media')->makeDirectory('outputs');

        $users = User::whereIn('email', [
            'alice@mediaflow.test', 'bob@mediaflow.test',   'carol@mediaflow.test',
            'dave@mediaflow.test',  'eve@mediaflow.test',    'frank@mediaflow.test',
            'grace@mediaflow.test', 'henry@mediaflow.test',  'iris@mediaflow.test',
            'jack@mediaflow.test',
        ])->get();

        if ($users->isEmpty()) {
            $this->command->warn('  No seeded users found — run UserSeeder first.');
            return;
        }

        $count = 0;

        foreach ($users as $user) {
            // 3 completed items
            foreach (range(1, 3) as $i) {
                $this->createCompleted($user->id, $i);
                $count++;
            }

            // 1 pending
            $this->createPending($user->id);
            $count++;

            // 1 failed
            $this->createFailed($user->id);
            $count++;
        }

        $this->command->info("  <fg=green>✓</> {$count} media records seeded across {$users->count()} users.");
    }

    // -------------------------------------------------------------------------

    private function createCompleted(int $userId, int $index): void
    {
        $format = $this->nextFormat();
        $uuid   = Str::uuid()->toString();

        $originalName   = "photo_{$index}.{$format['ext']}";
        $storedFilename = "{$uuid}.{$format['ext']}";

        // Write source image (500×400, colourful)
        $this->writeGdImage($format, $storedFilename, 500, 400);

        // Write outputs (all JPEG, matching the real pipeline)
        $thumbKey     = "outputs/{$uuid}_thumbnail.jpg";
        $resizedKey   = "outputs/{$uuid}_resized.jpg";
        $optimizedKey = "outputs/{$uuid}_optimized.jpg";

        $this->writeGdImage(['mime' => 'image/jpeg', 'ext' => 'jpg', 'gd' => 'imagejpeg'], $thumbKey,    150, 100);
        $this->writeGdImage(['mime' => 'image/jpeg', 'ext' => 'jpg', 'gd' => 'imagejpeg'], $resizedKey,  800, 600);
        $this->writeGdImage(['mime' => 'image/jpeg', 'ext' => 'jpg', 'gd' => 'imagejpeg'], $optimizedKey, 800, 600);

        Media::create([
            'user_id'          => $userId,
            'uuid'             => $uuid,
            'original_filename'=> $originalName,
            'stored_filename'  => $storedFilename,
            'mime_type'        => $format['mime'],
            'file_size'        => Storage::disk('media')->size($storedFilename),
            'status'           => Media::STATUS_COMPLETED,
            'processing_step'  => null,
            'progress'         => 100,
            'error_message'    => null,
            'bytes_processed'  => Storage::disk('media')->size($storedFilename),
            'outputs'          => [
                'thumbnail' => $thumbKey,
                'resized'   => $resizedKey,
                'optimized' => $optimizedKey,
            ],
            'created_at'       => now()->subDays(rand(0, 60)),
        ]);
    }

    private function createPending(int $userId): void
    {
        $format = $this->nextFormat();
        $uuid   = Str::uuid()->toString();

        $storedFilename = "{$uuid}.{$format['ext']}";
        $this->writeGdImage($format, $storedFilename, 500, 400);

        Media::create([
            'user_id'          => $userId,
            'uuid'             => $uuid,
            'original_filename'=> "pending_upload.{$format['ext']}",
            'stored_filename'  => $storedFilename,
            'mime_type'        => $format['mime'],
            'file_size'        => Storage::disk('media')->size($storedFilename),
            'status'           => Media::STATUS_PENDING,
            'processing_step'  => null,
            'progress'         => 0,
            'error_message'    => null,
            'outputs'          => null,
            'created_at'       => now()->subMinutes(rand(1, 30)),
        ]);
    }

    private function createFailed(int $userId): void
    {
        $format    = $this->nextFormat();
        $uuid      = Str::uuid()->toString();
        $failSteps = array_keys(self::FAILURE_REASONS);
        $failStep  = $failSteps[array_rand($failSteps)];

        $storedFilename = "{$uuid}.{$format['ext']}";
        $this->writeGdImage($format, $storedFilename, 500, 400);

        Media::create([
            'user_id'          => $userId,
            'uuid'             => $uuid,
            'original_filename'=> "failed_image.{$format['ext']}",
            'stored_filename'  => $storedFilename,
            'mime_type'        => $format['mime'],
            'file_size'        => Storage::disk('media')->size($storedFilename),
            'status'           => Media::STATUS_FAILED,
            'processing_step'  => $failStep,
            'progress'         => 0,
            'error_message'    => self::FAILURE_REASONS[$failStep],
            'outputs'          => null,
            'created_at'       => now()->subHours(rand(1, 48)),
        ]);
    }

    // -------------------------------------------------------------------------

    /** Advance the format carousel and return the next entry. */
    private function nextFormat(): array
    {
        $format = self::FORMATS[$this->formatIndex % count(self::FORMATS)];
        $this->formatIndex++;
        return $format;
    }

    /**
     * Generate a colourful GD image and write it to the media disk.
     *
     * @param array{mime: string, ext: string, gd: string} $format
     */
    private function writeGdImage(array $format, string $diskPath, int $width, int $height): void
    {
        $image = imagecreatetruecolor($width, $height);

        // Random pastel background
        $r  = rand(160, 230); $g = rand(160, 230); $b = rand(160, 230);
        $bg = imagecolorallocate($image, $r, $g, $b);
        imagefill($image, 0, 0, $bg);

        // A few coloured ellipses for visual variety
        for ($i = 0; $i < 6; $i++) {
            $c = imagecolorallocate($image, rand(40, 200), rand(40, 200), rand(40, 200));
            imagefilledellipse(
                $image,
                rand(0, $width),
                rand(0, $height),
                rand((int)($width * 0.1), (int)($width * 0.5)),
                rand((int)($height * 0.1), (int)($height * 0.5)),
                $c,
            );
        }

        ob_start();
        match ($format['gd']) {
            'imagejpeg' => imagejpeg($image, null, 85),
            'imagepng'  => imagepng($image, null, 6),
            'imagegif'  => imagegif($image),
            'imagewebp' => imagewebp($image, null, 85),
            default     => imagejpeg($image, null, 85),
        };
        $data = ob_get_clean();
        imagedestroy($image);

        Storage::disk('media')->put($diskPath, $data);
    }
}
