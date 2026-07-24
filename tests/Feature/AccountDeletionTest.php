<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Controllers\PendingLogController;
use App\Models\FoodItem;
use App\Models\FoodItemAlias;
use App\Models\Goal;
use App\Models\Invite;
use App\Models\MealEntry;
use App\Models\RecipeIngredient;
use App\Models\Recognition;
use App\Models\User;
use App\Models\WeightEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Leaving takes everything with it.
 *
 * The fixture below is deliberately the awkward case: a recipe built on the
 * person's own items, with an entry pointing at one of them. Two of the keys in
 * this schema are not plain cascades — a recipe line holds its ingredient with
 * RESTRICT, and a logged entry's link takes no delete action at all — so a
 * deletion that gets the order wrong does not half-work, it fails outright.
 */
class AccountDeletionTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'a-long-enough-password';

    /** @var list<string> */
    private const EVERY_TABLE = [
        'meal_entries',
        'recipe_ingredients',
        'food_item_aliases',
        'food_items',
        'weight_entries',
        'goals',
        'recognitions',
    ];

    /**
     * Somebody with one of everything, including the things that block a
     * careless delete.
     */
    private function somebodyWithAFullDiary(): User
    {
        $user = User::factory()->create(['password' => self::PASSWORD]);
        $this->actingAs($user);

        $ingredient = FoodItem::factory()->create(['name' => "{$user->id}: ingredient"]);
        $recipe = FoodItem::factory()->recipe()->create(['name' => "{$user->id}: recipe"]);

        RecipeIngredient::query()->create([
            'recipe_id' => $recipe->id,
            'ingredient_id' => $ingredient->id,
            'grams' => 150,
        ]);
        FoodItemAlias::query()->create(['food_item_id' => $ingredient->id, 'name' => "{$user->id}: alias"]);

        MealEntry::factory()->create(['name' => "{$user->id}: entry", 'food_item_id' => $ingredient->id]);
        WeightEntry::query()->create(['recorded_on' => '2026-07-2'.($user->id % 10), 'weight_kg' => 70 + $user->id]);
        Goal::query()->create(['daily_kcal' => 2000 + $user->id]);
        Recognition::query()->create([]);

        return $user;
    }

    /**
     * @return array<string, list<mixed>>
     */
    private function rowsOf(User $user): array
    {
        $rows = [];

        foreach (self::EVERY_TABLE as $table) {
            $rows[$table] = DB::table($table)->where('user_id', $user->id)->orderBy('id')
                ->get()->map(fn (object $row): array => (array) $row)->all();
        }

        return $rows;
    }

    public function test_deleting_an_account_leaves_nothing_of_it_behind(): void
    {
        $user = $this->somebodyWithAFullDiary();

        // Every table has something in it, so the emptiness afterwards is the
        // deletion and not a fixture that never wrote anything.
        foreach ($this->rowsOf($user) as $table => $rows) {
            $this->assertNotEmpty($rows, "The fixture wrote nothing to {$table}.");
        }

        $this->from(route('goal.edit'))
            ->delete(route('account.destroy'), ['current_password' => self::PASSWORD])
            ->assertRedirect(route('login'));

        foreach ($this->rowsOf($user) as $table => $rows) {
            $this->assertSame([], $rows, "Rows are left in {$table}.");
        }

        $this->assertNull(DB::table('users')->where('id', $user->id)->first(), 'The account is still there.');
        $this->assertGuest();
    }

    #[DataProvider('everyTable')]
    public function test_no_table_is_forgotten(string $table): void
    {
        // The same claim, one table per case, so a failure names the table that
        // was missed instead of the first one in a list.
        $user = $this->somebodyWithAFullDiary();

        $this->assertGreaterThan(0, DB::table($table)->where('user_id', $user->id)->count());

        $this->delete(route('account.destroy'), ['current_password' => self::PASSWORD]);

        $this->assertSame(0, DB::table($table)->where('user_id', $user->id)->count(),
            "{$table} still holds rows belonging to a deleted account.");
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function everyTable(): array
    {
        $cases = [];

        foreach (self::EVERY_TABLE as $table) {
            $cases[$table] = [$table];
        }

        return $cases;
    }

    public function test_leaving_keeps_no_session_behind(): void
    {
        $user = $this->somebodyWithAFullDiary();

        // `sessions.user_id` has no foreign key, so nothing removes these on its
        // own. Signing out clears the session in hand; a row left in the table
        // still names an account that no longer exists.
        DB::table('sessions')->insert([
            'id' => 'another-device-of-theirs',
            'user_id' => $user->id,
            'ip_address' => null,
            'user_agent' => null,
            'payload' => '',
            'last_activity' => time(),
        ]);

        $this->delete(route('account.destroy'), ['current_password' => self::PASSWORD])
            ->assertRedirect(route('login'));

        $this->assertSame(0, DB::table('sessions')->where('user_id', $user->id)->count());
    }

    public function test_the_invitation_they_joined_with_stops_naming_them(): void
    {
        // The invitation itself stays: it is the owner's record that somebody
        // was invited, and it must not become spendable again.
        $owner = User::factory()->create();
        $owner->forceFill(['is_owner' => true])->save();
        $code = Invite::issue($owner);

        $this->post(route('register'), [
            'invite_code' => $code,
            'name' => 'An Invited Person',
            'email' => 'invited@example.test',
            'password' => self::PASSWORD,
            'password_confirmation' => self::PASSWORD,
        ])->assertRedirect();

        $person = User::query()->where('email', 'invited@example.test')->sole();
        $this->actingAs($person);

        $this->delete(route('account.destroy'), ['current_password' => self::PASSWORD]);

        $invite = Invite::query()->sole();
        $this->assertNull($invite->used_by, 'A deleted account is still named on an invitation.');
        $this->assertNotNull($invite->used_at, 'The invitation became spendable again.');
    }

    public function test_deleting_one_account_does_not_touch_another(): void
    {
        $theirs = $this->somebodyWithAFullDiary();
        $before = $this->rowsOf($theirs);

        $mine = $this->somebodyWithAFullDiary();
        $this->actingAs($mine);

        $this->delete(route('account.destroy'), ['current_password' => self::PASSWORD])
            ->assertRedirect(route('login'));

        $this->assertSame($before, $this->rowsOf($theirs), 'Deleting one account changed another one.');
        $this->assertNotNull(DB::table('users')->where('id', $theirs->id)->first());
    }

    public function test_the_wrong_password_deletes_nothing(): void
    {
        $user = $this->somebodyWithAFullDiary();
        $before = $this->rowsOf($user);

        $this->from(route('goal.edit'))
            ->delete(route('account.destroy'), ['current_password' => 'not-my-password'])
            ->assertSessionHasErrors('current_password');

        $this->assertSame($before, $this->rowsOf($user));
        $this->assertNotNull(DB::table('users')->where('id', $user->id)->first());
        $this->assertAuthenticatedAs($user);
    }

    public function test_the_owner_cannot_delete_the_account_that_administers_the_installation(): void
    {
        // There is no way to appoint another owner from inside the application,
        // so an owner who could leave would leave an instance nobody can invite
        // anybody to.
        $owner = $this->somebodyWithAFullDiary();
        $owner->forceFill(['is_owner' => true])->save();

        $this->from(route('goal.edit'))
            ->delete(route('account.destroy'), ['current_password' => self::PASSWORD])
            ->assertSessionHasErrors('delete_account');

        $this->assertNotNull(DB::table('users')->where('id', $owner->id)->first());
        $this->assertNotEmpty($this->rowsOf($owner)['meal_entries']);
    }

    public function test_a_photo_waiting_on_the_confirm_screen_goes_too(): void
    {
        $user = $this->somebodyWithAFullDiary();

        $photo = storage_path('app/private/photos').'/'.uniqid('pending-', true).'.jpg';
        @mkdir(dirname($photo), 0777, true);
        file_put_contents($photo, 'a prepared photo');

        $this->withSession([PendingLogController::SESSION_KEY => ['photo' => $photo, 'items' => []]])
            ->delete(route('account.destroy'), ['current_password' => self::PASSWORD])
            ->assertRedirect(route('login'));

        $this->assertFileDoesNotExist($photo, 'A photo was left on disk after the account went.');
    }
}
