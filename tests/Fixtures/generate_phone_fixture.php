<?php

declare(strict_types=1);

/**
 * Regenerates the phone-like JPEG fixture used by PhotoPreparerTest for the
 * orientation guarantee.
 *
 * A phone does not rotate the pixels it captures; it stores the frame in sensor
 * order and records the rotation as an EXIF Orientation tag. Stripping EXIF
 * without first applying that tag would leave the photo on its side, so the
 * test needs a fixture that carries a real Orientation tag (plus GPS and camera
 * identity, like any phone shot). The pixels are split left-red / right-blue so
 * the test can prove the rotation went the right way, not just that a tag
 * vanished.
 *
 * GD cannot write EXIF, so regenerating this needs the `lsolesen/pel` library,
 * installed only for that purpose and then removed (abandoned upstream, so
 * deliberately not a standing dependency):
 *
 *     composer require --dev lsolesen/pel
 *     php tests/Fixtures/generate_phone_fixture.php
 *     composer remove --dev lsolesen/pel
 */

use lsolesen\pel\PelEntryAscii;
use lsolesen\pel\PelEntryByte;
use lsolesen\pel\PelEntryRational;
use lsolesen\pel\PelEntryShort;
use lsolesen\pel\PelEntryTime;
use lsolesen\pel\PelExif;
use lsolesen\pel\PelIfd;
use lsolesen\pel\PelJpeg;
use lsolesen\pel\PelTag;
use lsolesen\pel\PelTiff;

require __DIR__.'/../../vendor/autoload.php';

// Stored landscape 64×48, tagged to display rotated 90° CW (Orientation 6). The
// left half is red, the right half blue; after a correct clockwise rotation the
// left half becomes the top half of a standing 48×64 portrait.
$image = imagecreatetruecolor(64, 48);
if ($image === false) {
    fwrite(STDERR, "Could not create image.\n");
    exit(1);
}
imagefilledrectangle($image, 0, 0, 31, 47, imagecolorallocate($image, 220, 30, 30));
imagefilledrectangle($image, 32, 0, 63, 47, imagecolorallocate($image, 30, 30, 220));

$jpeg = new PelJpeg($image);

$exif = new PelExif;
$jpeg->setExif($exif);

$tiff = new PelTiff;
$exif->setTiff($tiff);

$ifd0 = new PelIfd(PelIfd::IFD0);
$tiff->setIfd($ifd0);

// The orientation tag a phone writes instead of rotating the pixels.
$ifd0->addEntry(new PelEntryShort(PelTag::ORIENTATION, 6));

// Identifying metadata a phone embeds beyond GPS — device and capture time.
$ifd0->addEntry(new PelEntryAscii(PelTag::MAKE, 'ACME'));
$ifd0->addEntry(new PelEntryAscii(PelTag::MODEL, 'PhoneCam X100'));

$exifIfd = new PelIfd(PelIfd::EXIF);
$ifd0->addSubIfd($exifIfd);
$exifIfd->addEntry(new PelEntryTime(PelTag::DATE_TIME_ORIGINAL, 1_717_243_800, PelEntryTime::EXIF_STRING));

// A GPS sub-IFD with a real coordinate — a home address is exactly what a phone
// would embed here.
$gps = new PelIfd(PelIfd::GPS);
$ifd0->addSubIfd($gps);

$gps->addEntry(new PelEntryByte(PelTag::GPS_VERSION_ID, 2, 2, 0, 0));
$gps->addEntry(new PelEntryAscii(PelTag::GPS_LATITUDE_REF, 'N'));
$gps->addEntry(new PelEntryRational(PelTag::GPS_LATITUDE, [55, 1], [45, 1], [1509, 100]));
$gps->addEntry(new PelEntryAscii(PelTag::GPS_LONGITUDE_REF, 'E'));
$gps->addEntry(new PelEntryRational(PelTag::GPS_LONGITUDE, [37, 1], [37, 1], [1416, 100]));

$out = __DIR__.'/phone-portrait-gps.jpg';
file_put_contents($out, $jpeg->getBytes());

$check = exif_read_data($out);
$hasAll = is_array($check)
    && (int) ($check['Orientation'] ?? 0) === 6
    && isset($check['GPSLatitude'])
    && isset($check['Make'])
    && isset($check['Model'])
    && isset($check['DateTimeOriginal']);

echo $hasAll
    ? "OK: phone fixture written with Orientation 6 + GPS + Make/Model/DateTimeOriginal at {$out}\n"
    : "FAILED: fixture is missing expected metadata\n";
exit($hasAll ? 0 : 1);
