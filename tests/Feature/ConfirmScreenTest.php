<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Controllers\PendingLogController;
use App\Models\MealEntry;
use App\Nutrition\NutrientSource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The confirm screen preselects nothing (the brief's "nothing auto-selected
 * when both answer"), refuses to log a dish without a chosen source, uses the
 * chosen source's numbers scaled by the portion, and — when no database source
 * answered — offers hand entry and the model estimate, writing the estimate as
 * estimated, never verified.
 */
class ConfirmScreenTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->signIn();
    }

    /**
     * @param  list<array<string, mixed>>  $candidates
     * @return array<string, mixed>
     */
    private function pending(array $candidates, float $grams = 100): array
    {
        return [
            PendingLogController::SESSION_KEY => [
                'photo' => null,
                'items' => [[
                    'name' => 'Borscht',
                    'grams' => $grams,
                    'english' => 'Borscht',
                    'native' => null,
                    'candidates' => $candidates,
                ]],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function candidate(string $source, float $kcal, bool $verified, ?string $imageUrl = null): array
    {
        return [
            'label' => ucfirst($source).' match',
            'source' => $source,
            'source_label' => $source,
            'kcal' => $kcal,
            'protein' => 1.0,
            'fat' => 1.0,
            'carbs' => 1.0,
            'verified' => $verified,
            'matched_via' => null,
            'food_item_id' => null,
            'external_id' => null,
            'image_url' => $imageUrl,
        ];
    }

    public function test_no_source_is_selected_by_default_and_submit_starts_disabled(): void
    {
        $pending = $this->pending([
            $this->candidate('usda', 100, true),
            $this->candidate('open_food_facts', 250, true),
        ]);

        $html = (string) $this->withSession($pending)->get(route('log.confirm'))
            ->assertOk()->getContent();

        // Every choice, including hand entry and skip, renders unchecked. Read off
        // the radios themselves rather than the page, so the assertion cannot pass
        // just because the word is missing.
        preg_match_all('/<input type="radio"[^>]*>/', $html, $radios);
        $this->assertGreaterThanOrEqual(4, count($radios[0]), 'The candidates did not render.');
        foreach ($radios[0] as $radio) {
            $this->assertStringNotContainsString('checked', $radio);
        }

        // And the button that would log them says why it cannot yet.
        $this->assertMatchesRegularExpression('/<button[^>]*data-confirm-submit[^>]*disabled/', $html);
        $this->assertStringContainsString(__('confirm.choose_hint'), $html);
    }

    /**
     * The block that explains the zero-match case belongs on a dish nothing
     * answered for, and nowhere else.
     */
    public function test_the_no_match_explanation_appears_only_when_nothing_answered(): void
    {
        $this->withSession($this->pending([$this->candidate('estimated', 50, false)]))
            ->get(route('log.confirm'))
            ->assertOk()
            ->assertSee('zero-note')
            ->assertSee(__('confirm.no_matches'));

        $this->withSession($this->pending([$this->candidate('usda', 100, true)]))
            ->get(route('log.confirm'))
            ->assertOk()
            ->assertDontSee('zero-note');
    }

    public function test_the_chosen_source_supplies_its_own_values_scaled_by_weight(): void
    {
        $pending = $this->pending([
            $this->candidate('usda', 100, true),
            $this->candidate('open_food_facts', 250, true),
        ]);

        // Choose Open Food Facts (250 / 100 g) at 200 g → 500 kcal, not USDA's 100.
        $this->withSession($pending)->post(route('log.confirm.store'), [
            'meal' => 'lunch',
            'items' => [['candidate' => 1, 'grams' => 200]],
        ])->assertRedirect();

        $entry = MealEntry::query()->firstOrFail();
        $this->assertSame(NutrientSource::OpenFoodFacts, $entry->source);
        $this->assertSame(500.0, $entry->kcal);
    }

    public function test_a_dish_with_no_source_chosen_is_not_logged(): void
    {
        $pending = $this->pending([$this->candidate('usda', 100, true)]);

        // No candidate for the dish: nothing is logged.
        $this->withSession($pending)->post(route('log.confirm.store'), [
            'meal' => 'lunch',
            'items' => [['grams' => 150]],
        ])->assertRedirect();

        $this->assertSame(0, MealEntry::count());
    }

    public function test_with_no_matches_the_estimate_is_offered_and_written_estimated(): void
    {
        // No USDA/OFF answered: only the model's estimate is a candidate.
        $pending = $this->pending([$this->candidate('estimated', 50, false)]);

        $this->withSession($pending)->get(route('log.confirm'))
            ->assertOk()
            ->assertSee(__('confirm.manual_option'))   // hand entry offered
            ->assertSee('≈');                          // the estimate, marked

        // Choosing the estimate logs it as estimated — never verified.
        $this->withSession($pending)->post(route('log.confirm.store'), [
            'meal' => 'dinner',
            'items' => [['candidate' => 0, 'grams' => 100]],
        ])->assertRedirect();

        $entry = MealEntry::query()->firstOrFail();
        $this->assertSame(NutrientSource::Estimated, $entry->source);
        $this->assertFalse($entry->isVerified());
    }

    public function test_an_open_food_facts_candidate_shows_its_thumbnail_and_one_without_renders_cleanly(): void
    {
        // With an image: the thumbnail is pulled by link.
        $this->withSession($this->pending([
            $this->candidate('open_food_facts', 120, true, 'https://images.openfoodfacts.org/z.small.jpg'),
        ]))->get(route('log.confirm'))
            ->assertOk()
            ->assertSee('https://images.openfoodfacts.org/z.small.jpg', false);

        // Without one: no image element at all, and no error.
        $html = (string) $this->withSession($this->pending([
            $this->candidate('open_food_facts', 120, true),
        ]))->get(route('log.confirm'))->assertOk()->getContent();

        $this->assertStringNotContainsString('<img', $html);
        $this->assertStringNotContainsString('cthumb', $html);
    }

    public function test_hand_entered_values_are_logged_verified_at_zero_matches(): void
    {
        $pending = $this->pending([$this->candidate('estimated', 50, false)]);

        $this->withSession($pending)->post(route('log.confirm.store'), [
            'meal' => 'dinner',
            'items' => [[
                'candidate' => 'manual',
                'grams' => 100,
                'manual' => ['name' => 'Candy bar', 'kcal' => 500, 'protein' => 5, 'fat' => 25, 'carbs' => 60],
            ]],
        ])->assertRedirect();

        $entry = MealEntry::query()->firstOrFail();
        $this->assertSame(NutrientSource::Manual, $entry->source);
        $this->assertTrue($entry->isVerified());
    }
}
