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

    public function resolve(SearchTerms $terms, ?NutrientProfile $estimatedFallback = null): Resolution
    {
        // Tier 1: the personal library, a local query, always first. Searched by
        // every term so an item stored under either language is still found.
        $libraryMatches = $this->searchLibrary($terms);

        // Tier 2: the external APIs, fired concurrently so their latencies
        // overlap instead of adding up.
        [$apiMatches, $notices] = $this->queryRemotes($terms);

        $unresolved = $libraryMatches === [] && $apiMatches === [];

        // Tier 3: the model's estimate, and only as a genuine last resort.
        $estimated = ($unresolved && $estimatedFallback !== null)
            ? new NutrientMatch(
                description: $terms->display().' (estimated)',
                profile: $estimatedFallback,
            )
            : null;

        return new Resolution(
            query: $terms->display(),
            libraryMatches: $libraryMatches,
            apiMatches: $apiMatches,
            estimated: $estimated,
            notices: $notices,
        );
    }

    /**
     * @return list<NutrientMatch>
     */
    private function searchLibrary(SearchTerms $terms): array
    {
        $matches = [];

        foreach ($terms->all() as $term) {
            $matches = [...$matches, ...$this->library->search($term)];
        }

        return $this->dedupe($matches);
    }

    /**
     * @return array{0: list<NutrientMatch>, 1: list<ResolutionNotice>}
     */
    private function queryRemotes(SearchTerms $terms): array
    {
        if ($this->remoteSources === []) {
            return [[], []];
        }

        // Each source contributes one or more requests (Open Food Facts fires
        // two — one per language); keys stay unique with a per-source index.
        $plan = [];
        foreach ($this->remoteSources as $source) {
            $plan[] = ['source' => $source, 'requests' => $source->requestsFor($terms)];
        }

        /** @var array<string, Response|\Throwable> $responses */
        $responses = Http::pool(function (Pool $pool) use ($plan): array {
            $requests = [];

            foreach ($plan as $entry) {
                foreach ($entry['requests'] as $i => $request) {
                    $requests[] = $pool->as($entry['source']->poolKey().'#'.$i)
                        ->withHeaders($request->headers)
                        ->timeout($request->timeoutSeconds)
                        ->get($request->url, $request->query);
                }
            }

            return $requests;
        });

        $matches = [];
        $notices = [];

        foreach ($plan as $entry) {
            $source = $entry['source'];
            $sourceMatches = [];
            $anySuccess = false;
            $failure = null;

            foreach (array_keys($entry['requests']) as $i) {
                $response = $responses[$source->poolKey().'#'.$i] ?? null;

                if (! $response instanceof Response) {
                    // A timeout or connection error: say the source was
                    // unavailable rather than pretending it returned nothing.
                    $failure ??= $source->source()->label().' could not be reached.';

                    continue;
                }

                if ($response->status() === 429) {
                    // Provider rate limits are theirs, not ours — report, not fail.
                    $failure ??= $source->source()->label().' is rate limited; try again shortly.';

                    continue;
                }

                if (! $response->successful()) {
                    $failure ??= $source->source()->label().' returned an error.';

                    continue;
                }

                $anySuccess = true;
                $sourceMatches = [...$sourceMatches, ...$source->parse($response)];
            }

            // One notice per source, and only when none of its lookups worked —
            // a Russian hit shouldn't be buried under an English-lookup error.
            if (! $anySuccess && $failure !== null) {
                $notices[] = new ResolutionNotice($source->source(), $failure);
            }

            $matches = [...$matches, ...$this->dedupe($sourceMatches)];
        }

        return [$matches, $notices];
    }

    /**
     * Drop repeats a multi-term search can produce — the same product returned
     * by both the English and the native lookup. Identity is the external id
     * (barcode / fdcId / library row); failing that, the description.
     *
     * @param  list<NutrientMatch>  $matches
     * @return list<NutrientMatch>
     */
    private function dedupe(array $matches): array
    {
        $seen = [];
        $unique = [];

        foreach ($matches as $match) {
            $key = $match->externalId ?? 'desc:'.mb_strtolower($match->description);

            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $unique[] = $match;
        }

        return $unique;
    }
}
