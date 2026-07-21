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
 * Open Food Facts — strong at branded packaged goods with barcodes (yoghurt,
 * bread, cereal). ODbL-licensed, no key required. A descriptive User-Agent is
 * requested by the project's terms, so it is always sent.
 *
 * v1 searches by name only; barcode scanning is deliberately out of scope.
 */
class OpenFoodFactsSource implements RemoteNutritionSource
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly string $userAgent,
    ) {}

    public function source(): NutrientSource
    {
        return NutrientSource::OpenFoodFacts;
    }

    public function poolKey(): string
    {
        return 'open_food_facts';
    }

    public function request(string $name): RemoteRequest
    {
        return new RemoteRequest(
            url: rtrim($this->baseUrl, '/').'/cgi/search.pl',
            query: [
                'search_terms' => $name,
                'search_simple' => 1,
                'action' => 'process',
                'json' => 1,
                'page_size' => 5,
                'fields' => 'code,product_name,nutriments',
            ],
            headers: ['User-Agent' => $this->userAgent],
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
        /** @var array<int, array<string, mixed>> $products */
        $products = $response->json('products') ?? [];

        $matches = [];

        foreach ($products as $product) {
            $name = is_string($product['product_name'] ?? null) ? trim($product['product_name']) : '';
            if ($name === '') {
                continue;
            }

            $nutriments = $product['nutriments'] ?? null;
            if (! is_array($nutriments)) {
                continue;
            }

            $matches[] = new NutrientMatch(
                description: $name,
                profile: new NutrientProfile(
                    // Open Food Facts publishes per-100 g figures under the
                    // `*_100g` keys.
                    kcal: $this->number($nutriments['energy-kcal_100g'] ?? null),
                    proteinG: $this->number($nutriments['proteins_100g'] ?? null),
                    fatG: $this->number($nutriments['fat_100g'] ?? null),
                    carbsG: $this->number($nutriments['carbohydrates_100g'] ?? null),
                    source: NutrientSource::OpenFoodFacts,
                ),
                externalId: isset($product['code']) && is_scalar($product['code'])
                    ? (string) $product['code']
                    : null,
            );
        }

        return $matches;
    }

    private function number(mixed $value): float
    {
        return is_numeric($value) ? (float) $value : 0.0;
    }
}
