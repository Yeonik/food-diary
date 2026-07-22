<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Middleware\SetLocale;
use App\Models\FoodItem;
use App\Models\MealEntry;
use App\Models\RecipeIngredient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The secondary forms that were not part of the screen rebuild — the unlock
 * gate, the library item and recipe editors, the entry editor — render in both
 * locales with no English literal left behind and no unresolved translation key
 * leaking into the markup.
 */
class SecondaryFormLocalizationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A rendered page must never contain a bare "group.key" — that is what
     * Laravel echoes when a translation is missing.
     */
    private function assertNoUntranslatedKeys(string $html, string $where): void
    {
        $this->assertDoesNotMatchRegularExpression(
            '/\b(?:access|entries|library)\.[a-z_]+/',
            $html,
            "An untranslated key leaked in {$where}.",
        );
    }

    public function test_the_unlock_gate_localises_in_both_locales(): void
    {
        // A password makes the instance locked, so the gate actually renders.
        config(['nutrition.access_password' => 'letmein']);

        $expect = ['en' => 'Access password', 'ru' => 'Пароль доступа'];

        foreach (['en', 'ru'] as $locale) {
            $response = $this->withCookie(SetLocale::COOKIE, $locale)->get(route('unlock.show'));
            $response->assertOk()->assertSee($expect[$locale]);
            $this->assertNoUntranslatedKeys((string) $response->getContent(), "{$locale} at unlock");
        }
    }

    public function test_the_library_and_entry_editors_localise_in_both_locales(): void
    {
        $item = FoodItem::factory()->create(['name' => 'Test item']);
        $recipe = FoodItem::factory()->recipe()->create(['name' => 'Test recipe']);
        RecipeIngredient::factory()->create(['recipe_id' => $recipe->id, 'ingredient_id' => $item->id]);
        $entry = MealEntry::factory()->create();

        // url => [locale => a phrase that only appears once translated correctly]
        $cases = [
            route('library.create') => ['en' => 'A direct nutrient profile', 'ru' => 'Профиль питательности напрямую'],
            route('library.edit', $item) => ['en' => 'Corrections apply to future logs', 'ru' => 'Правки применяются к будущим записям'],
            route('library.recipe.create') => ['en' => 'Define a recipe', 'ru' => 'Создать рецепт'],
            route('library.recipe.edit', $recipe) => ['en' => 'Edit recipe', 'ru' => 'Изменить рецепт'],
            route('entries.edit', $entry) => ['en' => 'This changes only this entry', 'ru' => 'Меняется только эта запись'],
        ];

        foreach ($cases as $url => $expect) {
            foreach (['en', 'ru'] as $locale) {
                $response = $this->withCookie(SetLocale::COOKIE, $locale)->get($url);
                $response->assertOk()->assertSee($expect[$locale]);
                $this->assertNoUntranslatedKeys((string) $response->getContent(), "{$locale} at {$url}");
            }
        }
    }
}
