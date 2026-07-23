<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Goal;
use App\Models\MealEntry;
use App\Models\WeightEntry;
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

    protected function setUp(): void
    {
        parent::setUp();

        $this->signIn();
    }

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

    /**
     * The weight line gets a scale so it can be read, and that is all it gets.
     * A dashed reference on this chart would be a target weight, which the app
     * does not have and does not offer (hard rule 4).
     */
    public function test_the_weight_line_is_given_a_scale_and_no_target(): void
    {
        $today = CarbonImmutable::today();
        $this->logDay($today, 1800);

        foreach ([80.0, 79.4, 78.2] as $i => $kg) {
            WeightEntry::query()->create([
                'recorded_on' => $today->subDays(2 - $i)->toDateString(),
                'weight_kg' => $kg,
            ]);
        }

        foreach ([route('weight.index'), route('history.index')] as $url) {
            $html = (string) $this->get($url)->assertOk()->getContent();

            $this->assertStringContainsString('chart__line', $html, "No weight line on {$url}.");
            $this->assertStringContainsString('chart-scale', $html, "The line on {$url} carries no scale.");
            $this->assertStringContainsString('chart-dates', $html, "The line on {$url} carries no dates.");
        }

        // Setting a goal must not put a reference on the weight line: a daily
        // calorie goal is not a target weight, and the app has no such thing.
        Goal::query()->create(['daily_kcal' => 2000]);

        preg_match('/<div class="chart-plot">(.*?)<\/svg>/s', (string) $this->get(route('weight.index'))
            ->assertOk()->getContent(), $chart);

        preg_match_all('/<line class="([^"]*)"/', $chart[1] ?? '', $rules);
        $this->assertNotEmpty($rules[1], 'No rules at all, so nothing was actually checked.');
        $this->assertSame(
            array_fill(0, count($rules[1]), 'chart__grid'),
            $rules[1],
            'The weight line carries a rule that is not part of its scale.',
        );
    }

    public function test_the_goal_line_is_drawn_only_when_a_goal_is_set(): void
    {
        $this->logDay(CarbonImmutable::today(), 1800);

        $this->get(route('history.index'))->assertDontSee('chart__goal');

        Goal::query()->create(['daily_kcal' => 2000]);

        $this->get(route('history.index'))->assertSee('chart__goal');
    }
}
