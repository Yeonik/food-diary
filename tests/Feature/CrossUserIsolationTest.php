<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\FoodItem;
use App\Models\MealEntry;
use App\Models\RecipeIngredient;
use App\Models\User;
use App\Models\WeightEntry;
use App\Nutrition\MealLogService;
use App\Nutrition\MealType;
use App\Nutrition\NutrientSource;
use App\Nutrition\SearchTerms;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ViewErrorBag;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The most important test class in the project.
 *
 * Every route that takes an id is walked while signed in as somebody else, and
 * three things are asserted each time. The third matters most: a test that only
 * checks the status code will happily pass while the write it was supposed to
 * prevent has already happened, because the refusal came afterwards. So the
 * whole of every domain table is read back past the scope and compared.
 *
 * The second matters second. Answering 403 for another person's record would be
 * a refusal that confirms the record exists — an existence oracle, walkable by
 * id. Here the two cases are not merely both refusals: they are the same bytes,
 * because the scope means the router genuinely cannot tell them apart.
 *
 * Debug output is turned off for these, since that is the condition under which
 * the claim matters and the only one in which the two bodies are comparable.
 */
class CrossUserIsolationTest extends TestCase
{
    use RefreshDatabase;

    /** An id that has never existed, to answer exactly as another person's does. */
    private const NEVER_EXISTED = 999999;

    /** @var list<string> */
    private const DOMAIN_TABLES = [
        'food_items',
        'food_item_aliases',
        'recipe_ingredients',
        'meal_entries',
        'weight_entries',
        'goals',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        // A production instance renders the plain error page. With debug on, the
        // page carries the model and the id, so the two refusals would differ in
        // ways that say nothing about existence and would only make the
        // comparison useless.
        config(['app.debug' => false]);
    }

    /**
     * One of everything, belonging to one person.
     *
     * @return array<string, FoodItem|MealEntry|WeightEntry>
     */
    private function belongingsOf(User $user): array
    {
        $this->signIn($user);

        // `spare` is deliberately not used by any recipe: the library's delete
        // route refuses an item a recipe depends on, and that refusal would mask
        // whether the row was reachable in the first place.
        $spare = FoodItem::factory()->create(['name' => "{$user->id}: spare item"]);
        $ingredient = FoodItem::factory()->create(['name' => "{$user->id}: ingredient"]);
        $recipe = FoodItem::factory()->recipe()->create(['name' => "{$user->id}: recipe"]);

        RecipeIngredient::query()->create([
            'recipe_id' => $recipe->id,
            'ingredient_id' => $ingredient->id,
            'grams' => 150,
        ]);

        return [
            'spare' => $spare,
            'ingredient' => $ingredient,
            'recipe' => $recipe,
            'entry' => MealEntry::factory()->create(['name' => "{$user->id}: entry"]),
            'weight' => WeightEntry::query()->create([
                'recorded_on' => '2026-07-2'.$user->id,
                'weight_kg' => 70 + $user->id,
            ]),
        ];
    }

    /**
     * Every route in the application that resolves a record by id from the path,
     * where route model binding runs it through the scope. The ones that take an
     * id from the body are asserted separately, below.
     *
     * @return array<string, array{0: string, 1: string, 2: string, 3: Closure}>
     */
    public static function routesThatTakeAnId(): array
    {
        $nothing = fn (array $mine): array => [];

        $entry = fn (array $mine): array => [
            'name' => 'Renamed by somebody else',
            'meal' => 'lunch',
            'grams' => 999,
            'kcal' => 999,
            'protein_g' => 99,
            'fat_g' => 99,
            'carbs_g' => 99,
        ];

        $item = fn (array $mine): array => [
            'name' => 'Renamed by somebody else',
            'kcal_per_100g' => 999,
            'protein_g_per_100g' => 99,
            'fat_g_per_100g' => 99,
            'carbs_g_per_100g' => 99,
        ];

        // A recipe update deletes and rewrites the ingredient list, so reaching
        // one would not merely rename it — it would empty it.
        $recipe = fn (array $mine): array => [
            'name' => 'Renamed by somebody else',
            'ingredients' => [['item_id' => $mine['spare']->id, 'grams' => 250]],
        ];

        return [
            'open an entry for editing' => ['get', 'entries.edit', 'entry', $nothing],
            'change an entry' => ['patch', 'entries.update', 'entry', $entry],
            'delete an entry' => ['delete', 'entries.destroy', 'entry', $nothing],

            'open a library item for editing' => ['get', 'library.edit', 'spare', $nothing],
            'change a library item' => ['patch', 'library.update', 'spare', $item],
            'delete a library item' => ['delete', 'library.destroy', 'spare', $nothing],

            'open a recipe for editing' => ['get', 'library.recipe.edit', 'recipe', $nothing],
            'change a recipe' => ['patch', 'library.recipe.update', 'recipe', $recipe],

            'delete a weight reading' => ['delete', 'weight.destroy', 'weight', $nothing],
        ];
    }

