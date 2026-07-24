<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Invite;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Suspension: an account kept, with its way in closed.
 *
 * This class holds what suspension *is*. What it does to a request is in
 * {@see SuspendedAccessTest}, and who is allowed to apply it is in
 * {@see OwnerUserAdminTest}.
 */
class SuspensionTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_account_is_active_until_it_is_suspended(): void
    {
        $user = User::factory()->create();

        $this->assertFalse($user->isSuspended());
        $this->assertNull($user->suspended_at);
    }

    public function test_a_suspended_account_knows_when_it_began(): void
    {
        $user = User::factory()->create();

        $user->forceFill(['suspended_at' => now()])->save();

        // The state and the moment are the same column, so they cannot disagree.
        $this->assertTrue($user->fresh()?->isSuspended());
        $this->assertNotNull($user->fresh()?->suspended_at);
    }

    public function test_lifting_a_suspension_leaves_no_trace_on_the_account(): void
    {
        $user = User::factory()->create(['name' => 'Unchanged']);
        $user->forceFill(['suspended_at' => now()])->save();

        $user->forceFill(['suspended_at' => null])->save();

        $restored = $user->fresh();
        $this->assertFalse($restored?->isSuspended());
        // Reversible means reversible: nothing else moved on the way through.
        $this->assertSame('Unchanged', $restored?->name);
    }

    public function test_registering_cannot_suspend_or_unsuspend_anybody(): void
    {
        // The same guarantee `is_owner` has, for the same reason. Registration
        // names the attributes it writes, and `suspended_at` is not fillable if
        // anybody ever changes that.
        $code = Invite::issue(User::factory()->create());

        $this->post(route('register'), [
            'invite_code' => $code,
            'name' => 'New Person',
            'email' => 'new@example.test',
            'password' => 'a-long-enough-password',
            'password_confirmation' => 'a-long-enough-password',
            'suspended_at' => null,
            'is_owner' => true,
        ])->assertRedirect();

        $created = User::query()->where('email', 'new@example.test')->sole();

        $this->assertFalse($created->isSuspended());
        $this->assertFalse($created->isOwner());
        $this->assertNotContains('suspended_at', $created->getFillable());
    }

    public function test_the_column_is_nullable_so_existing_accounts_stay_active(): void
    {
        // The migration is additive: every account that existed before it ran
        // has null here, which is the active state. Nobody is suspended by being
        // upgraded.
        $this->assertTrue(Schema::hasColumn('users', 'suspended_at'));

        $column = collect(Schema::getColumns('users'))->firstWhere('name', 'suspended_at');

        $this->assertIsArray($column);
        $this->assertTrue($column['nullable'], 'suspended_at must be nullable.');
        $this->assertNull($column['default'], 'suspended_at must default to active.');
    }
}
