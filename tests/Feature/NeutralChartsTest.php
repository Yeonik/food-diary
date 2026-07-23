<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Goal;
use App\Models\MealEntry;
use App\Nutrition\MealType;
use App\Nutrition\NutrientSource;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Hard rule 4, pinned where it is easiest to lose: the charts. A day over the
 * goal must draw exactly like a day under it — the goal line is a reference, not
 * a pass mark, and no bar is ever coloured to pass judgement on its value.
 *
 * The assertions are on the rendered markup rather than on a screenshot, because
 * what makes a bar "red" is a class or a fill, and that is what a well-meant
 * change would add.
 */
class NeutralChartsTest extends TestCase
{
    use RefreshDatabase;

    private function logDay(CarbonImmutable $day, int $kcal): void
    {
        MealEntry::factory()->create([
            'logged_at' => $day->setTime(13, 0),
            'meal' => MealType::Lunch,
            'name' => 'Dish',
            'grams' => 300,
            'kcal' => $kcal,
            'protein_g' => 20.0,
            'fat_g' => 10.0,
            'carbs_g' => 40.0,
            'source' => NutrientSource::Manual,
        ]);
    }

    /** @return list<string> every class attribute on a bar, in order */
    private function barClasses(string $html): array
    {
        preg_match_all('/<rect class="([^"]*)"/', $html, $matches);

        return array_map(trim(...), $matches[1]);
    }

    public function test_a_bar_over_the_goal_is_drawn_exactly_like_one_under_it(): void
    {
        $today = CarbonImmutable::today();
        Goal::query()->create(['daily_kcal' => 2000]);

        $this->logDay($today->subDays(2), 1200);   // well under
        $this->logDay($today->subDay(), 2000);     // exactly on
        $this->logDay($today, 3400);               // well over

        $html = (string) $this->get(route('history.index'))->getContent();
        $classes = array_values(array_filter(
            $this->barClasses($html),
            fn (string $class): bool => ! str_contains($class, 'chart__bar--empty'),
        ));

        $this->assertCount(3, $classes);
        $this->assertSame(['chart__bar', 'chart__bar', 'chart__bar'], $classes);
    }

    public function test_no_chart_paints_a_verdict_colour(): void
    {
        $today = CarbonImmutable::today();
        Goal::query()->create(['daily_kcal' => 2000]);
        $this->logDay($today, 3400);

        $html = (string) $this->get(route('history.index'))->getContent();
        $charts = [];
        preg_match_all('/<svg class="chart".*?<\/svg>/s', $html, $charts);

        $this->assertNotEmpty($charts[0], 'No chart rendered, so nothing was actually checked.');

        foreach ($charts[0] as $svg) {
            $this->assertDoesNotMatchRegularExpression(
                '/(fill|stroke)="(?:red|green|orange|#[0-9a-f]*(?:f00|0f0)[0-9a-f]*)"/i',
                $svg,
                'A chart carries a judgement colour.',
            );
        }
    }

    public function test_the_goal_line_is_drawn_only_when_a_goal_is_set(): void
    {
        $this->logDay(CarbonImmutable::today(), 1800);

        $this->get(route('history.index'))->assertDontSee('chart__goal');

        Goal::query()->create(['daily_kcal' => 2000]);

        $this->get(route('history.index'))->assertSee('chart__goal');
    }
}
