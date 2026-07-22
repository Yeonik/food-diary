<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\FoodItem;
use App\Models\FoodItemAlias;
use App\Models\Goal;
use App\Models\MealEntry;
use App\Models\RecipeIngredient;
use App\Models\WeightEntry;
use App\Nutrition\FoodItemKind;
use App\Nutrition\MealType;
use App\Nutrition\NutrientSource;
use App\Nutrition\ProfileOrigin;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Deterministic demo data for local visual review — enough to see every styled
 * state at once. It uses NO Faker and NO factories, so it runs in a production
 * image built with `composer install --no-dev`:
 *
 *     php artisan db:seed --class=DemoSeeder
 *
 * It is local-only (see the guard below): demo data must never reach a real
 * instance. It resets the diary tables first so repeated runs give a clean,
 * identical state — which is why it refuses to touch anything but a local box.
 * The production path (DatabaseSeeder) is left untouched.
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local')) {
            $env = app()->environment();
            $this->command?->warn("DemoSeeder is local-only and will not run in \"{$env}\". Demo data must not reach production.");

            return;
        }

        $today = CarbonImmutable::today();

        DB::transaction(function () use ($today): void {
            $this->reset();
            $this->seedGoal();
            $this->seedLibrary();
            $this->seedTodayOverGoal($today);
            $this->seedWeightSeries($today);
        });

        $this->command?->info('Demo data seeded: goal, an over-goal day across all meals and provenances, a library with a recipe, and two weeks of weight.');
    }

    /**
     * Clear the diary tables (children before parents) so a re-run is clean and
     * identical rather than piling up duplicates.
     */
    private function reset(): void
    {
        MealEntry::query()->delete();
        RecipeIngredient::query()->delete();
        FoodItemAlias::query()->delete();
        FoodItem::query()->delete();
        WeightEntry::query()->delete();
        Goal::query()->delete();
    }

    /** A goal with a kcal target and macros, so the Day ring is drawn. */
    private function seedGoal(): void
    {
        Goal::query()->create([
            'daily_kcal' => 2000,
            'protein_g' => 120,
            'fat_g' => 70,
            'carbs_g' => 220,
            'show_breakfast' => true,
            'show_lunch' => true,
            'show_dinner' => true,
            'show_snack' => true,
        ]);
    }

    /** Direct products across all three origins, plus one recipe with ingredients. */
    private function seedLibrary(): void
    {
        $this->directItem('Куриная грудка', ProfileOrigin::Usda, null, 165, 31, 3.6, 0);
        $this->directItem('Гречка варёная', ProfileOrigin::OpenFoodFacts, '4600699000001', 110, 4, 1.1, 21);
        $this->directItem('Хлеб «Бородинский»', ProfileOrigin::OpenFoodFacts, '4600699000002', 208, 6.8, 1.3, 40, 'Borodinsky bread');
        $this->directItem('Овсянка «Геркулес»', ProfileOrigin::Manual, null, 352, 12, 6, 62);

        $beet = $this->directItem('Свёкла', ProfileOrigin::Manual, null, 43, 1.6, 0.1, 9.6);
        $potato = $this->directItem('Картофель', ProfileOrigin::Usda, null, 77, 2, 0.1, 17);
        $beef = $this->directItem('Говядина', ProfileOrigin::Usda, null, 250, 26, 17, 0);
        $cabbage = $this->directItem('Капуста', ProfileOrigin::Manual, null, 25, 1.3, 0.1, 5.8);

        $borsch = FoodItem::query()->create([
            'name' => 'Борщ домашний',
            'kind' => FoodItemKind::Recipe,
            'origin' => null,
            'external_id' => null,
            'kcal_per_100g' => null,
            'protein_g_per_100g' => null,
            'fat_g_per_100g' => null,
            'carbs_g_per_100g' => null,
        ]);

        foreach ([[$beet, 200], [$potato, 150], [$beef, 200], [$cabbage, 150]] as [$ingredient, $grams]) {
            RecipeIngredient::query()->create([
                'recipe_id' => $borsch->id,
                'ingredient_id' => $ingredient->id,
                'grams' => $grams,
            ]);
        }
    }

    /**
     * A single day whose logged calories exceed the 2000 target — so the ring
     * can be checked for the rule that it NEVER reddens over goal — with entries
     * in all four meals covering every provenance glyph: the four verified
     * sources (Library, Manual, USDA, Open Food Facts — the barcode path logs as
     * Open Food Facts too) with a ✓, and one photo estimate with a ≈.
     */
    private function seedTodayOverGoal(CarbonImmutable $today): void
    {
        // [meal, hour, minute, name, grams, kcal, protein, fat, carbs, source]
        $rows = [
            [MealType::Breakfast, 8, 30, 'Овсянка на воде', 250, 220, 8, 4, 38, NutrientSource::PersonalLibrary],
            [MealType::Breakfast, 8, 35, 'Кофе с молоком', 200, 60, 3, 3, 5, NutrientSource::Manual],

            [MealType::Lunch, 13, 0, 'Куриная грудка, гриль', 180, 300, 56, 7, 0, NutrientSource::Usda],
            [MealType::Lunch, 13, 5, 'Гречка варёная', 200, 220, 8, 2, 44, NutrientSource::OpenFoodFacts],
            [MealType::Lunch, 13, 10, 'Хлеб «Бородинский»', 80, 200, 6, 1.5, 40, NutrientSource::OpenFoodFacts],

            // Kefir stands in for the barcode path — it too resolves to Open Food Facts.
            [MealType::Dinner, 19, 30, 'Кефир 1% (штрихкод)', 250, 100, 7, 2.5, 10, NutrientSource::OpenFoodFacts],
            [MealType::Dinner, 19, 35, 'Борщ домашний', 350, 320, 12, 14, 32, NutrientSource::PersonalLibrary],
            [MealType::Dinner, 19, 40, 'Оливье', 200, 380, 6, 28, 20, NutrientSource::PersonalLibrary],

            [MealType::Snack, 16, 0, 'Банан', 120, 107, 1.3, 0.4, 27, NutrientSource::Usda],
            // The one estimate — logged from a photo, shown with ≈, never verified.
            [MealType::Snack, 16, 5, 'Печенье овсяное', 60, 280, 4, 12, 38, NutrientSource::Estimated],
        ];

        foreach ($rows as [$meal, $h, $m, $name, $grams, $kcal, $protein, $fat, $carbs, $source]) {
            MealEntry::query()->create([
                'logged_at' => $today->setTime($h, $m),
                'meal' => $meal,
                'name' => $name,
                'grams' => $grams,
                'kcal' => $kcal,
                'protein_g' => $protein,
                'fat_g' => $fat,
                'carbs_g' => $carbs,
                'source' => $source,
                'food_item_id' => null,
            ]);
        }
    }

    /** Two weeks of daily weight, gently trending down, for the chart line. */
    private function seedWeightSeries(CarbonImmutable $today): void
    {
        $kg = [78.6, 78.5, 78.7, 78.4, 78.3, 78.4, 78.1, 78.0, 78.2, 77.9, 77.8, 77.9, 77.6, 77.5];

        foreach ($kg as $i => $weight) {
            WeightEntry::query()->create([
                'recorded_on' => $today->subDays(count($kg) - 1 - $i)->toDateString(),
                'weight_kg' => $weight,
            ]);
        }
    }

    private function directItem(
        string $name,
        ProfileOrigin $origin,
        ?string $externalId,
        float $kcal,
        float $protein,
        float $fat,
        float $carbs,
        ?string $altName = null,
    ): FoodItem {
        return FoodItem::query()->create([
            'name' => $name,
            'alt_name' => $altName,
            'kind' => FoodItemKind::Direct,
            'origin' => $origin,
            'external_id' => $externalId,
            'kcal_per_100g' => $kcal,
            'protein_g_per_100g' => $protein,
            'fat_g_per_100g' => $fat,
            'carbs_g_per_100g' => $carbs,
        ]);
    }
}
