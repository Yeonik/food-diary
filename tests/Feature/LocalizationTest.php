<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Middleware\SetLocale;
use App\Models\WeightEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Tests\TestCase;

/**
 * The localisation mechanism: matching key sets across locales, both languages
 * rendering the key screens, and the choice resolving from a saved cookie, then
 * Accept-Language, then the default.
 */
class LocalizationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Every translation key in a locale, as "group.key".
     *
     * @return list<string>
     */
    private function keys(string $locale): array
    {
        $keys = [];

        foreach (glob(lang_path($locale).'/*.php') ?: [] as $file) {
            $group = basename($file, '.php');
            /** @var array<string, mixed> $data */
            $data = require $file;
            foreach (array_keys(Arr::dot($data)) as $key) {
                $keys[] = $group.'.'.$key;
            }
        }

        sort($keys);

        return $keys;
    }

    public function test_ru_and_en_declare_exactly_the_same_keys(): void
    {
        $en = $this->keys('en');
        $ru = $this->keys('ru');

        $this->assertNotEmpty($en);
        $this->assertSame($en, $ru, 'ru and en key sets differ — a translation is missing or extra.');
    }

    public function test_the_key_screens_return_ok_in_both_locales(): void
    {
        $screens = [
            '/',
            route('history.index'),
            route('weight.index'),
            route('library.index'),
            route('goal.edit'),
            route('log.photo'),
            route('log.manual'),
        ];

        foreach (['en', 'ru'] as $locale) {
            foreach ($screens as $url) {
                $this->withCookie(SetLocale::COOKIE, $locale)->get($url)->assertOk();
            }
        }
    }

    public function test_the_diary_renders_in_the_chosen_locale_without_the_other_leaking(): void
    {
        $this->withCookie(SetLocale::COOKIE, 'en')->get('/')
            ->assertSee('Today')
            ->assertDontSee('Сегодня');

        $this->withCookie(SetLocale::COOKIE, 'ru')->get('/')
            ->assertSee('Сегодня')
            ->assertDontSee('Today');
    }

    public function test_dates_and_numbers_follow_the_locale(): void
    {
        // A past date localises its month name — not left in English.
        $this->withCookie(SetLocale::COOKIE, 'ru')->get('/?date=2026-06-15')->assertSee('июня');
        $this->withCookie(SetLocale::COOKIE, 'en')->get('/?date=2026-06-15')->assertSee('June');

        // Weight is written with a decimal comma in ru, a point in en.
        WeightEntry::factory()->create(['recorded_on' => '2026-06-15', 'weight_kg' => 77.5]);
        $this->withCookie(SetLocale::COOKIE, 'ru')->get(route('weight.index'))->assertSee('77,5');
        $this->withCookie(SetLocale::COOKIE, 'en')->get(route('weight.index'))->assertSee('77.5');
    }

    public function test_the_language_choice_persists_and_applies_on_the_next_request(): void
    {
        $this->post(route('locale.update'), ['locale' => 'ru'])
            ->assertRedirect()
            ->assertCookie(SetLocale::COOKIE, 'ru');

        // The saved cookie drives the next request.
        $this->withCookie(SetLocale::COOKIE, 'ru')->get('/')->assertSee('Сегодня');
    }

    public function test_accept_language_selects_on_a_first_visit_and_a_saved_choice_overrides_it(): void
    {
        // First visit, no cookie: the browser's preference is honoured.
        $this->withHeader('Accept-Language', 'ru')->get('/')->assertSee('Сегодня');

        // A saved choice beats the header.
        $this->withCookie(SetLocale::COOKIE, 'en')
            ->withHeader('Accept-Language', 'ru')
            ->get('/')
            ->assertSee('Today');
    }
}