    /**
     * Every row of every scoped table, read past the scope, in a stable order.
     *
     * @return array<string, list<array<string, mixed>>>
     */
    private function everything(): array
    {
        $state = [];

        foreach (self::DOMAIN_TABLES as $table) {
            $state[$table] = DB::table($table)->orderBy('id')->get()
                ->map(fn (object $row): array => (array) $row)
                ->all();
        }

        return $state;
    }

    #[DataProvider('routesThatTakeAnId')]
    public function test_one_person_cannot_reach_another_persons_record(
        string $method,
        string $route,
        string $target,
        Closure $payload,
    ): void {
        $theirs = $this->belongingsOf(User::factory()->create());
        $mine = $this->belongingsOf(User::factory()->create());

        // Signed in as the second person, reaching for the first person's id.
        $body = $payload($mine);
        $before = $this->everything();

        $theirId = $theirs[$target]->getKey();
        $this->assertNotSame(self::NEVER_EXISTED, $theirId, 'The fixture collided with the missing id.');

        $reached = $this->{$method}(route($route, $theirId), $body);
        $missing = $this->{$method}(route($route, self::NEVER_EXISTED), $body);

        // 1. Refused.
        $reached->assertNotFound();

        // 2. Refused in exactly the same words as an id that never existed. Not
        //    403: a refusal that distinguishes the two confirms the record is
        //    there, and an attacker with a loop has the whole table.
        $this->assertSame($missing->status(), $reached->status());
        $this->assertSame((string) $missing->getContent(), (string) $reached->getContent());

        // 3. And nothing happened on the way to being refused — not to their
        //    record, and not to anything else either.
        $this->assertSame($before, $this->everything(), 'The attempt changed the database.');

        $stillThere = DB::table($theirs[$target]->getTable())->where('id', $theirId)->first();
        $this->assertNotNull($stillThere, 'Their record is gone.');
    }

    /**
     * The ids above all arrive in the path, where route model binding resolves
     * them through the scope. These three arrive in the body — a merge target, a
     * recipe line, a logged entry's provenance link — so the scope never sees
     * them unless the code that reads them says whose row it means.
     */
    public function test_a_merge_cannot_name_another_persons_item_as_the_survivor(): void
    {
        $theirs = $this->belongingsOf(User::factory()->create());
        $mine = $this->belongingsOf(User::factory()->create());

        $before = $this->everything();

        $reached = $this->from(route('library.index'))
            ->post(route('library.merge', $mine['spare']), ['target_id' => $theirs['spare']->getKey()]);
        $refusedTheirs = $this->refusal();

        $missing = $this->from(route('library.index'))
            ->post(route('library.merge', $mine['spare']), ['target_id' => self::NEVER_EXISTED]);
        $refusedMissing = $this->refusal();

        // 1. Refused. Not a 404 here: the id is a submitted field, so an unknown
        //    one has always been a validation error, and it stays one. Read from
        //    the capture above rather than from the session, which by now holds
        //    the second request's errors and would answer for the wrong attempt.
        $this->assertArrayHasKey('target_id', $refusedTheirs, 'The merge was not refused.');

        // 2. In the same words as an id that never existed — the refusal says
        //    the field is invalid, and nothing about whether the row is there.
        $this->assertSame($missing->status(), $reached->status());
        $this->assertSame((string) $missing->getContent(), (string) $reached->getContent());
        $this->assertSame($refusedMissing, $refusedTheirs);

        // 3. And neither library moved: not their item, which a merge would have
        //    renamed, and not mine, which a merge would have deleted.
        $this->assertSame($before, $this->everything(), 'The attempt changed the database.');
    }

