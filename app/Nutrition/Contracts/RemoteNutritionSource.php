<?php

declare(strict_types=1);

namespace App\Nutrition\Contracts;

use App\Nutrition\NutrientMatch;
use App\Nutrition\RemoteRequest;
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
     * Describe the lookup request for the given name.
     */
    public function request(string $name): RemoteRequest;

    /**
     * Turn a successful response into candidate matches.
     *
     * @return list<NutrientMatch>
     */
    public function parse(Response $response): array;
}
