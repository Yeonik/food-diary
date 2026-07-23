<?php

declare(strict_types=1);

namespace App\Nutrition\Exceptions;

use RuntimeException;

/**
 * Thrown when a person has used their recognitions for the day.
 *
 * Deliberately **not** a {@see RecognitionFailedException}. Nothing failed: the
 * provider was never asked, and telling somebody the recogniser is unavailable
 * when in fact their own allowance is spent is a message that sends them to look
 * at the wrong thing — refreshing, retrying, checking their connection, none of
 * which can help. An error naming the wrong cause is worse than no error at all,
 * which is the same reason the provider's own 429 is reported as a quota problem
 * with the API plan rather than as a generic outage.
 *
 * A separate type, so the two cannot be caught by the same arm and given the
 * same words by accident.
 */
final class DailyLimitReachedException extends RuntimeException
{
    public function __construct(public readonly int $limit)
    {
        parent::__construct("The daily recognition limit of {$limit} is used up.");
    }
}
