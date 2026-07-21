<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccessGateTest extends TestCase
{
    use RefreshDatabase;

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
