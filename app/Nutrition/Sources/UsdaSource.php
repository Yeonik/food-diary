<?php

declare(strict_types=1);

namespace App\Nutrition\Sources;

use App\Nutrition\Contracts\RemoteNutritionSource;
use App\Nutrition\NameMatcher;
use App\Nutrition\NutrientMatch;
use App\Nutrition\NutrientProfile;
use App\Nutrition\NutrientSource;
use App\Nutrition\RemoteRequest;
use App\Nutrition\SearchTerms;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * USDA FoodData Central — strong at raw foods and ingredients (beef, rice,
 * carrot). Public-domain data, free key from api.data.gov.
 *
 * The key travels in the X-Api-Key header, never in the URL — a key in a URL
 * ends up in logs and browser history.
 */
class UsdaSource implements RemoteNutritionSource
{
    /**
     * Search only whole, raw and basic foods. Foundation and SR Legacy are the
     * ingredients a recipe is built from — "Potatoes, raw", "Potatoes, boiled" —
     * with their own per-100 g figures. Without this parameter the search also
     * returns FNDDS survey foods, the mixed dishes a diet study codes ("...
     * patty", "... soup", "..., NFS"), and those crowd the raw ingredient off the
     * top. Branded is left out on purpose: packaged products are Open Food Facts'
     * work, and here they would be redundant noise beneath the ingredient.
     */
    private const RAW_FOOD_DATA_TYPES = 'Foundation,SR Legacy';

    /**
     * Ask for a generous page. USDA sorts by its own relevance, which buries the
     * raw base food under everything else that merely contains the word — the
     * raw potato is around the twentieth "potato" result. We re-rank locally, so
     * the base food has to be IN the page for the re-rank to lift it; five was
     * not enough to hold it.
     */
    private const FETCH_SIZE = 25;

    /** How many ranked candidates to actually offer — a short list, not the page. */
    private const SURFACED_LIMIT = 6;

    public function __construct(
        private readonly string $baseUrl,
        private readonly ?string $apiKey,
        private readonly NameMatcher $matcher,
    ) {}

    public function source(): NutrientSource
    {
        return NutrientSource::Usda;
    }

    public function poolKey(): string
    {
        return 'usda';
    }

    /**
     * USDA indexes English food names, so only the English term is searched;
     * a Russian package name would return nothing useful.
     *
     * @return list<RemoteRequest>
     */
    public function requestsFor(SearchTerms $terms): array
    {
        return [$this->requestFor($terms->english)];
    }

    private function requestFor(string $name): RemoteRequest
    {
        return new RemoteRequest(
            url: rtrim($this->baseUrl, '/').'/foods/search',
            query: [
                'query' => $name,
                'dataType' => self::RAW_FOOD_DATA_TYPES,
                'pageSize' => self::FETCH_SIZE,
            ],
            headers: $this->apiKey !== null && $this->apiKey !== ''
                ? ['X-Api-Key' => $this->apiKey]
                : [],
        );
    }

    /**
     * @return list<NutrientMatch>
     */
    public function search(string $name): array
    {
        $request = $this->requestFor($name);

        $response = Http::withHeaders($request->headers)
            ->timeout($request->timeoutSeconds)
            ->get($request->url, $request->query);

        return $response->successful() ? $this->parse($response, new SearchTerms($name)) : [];
    }

    /**
     * Parse and re-rank the page. USDA's own order puts derivatives first — for
     * "potato": "Bread, potato", "Flour, potato", "Potato pancakes", "Babyfood,
     * ..." — and the raw base food far down. We lift the base food back to the
     * top by the structure of the description, never by touching its numbers.
     *
     * @return list<NutrientMatch>
     */
    public function parse(Response $response, SearchTerms $terms): array
    {
        /** @var array<int, mixed> $foods */
        $foods = $response->json('foods') ?? [];

        /** @var list<array<string, mixed>> $foods */
        $foods = array_values(array_filter(
            $foods,
            static fn ($food): bool => is_array($food) && is_string($food['description'] ?? null),
        ));

        // Decorate with the rank key once, sort by it (descending — higher key
        // first), then undecorate. usort is stable on PHP 8, so foods with an
        // equal key keep USDA's own order among themselves.
        $keyed = array_map(
            fn (array $food): array => ['key' => $this->rankKey($terms->english, $food), 'food' => $food],
            $foods,
        );
        usort($keyed, static fn (array $a, array $b): int => $b['key'] <=> $a['key']);

        $matches = [];

        foreach (array_slice($keyed, 0, self::SURFACED_LIMIT) as $row) {
            /** @var array<string, mixed> $food */
            $food = $row['food'];
            /** @var string $description */
            $description = $food['description'];

            $matches[] = new NutrientMatch(
                description: $description,
                profile: $this->profileFrom($food['foodNutrients'] ?? null),
                externalId: isset($food['fdcId']) ? (string) $food['fdcId'] : null,
            );
        }

        return $matches;
    }

