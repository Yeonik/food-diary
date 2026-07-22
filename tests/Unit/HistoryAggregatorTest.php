<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\MealEntry;
use App\Nutrition\HistoryAggregator;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Tests\TestCase;

/**
 * The aggregator's arithmetic, checked against hand figures. It runs on
 * unsaved models — no database, no network — so only the rules are under test:
 * a continuous day series with zeros for unlogged days, and an average that
 * divides by the days that have entries.
 */
class HistoryAggregatorTest extends TestCase
{
    private function entry(string $date, float $kcal): MealEntry
    {
        return MealEntry::factory()->make([
            'logged_at' => $date.' 12:00:00',
            'kcal' => $kcal,
            'protein_g' => 0,
            'fat_g' => 0,
            'carbs_g' => 0,
        ]);
    }

    /**
     * @param  list<MealEntry>  $entries
     * @return Collection<int, MealEntry>
     */
    private function collect(array $entries): Collection
    {
        return new Collection($entries);
    }

    public function test_the_bar_series_is_continuous_with_zeros_for_unlogged_days(): void
    {
        $summary = (new HistoryAggregator)->summarise(
            $this->collect([
                $this->entry('2026-06-02', 200),
                $this->entry('2026-06-05', 300),
            ]),
            CarbonImmutable::parse('2026-06-01'),
            CarbonImmutable::parse('2026-06-07'),
        );

        // Seven calendar days, none skipped.
        $this->assertCount(7, $summary->days);
        $this->assertSame(['date' => '2026-06-01', 'kcal' => 0], $summary->days[0]);
        $this->assertSame(['date' => '2026-06-02', 'kcal' => 200], $summary->days[1]);
        $this->assertSame(['date' => '2026-06-05', 'kcal' => 300], $summary->days[4]);
        $this->assertSame(['date' => '2026-06-07', 'kcal' => 0], $summary->days[6]);
        $this->assertSame(500, $summary->totalKcal);
        $this->assertSame(2, $summary->entryCount);
    }

    public function test_daily_sums_match_hand_arithmetic(): void
    {
        $summary = (new HistoryAggregator)->summarise(
            $this->collect([
                $this->entry('2026-06-03', 100),
                $this->entry('2026-06-03', 150),
                $this->entry('2026-06-04', 200),
            ]),
            CarbonImmutable::parse('2026-06-01'),
            CarbonImmutable::parse('2026-06-07'),
        );

        // 100 + 150 on the 3rd, 200 on the 4th.
        $this->assertSame(250, $summary->days[2]['kcal']);
        $this->assertSame(200, $summary->days[3]['kcal']);
        $this->assertSame(450, $summary->totalKcal);
    }

    public function test_average_divides_by_days_with_entries(): void
    {
        $summary = (new HistoryAggregator)->summarise(
            $this->collect([
                $this->entry('2026-06-01', 300),
                $this->entry('2026-06-02', 300),
                $this->entry('2026-06-03', 300),
            ]),
            CarbonImmutable::parse('2026-06-01'),
            CarbonImmutable::parse('2026-06-07'),
        );

        // 900 over 3 logged days of 7: 300, never 900 / 7 = 129.
        $this->assertSame(300, $summary->avgKcalPerDay);
    }

    public function test_a_custom_range_counts_only_its_own_days(): void
    {
        // Three logged days inside a ten-day custom range.
        $summary = (new HistoryAggregator)->summarise(
            $this->collect([
                $this->entry('2026-06-02', 400),
                $this->entry('2026-06-05', 400),
                $this->entry('2026-06-09', 400),
            ]),
            CarbonImmutable::parse('2026-06-01'),
            CarbonImmutable::parse('2026-06-10'),
        );

        $this->assertCount(10, $summary->days);
        $this->assertSame(1200, $summary->totalKcal);
        $this->assertSame(400, $summary->avgKcalPerDay); // 1200 / 3, not / 10
        $this->assertSame(3, $summary->entryCount);
    }
}
