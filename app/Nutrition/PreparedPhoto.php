<?php

declare(strict_types=1);

namespace App\Nutrition;

/**
 * A photo that has been made safe to send onward: validated as a real image,
 * re-encoded (which drops EXIF, including the GPS tags phone cameras embed),
 * resized, and stored under a generated name. The original client filename is
 * gone by this point — it never reaches a path.
 */
final readonly class PreparedPhoto
{
    public function __construct(
        public string $path,
        public string $mimeType,
        public int $width,
        public int $height,
    ) {}

    public function contents(): string
    {
        $data = file_get_contents($this->path);

        return $data === false ? '' : $data;
    }
}
