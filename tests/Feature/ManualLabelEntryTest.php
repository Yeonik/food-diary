<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\FoodItem;
use App\Models\MealEntry;
use App\Nutrition\MealLogService;
use App\Nutrition\NutrientSource;
use App\Nutrition\ProfileOrigin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * A value the user typed off a package label is the most authoritative source
 * there is for a packaged good. It must be logged as verified — not an estimate —
 * attributed honestly to the person, and promoted to the library so it answers
 * first next time.
 */
class ManualLabelEntryTest extends TestCase
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

    /**
     * Drive the photo flow so the session holds a pending item, then return the
     * confirm payload for the first recognised dish. No database answers, so its
     * only candidate is the model's estimate — exactly the case the label path
     * is for.
     */
    private function pendingFirstItem(): void
    {
        Http::fake([
            'api.nal.usda.gov/*' => Http::response(['foods' => []]),
            'world.openfoodfacts.org/*' => Http::response(['products' => []]),
        ]);

        $this->post(route('log.photo.store'), ['photo' => UploadedFile::fake()->image('candy.jpg', 300, 300)])
            ->assertRedirect(route('log.confirm'));
    }

    public function test_a_label_value_is_logged_verified_and_attributed_to_the_person(): void
    {
        $this->pendingFirstItem();

        // The user reads the wrapper and overrides both the name and the numbers.
        $response = $this->post(route('log.confirm.store'), [
            'meal' => 'snack',
            'items' => [
                ['include' => 1, 'candidate' => 'manual', 'grams' => 100, 'manual' => [
                    'name' => 'Alpen Gold hazelnut', 'kcal' => 460, 'protein' => 9, 'fat' => 29, 'carbs' => 42,
                ]],
                ['include' => 0],
            ],
        ]);

        $response->assertRedirect();

        $this->assertSame(1, MealEntry::count());
        $entry = MealEntry::query()->firstOrFail();

        // Verified, and attributed to the person — never to a database source.
        $this->assertSame(NutrientSource::Manual, $entry->source);
        $this->assertTrue($entry->isVerified());
        $this->assertSame('Alpen Gold hazelnut', $entry->name);
        $this->assertEqualsWithDelta(460.0, $entry->kcal, 0.001);
        $this->assertEqualsWithDelta(29.0, $entry->fat_g, 0.001);
    }

    public function test_a_label_value_is_promoted_and_resolves_first_next_time(): void
    {
        $this->pendingFirstItem();

        $this->post(route('log.confirm.store'), [
            'meal' => 'snack',
            'items' => [
                ['include' => 1, 'candidate' => 'manual', 'grams' => 100, 'manual' => [
                    'name' => 'Alpen Gold hazelnut', 'kcal' => 460, 'protein' => 9, 'fat' => 29, 'carbs' => 42,
                ]],
                ['include' => 0],
            ],
        ])->assertRedirect();

        // It landed in the library, marked as hand-entered provenance.
        $item = FoodItem::query()->where('name', 'Alpen Gold hazelnut')->firstOrFail();
        $this->assertSame(ProfileOrigin::Manual, $item->origin);

        // And the next resolution answers from tier 1, the personal library.
        $pending = app(MealLogService::class)->pendingForName('Alpen Gold hazelnut');
        $this->assertNotEmpty($pending['candidates']);
        $this->assertSame(NutrientSource::PersonalLibrary->value, $pending['candidates'][0]['source']);
    }

    public function test_an_incomplete_label_entry_is_rejected_and_nothing_is_logged(): void
    {
        $this->pendingFirstItem();

        // Carbs left blank: the row is incomplete and must not log a partial 0.
        $response = $this->post(route('log.confirm.store'), [
            'meal' => 'snack',
            'items' => [
                ['include' => 1, 'candidate' => 'manual', 'grams' => 100, 'manual' => [
                    'name' => 'Alpen Gold hazelnut', 'kcal' => 460, 'protein' => 9, 'fat' => 29,
                ]],
            ],
        ]);

        $response->assertSessionHasErrors('items.0.manual.carbs');
        $this->assertSame(0, MealEntry::count());
        $this->assertSame(0, FoodItem::count());
    }
}
