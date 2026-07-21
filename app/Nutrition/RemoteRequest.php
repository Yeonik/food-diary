<?php

declare(strict_types=1);

namespace App\Nutrition;

/**
 * A description of one outgoing HTTP request, kept as plain data so the resolver
 * can batch several sources into a single parallel pool without knowing the
 * shape of any one API. The source that produced it also parses the response.
 */
final readonly class RemoteRequest
{
    /**
     * @param  array<string, string|int|float|bool>  $query
     * @param  array<string, string>  $headers
     */
    public function __construct(
        public string $url,
        public array $query = [],
        public array $headers = [],
        public int $timeoutSeconds = 8,
    ) {}
}
