<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Nutrition\Exceptions\InvalidPhotoException;
use App\Nutrition\PhotoPreparer;
use PHPUnit\Framework\TestCase;

class PhotoPreparerTest extends TestCase
{
    private string $workDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workDir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'fd-'.bin2hex(random_bytes(4));
        mkdir($this->workDir, 0755, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->workDir.DIRECTORY_SEPARATOR.'*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($this->workDir);

        parent::tearDown();
    }

    public function test_a_file_that_is_not_an_image_is_rejected(): void
    {
        // A .jpg extension on content that is plainly not an image: validation
        // is by content, so this must be rejected.
        $notAnImage = $this->workDir.DIRECTORY_SEPARATOR.'payload.jpg';
        file_put_contents($notAnImage, 'this is not an image, whatever the extension says');

        $this->expectException(InvalidPhotoException::class);

        (new PhotoPreparer)->prepare($notAnImage, $this->workDir);
    }

    public function test_the_client_filename_never_reaches_the_stored_path(): void
    {
        $source = $this->workDir.DIRECTORY_SEPARATOR.'my-home-address-photo.jpg';
        copy($this->fixture(), $source);

        $prepared = (new PhotoPreparer)->prepare($source, $this->workDir);

        $storedName = basename($prepared->path);
        $this->assertStringNotContainsString('my-home-address-photo', $storedName);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{32}\.jpg$/', $storedName);
    }

    public function test_all_exif_metadata_is_stripped_not_only_gps(): void
    {
        // Sanity: the fixture really carries identifying metadata to begin with —
        // GPS coordinates, the device make and model, and the capture time —
        // otherwise the test would pass vacuously.
        $before = @exif_read_data($this->fixture());
        $this->assertIsArray($before);
        foreach (['GPSLatitude', 'Make', 'Model', 'DateTimeOriginal'] as $tag) {
            $this->assertArrayHasKey($tag, $before, "Fixture should carry {$tag}.");
        }

        $prepared = (new PhotoPreparer)->prepare($this->fixture(), $this->workDir);

        // exif_read_data always reports file-level facts (FileName, FileSize,
        // MimeType) computed from the file itself — those are not embedded
        // metadata. What must be gone is every embedded section and every camera
        // tag: no EXIF, no GPS, no comment.
        $after = @exif_read_data($prepared->path);
        $this->assertIsArray($after);
        $this->assertSame('', $after['SectionsFound'] ?? null, 'The prepared image still has metadata sections.');
        foreach (['GPSLatitude', 'GPSLongitude', 'Make', 'Model', 'DateTimeOriginal'] as $tag) {
            $this->assertArrayNotHasKey($tag, $after, "Tag {$tag} survived preparation.");
        }
    }

    public function test_a_phone_photos_orientation_is_baked_into_the_pixels_and_all_exif_removed(): void
    {
        $fixture = $this->phoneFixture();

        // Sanity: a real phone-like frame — landscape pixels tagged to display
        // rotated 90° clockwise, carrying GPS and camera identity.
        $before = @exif_read_data($fixture);
        $this->assertIsArray($before);
        $this->assertSame(6, (int) ($before['Orientation'] ?? 0), 'Fixture should carry Orientation 6.');
        $this->assertArrayHasKey('GPSLatitude', $before);
        $size = getimagesize($fixture);
        $this->assertIsArray($size);
        $this->assertSame([64, 48], [$size[0], $size[1]], 'Fixture pixels are stored landscape.');

        $prepared = (new PhotoPreparer)->prepare($fixture, $this->workDir);

        // The tag is gone with the rest of the EXIF — no sections, no orientation,
        // no GPS survive.
        $after = @exif_read_data($prepared->path);
        $this->assertIsArray($after);
        $this->assertSame('', $after['SectionsFound'] ?? null, 'The prepared image still has metadata sections.');
        $this->assertArrayNotHasKey('Orientation', $after);
        $this->assertArrayNotHasKey('GPSLatitude', $after);

        // But the rotation was applied to the PIXELS, not lost with the tag: the
        // stored 64×48 landscape now stands as a 48×64 portrait.
        $this->assertSame(48, $prepared->width);
        $this->assertSame(64, $prepared->height);

        // And in the right direction — Orientation 6 rotates clockwise, which
        // moves the left (red) half of the stored frame to the top.
        $out = imagecreatefromjpeg($prepared->path);
        $this->assertNotFalse($out);
        $this->assertTrue($this->redDominates($out, 24, 12), 'The top of the corrected image should be the red half.');
        $this->assertFalse($this->redDominates($out, 24, 52), 'The bottom of the corrected image should be the blue half.');
        imagedestroy($out);
    }

    /**
     * Whether the pixel at ($x, $y) is dominantly red rather than blue. Solid
     * colour blocks survive JPEG quantisation well away from their boundary, so
     * comparing the channels is a stable check of which half landed here.
     */
    private function redDominates(\GdImage $image, int $x, int $y): bool
    {
        $rgb = imagecolorat($image, $x, $y);

        return (($rgb >> 16) & 0xFF) > ($rgb & 0xFF);
    }

    private function fixture(): string
    {
        return dirname(__DIR__).DIRECTORY_SEPARATOR.'Fixtures'.DIRECTORY_SEPARATOR.'meal-with-gps.jpg';
    }

    private function phoneFixture(): string
    {
        return dirname(__DIR__).DIRECTORY_SEPARATOR.'Fixtures'.DIRECTORY_SEPARATOR.'phone-portrait-gps.jpg';
    }
}
