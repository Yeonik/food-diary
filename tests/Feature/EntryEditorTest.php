<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\FoodItem;
use App\Models\MealEntry;
use App\Nutrition\MealType;
use App\Nutrition\NutrientSource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Editing one logged entry. Correcting or removing what you ate is ordinary and
 * unpenalised, and it reaches nothing else: the entry holds its own snapshot.
 */
class EntryEditorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->signIn();
    }

    private function entry(): MealEntry
    {
        return MealEntry::factory()->create([
            'name' => 'Buckwheat',
            'meal' => MealType::Dinner,
            'grams' => 150,
            'kcal' => 165,
            'protein_g' => 6.0,
            'fat_g' => 2.0,
            'carbs_g' => 33.0,
            'source' => NutrientSource::PersonalLibrary,
        ]);
    }

    public function test_the_editor_shows_the_entry_filled_in_with_the_source_it_was_logged_with(): void
    {
        $html = (string) $this->get(route('entries.edit', $this->entry()))->assertOk()->getContent();

        $this->assertStringContainsString('value="Buckwheat"', $html);
        $this->assertStringContainsString('value="150"', $html);
        $this->assertStringContainsString('value="165"', $html);
        $this->assertStringContainsString(__('source.personal_library'), $html);

        // And it states plainly that this is free to change.
        $this->assertStringContainsString(__('entries.note'), $html);
    }

    public function test_deleting_is_offered_beside_saving(): void
    {
        $entry = $this->entry();

        $html = (string) $this->get(route('entries.edit', $entry))->assertOk()->getContent();

        $this->assertStringContainsString(route('entries.destroy', $entry), $html);
        $this->assertStringContainsString(__('common.delete'), $html);
        $this->assertStringContainsString(__('common.save'), $html);
    }

    public function test_an_edit_changes_this_entry_and_nothing_else(): void
    {
        $entry = $this->entry();
        $other = $this->entry();

        $this->patch(route('entries.update', $entry), [
            'name' => 'Buckwheat with butter',
            'meal' => MealType::Lunch->value,
            'grams' => 200,
            'kcal' => 260,
            'protein_g' => 7,
            'fat_g' => 9,
            'carbs_g' => 35,
        ])->assertRedirect();

        $entry->refresh();
        $this->assertSame('Buckwheat with butter', $entry->name);
        $this->assertSame(MealType::Lunch, $entry->meal);
        $this->assertSame(260.0, $entry->kcal);

        // The other entry is untouched.
        $this->assertSame(165.0, $other->refresh()->kcal);
    }

    public function test_an_edit_does_not_reach_back_into_the_library(): void
    {
        $item = FoodItem::factory()->create(['name' => 'Buckwheat', 'kcal_per_100g' => 110]);
        $entry = MealEntry::factory()->create([
            'name' => 'Buckwheat',
            'food_item_id' => $item->id,
            'grams' => 150,
            'kcal' => 165,
            'protein_g' => 6.0,
            'fat_g' => 2.0,
            'carbs_g' => 33.0,
            'source' => NutrientSource::PersonalLibrary,
        ]);

        $this->patch(route('entries.update', $entry), [
            'name' => 'Buckwheat, more of it',
            'meal' => $entry->meal->value,
            'grams' => 300,
            'kcal' => 330,
            'protein_g' => 12,
            'fat_g' => 4,
            'carbs_g' => 66,
        ])->assertRedirect();

        // The library item it came from is exactly as it was.
        $this->assertSame(110.0, $item->refresh()->kcal_per_100g);
        $this->assertSame('Buckwheat', $item->name);
    }

    public function test_deleting_removes_the_entry_and_returns_to_its_day(): void
    {
        $entry = $this->entry();
        $day = $entry->logged_at->toDateString();

        $this->delete(route('entries.destroy', $entry))
            ->assertRedirect(route('diary.index', ['date' => $day]));

        $this->assertSame(0, MealEntry::query()->count());
    }
}
