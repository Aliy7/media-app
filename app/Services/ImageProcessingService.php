<?php

namespace App\Services;

use App\Exceptions\ImageProcessingException;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManager;
use Intervention\Image\Interfaces\ImageInterface; // used in private encode helpers

class ImageProcessingService
{
    public function __construct(private readonly ImageManager $manager) {}

    /**
     * Resize an image to the given dimensions.
     *
     * @param  string  $path   Path relative to the given disk
     * @param  int     $width  Target width in pixels
     * @param  int     $height Target height in pixels
     * @param  string  $disk   Laravel storage disk name
     * @return string          Output path on the same disk
     *
     * @throws ImageProcessingException
     */
    public function resize(string $path, int $width, int $height, string $disk = 'media'): string
    {
        try {
            $contents   = Storage::disk($disk)->get($path);
            $outputPath = $this->outputPath($path, 'resized');

            $image   = $this->manager->read($contents)->resize($width, $height);
            $encoded = $this->encodeForFormat($image, $path, quality: 90);

            Storage::disk($disk)->put($outputPath, $encoded);

            return $outputPath;
        } catch (ImageProcessingException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new ImageProcessingException("Resize failed for [{$path}]: {$e->getMessage()}", previous: $e);
        }
    }

    /**
     * Generate a thumbnail by cropping to fill the given dimensions.
     *
     * @param  string  $path    Path relative to the given disk
     * @param  int     $width   Target width in pixels
     * @param  int     $height  Target height in pixels
     * @param  string  $disk    Laravel storage disk name
     * @return string           Output path on the same disk
     *
     * @throws ImageProcessingException
     */
    public function thumbnail(string $path, int $width, int $height, string $disk = 'media'): string
    {
        try {
            $contents   = Storage::disk($disk)->get($path);
            $outputPath = $this->outputPath($path, 'thumbnail');

            $image   = $this->manager->read($contents)->cover($width, $height);
            $encoded = $this->encodeForFormat($image, $path, quality: 85);

            Storage::disk($disk)->put($outputPath, $encoded);

            return $outputPath;
        } catch (ImageProcessingException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new ImageProcessingException("Thumbnail generation failed for [{$path}]: {$e->getMessage()}", previous: $e);
        }
    }

    /**
     * Optimise an image by reducing quality. Format is preserved from the source file.
     *
     * @param  string  $path  Path relative to the given disk
     * @param  string  $disk  Laravel storage disk name
     * @return string         Output path on the same disk
     *
     * @throws ImageProcessingException
     */
    public function optimize(string $path, string $disk = 'media'): string
    {
        try {
            $contents   = Storage::disk($disk)->get($path);
            $outputPath = $this->outputPath($path, 'optimized');

            $image   = $this->manager->read($contents);
            $encoded = $this->encodeOptimized($image, $path);

            Storage::disk($disk)->put($outputPath, $encoded);

            return $outputPath;
        } catch (ImageProcessingException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new ImageProcessingException("Optimization failed for [{$path}]: {$e->getMessage()}", previous: $e);
        }
    }

    // -----------------------------------------------------------------------
    // Private helpers
    // -----------------------------------------------------------------------

    private function outputPath(string $sourcePath, string $suffix): string
    {
        $dir      = ltrim(dirname($sourcePath), '.');
        $filename = pathinfo($sourcePath, PATHINFO_FILENAME);
        $ext      = pathinfo($sourcePath, PATHINFO_EXTENSION) ?: 'jpg';
        $prefix   = $dir ? $dir . '/' : '';

        return $prefix . 'outputs/' . $filename . '_' . $suffix . '.' . $ext;
    }

    private function encodeForFormat(ImageInterface $image, string $sourcePath, int $quality = 90): string
    {
        return match (strtolower(pathinfo($sourcePath, PATHINFO_EXTENSION))) {
            'png'        => $image->toPng()->toString(),
            'gif'        => $image->toGif()->toString(),
            'webp'       => $image->toWebp($quality)->toString(),
            'avif'       => $image->toAvif($quality)->toString(),
            default      => $image->toJpeg($quality)->toString(),
        };
    }

    private function encodeOptimized(ImageInterface $image, string $sourcePath): string
    {
        return match (strtolower(pathinfo($sourcePath, PATHINFO_EXTENSION))) {
            'png'   => $image->toPng()->toString(),   // PNG is lossless; compression handled by encoder defaults
            'gif'   => $image->toGif()->toString(),
            'webp'  => $image->toWebp(65)->toString(),
            'avif'  => $image->toAvif(65)->toString(),
            default => $image->toJpeg(75)->toString(),
        };
    }
}
