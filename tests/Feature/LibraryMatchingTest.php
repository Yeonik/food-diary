<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\FoodItem;
use App\Models\FoodItemAlias;
use App\Nutrition\Contracts\FoodRecogniser;
use App\Nutrition\NutrientProfile;
use App\Nutrition\NutrientSource;
use App\Nutrition\RecognisedItem;
use App\Nutrition\Recognisers\FakeRecogniser;
use App\Nutrition\SearchTerms;
use App\Nutrition\Sources\PersonalLibrarySource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The library must survive the model rephrasing a package from photo to photo.
 * Matching is loose (shared tokens), capped, offered not auto-selected, and it
 * learns confirmed phrasings as aliases — never a bare recognition.
 */
class LibraryMatchingTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        foreach (glob(storage_path('app/private/photos').'/*') ?: [] as $file) {
            @unlink($file);
        }

        parent::tearDown();
    }

    private function fakeEmptyApis(): void
    {
        Http::fake([
            'api.nal.usda.gov/*' => Http::response(['foods' => []]),
            'world.openfoodfacts.org/*' => Http::response(['products' => []]),
        ]);
    }

    private function bindRecogniser(string $english, ?string $native = null): void
    {
        $this->app->instance(FoodRecogniser::class, new FakeRecogniser([
            new RecognisedItem(
                name: $english,
                estimatedGrams: 18.0,
                confidence: 0.95,
                nativeName: $native,
                estimatedProfile: new NutrientProfile(470, 8, 31, 48, NutrientSource::Estimated),
            ),
        ]));
    }

    private function uploadPhoto(): void
    {
        $this->post(route('log.photo.store'), ['photo' => UploadedFile::fake()->image('candy.jpg', 300, 300)])
            ->assertRedirect(route('log.confirm'));
    }

    public function test_a_drifting_recognition_still_surfaces_the_hand_edited_stored_item(): void
    {
        // The exact reported case: a hand-typed item with a long specific name and
        // no second name, then a later photo whose wording differs.
        FoodItem::factory()->direct(kcal: 460, protein: 9, fat: 29, carbs: 42)
            ->create(['name' => 'Победа 100% Charged без добавления сахара Stevia', 'alt_name' => null]);

        $this->fakeEmptyApis();
        $this->bindRecogniser(
            english: 'Pobeda Charged sugar free chocolate wafer candy with stevia',
            native: 'Конфеты Победа 100% Charged без добавления сахара со стевией',
        );
        $this->uploadPhoto();

        // It appears as a candidate, shown by its full stored name and marked as
        // coming from the personal library — no longer lost.
        $this->get(route('log.confirm'))
            ->assertOk()
            ->assertSee('Победа 100% Charged без добавления сахара Stevia')
            ->assertSee('Your library');
    }

    public function test_library_candidates_are_capped(): void
    {
        for ($n = 1; $n <= 8; $n++) {
            FoodItem::factory()->direct(kcal: 400, protein: 5, fat: 20, carbs: 60)
                ->create(['name' => "Победа Charged вариант {$n}"]);
        }

        $matches = app(PersonalLibrarySource::class)->matchesFor(new SearchTerms('Победа Charged'));

        // Eight qualify; the source offers a short list, not all of them.
        $this->assertLessThanOrEqual(5, count($matches));
        $this->assertCount(5, $matches);
    }

    public function test_matched_via_names_the_alias_that_surfaced_the_item(): void
    {
        $item = FoodItem::factory()->direct(kcal: 460, protein: 9, fat: 29, carbs: 42)
            ->create(['name' => 'My chocolate', 'alt_name' => null]);
        FoodItemAlias::create(['food_item_id' => $item->id, 'name' => 'Победа Charged Stevia']);

        $matches = app(PersonalLibrarySource::class)->matchesFor(
            new SearchTerms('Pobeda Charged Stevia bar', 'Конфеты Победа Charged Stevia'),
        );

        $this->assertNotEmpty($matches);
        // The full stored name is the description; the alias is the explanation.
        $this->assertSame('My chocolate', $matches[0]->description);
        $this->assertSame('Победа Charged Stevia', $matches[0]->matchedVia);
    }

    public function test_recognition_alone_records_no_alias(): void
    {
        FoodItem::factory()->direct(kcal: 460, protein: 9, fat: 29, carbs: 42)
            ->create(['name' => 'Победа Charged Stevia', 'alt_name' => null]);

        $this->fakeEmptyApis();
        $this->bindRecogniser(english: 'Pobeda Charged bar', native: 'Победа Charged со стевией');
        $this->uploadPhoto();

        // The photo was recognised and the item matched, but nothing was
        // confirmed — so no phrasing is learned. Only confirmation teaches.
        $this->assertSame(0, FoodItemAlias::count());
    }

    public function test_a_manually_entered_barcode_becomes_the_items_stable_id(): void
    {
        $this->fakeEmptyApis();
        $this->bindRecogniser(english: 'Some unknown candy');
        $this->uploadPhoto();

        $this->post(route('log.confirm.store'), [
            'meal' => 'snack',
            'items' => [['include' => 1, 'candidate' => 'manual', 'grams' => 18, 'manual' => [
                'name' => 'Победа Charged', 'kcal' => 460, 'protein' => 9, 'fat' => 29, 'carbs' => 42,
                'barcode' => '4600000000001',
            ]]],
        ])->assertRedirect();

        $item = FoodItem::query()->firstOrFail();
        $this->assertSame('Победа Charged', $item->name);
        $this->assertSame('4600000000001', $item->external_id);
    }
}
