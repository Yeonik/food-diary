<?php

declare(strict_types=1);

/**
 * Regenerates the GPS-tagged JPEG fixture used by PhotoPreparerTest.
 *
 * The EXIF-stripping guarantee is a privacy claim the README makes, so the test
 * behind it exercises a REAL photo carrying real GPS coordinates rather than a
 * synthetic stand-in. The produced file (meal-with-gps.jpg) is committed and
 * read back with ext-exif at test time, so nothing here runs in CI.
 *
 * GD cannot write EXIF, so regenerating the fixture needs the `lsolesen/pel`
 * library, installed only for that purpose and then removed (it is abandoned
 * upstream, so it is deliberately not a standing dependency):
 *
 *     composer require --dev lsolesen/pel
 *     php tests/Fixtures/generate_gps_fixture.php
 *     composer remove --dev lsolesen/pel
 */

use lsolesen\pel\PelEntryAscii;
use lsolesen\pel\PelEntryByte;
use lsolesen\pel\PelEntryRational;
use lsolesen\pel\PelEntryTime;
use lsolesen\pel\PelExif;
use lsolesen\pel\PelIfd;
use lsolesen\pel\PelJpeg;
use lsolesen\pel\PelTag;
use lsolesen\pel\PelTiff;

require __DIR__.'/../../vendor/autoload.php';

$image = imagecreatetruecolor(64, 48);
if ($image === false) {
    fwrite(STDERR, "Could not create image.\n");
    exit(1);
}
imagefill($image, 0, 0, imagecolorallocate($image, 120, 90, 60));

$jpeg = new PelJpeg($image);

$exif = new PelExif;
$jpeg->setExif($exif);

$tiff = new PelTiff;
$exif->setTiff($tiff);

$ifd0 = new PelIfd(PelIfd::IFD0);
$tiff->setIfd($ifd0);

// Identifying metadata a phone camera embeds beyond GPS: device make/model and
// the capture time. These leak just as surely as coordinates and must go too.
$ifd0->addEntry(new PelEntryAscii(PelTag::MAKE, 'ACME'));
$ifd0->addEntry(new PelEntryAscii(PelTag::MODEL, 'PhoneCam X100'));

$exifIfd = new PelIfd(PelIfd::EXIF);
$ifd0->addSubIfd($exifIfd);
// A fixed timestamp keeps the fixture byte-for-byte reproducible.
$exifIfd->addEntry(new PelEntryTime(PelTag::DATE_TIME_ORIGINAL, 1_717_243_800, PelEntryTime::EXIF_STRING));

// A GPS sub-IFD with a real coordinate (a home address is exactly what a phone
// would embed here).
$gps = new PelIfd(PelIfd::GPS);
$ifd0->addSubIfd($gps);

$gps->addEntry(new PelEntryByte(PelTag::GPS_VERSION_ID, 2, 2, 0, 0));
$gps->addEntry(new PelEntryAscii(PelTag::GPS_LATITUDE_REF, 'N'));
$gps->addEntry(new PelEntryRational(PelTag::GPS_LATITUDE, [55, 1], [45, 1], [1509, 100]));
$gps->addEntry(new PelEntryAscii(PelTag::GPS_LONGITUDE_REF, 'E'));
$gps->addEntry(new PelEntryRational(PelTag::GPS_LONGITUDE, [37, 1], [37, 1], [1416, 100]));

$out = __DIR__.'/meal-with-gps.jpg';
file_put_contents($out, $jpeg->getBytes());

$check = exif_read_data($out);
$hasAll = is_array($check)
    && isset($check['GPSLatitude'])
    && isset($check['Make'])
    && isset($check['Model'])
    && isset($check['DateTimeOriginal']);

echo $hasAll
    ? "OK: fixture written with GPS + Make/Model/DateTimeOriginal at {$out}\n"
    : "FAILED: fixture is missing expected metadata\n";
exit($hasAll ? 0 : 1);
