<?php

declare(strict_types=1);

namespace App\Nutrition\Sources;

use App\Nutrition\Contracts\RemoteNutritionSource;
use App\Nutrition\NutrientMatch;
use App\Nutrition\NutrientProfile;
use App\Nutrition\NutrientSource;
use App\Nutrition\RemoteRequest;
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
    public function __construct(
        private readonly string $baseUrl,
        private readonly ?string $apiKey,
    ) {}

    public function source(): NutrientSource
    {
        return NutrientSource::Usda;
    }

    public function poolKey(): string
    {
        return 'usda';
    }

    public function request(string $name): RemoteRequest
    {
        return new RemoteRequest(
            url: rtrim($this->baseUrl, '/').'/foods/search',
            query: [
                'query' => $name,
                'pageSize' => 5,
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
        $request = $this->request($name);

        $response = Http::withHeaders($request->headers)
            ->timeout($request->timeoutSeconds)
            ->get($request->url, $request->query);

        return $response->successful() ? $this->parse($response) : [];
    }

    /**
     * @return list<NutrientMatch>
     */
    public function parse(Response $response): array
    {
        /** @var array<int, array<string, mixed>> $foods */
        $foods = $response->json('foods') ?? [];

        $matches = [];

        foreach ($foods as $food) {
            $description = is_string($food['description'] ?? null) ? $food['description'] : null;
            if ($description === null) {
                continue;
            }

            $matches[] = new NutrientMatch(
                description: $description,
                profile: $this->profileFrom($food['foodNutrients'] ?? null),
                externalId: isset($food['fdcId']) ? (string) $food['fdcId'] : null,
            );
        }

        return $matches;
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
