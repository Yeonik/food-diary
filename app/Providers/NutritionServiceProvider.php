<?php

declare(strict_types=1);

namespace App\Providers;

use App\Nutrition\Contracts\FoodRecogniser;
use App\Nutrition\Contracts\IngredientTranslator;
use App\Nutrition\FakeIngredientTranslator;
use App\Nutrition\FoodResolver;
use App\Nutrition\GeminiIngredientTranslator;
use App\Nutrition\NameMatcher;
use App\Nutrition\PhotoPreparer;
use App\Nutrition\Recognisers\FakeRecogniser;
use App\Nutrition\Recognisers\GeminiRecogniser;
use App\Nutrition\Recognisers\MeteredRecogniser;
use App\Nutrition\RecognitionQuota;
use App\Nutrition\Sources\OpenFoodFactsSource;
use App\Nutrition\Sources\PersonalLibrarySource;
use App\Nutrition\Sources\UsdaSource;
use Illuminate\Contracts\Cache\Repository;
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
            $recogniser = $app->environment('testing')
                ? new FakeRecogniser
                : new GeminiRecogniser(
                    $this->string('nutrition.gemini.base_url'),
                    $this->string('nutrition.gemini.model'),
                    $this->nullableString('nutrition.gemini.key'),
                    (int) config('nutrition.gemini.timeout', 60),
                );

            // Metered in every environment, the fake included. The quota is part
            // of what recognition *is* here, not a production-only wrapper — a
            // suite that ran without it could not tell whether the limit works.
            return new MeteredRecogniser($recogniser, $app->make(RecognitionQuota::class));
        });

        // Translating a foreign ingredient name for USDA. The fake, in the test
        // suite, translates nothing unless a test seeds it — so CI makes no call
        // and needs no key, the same seam the recogniser has. It is a singleton
        // so a test can seed the one instance the controller will resolve.
        $this->app->singleton(IngredientTranslator::class, function (Application $app): IngredientTranslator {
            if ($app->environment('testing')) {
                return new FakeIngredientTranslator;
            }

            return new GeminiIngredientTranslator(
                $this->string('nutrition.gemini.base_url'),
                $this->string('nutrition.gemini.model'),
                $this->nullableString('nutrition.gemini.key'),
                $app->make(Repository::class),
                (int) config('nutrition.gemini.translate_timeout', 15),
            );
        });

        // Bound once so the barcode lookup path (a controller) and the resolver
        // (tier 2) share the same configured client.
        $this->app->singleton(OpenFoodFactsSource::class, fn (): OpenFoodFactsSource => new OpenFoodFactsSource(
            $this->string('nutrition.open_food_facts.base_url'),
            $this->string('nutrition.open_food_facts.user_agent'),
        ));

        $this->app->singleton(FoodResolver::class, fn (Application $app): FoodResolver => new FoodResolver(
            $app->make(PersonalLibrarySource::class),
            [
                new UsdaSource(
                    $this->string('nutrition.usda.base_url'),
                    $this->nullableString('nutrition.usda.key'),
                    $app->make(NameMatcher::class),
                ),
                $app->make(OpenFoodFactsSource::class),
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
