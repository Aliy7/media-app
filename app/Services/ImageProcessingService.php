<?php

namespace App\Services;

use Intervention\Image\Drivers\Imagick\Driver as ImagickDriver;
use Intervention\Image\ImageManager;

class ImageProcessingService
{
    private ImageManager $manager;

    public function __construct()
    {
        $this->manager = new ImageManager(new ImagickDriver());
    }

    /**
     * Resize an image to the given dimensions.
     *
     * @param  string  $path        Absolute path to the source image
     * @param  int     $width       Target width in pixels
     * @param  int     $height      Target height in pixels
     * @param  string  $outputDir   Directory to write the resized file into
     * @return string               Absolute path of the written file
     */
    public function resize(string $path, int $width, int $height, string $outputDir): string
    {
        $filename   = pathinfo($path, PATHINFO_FILENAME);
        $extension  = pathinfo($path, PATHINFO_EXTENSION) ?: 'jpg';
        $outputPath = $outputDir . '/' . $filename . '_resized.' . $extension;

        $this->manager
            ->read($path)
            ->resize($width, $height)
            ->save($outputPath);

        return $outputPath;
    }

    /**
     * Generate a square thumbnail by cropping to fill the given size.
     *
     * @param  string  $path        Absolute path to the source image
     * @param  int     $size        Side length in pixels for the square thumbnail
     * @param  string  $outputDir   Directory to write the thumbnail into
     * @return string               Absolute path of the written file
     */
    public function thumbnail(string $path, int $size, string $outputDir): string
    {
        $filename   = pathinfo($path, PATHINFO_FILENAME);
        $extension  = pathinfo($path, PATHINFO_EXTENSION) ?: 'jpg';
        $outputPath = $outputDir . '/' . $filename . '_thumbnail.' . $extension;

        $this->manager
            ->read($path)
            ->cover($size, $size)
            ->save($outputPath);

        return $outputPath;
    }

    /**
     * Optimise an image by reducing JPEG quality to 75.
     *
     * @param  string  $path        Absolute path to the source image
     * @param  string  $outputDir   Directory to write the optimised file into
     * @return string               Absolute path of the written file
     */
    public function optimize(string $path, string $outputDir): string
    {
        $filename   = pathinfo($path, PATHINFO_FILENAME);
        $outputPath = $outputDir . '/' . $filename . '_optimized.jpg';

        $this->manager
            ->read($path)
            ->toJpeg(75)
            ->save($outputPath);

        return $outputPath;
    }
}
