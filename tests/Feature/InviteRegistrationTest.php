<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Invite;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * An account exists because somebody was invited to make one.
 *
 * There is no open registration: the form takes a code, the code is spent by
 * creating the account, and a code is worth exactly one account. Every test here
 * goes through the real registration route rather than the model, because the
 * claim is about what a stranger with a browser can do.
 */
class InviteRegistrationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, string>
     */
    private function details(string $code): array
    {
        return [
            'invite_code' => $code,
            'name' => 'A New Person',
            'email' => 'new@example.test',
            'password' => 'a-long-enough-password',
            'password_confirmation' => 'a-long-enough-password',
        ];
    }

    public function test_a_valid_code_creates_the_account_and_is_spent(): void
    {
        $owner = User::factory()->create();
        $code = Invite::issue($owner);

        $this->post(route('register'), $this->details($code))
            ->assertRedirect();

        $person = User::query()->where('email', 'new@example.test')->sole();
        $invite = Invite::query()->sole();

        $this->assertNotNull($invite->used_at, 'The code is still unspent.');
        $this->assertSame($person->id, $invite->used_by);
        $this->assertSame($owner->id, $invite->created_by);
        $this->assertAuthenticatedAs($person);
    }

    public function test_registration_without_a_code_is_refused(): void
    {
        $details = $this->details('');
        unset($details['invite_code']);

        $this->from(route('register'))
            ->post(route('register'), $details)
            ->assertSessionHasErrors('invite_code');

        $this->assertNobodyWasCreated();
    }

    public function test_an_unknown_code_is_refused(): void
    {
        // A real invitation exists, so what is refused below is the code and
        // not the absence of any invitation at all.
        Invite::issue();

        $this->from(route('register'))
            ->post(route('register'), $this->details('not-a-code-anybody-issued'))
            ->assertSessionHasErrors('invite_code');

        $this->assertNobodyWasCreated();
        $this->assertNull(Invite::query()->sole()->used_at, 'An unrelated invitation was spent.');
    }

    public function test_an_expired_code_is_refused(): void
    {
        $code = Invite::issue(expiresAt: CarbonImmutable::now()->subMinute());

        $this->from(route('register'))
            ->post(route('register'), $this->details($code))
            ->assertSessionHasErrors('invite_code');

        $this->assertNobodyWasCreated();
        $this->assertNull(Invite::query()->sole()->used_at, 'An expired code was marked as spent.');
    }

    public function test_a_code_expiring_later_still_works(): void
    {
        // Without this the test above would pass just as well against a rule
        // that refused every invitation carrying a date at all.
        $code = Invite::issue(expiresAt: CarbonImmutable::now()->addDay());

        $this->post(route('register'), $this->details($code))->assertRedirect();

        $this->assertNotNull(Invite::query()->sole()->used_at);
    }

    public function test_a_spent_code_cannot_be_spent_again(): void
    {
        $code = Invite::issue();

        $this->post(route('register'), $this->details($code))->assertRedirect();
        $this->post('/logout');

        $again = $this->details($code);
        $again['email'] = 'second@example.test';

        $this->from(route('register'))
            ->post(route('register'), $again)
            ->assertSessionHasErrors('invite_code');

        $this->assertSame(1, User::query()->where('email', 'like', '%@example.test')->count(),
            'One code admitted two accounts.');
        $this->assertNull(User::query()->where('email', 'second@example.test')->first());
    }

    public function test_a_refused_registration_leaves_no_account_behind(): void
    {
        // The account row is written before the code is spent, so a refusal has
        // to take it back with it. Otherwise a stranger with no code could still
        // claim an address, and the person it belongs to could never register.
        $this->from(route('register'))
            ->post(route('register'), $this->details('not-a-code-anybody-issued'))
            ->assertSessionHasErrors('invite_code');

        $this->assertNobodyWasCreated();

        // And the address is free afterwards, which is the part that would hurt.
        $code = Invite::issue();
        $this->post(route('register'), $this->details($code))->assertRedirect();

        $this->assertNotNull(User::query()->where('email', 'new@example.test')->first());
    }

    public function test_spending_a_code_is_one_conditional_write_and_not_a_read_then_a_write(): void
    {
        // Two registrations arriving together both read an unspent invitation
        // before either writes to it: read, read, write, write — and one code
        // makes two accounts. Nothing in a single-process test suite can
        // interleave two requests to show that happening, so what is asserted
        // here is the property that makes it impossible — that the check and
        // the write are the same statement, and the row is claimed by its
        // condition rather than by what a previous query said about it.
        $code = Invite::issue();
        $user = User::factory()->create();

        $statements = [];
        DB::listen(function (QueryExecuted $query) use (&$statements): void {
            if (str_contains($query->sql, 'invites')) {
                $statements[] = $query->sql;
            }
        });

        $this->assertTrue(Invite::spend($code, $user));

        $this->assertCount(1, $statements,
            "Spending a code touched `invites` more than once:\n".implode("\n", $statements));
        $this->assertStringStartsWith('update', $statements[0]);
        $this->assertStringContainsString('"used_at" is null', $statements[0],
            'The update does not require the invitation to be unspent, so it would overwrite a spent one.');
    }

    public function test_the_code_itself_is_never_stored(): void
    {
        $code = Invite::issue();

        $stored = (array) DB::table('invites')->sole();

        foreach ($stored as $column => $value) {
            $this->assertNotSame($code, $value, "The code is sitting in `{$column}` in plain.");
        }

        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', (string) $stored['token_hash']);
    }

    private function assertNobodyWasCreated(): void
    {
        $this->assertNull(User::query()->where('email', 'new@example.test')->first(),
            'An account was created without a usable code.');
        $this->assertGuest();
    }
}
