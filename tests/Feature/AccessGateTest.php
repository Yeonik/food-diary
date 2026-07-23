<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class AccessGateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // The throttle is keyed per IP and outlives a test's database, so it is
        // cleared explicitly rather than left to leak into the next one.
        RateLimiter::clear('');
    }

    public function test_the_diary_is_open_when_no_password_is_configured(): void
    {
        config(['nutrition.access_password' => null]);

        $this->get(route('diary.index'))->assertOk();
    }

    public function test_a_configured_password_locks_and_then_unlocks_the_app(): void
    {
        config(['nutrition.access_password' => 'letmein']);

        $this->get(route('diary.index'))->assertRedirect(route('unlock.show'));

        $this->post(route('unlock'), ['password' => 'wrong'])->assertSessionHasErrors('password');
        $this->get(route('diary.index'))->assertRedirect(route('unlock.show'));

        $this->post(route('unlock'), ['password' => 'letmein']);
        $this->get(route('diary.index'))->assertOk();
    }

    /**
     * The gate is one shared secret with no lockout of its own, so the throttle
     * on the attempts is the only brake on guessing it. Five a minute per IP.
     */
    public function test_guessing_is_throttled(): void
    {
        config(['nutrition.access_password' => 'letmein']);

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->post(route('unlock'), ['password' => 'guess-'.$attempt])
                ->assertStatus(302);
        }

        // The sixth is refused by the throttle rather than reaching the check.
        $this->post(route('unlock'), ['password' => 'guess-6'])->assertStatus(429);

        // And the correct password is refused too while the window holds — the
        // throttle does not care which one it was.
        $this->post(route('unlock'), ['password' => 'letmein'])->assertStatus(429);
        $this->get(route('diary.index'))->assertRedirect(route('unlock.show'));
    }

    public function test_a_refused_password_says_so_in_the_chosen_locale(): void
    {
        config(['nutrition.access_password' => 'letmein']);

        $this->withCookie(SetLocale::COOKIE, 'ru')
            ->post(route('unlock'), ['password' => 'wrong'])
            ->assertSessionHasErrors(['password' => 'Пароль не подходит.']);

        $this->withCookie(SetLocale::COOKIE, 'en')
            ->post(route('unlock'), ['password' => 'wrong'])
            ->assertSessionHasErrors(['password' => 'That password does not match.']);
    }

    public function test_the_gate_offers_no_navigation_out_of_itself(): void
    {
        config(['nutrition.access_password' => 'letmein']);

        $html = (string) $this->get(route('unlock.show'))->assertOk()->getContent();

        // Nothing behind the gate is reachable, so nothing behind it is listed.
        $this->assertStringNotContainsString(route('library.index'), $html);
        $this->assertStringNotContainsString(route('history.index'), $html);
        $this->assertStringNotContainsString('class="tabbar"', $html);
    }

    public function test_a_bcrypt_hashed_password_is_accepted(): void
    {
        // The recommended form: a hash at rest, never the plaintext secret.
        config(['nutrition.access_password' => password_hash('letmein', PASSWORD_BCRYPT)]);

        $this->get(route('diary.index'))->assertRedirect(route('unlock.show'));
        $this->post(route('unlock'), ['password' => 'nope'])->assertSessionHasErrors('password');

        $this->post(route('unlock'), ['password' => 'letmein']);
        $this->get(route('diary.index'))->assertOk();
    }
}
