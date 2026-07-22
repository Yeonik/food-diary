<?php

declare(strict_types=1);

namespace App\Nutrition;

use App\Nutrition\Exceptions\InvalidPhotoException;

/**
 * Turns an attacker-controlled upload into a {@see PreparedPhoto} safe to send
 * to a third-party recogniser. Three deliberate security steps, each required
 * by the brief:
 *
 *   1. Validate by CONTENT, not extension — a file is an image only if it
 *      decodes as one of the allowed types.
 *   2. Strip EXIF by re-encoding through GD — phone photos carry GPS, and
 *      sending home coordinates to an API is a disclosure the user did not ask
 *      for. This is a README claim, so it is enforced here, not just promised.
 *      The EXIF orientation is first baked into the pixels, so dropping the tag
 *      leaves the photo standing correctly rather than on its side.
 *   3. Store under a GENERATED name — the client filename is discarded and
 *      never becomes part of a path.
 */
class PhotoPreparer
{
    /** @var list<int> */
    private const ALLOWED_TYPES = [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP];

    public function __construct(private readonly int $maxDimension = 1600) {}

    /**
     * @throws InvalidPhotoException
     */
    public function prepare(string $sourcePath, string $targetDirectory): PreparedPhoto
    {
        $data = @file_get_contents($sourcePath);
        if ($data === false || $data === '') {
            throw new InvalidPhotoException('The upload could not be read.');
        }

        // Step 1 — content validation. getimagesizefromstring returns false for
        // anything that is not a real image, whatever the extension claimed.
        $info = @getimagesizefromstring($data);
        if ($info === false || ! in_array($info[2], self::ALLOWED_TYPES, true)) {
            throw new InvalidPhotoException('The upload is not an image of an allowed type.');
        }

        $image = @imagecreatefromstring($data);
        if ($image === false) {
            throw new InvalidPhotoException('The image could not be decoded.');
        }

        // A phone records the shot's rotation as an EXIF tag rather than
        // rotating the pixels. GD decodes the raw pixels and drops EXIF, so
        // without this the tag would be lost and the photo stored on its side.
        // Bake the rotation into the pixels here, while the source EXIF is still
        // readable — so the picture stands correctly AND carries no orientation.
        $image = $this->applyOrientation($image, $sourcePath);

        // Step 2 begins — resize on a freshly decoded canvas. Neither the
        // decode nor the JPEG re-encode below carries the source's EXIF block.
        $image = $this->resizeWithinBounds($image);

        // Step 3 — a generated name, unrelated to anything the client sent.
        if (! is_dir($targetDirectory)) {
            mkdir($targetDirectory, 0755, true);
        }
        $targetPath = rtrim($targetDirectory, '/\\').DIRECTORY_SEPARATOR.bin2hex(random_bytes(16)).'.jpg';

        // Encode in memory so the one remaining scrap of metadata — GD's own
        // "CREATOR: gd-jpeg" comment — can be removed before anything is written.
        // The result carries no EXIF and no comment at all.
        ob_start();
        $encoded = imagejpeg($image, null, 85);
        $jpeg = (string) ob_get_clean();

        if ($encoded === false || $jpeg === '') {
            throw new InvalidPhotoException('The prepared image could not be encoded.');
        }

        if (file_put_contents($targetPath, $this->stripComments($jpeg)) === false) {
            throw new InvalidPhotoException('The prepared image could not be written.');
        }

        $width = imagesx($image);
        $height = imagesy($image);

        return new PreparedPhoto(
            path: $targetPath,
            mimeType: 'image/jpeg',
            width: $width,
            height: $height,
        );
    }

    /**
     * Remove JPEG comment segments (marker 0xFFFE) from an encoded image,
     * leaving the image data and everything else untouched. This is the last
     * non-pixel bytes GD leaves behind after a re-encode.
     */
    private function stripComments(string $jpeg): string
    {
        $length = strlen($jpeg);

        // Must begin with the Start-Of-Image marker; if not, leave it alone.
        if ($length < 2 || $jpeg[0] !== "\xFF" || $jpeg[1] !== "\xD8") {
            return $jpeg;
        }

        $out = "\xFF\xD8";
        $pos = 2;

        while ($pos + 1 < $length) {
            if ($jpeg[$pos] !== "\xFF") {
                $out .= substr($jpeg, $pos);
                break;
            }

            $marker = ord($jpeg[$pos + 1]);

            // Start of scan: the compressed pixel data runs to the end.
            if ($marker === 0xDA) {
                $out .= substr($jpeg, $pos);
                break;
            }

            // Standalone markers (TEM, restart markers) have no length field.
            if ($marker === 0x01 || ($marker >= 0xD0 && $marker <= 0xD7)) {
                $out .= substr($jpeg, $pos, 2);
                $pos += 2;

                continue;
            }

            if ($pos + 3 >= $length) {
                $out .= substr($jpeg, $pos);
                break;
            }

            $segmentLength = 2 + ((ord($jpeg[$pos + 2]) << 8) | ord($jpeg[$pos + 3]));

            // 0xFFFE is a comment — drop it; copy every other segment verbatim.
            if ($marker !== 0xFE) {
                $out .= substr($jpeg, $pos, $segmentLength);
            }

            $pos += $segmentLength;
        }

        return $out;
    }

    /**
     * Apply the source's EXIF orientation to the pixels, so the stored image
     * stands the right way up once the tag is gone. Covers all eight orientation
     * values — the six that rotate, plus the two pure mirrors. Sources without
     * EXIF (PNG, WebP, an untagged JPEG) report orientation 1 and are untouched.
     */
    private function applyOrientation(\GdImage $image, string $sourcePath): \GdImage
    {
        $exif = @exif_read_data($sourcePath);
        $orientation = is_array($exif) && isset($exif['Orientation']) ? (int) $exif['Orientation'] : 1;

        // Orientations 2, 4, 5 and 7 are mirrored; 5 and 7 also carry a rotation.
        if (in_array($orientation, [2, 4, 5, 7], true)) {
            imageflip($image, $orientation === 4 ? IMG_FLIP_VERTICAL : IMG_FLIP_HORIZONTAL);
        }

        // imagerotate turns anticlockwise for a positive angle, so a clockwise
        // correction is a negative one. 6 → 90° CW, 8 → 90° CCW, 3 → 180°.
        $angle = match ($orientation) {
            3 => 180,
            6, 5 => -90,
            8, 7 => 90,
            default => 0,
        };

        if ($angle !== 0) {
            $rotated = imagerotate($image, $angle, 0);
            if ($rotated !== false) {
                $image = $rotated;
            }
        }

        return $image;
    }

    private function resizeWithinBounds(\GdImage $image): \GdImage
    {
        $width = imagesx($image);
        $height = imagesy($image);
        $longest = max($width, $height);

        if ($longest <= $this->maxDimension) {
            return $image;
        }

        $scale = $this->maxDimension / $longest;
        $scaled = imagescale($image, (int) round($width * $scale), (int) round($height * $scale));

        return $scaled === false ? $image : $scaled;
    }
}
