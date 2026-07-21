<?php

declare(strict_types=1);

namespace App\Nutrition\Exceptions;

use RuntimeException;

/**
 * Thrown when the recogniser cannot produce a result — the provider was
 * unreachable, rate limited, or returned something unusable. The message is
 * safe to show a user and never contains the API key.
 */
final class RecognitionFailedException extends RuntimeException {}
