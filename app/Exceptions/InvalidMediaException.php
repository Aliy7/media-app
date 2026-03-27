<?php

namespace App\Exceptions;

use Exception;

class InvalidMediaException extends Exception
{
    public static function storageFailure(): self
    {
        return new self('Failed to store the uploaded file.');
    }

    public static function invalidMimeType(string $mimeType): self
    {
        return new self("File type '{$mimeType}' is not allowed. Accepted types: JPEG, PNG, GIF, WebP.");
    }

    public static function fileTooLarge(int $sizeBytes, int $limitBytes): self
    {
        $sizeMb  = round($sizeBytes / 1048576, 1);
        $limitMb = round($limitBytes / 1048576);

        return new self("File size {$sizeMb}MB exceeds the {$limitMb}MB limit.");
    }

    public static function dimensionsTooSmall(int $width, int $height): self
    {
        return new self("Image dimensions {$width}×{$height}px are below the minimum 100×100px.");
    }

    public static function dimensionsTooLarge(int $width, int $height): self
    {
        return new self("Image dimensions {$width}×{$height}px exceed the maximum 8000×8000px.");
    }

    public static function unreadableFile(): self
    {
        return new self('The uploaded file could not be read as an image.');
    }

    public static function filenameTooLong(int $length): self
    {
        return new self("Filename ({$length} characters) exceeds the 255-character limit.");
    }
}