    public function test_a_recipe_cannot_be_built_on_another_persons_item(): void
    {
        $theirs = $this->belongingsOf(User::factory()->create());
        $mine = $this->belongingsOf(User::factory()->create());

        $before = $this->everything();

        $line = fn (int $id): array => [
            'name' => 'A recipe of mine',
            'ingredients' => [['item_id' => $id, 'grams' => 100]],
        ];

        $reached = $this->from(route('library.index'))
            ->patch(route('library.recipe.update', $mine['recipe']), $line($theirs['spare']->getKey()));
        $refusedTheirs = $this->refusal();

        $missing = $this->from(route('library.index'))
            ->patch(route('library.recipe.update', $mine['recipe']), $line(self::NEVER_EXISTED));
        $refusedMissing = $this->refusal();

        $this->assertArrayHasKey('ingredients.0.item_id', $refusedTheirs, 'The recipe was not refused.');
        $this->assertSame($missing->status(), $reached->status());
        $this->assertSame((string) $missing->getContent(), (string) $reached->getContent());
        $this->assertSame($refusedMissing, $refusedTheirs);

        // A recipe holding somebody else's ingredient would put their food into
        // the calculator's reach, one line at a time.
        $this->assertSame($before, $this->everything(), 'The attempt changed the database.');
    }

    public function test_a_logged_entry_never_links_to_another_persons_library_item(): void
    {
        // The provenance link on a diary entry is the third body-borne id, and
        // the only one no form carries: it is built server-side from a tier-1
        // match, and tier one already answers from the signed-in person's
        // library alone. This asserts the seam underneath that, by handing the
        // log service a candidate naming an item somebody else owns — the state
        // a future caller could reach without the check, and the one thing the
        // service must not write.
        $them = User::factory()->create();
        $theirs = $this->belongingsOf($them);
        $this->belongingsOf(User::factory()->create());

        $items = DB::table('food_items')->count();
        $aliases = DB::table('food_item_aliases')->count();

        $entry = app(MealLogService::class)->commit(
            [
                'source' => NutrientSource::PersonalLibrary->value,
                'kcal' => 100.0,
                'protein' => 1.0,
                'fat' => 2.0,
                'carbs' => 3.0,
                'food_item_id' => $theirs['spare']->getKey(),
                'external_id' => (string) $theirs['spare']->getKey(),
            ],
            new SearchTerms('Something they logged'),
            150.0,
            MealType::Lunch,
            CarbonImmutable::now(),
        );

        $this->assertNull($entry->food_item_id, 'A diary entry links to another person\'s library item.');

        // Nor was it laundered into a copy: an item claiming to come from a
        // personal library that is not this person's is not promoted into theirs.
        $this->assertSame($items, DB::table('food_items')->count(), 'Something was written to a library.');

        // And nothing was learned on their item's behalf either — an alias would
        // change how their next photo resolves.
        $this->assertSame($aliases, DB::table('food_item_aliases')->count(), 'An alias was recorded on their item.');
    }

    /**
     * The refusal a person is left holding: the flashed validation messages, in
     * the words the form will show them.
     *
     * Read straight after the request it belongs to — the session carries one
     * set at a time, and the next request replaces it. What is in there is a
     * plain array once flashed, so both shapes are handled rather than assumed.
     *
     * @return array<string, list<string>>
     */
    private function refusal(): array
    {
        $errors = session('errors');

        if ($errors instanceof ViewErrorBag) {
            /** @var array<string, list<string>> */
            return $errors->getBag('default')->messages();
        }

        /** @var array<string, list<string>> */
        return is_array($errors) && is_array($errors['default']['messages'] ?? null)
            ? $errors['default']['messages']
            : [];
    }

    public function test_a_person_can_do_all_of_that_to_their_own_records(): void
    {
        // Without this the class could pass with every route broken for
        // everybody, which would isolate users perfectly and be useless.
        $mine = $this->belongingsOf(User::factory()->create());

        foreach (self::routesThatTakeAnId() as $name => [$method, $route, $target, $payload]) {
            $again = $this->belongingsOf(User::factory()->create());

            $response = $this->{$method}(route($route, $again[$target]->getKey()), $payload($again));

            $this->assertNotSame(404, $response->status(), "A person cannot {$name} on their own record.");
        }
    }
}
