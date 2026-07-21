<?php

declare(strict_types=1);

namespace App\Nutrition\Contracts;

use App\Nutrition\NutrientMatch;
use App\Nutrition\RemoteRequest;
use App\Nutrition\SearchTerms;
use Illuminate\Http\Client\Response;

/**
 * A nutrition source backed by an external HTTP API. It describes its request
 * as plain data ({@see RemoteRequest}) and parses the response itself, which
 * lets the resolver fire USDA and Open Food Facts together in one parallel pool
 * while each source stays responsible for its own wire format.
 */
interface RemoteNutritionSource extends NutritionSource
{
    /**
     * A stable key used to match this source's response inside the pool.
     */
    public function poolKey(): string;

    /**
     * Describe the lookup request(s) for these terms. A source may return more
     * than one — Open Food Facts searches both the English and native names,
     * USDA only the English one — and each response is parsed independently.
     *
     * @return list<RemoteRequest>
     */
    public function requestsFor(SearchTerms $terms): array;

    /**
     * Turn a successful response into candidate matches.
     *
     * @return list<NutrientMatch>
     */
    public function parse(Response $response): array;
}
