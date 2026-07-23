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
}
