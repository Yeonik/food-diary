<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Goal;
use App\Models\MealEntry;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The demo seeder's guard. The suite runs under the "testing" environment, which
 * is not "local", so calling it here exercises the refusal directly — a manual
 * check is easy to get wrong, because a cached config pins the environment and
 * an APP_ENV set on the command line then changes nothing.
 */
class DemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_refuses_to_run_outside_a_local_environment(): void
    {
        $this->assertNotSame('local', app()->environment(), 'The suite must not run as local for this to mean anything.');

        $this->seed(DemoSeeder::class);

        $this->assertSame(0, Goal::query()->count(), 'The seeder wrote a goal outside local.');
        $this->assertSame(0, MealEntry::query()->count(), 'The seeder wrote entries outside local.');
    }

    public function test_it_leaves_existing_data_untouched_outside_a_local_environment(): void
    {
        $existing = MealEntry::factory()->create(['name' => 'A real entry']);

        $this->seed(DemoSeeder::class);

        $this->assertDatabaseHas('meal_entries', ['id' => $existing->id, 'name' => 'A real entry']);
    }
}
