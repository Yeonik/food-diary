<?php

declare(strict_types=1);

namespace App\Nutrition\Exceptions;

use RuntimeException;

/**
 * Thrown when an upload is not a usable image. Uploads are attacker-controlled,
 * so the content is validated rather than the extension: anything that does not
 * decode as a real image of an allowed type is rejected here.
 */
final class InvalidPhotoException extends RuntimeException {}
