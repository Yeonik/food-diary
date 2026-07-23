<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Controllers\PendingLogController;
use App\Models\FoodItem;
use App\Models\MealEntry;
use App\Nutrition\Contracts\FoodRecogniser;
use App\Nutrition\NutrientSource;
use App\Nutrition\Recognisers\GeminiRecogniser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MealPhotoFlowTest extends TestCase
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

    private function fakeEmptyApis(): void
    {
        Http::fake([
            'api.nal.usda.gov/*' => Http::response(['foods' => []]),
            'world.openfoodfacts.org/*' => Http::response(['products' => []]),
        ]);
    }

    public function test_an_upload_that_is_not_an_image_is_rejected(): void
    {
        $notAnImage = UploadedFile::fake()->createWithContent('payload.jpg', str_repeat('not an image ', 128));

        $response = $this->post(route('log.photo.store'), ['photo' => $notAnImage]);

        $response->assertSessionHasErrors('photo');
        $this->assertCount(0, glob(storage_path('app/private/photos').'/*') ?: []);
    }

    public function test_the_client_filename_never_reaches_a_stored_path(): void
    {
        $this->fakeEmptyApis();

        $upload = UploadedFile::fake()->image('my-home-address.jpg', 240, 180);

        $this->post(route('log.photo.store'), ['photo' => $upload])
            ->assertRedirect(route('log.confirm'));

        $pending = session(PendingLogController::SESSION_KEY);
        $storedName = basename($pending['photo']);

        $this->assertStringNotContainsString('my-home-address', $storedName);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{32}\.jpg$/', $storedName);
    }

    public function test_a_photo_is_recognised_confirmed_and_logged_with_its_source_shown(): void
    {
        $this->fakeEmptyApis();

        // The dishes the fake recogniser names are in the library, so they resolve
        // from tier 1 and are logged as personal_library, not as estimates.
        FoodItem::factory()->direct(kcal: 165, protein: 31, fat: 3.6, carbs: 0)
            ->create(['name' => 'Grilled chicken breast']);
        FoodItem::factory()->direct(kcal: 130, protein: 2.7, fat: 0.3, carbs: 28)
            ->create(['name' => 'Steamed rice']);

        $this->post(route('log.photo.store'), ['photo' => UploadedFile::fake()->image('meal.jpg', 400, 300)])
            ->assertRedirect(route('log.confirm'));

        $this->get(route('log.confirm'))
            ->assertOk()
            ->assertSee('Grilled chicken breast')
            // Provenance is shown: a library match reads as verified (the check).
            ->assertSee('✓');

        $response = $this->post(route('log.confirm.store'), [
            'meal' => 'lunch',
            'items' => [
                ['candidate' => 0, 'grams' => 180],
                ['candidate' => 0, 'grams' => 150],
            ],
        ]);

        $response->assertRedirect();
        $this->assertSame(2, MealEntry::count());
        $this->assertSame(2, MealEntry::query()->where('source', NutrientSource::PersonalLibrary->value)->count());

        // The photo is deleted once the entry is confirmed.
        $this->assertCount(0, glob(storage_path('app/private/photos').'/*') ?: []);
    }

    public function test_upload_fails_loudly_when_the_recogniser_is_not_configured(): void
    {
        // A real recogniser with no key: it must raise, never substitute a fake.
        $this->app->instance(FoodRecogniser::class, new GeminiRecogniser(
            'https://generativelanguage.googleapis.com/v1beta',
            'gemini-2.0-flash',
            null,
        ));

        $response = $this->post(route('log.photo.store'), ['photo' => UploadedFile::fake()->image('olives.jpg', 300, 300)]);

        $response->assertSessionHasErrors('photo');
        // No invented result: no entry, and no pending confirmation to proceed with.
        $this->assertSame(0, MealEntry::count());
        $this->assertNull(session(PendingLogController::SESSION_KEY));
    }

    public function test_upload_fails_loudly_when_gemini_rejects_the_key(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response(['error' => ['message' => 'API key not valid']], 401),
        ]);
        $this->app->instance(FoodRecogniser::class, new GeminiRecogniser(
            'https://generativelanguage.googleapis.com/v1beta',
            'gemini-2.0-flash',
            'invalid-key',
        ));

        $response = $this->post(route('log.photo.store'), ['photo' => UploadedFile::fake()->image('olives.jpg', 300, 300)]);

        $response->assertSessionHasErrors('photo');
        $this->assertSame(0, MealEntry::count());
        $this->assertNull(session(PendingLogController::SESSION_KEY));
    }
}
