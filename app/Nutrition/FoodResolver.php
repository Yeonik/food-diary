<?php

declare(strict_types=1);

namespace App\Nutrition;

use App\Nutrition\Contracts\RemoteNutritionSource;
use App\Nutrition\Sources\PersonalLibrarySource;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Walks the resolution ladder for a food name and records which tier answered:
 *
 *   1. the personal library — always consulted first, trusted above all;
 *   2. USDA and Open Food Facts — queried together in one parallel pool, not
 *      ranked against each other, both returned and labelled;
 *   3. the model's own estimate — offered only when neither of the above did.
 *
 * The resolver never picks a winner among the candidates. It hands back a
 * {@see Resolution} for the user to choose from.
 */
class FoodResolver
{
    /**
     * @param  list<RemoteNutritionSource>  $remoteSources  the external APIs (USDA, Open Food Facts)
     */
    public function __construct(
        private readonly PersonalLibrarySource $library,
        private readonly array $remoteSources,
    ) {}

    public function resolve(string $name, ?NutrientProfile $estimatedFallback = null): Resolution
    {
        // Tier 1: the personal library, a local query, always first.
        $libraryMatches = $this->library->search($name);

        // Tier 2: the external APIs, fired concurrently so their latencies
        // overlap instead of adding up.
        [$apiMatches, $notices] = $this->queryRemotes($name);

        $unresolved = $libraryMatches === [] && $apiMatches === [];

        // Tier 3: the model's estimate, and only as a genuine last resort.
        $estimated = ($unresolved && $estimatedFallback !== null)
            ? new NutrientMatch(
                description: $name.' (estimated)',
                profile: $estimatedFallback,
            )
            : null;

        return new Resolution(
            query: $name,
            libraryMatches: $libraryMatches,
            apiMatches: $apiMatches,
            estimated: $estimated,
            notices: $notices,
        );
    }

    /**
     * @return array{0: list<NutrientMatch>, 1: list<ResolutionNotice>}
     */
    private function queryRemotes(string $name): array
    {
        if ($this->remoteSources === []) {
            return [[], []];
        }

        /** @var array<string, Response|\Throwable> $responses */
        $responses = Http::pool(function (Pool $pool) use ($name): array {
            $requests = [];

            foreach ($this->remoteSources as $source) {
                $request = $source->request($name);

                $requests[] = $pool->as($source->poolKey())
                    ->withHeaders($request->headers)
                    ->timeout($request->timeoutSeconds)
                    ->get($request->url, $request->query);
            }

            return $requests;
        });

        $matches = [];
        $notices = [];

        foreach ($this->remoteSources as $source) {
            $response = $responses[$source->poolKey()] ?? null;

            if (! $response instanceof Response) {
                // A timeout or connection error: say the source was unavailable
                // rather than pretending it returned nothing.
                $notices[] = new ResolutionNotice(
                    $source->source(),
                    $source->source()->label().' could not be reached.',
                );

                continue;
            }

            if ($response->status() === 429) {
                // Provider rate limits are theirs, not ours — report, don't fail.
                $notices[] = new ResolutionNotice(
                    $source->source(),
                    $source->source()->label().' is rate limited; try again shortly.',
                );

                continue;
            }

            if (! $response->successful()) {
                $notices[] = new ResolutionNotice(
                    $source->source(),
                    $source->source()->label().' returned an error.',
                );

                continue;
            }

            $matches = [...$matches, ...$source->parse($response)];
        }

        return [$matches, $notices];
    }
}
