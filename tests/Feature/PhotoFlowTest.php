<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\FoodItem;
use App\Models\MealEntry;
use App\Nutrition\Contracts\FoodRecogniser;
use App\Nutrition\FoodResolver;
use App\Nutrition\MealType;
use App\Nutrition\NutrientSource;
use App\Nutrition\PhotoPreparer;
use App\Nutrition\Recognisers\FakeRecogniser;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The whole path — prepare a photo, recognise it, resolve each dish, log an
 * entry — running with no network call and no API key. This is what makes the
 * repository verifiable by anyone.
 */
class PhotoFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_photo_to_logged_entry_runs_with_no_network_and_no_key(): void
    {
        // In the test environment the recogniser seam is the fake — no key, no call.
        $recogniser = $this->app->make(FoodRecogniser::class);
        $this->assertInstanceOf(FakeRecogniser::class, $recogniser);

        // The dishes the fake will name are already in the personal library, so
        // resolution answers from tier 1. The external APIs are faked empty only
        // to honour preventStrayRequests — the pool must reach no real host.
        Http::fake([
            'api.nal.usda.gov/*' => Http::response(['foods' => []]),
            'world.openfoodfacts.org/*' => Http::response(['products' => []]),
        ]);
        FoodItem::factory()->direct(kcal: 165, protein: 31, fat: 3.6, carbs: 0)
            ->create(['name' => 'Grilled chicken breast']);
        FoodItem::factory()->direct(kcal: 130, protein: 2.7, fat: 0.3, carbs: 28)
            ->create(['name' => 'Steamed rice']);

        // Prepare a real photo (also strips EXIF); this touches no network.
        $prepared = (new PhotoPreparer)->prepare(
            dirname(__DIR__).'/Fixtures/meal-with-gps.jpg',
            sys_get_temp_dir(),
        );

        $resolver = $this->app->make(FoodResolver::class);
        $logged = [];

        foreach ($recogniser->recognise($prepared) as $item) {
            $resolution = $resolver->resolve($item->name, $item->estimatedProfile);

            // Take the top library candidate — exactly what the confirm screen
            // would default to — and snapshot it onto an entry.
            $match = $resolution->libraryMatches[0];

            $entry = MealEntry::fromPortion(
                $match->profile->forGrams($item->estimatedGrams),
                $item->name,
                MealType::Lunch,
                CarbonImmutable::parse('2026-06-15 13:00'),
            );
            $entry->save();
            $logged[] = $entry;
        }

        $this->assertCount(2, $logged);
        $this->assertSame(2, MealEntry::count());

        foreach ($logged as $entry) {
            $this->assertSame(NutrientSource::PersonalLibrary, $entry->source);
            $this->assertTrue($entry->isVerified());
        }

        @unlink($prepared->path);
    }
}
