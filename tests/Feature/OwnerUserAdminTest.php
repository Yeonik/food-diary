<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\MealEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The accounts screen belongs to the owner.
 *
 * The same boundary as {@see OwnerInvitesTest}, drawn between the owner and
 * everybody else rather than between two people — and asserted the same way: a
 * refusal is only a refusal if nothing happened behind it.
 *
 * There is a second claim here that the invites screen does not have to make.
 * This is the one screen that reads across every account on purpose, so it is
 * worth pinning down what it does *not* read: administering who may use the
 * instance never turns into reading the diaries on it.
 */
class OwnerUserAdminTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        $owner = User::factory()->create();
        $owner->forceFill(['is_owner' => true])->save();

        return $owner;
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function everyAccountRoute(): array
    {
        return [
            'the list' => ['get', 'users.index'],
        ];
    }

    #[DataProvider('everyAccountRoute')]
    public function test_somebody_who_is_not_the_owner_is_refused(string $method, string $route): void
    {
        $this->owner();

        // An ordinary invited account: signed in, entirely legitimate, and with
        // no business here.
        $this->actingAs(User::factory()->create());

        $this->{$method}(route($route))->assertForbidden();
    }

    public function test_a_guest_is_sent_to_sign_in_rather_than_refused(): void
    {
        // Not 403: with nobody signed in the answer is the same one every other
        // screen gives, so the gate does not announce that this screen exists.
        $this->get(route('users.index'))->assertRedirect(route('login'));
    }

    public function test_the_owner_sees_everybody_with_an_account(): void
    {
        $owner = $this->owner();
        $first = User::factory()->create(['name' => 'First Person', 'email' => 'first@example.test']);
        $second = User::factory()->create(['name' => 'Second Person', 'email' => 'second@example.test']);
        $second->forceFill(['suspended_at' => now()])->save();

        $this->actingAs($owner)->get(route('users.index'))
            ->assertOk()
            ->assertSee('First Person')
            ->assertSee('first@example.test')
            ->assertSee('Second Person')
            // The state is on the screen, not inferred from a missing button.
            ->assertSee(__('users.state.active'))
            ->assertSee(__('users.state.suspended'));

        $this->assertNotNull($first->fresh());
    }

    public function test_the_owner_is_in_the_list_and_marked(): void
    {
        $owner = $this->owner();

        // Listed like everybody else, so the roster is the whole roster — and
        // said to be the owner, so the absence of anything to press beside that
        // row reads as deliberate rather than as a bug.
        $this->actingAs($owner)->get(route('users.index'))
            ->assertOk()
            ->assertSee($owner->email)
            ->assertSee(__('users.owner'));
    }

    public function test_the_screen_shows_no_diary_contents(): void
    {
        $owner = $this->owner();
        $somebody = User::factory()->create();

        MealEntry::factory()->for($somebody)->create(['name' => 'a private supper']);

        // Administering who may use the instance is not the same power as
        // reading what they eat, and this screen does not quietly become the
        // second one.
        $this->actingAs($owner)->get(route('users.index'))
            ->assertOk()
            ->assertDontSee('a private supper');
    }

    public function test_the_owner_reaches_the_screen_from_the_settings_link(): void
    {
        $this->actingAs($this->owner())->get(route('goal.edit'))
            ->assertOk()
            ->assertSee(route('users.index'));
    }

    public function test_nobody_else_is_offered_the_link(): void
    {
        $this->actingAs(User::factory()->create())->get(route('goal.edit'))
            ->assertOk()
            ->assertDontSee(route('users.index'));
    }
}
