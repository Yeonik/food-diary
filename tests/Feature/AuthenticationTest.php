<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Accounts replace the shared-secret gate.
 *
 * Fortify owns the credential check, so what is worth asserting here is not that
 * Laravel can compare a hash — it is the wiring around it: that the application
 * has no page you can reach without an account, that the features we turned off
 * really are off, and that the throttle is on.
 */
class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // The throttle is keyed per email and IP and outlives a test's database.
        RateLimiter::clear('');
    }

    private function owner(string $password = 'correct-horse-battery'): User
    {
        return User::factory()->create([
            'email' => 'owner@example.test',
            'password' => $password,
        ]);
    }

    public function test_every_application_route_needs_an_account(): void
    {
        // Not a sample: the whole routing table. A page added later that forgets
        // to sit behind the guard fails here rather than in production.
        $routes = collect(app('router')->getRoutes())
            ->filter(fn ($route) => in_array('GET', $route->methods(), true))
            ->filter(fn ($route) => is_string($route->getName()) && $route->getName() !== '')
            ->reject(fn ($route) => in_array($route->getName(), [
                'login',        // the sign-in screen itself
                'register',     // reachable while signed out, by design
                'storage.local',
            ], true))
            ->reject(fn ($route) => str_contains($route->uri(), '{'))
            ->reject(fn ($route) => $route->uri() === 'up');   // the health check

        $this->assertGreaterThan(8, $routes->count(), 'Too few routes checked to mean anything.');

        foreach ($routes as $route) {
            $this->get('/'.ltrim($route->uri(), '/'))
                ->assertRedirect(route('login'));
        }
    }

    public function test_signing_in_and_out(): void
    {
        $user = $this->owner();

        $this->post(route('login'), [
            'email' => 'owner@example.test',
            'password' => 'correct-horse-battery',
        ])->assertRedirect('/');

        $this->assertAuthenticatedAs($user);
        $this->get(route('diary.index'))->assertOk();

        $this->post(route('logout'));

        $this->assertGuest();
        $this->get(route('diary.index'))->assertRedirect(route('login'));
    }

    public function test_a_wrong_password_does_not_sign_anyone_in(): void
    {
        $this->owner();

        $this->post(route('login'), [
            'email' => 'owner@example.test',
            'password' => 'not-it',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    /**
     * What a refused attempt actually shows: the status, and the words on the
     * page. Read from the rendered screen rather than the session, because what
     * leaks existence is what the person reads, not how it was stored.
     *
     * @return array{0: int, 1: string}
     */
    private function refusal(string $email, string $password): array
    {
        $this->flushSession();

        // `from` is not decoration: a rejected sign-in redirects back, and with
        // no referer "back" is `/`, which bounces to the sign-in screen — a
        // second hop, by which time the flashed errors have aged out. A browser
        // always sends the referer; the test has to say so.
        $response = $this->from(route('login'))->followingRedirects()
            ->post(route('login'), ['email' => $email, 'password' => $password]);

        preg_match('/<div class="errors">(.*?)<\/div>/s', (string) $response->getContent(), $shown);

        return [$response->status(), trim($shown[1] ?? '')];
    }

    public function test_an_unknown_address_answers_exactly_as_a_wrong_password_does(): void
    {
        $this->owner();

        $wrongPassword = $this->refusal('owner@example.test', 'not-it');
        $noSuchAccount = $this->refusal('nobody@example.test', 'not-it');

        $this->assertNotEmpty($wrongPassword[1], 'Neither attempt was refused, so nothing was compared.');

        // Whether an address has an account here is not something the sign-in
        // screen tells a stranger: same status, same words, same field. It is the
        // same reasoning as answering 404 for another user's record — an error
        // that distinguishes the two cases is an existence oracle.
        $this->assertSame($wrongPassword, $noSuchAccount);
    }

    public function test_guessing_is_throttled(): void
    {
        $this->owner();

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->post(route('login'), [
                'email' => 'owner@example.test',
                'password' => 'guess-'.$attempt,
            ])->assertStatus(302);
        }

        // The sixth is refused before the credentials are looked at — and so is
        // the correct password, because the throttle does not care which it was.
        $this->post(route('login'), [
            'email' => 'owner@example.test',
            'password' => 'correct-horse-battery',
        ])->assertStatus(429);

        $this->assertGuest();
    }

    public function test_a_signed_in_person_can_change_their_own_password(): void
    {
        $user = $this->signIn($this->owner());

        $this->put(route('user-password.update'), [
            'current_password' => 'correct-horse-battery',
            'password' => 'a-new-long-password',
            'password_confirmation' => 'a-new-long-password',
        ])->assertSessionHasNoErrors();

        $this->post(route('logout'));
        $this->post(route('login'), [
            'email' => 'owner@example.test',
            'password' => 'a-new-long-password',
        ]);

        $this->assertAuthenticatedAs($user->fresh());
    }

    public function test_changing_a_password_requires_the_current_one(): void
    {
        $this->signIn($this->owner());

        $this->put(route('user-password.update'), [
            'current_password' => 'not-it',
            'password' => 'a-new-long-password',
            'password_confirmation' => 'a-new-long-password',
        ])->assertSessionHasErrors('current_password', null, 'updatePassword');

        // A session left open on a borrowed phone must not be enough to take the
        // account, so the old password still works and the new one does not.
        $this->post(route('logout'));
        $this->post(route('login'), [
            'email' => 'owner@example.test',
            'password' => 'a-new-long-password',
        ]);
        $this->assertGuest();
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function absentRoutes(): array
    {
        return [
            'password reset request' => ['/forgot-password'],
            'password reset form' => ['/reset-password/token'],
            'two-factor challenge' => ['/two-factor-challenge'],
            'passkey login options' => ['/passkeys/login/options'],
        ];
    }

    /**
     * The features that are off are off at the routing table, not merely hidden
     * from the screens. laravel/passkeys ships with Fortify whether we want it
     * or not; this is the assertion that it stays dormant.
     */
    #[DataProvider('absentRoutes')]
    public function test_a_feature_we_did_not_enable_has_no_route(string $uri): void
    {
        $this->get($uri)->assertNotFound();
    }

    public function test_the_sign_in_screen_offers_no_way_out_of_itself(): void
    {
        $html = (string) $this->get(route('login'))->assertOk()->getContent();

        // Nothing behind the sign-in is reachable, so nothing behind it is listed.
        $this->assertStringNotContainsString(route('library.index'), $html);
        $this->assertStringNotContainsString('class="tabbar"', $html);

        // And no link to a reset that does not exist.
        $this->assertStringNotContainsString('forgot-password', $html);
    }

    public function test_the_password_columns_two_factor_would_need_were_never_added(): void
    {
        // Fortify's migrations are deliberately not published: publishing them
        // would put columns for a feature we do not offer into the schema.
        $this->assertFalse(
            Schema::hasColumn('users', 'two_factor_secret'),
            'Two-factor columns are in the schema for a feature that is off.',
        );
        $this->assertFalse(
            Schema::hasTable('passkeys'),
            'A passkeys table exists for a feature that is off.',
        );
    }
}
