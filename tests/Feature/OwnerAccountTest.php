<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Support\OwnerAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Tests\TestCase;

/**
 * The owner's account, and the two properties the deploy depends on: that a
 * missing address stops everything before a single row is written, and that a
 * second run never rewrites an account that already exists.
 */
class OwnerAccountTest extends TestCase
{
    use RefreshDatabase;

    private function configure(?string $email, ?string $password, ?string $name = null): void
    {
        config([
            'nutrition.owner.email' => $email,
            'nutrition.owner.password' => $password,
            'nutrition.owner.name' => $name,
        ]);
    }

    public function test_the_owner_is_created_from_configuration(): void
    {
        $this->configure('owner@example.test', 'a-long-enough-password', 'Yeonik');

        $owner = OwnerAccount::ensure();

        $this->assertSame('owner@example.test', $owner->email);
        $this->assertSame('Yeonik', $owner->name);
        $this->assertSame(1, User::query()->count());
    }

    public function test_the_password_is_stored_hashed_and_never_in_the_clear(): void
    {
        $this->configure('owner@example.test', 'a-long-enough-password');

        $owner = OwnerAccount::ensure();
        $stored = (string) $owner->getAttributes()['password'];

        $this->assertNotSame('a-long-enough-password', $stored);
        $this->assertTrue(Hash::check('a-long-enough-password', $stored));
    }

    public function test_an_address_with_capitals_is_stored_as_it_will_be_typed(): void
    {
        // Fortify lowercases what is typed at sign-in, so an address stored with
        // a capital would be an account nobody could ever reach.
        $this->configure('Owner@Example.Test', 'a-long-enough-password');

        $this->assertSame('owner@example.test', OwnerAccount::ensure()->email);
    }

    public function test_without_an_address_it_refuses_before_writing_anything(): void
    {
        $this->configure(null, 'a-long-enough-password');

        try {
            OwnerAccount::ensure();
            $this->fail('A missing address should have stopped this.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('OWNER_EMAIL', $e->getMessage());
        }

        // The point of the whole arrangement: the deploy fails having changed
        // nothing, so the previous release keeps serving an untouched database.
        $this->assertSame(0, User::query()->count());
    }

    public function test_without_a_password_it_also_refuses_before_writing_anything(): void
    {
        $this->configure('owner@example.test', null);

        try {
            OwnerAccount::ensure();
            $this->fail('A missing password should have stopped this.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('OWNER_PASSWORD', $e->getMessage());
        }

        $this->assertSame(0, User::query()->count());
    }

    public function test_the_refusal_never_repeats_the_password_back(): void
    {
        $this->configure(null, 'sec'.'ret-owner-password');

        try {
            OwnerAccount::ensure();
            $this->fail('This should have thrown.');
        } catch (RuntimeException $e) {
            // A message that quotes the configured secret would put it in the
            // deploy log, which is the one place it must never appear.
            $this->assertStringNotContainsString('secret-owner-password', $e->getMessage());
            $this->assertStringNotContainsString('secret-owner-password', (string) $e);
        }
    }

    public function test_a_second_run_leaves_a_changed_password_alone(): void
    {
        $this->configure('owner@example.test', 'the-original-password');
        $owner = OwnerAccount::ensure();

        // The owner signs in and changes their password, as they are told to.
        $owner->forceFill(['password' => 'what-they-chose-instead'])->save();

        // The platform still holds the old variable, and the migration runs again.
        $again = OwnerAccount::ensure();

        $this->assertTrue($again->is($owner));
        $this->assertSame(1, User::query()->count());
        $this->assertTrue(
            Hash::check('what-they-chose-instead', (string) $again->getAttributes()['password']),
            'A re-run reset the password back to the stale configured one.',
        );
    }
}
