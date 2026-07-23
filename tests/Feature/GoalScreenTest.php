<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Middleware\SetLocale;
use App\Models\Goal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The goal screen. What is asserted here is mostly the app's manners: a target
 * is optional and the screen says so, the controls work with no JavaScript, and
 * nothing on the page suggests what the number ought to be.
 */
class GoalScreenTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_target_card_is_dimmed_when_no_goal_is_set(): void
    {
        $html = (string) $this->get(route('goal.edit'))->getContent();

        $this->assertMatchesRegularExpression(
            '/<div class="card dim"[^>]*id="goal-card"/',
            $html,
            'With no goal the target card should render dimmed — that is how the screen says a goal is optional.',
        );
    }

    public function test_the_target_card_is_not_dimmed_once_a_goal_exists(): void
    {
        Goal::query()->create(['daily_kcal' => 2000]);

        $html = (string) $this->get(route('goal.edit'))->getContent();

        $this->assertStringContainsString('id="goal-card"', $html);
        $this->assertDoesNotMatchRegularExpression('/<div class="card dim"[^>]*id="goal-card"/', $html);
    }

    /**
     * The stepper buttons are an enhancement; the number itself is a real input,
     * so the screen is usable and submittable with scripting off.
     */
    public function test_every_target_is_an_editable_input_not_a_scripted_display(): void
    {
        Goal::query()->create(['daily_kcal' => 2000, 'protein_g' => 120, 'fat_g' => 70, 'carbs_g' => 220]);

        $html = (string) $this->get(route('goal.edit'))->getContent();

        foreach (['daily_kcal', 'protein_g', 'fat_g', 'carbs_g'] as $field) {
            $this->assertMatchesRegularExpression(
                '/<input type="number" id="'.$field.'" name="'.$field.'"/',
                $html,
                "The {$field} target must be an editable input.",
            );
        }
    }

    public function test_targets_are_shown_as_whole_numbers(): void
    {
        Goal::query()->create(['daily_kcal' => 2000, 'protein_g' => 120, 'fat_g' => 70, 'carbs_g' => 220]);

        $html = (string) $this->get(route('goal.edit'))->getContent();

        $this->assertStringContainsString('value="2000"', $html);
        $this->assertStringContainsString('value="120"', $html);
        $this->assertStringNotContainsString('value="2000.0"', $html);
        $this->assertStringNotContainsString('value="120.0"', $html);
    }

    /**
     * Hiding a meal is a display choice. The entries logged in it are kept and
     * still count — MealVisibilityTest proves the Day side of that; this checks
     * the screen states it rather than leaving the user to guess.
     */
    public function test_the_screen_says_that_hiding_a_meal_deletes_nothing(): void
    {
        $this->withCookie(SetLocale::COOKIE, 'ru')->get(route('goal.edit'))
            ->assertOk()
            ->assertSee(__('settings.meals_hint'));
    }

    public function test_the_language_switch_marks_the_current_locale(): void
    {
        $this->withCookie(SetLocale::COOKIE, 'ru')->get(route('goal.edit'))
            ->assertOk()
            ->assertSee('name="locale" value="ru" class="on"', false);

        $this->withCookie(SetLocale::COOKIE, 'en')->get(route('goal.edit'))
            ->assertOk()
            ->assertSee('name="locale" value="en" class="on"', false);
    }
}