    /**
     * A sortable key for one food against the query, higher first. The base
     * ingredient — the food whose name (the part before the first comma) IS the
     * thing searched, with only preparation words after it — outranks anything
     * that merely mentions it. So "Potatoes, flesh and skin, raw" beats "Bread,
     * potato" and "Potato pancakes", whose head word is a different food or a
     * compound dish.
     *
     * The key changes only the order the candidates are offered in; every number
     * on each candidate is still USDA's own for that food.
     *
     * @param  array<string, mixed>  $food
     * @return array{0: int, 1: int, 2: int, 3: int}
     */
    private function rankKey(string $query, array $food): array
    {
        /** @var string $description */
        $description = $food['description'];

        $queryTokens = $this->foldedTokens($query);
        $head = trim(explode(',', $description, 2)[0]);
        $headTokens = $this->foldedTokens($head);

        // The base food: the head is nothing but the queried food itself — every
        // head token is a query token. "Bread, potato" (head "bread"), "Potato
        // pancakes" (head token "pancakes") and "Snacks, ..." all fail this.
        $isBase = $headTokens !== [] && array_diff($headTokens, $queryTokens) === [];

        // Among base foods, raw is the canonical ingredient a recipe is weighed
        // from; then other simple preparations; then anything else.
        $descriptionTokens = $this->foldedTokens($description);
        $preparation = match (true) {
            in_array('raw', $descriptionTokens, true) => 3,
            (bool) array_intersect(['boiled', 'steamed'], $descriptionTokens) => 2,
            (bool) array_intersect(['cooked', 'baked', 'roasted'], $descriptionTokens) => 1,
            default => 0,
        };

        $isFoundation = ($food['dataType'] ?? null) === 'Foundation';

        // Shorter descriptions are the plainer, more basic entries; negated so
        // "less is more" sorts the right way under a descending comparison.
        return [
            $isBase ? 1 : 0,
            $preparation,
            $isFoundation ? 1 : 0,
            -mb_strlen($description),
        ];
    }

    /**
     * The significant tokens of a name, lower-cased and folded to a rough
     * singular so a plural in the description ("Potatoes") still matches a
     * singular query ("potato"). Reuses the library's matcher for tokenising.
     *
     * @return list<string>
     */
    private function foldedTokens(string $name): array
    {
        return array_values(array_unique(array_map(
            $this->singularise(...),
            $this->matcher->significantTokens($name),
        )));
    }

    /**
     * A crude English singular — enough to fold "potatoes"→"potato",
     * "carrots"→"carrot" for matching. Not linguistics, just plural tolerance.
     */
    private function singularise(string $token): string
    {
        if (mb_strlen($token) > 3 && str_ends_with($token, 'es')) {
            return mb_substr($token, 0, -2);
        }

        if (mb_strlen($token) > 2 && str_ends_with($token, 's')) {
            return mb_substr($token, 0, -1);
        }

        return $token;
    }

    /**
     * Pull the four macros out of FDC's nutrient list into a per-100 g profile.
     * FDC nutrient numbers: 208 energy (kcal), 203 protein, 204 total fat,
     * 205 carbohydrate.
     */
    private function profileFrom(mixed $foodNutrients): NutrientProfile
    {
        // Fixed-shape accumulator: the four keys always exist, so reads below
        // need no null-coalescing.
        $macros = ['208' => 0.0, '203' => 0.0, '204' => 0.0, '205' => 0.0];

        if (is_array($foodNutrients)) {
            foreach ($foodNutrients as $nutrient) {
                if (! is_array($nutrient)) {
                    continue;
                }

                $number = $nutrient['nutrientNumber'] ?? null;
                $value = $nutrient['value'] ?? null;

                if (is_scalar($number) && array_key_exists((string) $number, $macros) && is_numeric($value)) {
                    $macros[(string) $number] = (float) $value;
                }
            }
        }

        return new NutrientProfile(
            kcal: $macros['208'],
            proteinG: $macros['203'],
            fatG: $macros['204'],
            carbsG: $macros['205'],
            source: NutrientSource::Usda,
        );
    }
}
