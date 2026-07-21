<?php

declare(strict_types=1);

namespace Tests;

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
}
