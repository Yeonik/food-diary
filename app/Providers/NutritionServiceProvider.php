<?php

declare(strict_types=1);

namespace App\Providers;

use App\Nutrition\Contracts\FoodRecogniser;
use App\Nutrition\FoodResolver;
use App\Nutrition\PhotoPreparer;
use App\Nutrition\Recognisers\FakeRecogniser;
use App\Nutrition\Recognisers\GeminiRecogniser;
use App\Nutrition\Sources\OpenFoodFactsSource;
use App\Nutrition\Sources\PersonalLibrarySource;
use App\Nutrition\Sources\UsdaSource;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

/**
 * Wires the nutrition domain together. The important seam is the recogniser:
 * in the test environment it is always the fake, so the suite runs with no
 * network call and no API key.
 */
class NutritionServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(FoodRecogniser::class, function (Application $app): FoodRecogniser {
            // The fake is a TEST DOUBLE, never a runtime mode. An app that
            // silently serves invented food is the worst failure this project
            // can have — worse than an invented number, it is an invented meal.
            // So outside the test suite the real recogniser is ALWAYS used, and
            // a missing or invalid key fails loudly rather than falling back.
            if ($app->environment('testing')) {
                return new FakeRecogniser;
            }

            return new GeminiRecogniser(
                $this->string('nutrition.gemini.base_url'),
                $this->string('nutrition.gemini.model'),
                $this->nullableString('nutrition.gemini.key'),
            );
        });

        $this->app->singleton(FoodResolver::class, fn (Application $app): FoodResolver => new FoodResolver(
            $app->make(PersonalLibrarySource::class),
            [
                new UsdaSource(
                    $this->string('nutrition.usda.base_url'),
                    $this->nullableString('nutrition.usda.key'),
                ),
                new OpenFoodFactsSource(
                    $this->string('nutrition.open_food_facts.base_url'),
                    $this->string('nutrition.open_food_facts.user_agent'),
                ),
            ],
        ));

        $this->app->singleton(
            PhotoPreparer::class,
            fn (): PhotoPreparer => new PhotoPreparer((int) config('nutrition.photo.max_dimension', 1600)),
        );
    }

    private function string(string $key): string
    {
        $value = config($key);

        return is_string($value) ? $value : '';
    }

    private function nullableString(string $key): ?string
    {
        $value = config($key);

        return is_string($value) && $value !== '' ? $value : null;
    }
}
