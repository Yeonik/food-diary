<?php

declare(strict_types=1);

namespace App\Nutrition\Sources;

use App\Nutrition\Contracts\RemoteNutritionSource;
use App\Nutrition\NutrientMatch;
use App\Nutrition\NutrientProfile;
use App\Nutrition\NutrientSource;
use App\Nutrition\RemoteRequest;
use App\Nutrition\SearchTerms;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Open Food Facts — strong at branded packaged goods with barcodes (yoghurt,
 * bread, cereal). ODbL-licensed, no key required. A descriptive User-Agent is
 * requested by the project's terms, so it is always sent.
 *
 * It answers two ways: a name search (the resolver's tier 2) and a direct
 * barcode lookup ({@see productByCode}) — one code, one product — for the scan
 * path. Both carry a thumbnail URL when the product has one, shown only on the
 * confirm screen.
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

    /**
     * Russian products sit in Open Food Facts under their Russian names, but the
     * model gives an English one to search USDA with — so both terms are
     * searched here, and the resolver de-duplicates any product both return.
     *
     * @return list<RemoteRequest>
     */
    public function requestsFor(SearchTerms $terms): array
    {
        return array_map(fn (string $term): RemoteRequest => $this->requestFor($term), $terms->all());
    }

    private function requestFor(string $name): RemoteRequest
    {
        return new RemoteRequest(
            url: rtrim($this->baseUrl, '/').'/cgi/search.pl',
            query: [
                'search_terms' => $name,
                'search_simple' => 1,
                'action' => 'process',
                'json' => 1,
                'page_size' => 5,
                'fields' => 'code,product_name,nutriments,image_small_url,image_front_small_url',
            ],
            headers: ['User-Agent' => $this->userAgent],
        );
    }

    /**
     * Look one product up by its barcode via the v2 product endpoint — the scan
     * or type-a-code path. Returns a verified Open Food Facts match, or null
     * when the code is unknown or the response is unusable, so the caller can
     * say so plainly rather than invent a value.
     */
    public function productByCode(string $code): ?NutrientMatch
    {
        $request = $this->productRequest($code);

        $response = Http::withHeaders($request->headers)
            ->timeout($request->timeoutSeconds)
            ->get($request->url, $request->query);

        return $response->successful() ? $this->parseProduct($response) : null;
    }

    private function productRequest(string $code): RemoteRequest
    {
        return new RemoteRequest(
            url: rtrim($this->baseUrl, '/').'/api/v2/product/'.rawurlencode($code).'.json',
            query: [
                'fields' => 'code,product_name,nutriments,image_small_url,image_front_small_url',
            ],
            headers: ['User-Agent' => $this->userAgent],
        );
    }

    /**
     * Turn a v2 product response into a single match. `status` is 1 when the
     * barcode is known; anything else means no such product.
     */
    public function parseProduct(Response $response): ?NutrientMatch
    {
        if ($response->json('status') !== 1) {
            return null;
        }

        $product = $response->json('product');

        return is_array($product) ? $this->matchFromProduct($product) : null;
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

        return $response->successful() ? $this->parse($response) : [];
    }

    /**
     * @return list<NutrientMatch>
     */
    public function parse(Response $response): array
    {
        /** @var array<int, mixed> $products */
        $products = $response->json('products') ?? [];

        $matches = [];

        foreach ($products as $product) {
            $match = is_array($product) ? $this->matchFromProduct($product) : null;
            if ($match !== null) {
                $matches[] = $match;
            }
        }

        return $matches;
    }

    /**
     * One product object → one match, or null when it lacks a usable name or
     * nutriments. Shared by the name search and the barcode lookup.
     *
     * @param  array<string, mixed>  $product
     */
    private function matchFromProduct(array $product): ?NutrientMatch
    {
        $name = is_string($product['product_name'] ?? null) ? trim($product['product_name']) : '';
        if ($name === '') {
            return null;
        }

        $nutriments = $product['nutriments'] ?? null;
        if (! is_array($nutriments)) {
            return null;
        }

        return new NutrientMatch(
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
            imageUrl: $this->imageUrl($product),
        );
    }

    /**
     * The small front-of-pack thumbnail, if the product carries one.
     *
     * @param  array<string, mixed>  $product
     */
    private function imageUrl(array $product): ?string
    {
        foreach (['image_small_url', 'image_front_small_url'] as $key) {
            $url = $product[$key] ?? null;
            if (is_string($url) && $url !== '') {
                return $url;
            }
        }

        return null;
    }

    private function number(mixed $value): float
    {
        return is_numeric($value) ? (float) $value : 0.0;
    }
}
