<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Goal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GoalTest extends TestCase
{
    use RefreshDatabase;

    public function test_remaining_is_target_minus_logged(): void
    {
        $goal = Goal::factory()->create(['daily_kcal' => 2000]);

        $this->assertSame(500.0, $goal->remainingKcal(1500));
    }

    public function test_remaining_may_be_negative_and_carries_no_verdict(): void
    {
        // Over the target is just a number — no judgement attached to the sign.
        $goal = Goal::factory()->create(['daily_kcal' => 2000]);

        $this->assertSame(-300.0, $goal->remainingKcal(2300));
    }

    public function test_a_goal_without_a_kcal_target_shows_no_remaining(): void
    {
        $goal = Goal::factory()->create(['daily_kcal' => null]);

        $this->assertNull($goal->remainingKcal(1500));
    }

    public function test_with_no_goal_set_there_is_nothing_to_show(): void
    {
        // The diary works with no goal at all: no row, nothing to compute.
        $this->assertNull(Goal::query()->latest('id')->first());
    }
}
