<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\FoodItem;
use App\Nutrition\FoodResolver;
use App\Nutrition\NutrientProfile;
use App\Nutrition\NutrientSource;
use App\Nutrition\SearchTerms;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FoodResolverTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Both external APIs answer with one match each. Faked, so no network.
     */
    private function fakeBothApis(): void
    {
        Http::fake([
            'api.nal.usda.gov/*' => Http::response([
                'foods' => [[
                    'description' => 'Chicken breast, raw',
                    'fdcId' => 1234,
                    'foodNutrients' => [
                        ['nutrientNumber' => '208', 'value' => 165],
                        ['nutrientNumber' => '203', 'value' => 31],
                        ['nutrientNumber' => '204', 'value' => 3.6],
                        ['nutrientNumber' => '205', 'value' => 0],
                    ],
                ]],
            ]),
            'world.openfoodfacts.org/*' => Http::response([
                'products' => [[
                    'code' => '3596710403264',
                    'product_name' => 'Chicken breast fillets',
                    'nutriments' => [
                        'energy-kcal_100g' => 106,
                        'proteins_100g' => 24,
                        'fat_100g' => 1.1,
                        'carbohydrates_100g' => 0,
                    ],
                ]],
            ]),
        ]);
    }

    private function resolver(): FoodResolver
    {
        return $this->app->make(FoodResolver::class);
    }

    public function test_the_personal_library_wins_over_both_apis(): void
    {
        FoodItem::factory()->direct(kcal: 165, protein: 31, fat: 3.6, carbs: 0)
            ->create(['name' => 'Chicken breast']);
        $this->fakeBothApis();

        $resolution = $this->resolver()->resolve(new SearchTerms('Chicken breast'));

        $this->assertSame(1, $resolution->answeringTier());
        $this->assertTrue($resolution->hasLibraryMatch());
        $this->assertCount(1, $resolution->libraryMatches);
        $this->assertSame(NutrientSource::PersonalLibrary, $resolution->libraryMatches[0]->source());
    }

    public function test_usda_and_open_food_facts_are_both_returned_and_labelled(): void
    {
        // No library item for this name, so the API tier is what answers.
        $this->fakeBothApis();

        $resolution = $this->resolver()->resolve(new SearchTerms('chicken breast'));

        $this->assertCount(2, $resolution->apiMatches);

        $sources = array_map(fn ($match) => $match->source(), $resolution->apiMatches);
        $this->assertContains(NutrientSource::Usda, $sources);
        $this->assertContains(NutrientSource::OpenFoodFacts, $sources);
    }

    public function test_nothing_is_auto_selected_when_both_apis_answer(): void
    {
        $this->fakeBothApis();

        $resolution = $this->resolver()->resolve(new SearchTerms('chicken breast'));

        // Both candidates survive side by side; the resolver picks no winner and
        // offers no estimate to paper over the choice.
        $this->assertCount(2, $resolution->candidates());
        $this->assertNull($resolution->estimated);
        $this->assertSame(2, $resolution->answeringTier());
    }

    public function test_the_estimate_is_used_only_when_no_real_source_answers(): void
    {
        Http::fake([
            'api.nal.usda.gov/*' => Http::response(['foods' => []]),
            'world.openfoodfacts.org/*' => Http::response(['products' => []]),
        ]);

        $fallback = new NutrientProfile(200, 10, 8, 22, NutrientSource::Estimated);

        $resolution = $this->resolver()->resolve(new SearchTerms('some home-cooked thing'), $fallback);

        $this->assertTrue($resolution->isUnresolved());
        $this->assertNotNull($resolution->estimated);
        $this->assertSame(NutrientSource::Estimated, $resolution->estimated->source());
        $this->assertSame(3, $resolution->answeringTier());
    }

    public function test_a_rate_limited_source_becomes_a_notice_not_a_failure(): void
    {
        Http::fake([
            'api.nal.usda.gov/*' => Http::response('', 429),
            'world.openfoodfacts.org/*' => Http::response([
                'products' => [[
                    'code' => '1',
                    'product_name' => 'Greek yoghurt',
                    'nutriments' => ['energy-kcal_100g' => 59, 'proteins_100g' => 10, 'fat_100g' => 0.4, 'carbohydrates_100g' => 3.6],
                ]],
            ]),
        ]);

        $resolution = $this->resolver()->resolve(new SearchTerms('yoghurt'));

        // Open Food Facts still answers; USDA's rate limit is reported, not swallowed.
        $this->assertCount(1, $resolution->apiMatches);
        $this->assertSame(NutrientSource::OpenFoodFacts, $resolution->apiMatches[0]->source());
        $this->assertCount(1, $resolution->notices);
        $this->assertSame(NutrientSource::Usda, $resolution->notices[0]->source);
    }
}
