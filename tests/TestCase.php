<?php

declare(strict_types=1);

namespace Tests;

use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // The whole point of the project's CI: no real network call, ever. Any
        // outbound HTTP that a test did not explicitly fake throws loudly here,
        // so a missing fake can never turn into a silent live request.
        Http::preventStrayRequests();
    }

    /**
     * Sign in, creating an account when the test does not care whose it is.
     *
     * Signing in is never automatic: every application route needs an account
     * now, and a test that reaches one has to say so. Once records belong to
     * users, which user is signed in stops being a detail — a suite that
     * arranged it silently could not express "someone else's entry" at all.
     */
    protected function signIn(?User $user = null): User
    {
        $user ??= User::factory()->create();

        $this->actingAs($user);

        return $user;
    }
}
