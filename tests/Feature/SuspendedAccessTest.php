<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Middleware\SetLocale;
use App\Models\MealEntry;
use App\Models\User;
use App\Models\WeightEntry;
use App\Nutrition\MealType;
use App\Nutrition\NutrientSource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\ViewErrorBag;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * What suspension does to a request.
 *
 * Two claims, and the second is the one that needs the care:
 *
 * 1. A suspended account is told why, rather than being turned away at the
 *    sign-in screen with a message that would either be untrue or would make the
 *    form an oracle.
 * 2. The wall is closed by default. It is asserted here over routes the
 *    middleware has never heard of, including a write, because a refusal that
 *    arrives after the write is not a refusal.
 */
class SuspendedAccessTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'a-long-enough-password';

    private function suspended(): User
    {
        $user = User::factory()->create(['password' => self::PASSWORD]);
        $user->forceFill(['suspended_at' => now()])->save();

        return $user;
    }

    /**
     * Routes a suspended account must not reach. Nothing here is named in the
     * middleware: these pass because the default is closed, not because each one
     * was thought of.
     *
     * @return array<string, array{0: string, 1: string}>
     */
    public static function walledRoutes(): array
    {
        return [
            'the diary' => ['get', '/'],
            'history' => ['get', '/history'],
            'the library' => ['get', '/library'],
            'weight' => ['get', '/weight'],
            'the goal screen' => ['get', '/goal'],
            'logging by photo' => ['get', '/log/photo'],
            'logging by hand' => ['get', '/log/manual'],
            // Fortify's own route, not this application's — walled because the
            // middleware is on the whole web group, not on our route file.
            'changing a password' => ['put', '/user/password'],
        ];
    }

    #[DataProvider('walledRoutes')]
    public function test_a_suspended_account_is_told_instead_of_being_served(string $method, string $path): void
    {
        $this->actingAs($this->suspended());

        $response = $this->{$method}($path)->assertForbidden();

        // The notice, and no rail of links that all lead back to it.
        $response->assertSee(__('account.suspended_title'))
            ->assertDontSee(__('nav.history'));
    }

    public function test_the_wall_stops_a_write_before_it_happens(): void
    {
        $user = $this->suspended();
        $this->actingAs($user);

        $this->post('/weight', ['weight_kg' => '70.5', 'recorded_on' => '2026-07-01'])
            ->assertForbidden();

        // The assertion that matters: not the status, but that nothing was
        // written. A wall that answers after the insert has not walled anything.
        $this->assertSame(0, WeightEntry::ownedBy($user)->count());
    }

    public function test_signing_out_stays_reachable_so_somebody_can_leave(): void
    {
        $this->actingAs($this->suspended());

        $this->post('/logout')->assertRedirect();
        $this->assertGuest();
    }

    public function test_the_language_of_the_notice_can_be_changed(): void
    {
        $this->actingAs($this->suspended());

        // Reading the refusal in a language you do not have is its own kind of
        // refusal, so the switch is one of the two things left reachable.
        $this->post(route('locale.update'), ['locale' => 'ru'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->withCookie(SetLocale::COOKIE, 'ru')->get('/')
            ->assertForbidden()
            ->assertSee(__('account.suspended_title', [], 'ru'));
    }

    public function test_the_sign_in_screen_says_nothing_about_suspension(): void
    {
        $suspended = $this->suspended();
        $active = User::factory()->create(['password' => self::PASSWORD]);

        // A wrong password for a suspended account and a wrong password for an
        // active one must read identically, or the form tells a stranger which
        // addresses are real.
        // Captured straight after each request: read at the end, both would be
        // the second request's session and the comparison would prove nothing.
        $this->post('/login', ['email' => $suspended->email, 'password' => 'wrong-password'])
            ->assertRedirect();
        $forSuspended = $this->refusal();

        $this->flushSession();

        $this->post('/login', ['email' => $active->email, 'password' => 'wrong-password'])
            ->assertRedirect();
        $forActive = $this->refusal();

        $this->assertSame($forActive, $forSuspended);
        $this->assertNotSame([], $forActive, 'The refusal was empty, so this compared nothing.');
    }

    /**
     * The flashed messages, whichever shape the session holds them in.
     *
     * @return array<string, list<string>>
     */
    private function refusal(): array
    {
        $errors = session('errors');

        if ($errors instanceof ViewErrorBag) {
            /** @var array<string, list<string>> */
            return $errors->getBag('default')->messages();
        }

        /** @var array<string, list<string>> */
        return is_array($errors) && is_array($errors['default']['messages'] ?? null)
            ? $errors['default']['messages']
            : [];
    }

    public function test_correct_credentials_are_accepted_and_the_notice_comes_after(): void
    {
        $suspended = $this->suspended();

        // Authentication succeeds — the password was right, and saying otherwise
        // would be a lie. The explanation is on the other side of it.
        $this->post('/login', ['email' => $suspended->email, 'password' => self::PASSWORD])
            ->assertRedirect();
        $this->assertAuthenticatedAs($suspended);

        $this->get('/')->assertForbidden()->assertSee(__('account.suspended_title'));
    }

    public function test_lifting_the_suspension_restores_access_without_signing_in_again(): void
    {
        $user = $this->suspended();
        $this->actingAs($user);

        $this->get('/')->assertForbidden();

        $user->forceFill(['suspended_at' => null])->save();

        // The column is read on every request, so there is no stored copy to go
        // stale and nothing for the person to do.
        $this->get('/')->assertOk()->assertDontSee(__('account.suspended_title'));
    }

    public function test_an_established_session_is_walled_without_being_destroyed(): void
    {
        // The claim the whole design rests on, asserted through a real sign-in
        // and a real session rather than through `actingAs`, which would inject
        // whichever copy of the account this test happens to be holding.
        $user = User::factory()->create(['password' => self::PASSWORD]);

        $this->post('/login', ['email' => $user->email, 'password' => self::PASSWORD]);
        $this->get('/')->assertOk();

        // Suspended through a different instance entirely, the way another
        // person's request would do it.
        User::query()->whereKey($user->id)->update(['suspended_at' => now()]);

        // Same session, same cookie, nothing signed out or invalidated.
        $this->get('/')->assertForbidden()->assertSee(__('account.suspended_title'));
        $this->assertAuthenticated();

        User::query()->whereKey($user->id)->update(['suspended_at' => null]);

        $this->get('/')->assertOk();
    }

    public function test_suspension_takes_nothing_away(): void
    {
        $user = User::factory()->create();

        MealEntry::factory()->for($user)->create([
            'name' => 'a supper that stays',
            'meal' => MealType::Dinner->value,
            'source' => NutrientSource::PersonalLibrary->value,
            // Today's, because the diary opens on today.
            'logged_at' => now(),
        ]);

        $user->forceFill(['suspended_at' => now()])->save();

        $this->assertSame(1, MealEntry::ownedBy($user)->count());

        $user->forceFill(['suspended_at' => null])->save();
        $this->actingAs($user);

        $this->get('/')->assertOk()->assertSee('a supper that stays');
    }

    public function test_an_active_account_is_untouched_by_all_of_this(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/')->assertOk()->assertDontSee(__('account.suspended_title'));
    }
}
