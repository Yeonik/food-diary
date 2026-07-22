<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\FoodItem;
use App\Models\MealEntry;
use App\Nutrition\NutrientSource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The barcode path end to end, with Open Food Facts faked at the HTTP layer so
 * CI makes no network call: a known code resolves to one verified product, is
 * confirmed at a weight and logged with that product's numbers; an unknown code
 * says so and logs nothing.
 */
class BarcodeFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_known_code_logs_a_verified_open_food_facts_entry_with_the_products_values(): void
    {
        Http::fake([
            'world.openfoodfacts.org/api/v2/product/*' => Http::response([
                'status' => 1,
                'product' => [
                    'code' => '4600000000000',
                    'product_name' => 'Kefir 1%',
                    'nutriments' => [
                        'energy-kcal_100g' => 40,
                        'proteins_100g' => 3,
                        'fat_100g' => 1,
                        'carbohydrates_100g' => 4,
                    ],
                    'image_small_url' => 'https://images.openfoodfacts.org/k.small.jpg',
                ],
            ]),
        ]);

        // Step one: the code resolves to a product and moves to confirmation.
        $this->post(route('log.barcode.lookup'), ['code' => '4600000000000'])
            ->assertRedirect(route('log.barcode.confirm'));

        $this->get(route('log.barcode.confirm'))
            ->assertOk()
            ->assertSee('Kefir 1%')
            ->assertSee(__('source.open_food_facts'));

        // Step two: log 250 g → 40 kcal/100 g × 2.5 = 100 kcal, verified OFF.
        $this->post(route('log.barcode.confirm.store'), ['meal' => 'breakfast', 'grams' => 250])
            ->assertRedirect();

        $entry = MealEntry::query()->firstOrFail();
        $this->assertSame(NutrientSource::OpenFoodFacts, $entry->source);
        $this->assertTrue($entry->isVerified());
        $this->assertSame(100.0, $entry->kcal);

        // Confirming an OFF product promotes it to the library, keyed by its code.
        $this->assertSame('4600000000000', FoodItem::query()->firstOrFail()->external_id);
    }

    public function test_an_unknown_code_says_so_and_logs_nothing(): void
    {
        Http::fake([
            'world.openfoodfacts.org/api/v2/product/*' => Http::response(['status' => 0], 404),
        ]);

        $this->from(route('log.barcode'))
            ->post(route('log.barcode.lookup'), ['code' => '0000000000000'])
            ->assertRedirect(route('log.barcode'))
            ->assertSessionHas('barcode_status');

        // Nothing was staged for confirmation, and nothing was logged.
        $this->get(route('log.barcode.confirm'))->assertRedirect(route('log.barcode'));
        $this->assertSame(0, MealEntry::query()->count());
    }
}
