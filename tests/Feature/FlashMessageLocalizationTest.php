<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Middleware\SetLocale;
use App\Models\FoodItem;
use App\Models\MealEntry;
use App\Models\WeightEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The line an action leaves behind. These are written by controllers rather than
 * views, which is how they stayed English while every screen around them was
 * translated — nothing renders them until an action has already happened, so a
 * page test never sees one.
 */
class FlashMessageLocalizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_recording_and_removing_a_weight_reading_answers_in_the_chosen_locale(): void
    {
        $this->withCookie(SetLocale::COOKIE, 'ru')
            ->post(route('weight.store'), ['recorded_on' => '2026-07-20', 'weight_kg' => '77,5'])
            ->assertSessionHas('status', 'Вес записан.');

        $entry = WeightEntry::query()->sole();

        $this->withCookie(SetLocale::COOKIE, 'en')
            ->delete(route('weight.destroy', $entry))
            ->assertSessionHas('status', 'Reading removed.');
    }

    public function test_editing_and_deleting_an_entry_answers_in_the_chosen_locale(): void
    {
        $entry = MealEntry::factory()->create();

        $this->withCookie(SetLocale::COOKIE, 'ru')
            ->patch(route('entries.update', $entry), [
                'name' => 'Гречка',
                'meal' => $entry->meal->value,
                'grams' => 150,
                'kcal' => 165,
                'protein_g' => 6,
                'fat_g' => 2,
                'carbs_g' => 33,
            ])
            ->assertSessionHas('status', 'Запись изменена.');

        $this->withCookie(SetLocale::COOKIE, 'ru')
            ->delete(route('entries.destroy', $entry))
            ->assertSessionHas('status', 'Запись удалена.');
    }

    public function test_adding_and_removing_a_library_item_answers_in_the_chosen_locale(): void
    {
        $this->withCookie(SetLocale::COOKIE, 'ru')
            ->post(route('library.store'), [
                'name' => 'Гречка варёная',
                'kcal_per_100g' => 110,
                'protein_g_per_100g' => 4,
                'fat_g_per_100g' => 1.1,
                'carbs_g_per_100g' => 21,
            ])
            ->assertSessionHas('status', 'Продукт добавлен в библиотеку.');

        $item = FoodItem::query()->sole();

        $this->withCookie(SetLocale::COOKIE, 'ru')
            ->delete(route('library.destroy', $item))
            ->assertSessionHas('status', 'Продукт удалён.');
    }

    public function test_a_recipe_cycle_is_refused_in_the_chosen_locale(): void
    {
        $recipe = FoodItem::factory()->recipe()->create(['name' => 'Борщ']);

        $this->withCookie(SetLocale::COOKIE, 'ru')
            ->patch(route('library.recipe.update', $recipe), [
                'name' => 'Борщ',
                'ingredients' => [['item_id' => $recipe->id, 'grams' => 100]],
            ])
            ->assertSessionHasErrors(['ingredients' => 'Эти ингредиенты образуют цикл.']);
    }
}
