<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Middleware\SetLocale;
use App\Models\WeightEntry;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The weight log: recording a reading however its decimal is punctuated, and
 * reading it back in the locale's own notation.
 */
class WeightScreenTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->signIn();
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function separators(): array
    {
        return [
            'a dot' => ['77.5'],
            'a comma' => ['77,5'],
        ];
    }

    #[DataProvider('separators')]
    public function test_a_reading_is_recorded_whichever_decimal_separator_is_typed(string $typed): void
    {
        $this->post(route('weight.store'), [
            'recorded_on' => '2026-07-20',
            'weight_kg' => $typed,
        ])->assertRedirect(route('weight.index'));

        $entry = WeightEntry::query()->sole();
        $this->assertSame(77.5, (float) $entry->weight_kg);
    }

    public function test_a_value_that_is_not_a_number_is_still_refused(): void
    {
        $this->post(route('weight.store'), [
            'recorded_on' => '2026-07-20',
            'weight_kg' => 'seventy-seven',
        ])->assertSessionHasErrors('weight_kg');

        $this->assertSame(0, WeightEntry::query()->count());
    }

    public function test_the_log_prints_the_reading_in_the_locale_notation(): void
    {
        WeightEntry::query()->create(['recorded_on' => '2026-07-20', 'weight_kg' => 77.5]);

        $this->withCookie(SetLocale::COOKIE, 'ru')->get(route('weight.index'))
            ->assertOk()
            ->assertSee('77,5');

        $this->withCookie(SetLocale::COOKIE, 'en')->get(route('weight.index'))
            ->assertOk()
            ->assertSee('77.5');
    }

    public function test_the_screen_shows_the_empty_state_before_any_reading(): void
    {
        $this->get(route('weight.index'))
            ->assertOk()
            ->assertSee(__('weight.empty_title'))
            ->assertDontSee('chart__line');
    }

    public function test_the_line_is_drawn_once_readings_exist(): void
    {
        $today = CarbonImmutable::today();
        foreach ([78.6, 78.4, 78.1] as $i => $kg) {
            WeightEntry::query()->create([
                'recorded_on' => $today->subDays(2 - $i)->toDateString(),
                'weight_kg' => $kg,
            ]);
        }

        $this->get(route('weight.index'))
            ->assertOk()
            ->assertSee('chart__line')
            ->assertDontSee(__('weight.empty_title'));
    }

    /** @return list<string> the contents of one HTML block's spans, in order */
    private function labelsIn(string $html, string $class): array
    {
        preg_match('/<div class="'.$class.'"[^>]*>(.*?)<\/div>/s', $html, $block);
        preg_match_all('/<span[^>]*>(.*?)<\/span>/s', $block[1] ?? '', $labels);

        return array_map(trim(...), $labels[1] ?? []);
    }

    public function test_the_line_is_labelled_with_the_range_it_spans(): void
    {
        // A line with no numbers on it says nothing. The scale names the top, the
        // middle and the bottom of what is drawn — and nothing else: no target, no
        // reading of the direction (hard rule 4).
        foreach ([78.0, 80.0, 79.0] as $i => $kg) {
            WeightEntry::query()->create([
                'recorded_on' => '2026-07-2'.$i,
                'weight_kg' => $kg,
            ]);
        }

        $html = (string) $this->withCookie(SetLocale::COOKIE, 'ru')
            ->get(route('weight.index'))->assertOk()->getContent();

        $this->assertSame(['80,0', '79,0', '78,0'], $this->labelsIn($html, 'chart-scale'));
    }

    public function test_the_scale_collapses_to_one_number_when_nothing_moved(): void
    {
        foreach (['2026-07-20', '2026-07-21'] as $day) {
            WeightEntry::query()->create(['recorded_on' => $day, 'weight_kg' => 77.5]);
        }

        $html = (string) $this->withCookie(SetLocale::COOKIE, 'ru')
            ->get(route('weight.index'))->assertOk()->getContent();

        // A flat line has no range to divide, so three labels would all read 77,5.
        $this->assertSame(['77,5'], $this->labelsIn($html, 'chart-scale'));
    }

    public function test_the_dates_under_the_line_name_the_ends_of_the_period(): void
    {
        foreach (range(0, 9) as $i) {
            WeightEntry::query()->create([
                'recorded_on' => CarbonImmutable::parse('2026-07-01')->addDays($i)->toDateString(),
                'weight_kg' => 78.0 + $i / 10,
            ]);
        }

        $html = (string) $this->withCookie(SetLocale::COOKIE, 'ru')
            ->get(route('weight.index'))->assertOk()->getContent();
        $dates = $this->labelsIn($html, 'chart-dates');

        $this->assertCount(4, $dates, 'The axis should thin ten readings down to four dates.');
        $this->assertSame('1 июл', $dates[0]);
        $this->assertSame('10 июл', $dates[3]);
    }
}
