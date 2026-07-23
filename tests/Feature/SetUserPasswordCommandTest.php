<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * The stand-in for password reset by email. The README tells a locked-out person
 * that the owner does this from a shell, so the command has to exist and work.
 */
class SetUserPasswordCommandTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        return User::factory()->create([
            'email' => 'someone@example.test',
            'password' => 'the-old-password',
        ]);
    }

    public function test_it_sets_a_password_the_person_can_then_sign_in_with(): void
    {
        $user = $this->user();

        $this->artisan('diary:set-password', ['email' => 'someone@example.test'])
            ->expectsQuestion('New password', 'a-brand-new-password')
            ->expectsQuestion('Repeat it', 'a-brand-new-password')
            ->assertSuccessful();

        $this->post(route('login'), [
            'email' => 'someone@example.test',
            'password' => 'a-brand-new-password',
        ]);

        $this->assertAuthenticatedAs($user->fresh());
    }

    public function test_the_new_password_is_hashed(): void
    {
        $this->user();

        $this->artisan('diary:set-password', ['email' => 'someone@example.test'])
            ->expectsQuestion('New password', 'a-brand-new-password')
            ->expectsQuestion('Repeat it', 'a-brand-new-password')
            ->assertSuccessful();

        $stored = (string) User::query()->sole()->getAttributes()['password'];

        $this->assertNotSame('a-brand-new-password', $stored);
        $this->assertTrue(Hash::check('a-brand-new-password', $stored));
    }

    public function test_a_mistyped_repeat_changes_nothing(): void
    {
        $this->user();

        $this->artisan('diary:set-password', ['email' => 'someone@example.test'])
            ->expectsQuestion('New password', 'a-brand-new-password')
            ->expectsQuestion('Repeat it', 'a-brand-new-passwrod')
            ->assertFailed();

        $this->assertTrue(
            Hash::check('the-old-password', (string) User::query()->sole()->getAttributes()['password']),
            'A mistyped confirmation still changed the password.',
        );
    }

    public function test_a_password_under_the_floor_is_refused(): void
    {
        $this->user();

        $this->artisan('diary:set-password', ['email' => 'someone@example.test'])
            ->expectsQuestion('New password', 'short')
            ->expectsQuestion('Repeat it', 'short')
            ->assertFailed();

        $this->assertTrue(
            Hash::check('the-old-password', (string) User::query()->sole()->getAttributes()['password']),
        );
    }

    public function test_an_unknown_address_fails_without_creating_anything(): void
    {
        $this->artisan('diary:set-password', ['email' => 'nobody@example.test'])
            ->assertFailed();

        $this->assertSame(0, User::query()->count());
    }
}
