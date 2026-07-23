<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Invite;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Invitations belong to the owner.
 *
 * Two claims live here. One is the boundary: everything under /invites is the
 * owner's, and an invited person who signs in normally cannot reach any of it —
 * the same kind of assertion as the cross-user isolation class, except that the
 * line runs between the owner and everybody else rather than between two people.
 *
 * The other is that the code is shown once and then genuinely gone. A screen
 * that could show it again would mean it was stored, and the whole design of the
 * table says it is not.
 */
class OwnerInvitesTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        $owner = User::factory()->create();
        $owner->forceFill(['is_owner' => true])->save();

        return $owner;
    }

    /**
     * Create an invitation through the screen and return the code as the owner
     * saw it.
     */
    private function issueThroughTheScreen(): string
    {
        $this->post(route('invites.store'))->assertRedirect(route('invites.index'));

        $code = session('issued_code');
        $this->assertIsString($code, 'The screen did not hand back a code.');

        return $code;
    }

    public function test_the_owner_creates_an_invitation_and_is_shown_the_code(): void
    {
        $this->actingAs($this->owner());

        $code = $this->issueThroughTheScreen();

        $this->get(route('invites.index'))->assertOk()->assertSee($code);
        $this->assertSame(1, Invite::query()->count());
    }

    public function test_the_code_cannot_be_read_again_afterwards(): void
    {
        $this->actingAs($this->owner());

        $code = $this->issueThroughTheScreen();
        $this->get(route('invites.index'));

        // The flash is spent by the visit above, so the next one has nothing to
        // show. Anything else would mean it had been kept somewhere.
        $this->get(route('invites.index'))->assertOk()->assertDontSee($code);

        foreach ((array) DB::table('invites')->sole() as $column => $value) {
            $this->assertNotSame($code, $value, "The code is sitting in `{$column}` in plain.");
        }
    }

    public function test_a_revoked_invitation_no_longer_registers_an_account(): void
    {
        $this->actingAs($this->owner());
        $code = $this->issueThroughTheScreen();

        $invite = Invite::query()->sole();
        $this->delete(route('invites.destroy', $invite))->assertRedirect(route('invites.index'));

        $this->assertNotNull($invite->fresh()?->revoked_at, 'The invitation was not revoked.');

        $this->post('/logout');
        $this->from(route('register'))
            ->post(route('register'), [
                'invite_code' => $code,
                'name' => 'Somebody With A Dead Code',
                'email' => 'late@example.test',
                'password' => 'a-long-enough-password',
                'password_confirmation' => 'a-long-enough-password',
            ])
            ->assertSessionHasErrors('invite_code');

        $this->assertNull(User::query()->where('email', 'late@example.test')->first(),
            'A revoked invitation still created an account.');
    }

    public function test_an_invitation_that_was_used_cannot_be_revoked(): void
    {
        // There is an account behind it now, and marking the paperwork withdrawn
        // would say something that is not true. Removing the account is the way.
        $this->actingAs($this->owner());
        $code = $this->issueThroughTheScreen();

        $this->post('/logout');
        $this->post(route('register'), [
            'invite_code' => $code,
            'name' => 'An Invited Person',
            'email' => 'invited@example.test',
            'password' => 'a-long-enough-password',
            'password_confirmation' => 'a-long-enough-password',
        ])->assertRedirect();

        $spent = Invite::query()->sole();
        $this->post('/logout');
        $this->actingAs($this->owner());

        $this->from(route('invites.index'))
            ->delete(route('invites.destroy', $spent))
            ->assertSessionHasErrors('revoke');

        $after = $spent->fresh();
        $this->assertNull($after?->revoked_at, 'A spent invitation was marked revoked.');
        $this->assertNotNull($after?->used_at, 'The record of it being used was lost.');
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function everyInviteRoute(): array
    {
        return [
            'the list' => ['get', 'invites.index'],
            'creating one' => ['post', 'invites.store'],
            'revoking one' => ['delete', 'invites.destroy'],
        ];
    }

    #[DataProvider('everyInviteRoute')]
    public function test_somebody_who_is_not_the_owner_is_refused(string $method, string $route): void
    {
        $existing = Invite::issue($this->owner());
        $invite = Invite::query()->sole();

        // An ordinary invited account: signed in, entirely legitimate, and with
        // no business here.
        $this->actingAs(User::factory()->create());

        $this->{$method}(route($route, $invite))->assertForbidden();

        // And the refusal came before anything happened.
        $this->assertSame(1, Invite::query()->count(), 'An invitation was created.');
        $this->assertNull($invite->fresh()?->revoked_at, 'An invitation was revoked.');

        // The code they could not reach still works, which is what makes the
        // refusal above a refusal rather than a broken screen.
        $this->post('/logout');
        $this->post(route('register'), [
            'invite_code' => $existing,
            'name' => 'Properly Invited',
            'email' => 'proper@example.test',
            'password' => 'a-long-enough-password',
            'password_confirmation' => 'a-long-enough-password',
        ])->assertRedirect();

        $this->assertNotNull(User::query()->where('email', 'proper@example.test')->first());
    }

    #[DataProvider('everyInviteRoute')]
    public function test_a_guest_is_sent_to_sign_in(string $method, string $route): void
    {
        Invite::issue();
        $invite = Invite::query()->sole();

        $this->{$method}(route($route, $invite))->assertRedirect(route('login'));
    }

    public function test_the_owner_flag_is_what_decides_it(): void
    {
        // Not the first id, not the lowest, not the only one with invitations to
        // their name. A database restored with different numbering must not
        // change who administers this installation.
        $first = User::factory()->create();
        $second = $this->owner();

        $this->assertTrue($second->id > $first->id);

        $this->actingAs($first)->get(route('invites.index'))->assertForbidden();
        $this->actingAs($second)->get(route('invites.index'))->assertOk();
    }

    public function test_registering_cannot_make_somebody_the_owner(): void
    {
        // Two things have to hold for this: registration names the three
        // attributes it writes rather than passing the request through, and
        // `is_owner` is not fillable if anybody ever changes that.
        $code = Invite::issue($this->owner());

        $this->post(route('register'), [
            'invite_code' => $code,
            'is_owner' => true,
            'name' => 'Would Be Owner',
            'email' => 'ambitious@example.test',
            'password' => 'a-long-enough-password',
            'password_confirmation' => 'a-long-enough-password',
        ])->assertRedirect();

        $person = User::query()->where('email', 'ambitious@example.test')->sole();

        $this->assertFalse($person->isOwner(), 'A form field made somebody the owner.');
        $this->actingAs($person)->get(route('invites.index'))->assertForbidden();
    }
}
