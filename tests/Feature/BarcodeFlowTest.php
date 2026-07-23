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

    protected function setUp(): void
    {
        parent::setUp();

        $this->signIn();
    }

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
            ->assertSee(__('source.open_food_facts'))
            ->assertSee('https://images.openfoodfacts.org/k.small.jpg', false);

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

    /**
     * The confirm form carries a portion and a meal. The nutrient values come
     * from the product held in the session, so numbers posted alongside them are
     * ignored rather than believed.
     */
    public function test_the_form_cannot_supply_the_nutrient_values(): void
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
                ],
            ]),
        ]);

        $this->post(route('log.barcode.lookup'), ['code' => '4600000000000']);

        $this->post(route('log.barcode.confirm.store'), [
            'meal' => 'breakfast',
            'grams' => 100,
            // All of this is noise: it is not what the product says.
            'kcal' => 9999,
            'protein' => 99,
            'fat' => 99,
            'carbs' => 99,
            'source' => 'personal_library',
            'name' => 'Something else',
        ])->assertRedirect();

        $entry = MealEntry::query()->firstOrFail();
        $this->assertSame('Kefir 1%', $entry->name);
        $this->assertSame(40.0, $entry->kcal);
        $this->assertSame(3.0, $entry->protein_g);
        $this->assertSame(NutrientSource::OpenFoodFacts, $entry->source);
    }

    /**
     * Where the browser cannot scan, the screen says that — a different message
     * from a frame that would not read, and both have to survive a rewrite.
     */
    public function test_the_screen_offers_capture_and_the_typed_code_with_separate_explanations(): void
    {
        $html = (string) $this->get(route('log.barcode'))->assertOk()->getContent();

        // The capture panel, the number field, and the two distinct messages.
        $this->assertStringContainsString('data-barcode-scan', $html);
        $this->assertStringContainsString('data-barcode-code', $html);
        $this->assertStringContainsString(__('barcode.unsupported'), $html);
        $this->assertStringContainsString(__('barcode.unread'), $html);
        $this->assertNotSame(__('barcode.unsupported'), __('barcode.unread'));

        // The panel is hidden until scripting confirms the API exists; the number
        // field never is, because it is the whole feature without it.
        $this->assertMatchesRegularExpression('/<div data-barcode-scan hidden>/', $html);
        $this->assertDoesNotMatchRegularExpression('/<input[^>]*data-barcode-code[^>]*hidden/', $html);
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
