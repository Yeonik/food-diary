<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Nutrition\NutrientSource;
use App\Nutrition\Sources\OpenFoodFactsSource;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The Open Food Facts client, faked at the HTTP layer so CI makes no network
 * call: a barcode resolves to a verified match with that product's numbers, an
 * unknown code resolves to null, and a name search carries the thumbnail URL
 * the confirm screen shows.
 */
class OpenFoodFactsSourceTest extends TestCase
{
    private function source(): OpenFoodFactsSource
    {
        return new OpenFoodFactsSource('https://world.openfoodfacts.org', 'food-diary/test');
    }

    public function test_a_known_barcode_resolves_to_a_verified_match_with_the_products_values(): void
    {
        Http::fake([
            'world.openfoodfacts.org/api/v2/product/*' => Http::response([
                'status' => 1,
                'product' => [
                    'code' => '737628064502',
                    'product_name' => 'Thai peanut sauce',
                    'nutriments' => [
                        'energy-kcal_100g' => 247,
                        'proteins_100g' => 7,
                        'fat_100g' => 12,
                        'carbohydrates_100g' => 28,
                    ],
                    'image_small_url' => 'https://images.openfoodfacts.org/x.small.jpg',
                ],
            ]),
        ]);

        $match = $this->source()->productByCode('737628064502');

        $this->assertNotNull($match);
        $this->assertSame('Thai peanut sauce', $match->description);
        $this->assertSame(NutrientSource::OpenFoodFacts, $match->source());
        $this->assertTrue($match->profile->isVerified());
        $this->assertSame(247.0, $match->profile->kcal);
        $this->assertSame(28.0, $match->profile->carbsG);
        $this->assertSame('737628064502', $match->externalId);
        $this->assertSame('https://images.openfoodfacts.org/x.small.jpg', $match->imageUrl);
    }

    public function test_an_unknown_barcode_resolves_to_null_rather_than_a_guess(): void
    {
        Http::fake([
            'world.openfoodfacts.org/api/v2/product/*' => Http::response([
                'status' => 0,
                'status_verbose' => 'product not found',
            ], 404),
        ]);

        $this->assertNull($this->source()->productByCode('0000000000000'));
    }

    public function test_a_name_search_carries_the_thumbnail_url(): void
    {
        Http::fake([
            'world.openfoodfacts.org/cgi/search.pl*' => Http::response([
                'products' => [[
                    'code' => '123',
                    'product_name' => 'Greek yoghurt',
                    'nutriments' => ['energy-kcal_100g' => 59],
                    'image_small_url' => 'https://images.openfoodfacts.org/y.small.jpg',
                ]],
            ]),
        ]);

        $matches = $this->source()->search('yoghurt');

        $this->assertCount(1, $matches);
        $this->assertSame('https://images.openfoodfacts.org/y.small.jpg', $matches[0]->imageUrl);
    }
}
