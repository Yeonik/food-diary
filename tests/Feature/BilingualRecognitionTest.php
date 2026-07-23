<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\FoodItem;
use App\Models\MealEntry;
use App\Nutrition\Contracts\FoodRecogniser;
use App\Nutrition\FoodResolver;
use App\Nutrition\NutrientProfile;
use App\Nutrition\NutrientSource;
use App\Nutrition\ProfileOrigin;
use App\Nutrition\RecognisedItem;
use App\Nutrition\Recognisers\FakeRecogniser;
use App\Nutrition\SearchTerms;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Russian products live in Open Food Facts under their Russian names, while the
 * model gives an English name to search USDA with. Recognition therefore carries
 * two names: USDA is searched in English, Open Food Facts in both, the user is
 * shown the Russian name, and a confirmed item remembers both so it is found by
 * either name from then on.
 */
class BilingualRecognitionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->signIn();
    }

    protected function tearDown(): void
    {
        foreach (glob(storage_path('app/private/photos').'/*') ?: [] as $file) {
            @unlink($file);
        }

        parent::tearDown();
    }

    private function bindRecogniser(): void
    {
        $this->app->instance(FoodRecogniser::class, new FakeRecogniser([
            new RecognisedItem(
                name: 'Pobeda chocolate',
                estimatedGrams: 100.0,
                confidence: 0.9,
                nativeName: 'Победа',
                estimatedProfile: new NutrientProfile(500, 7, 30, 55, NutrientSource::Estimated),
            ),
        ]));
    }

    private function uploadPhoto(): void
    {
        $this->post(route('log.photo.store'), ['photo' => UploadedFile::fake()->image('candy.jpg', 300, 300)])
            ->assertRedirect(route('log.confirm'));
    }

    public function test_usda_is_searched_in_english_only_and_open_food_facts_in_both(): void
    {
        Http::fake([
            'api.nal.usda.gov/*' => Http::response(['foods' => []]),
            'world.openfoodfacts.org/*' => Http::response(['products' => []]),
        ]);

        app(FoodResolver::class)->resolve(new SearchTerms('Pobeda chocolate', 'Победа'));

        $isUsda = fn ($request): bool => str_contains($request->url(), 'api.nal.usda.gov');
        $isOff = fn ($request): bool => str_contains($request->url(), 'world.openfoodfacts.org');
        $decoded = fn ($request): string => urldecode($request->url());

        // USDA: the English term, and never the Russian one.
        Http::assertSent(fn ($r) => $isUsda($r) && str_contains($decoded($r), 'query=Pobeda chocolate'));
        Http::assertNotSent(fn ($r) => $isUsda($r) && str_contains($decoded($r), 'Победа'));

        // Open Food Facts: both terms.
        Http::assertSent(fn ($r) => $isOff($r) && str_contains($decoded($r), 'search_terms=Pobeda chocolate'));
        Http::assertSent(fn ($r) => $isOff($r) && str_contains($decoded($r), 'search_terms=Победа'));
    }

    public function test_the_russian_name_is_shown_and_both_names_are_stored_on_promotion(): void
    {
        // Open Food Facts answers (in Russian); USDA has nothing.
        Http::fake([
            'api.nal.usda.gov/*' => Http::response(['foods' => []]),
            'world.openfoodfacts.org/*' => Http::response(['products' => [[
                'code' => '4600000000001',
                'product_name' => 'Победа',
                'nutriments' => ['energy-kcal_100g' => 460, 'proteins_100g' => 9, 'fat_100g' => 29, 'carbohydrates_100g' => 42],
            ]]]),
        ]);
        $this->bindRecogniser();
        $this->uploadPhoto();

        // The confirm screen shows the Russian name, labelled Open Food Facts.
        $this->get(route('log.confirm'))
            ->assertOk()
            ->assertSee('Победа')
            ->assertSee('Open Food Facts');

        $this->post(route('log.confirm.store'), [
            'meal' => 'snack',
            'items' => [['include' => 1, 'candidate' => 0, 'grams' => 100]],
        ])->assertRedirect();

        $entry = MealEntry::query()->firstOrFail();
        $this->assertSame('Победа', $entry->name);
        $this->assertSame(NutrientSource::OpenFoodFacts, $entry->source);

        // The library item carries both names and the barcode — found by either
        // name next time, and exactly by the barcode.
        $item = FoodItem::query()->firstOrFail();
        $this->assertSame('Победа', $item->name);
        $this->assertSame('Pobeda chocolate', $item->alt_name);
        $this->assertSame('4600000000001', $item->external_id);
        $this->assertSame(ProfileOrigin::OpenFoodFacts, $item->origin);
    }

    public function test_a_confirmed_library_match_learns_the_recognised_name_as_an_alias(): void
    {
        // A pre-existing item stored under its English name only.
        $existing = FoodItem::factory()->direct(kcal: 460, protein: 9, fat: 29, carbs: 42)
            ->create(['name' => 'Pobeda chocolate', 'alt_name' => null]);

        Http::fake([
            'api.nal.usda.gov/*' => Http::response(['foods' => []]),
            'world.openfoodfacts.org/*' => Http::response(['products' => []]),
        ]);
        $this->bindRecogniser();
        $this->uploadPhoto();

        // Candidate 0 is the library match; confirming it records the Russian
        // phrasing as an alias on the same row rather than creating a second one.
        $this->post(route('log.confirm.store'), [
            'meal' => 'snack',
            'items' => [['include' => 1, 'candidate' => 0, 'grams' => 100]],
        ])->assertRedirect();

        $this->assertSame(1, FoodItem::count());
        $this->assertTrue($existing->fresh()?->aliases->pluck('name')->contains('Победа'));
        $this->assertSame(NutrientSource::PersonalLibrary, MealEntry::query()->firstOrFail()->source);
    }

    public function test_a_curated_alt_name_is_left_untouched_by_recognition(): void
    {
        $existing = FoodItem::factory()->direct(kcal: 460, protein: 9, fat: 29, carbs: 42)
            ->create(['name' => 'Pobeda chocolate', 'alt_name' => 'Победа (my note)']);

        Http::fake([
            'api.nal.usda.gov/*' => Http::response(['foods' => []]),
            'world.openfoodfacts.org/*' => Http::response(['products' => []]),
        ]);
        $this->bindRecogniser();
        $this->uploadPhoto();

        $this->post(route('log.confirm.store'), [
            'meal' => 'snack',
            'items' => [['include' => 1, 'candidate' => 0, 'grams' => 100]],
        ])->assertRedirect();

        // Recognition learns aliases but never rewrites the name the user curated.
        $this->assertSame('Победа (my note)', $existing->fresh()?->alt_name);
    }
}
