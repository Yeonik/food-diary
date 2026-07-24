<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\FoodItem;
use App\Nutrition\Contracts\IngredientTranslator;
use App\Nutrition\FakeIngredientTranslator;
use App\Nutrition\GeminiIngredientTranslator;
use App\Nutrition\ProfileOrigin;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Tests\TestCase;

/**
 * A foreign ingredient name is translated to English for USDA, which indexes
 * English and is the source that actually covers raw ingredients.
 *
 * The claim that matters most: translation changes only WHICH candidates USDA
 * returns, never their numbers. The numbers are always USDA's own, for whatever
 * it returned.
 */
class IngredientTranslationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->signIn();
    }

    private function translatorReturns(string $term, string $english): void
    {
        // The container binds a FakeIngredientTranslator singleton in testing;
        // seed the instance the controller will resolve.
        /** @var FakeIngredientTranslator $fake */
        $fake = app(IngredientTranslator::class);
        $fake->with($term, $english);
    }

    private function fakeUsda(callable $foodsFor): void
    {
        Http::fake([
            'api.nal.usda.gov/*' => function (Request $request) use ($foodsFor) {
                $query = $request->data()['query'] ?? '';

                return Http::response(['foods' => $foodsFor(is_string($query) ? $query : '')]);
            },
            'world.openfoodfacts.org/*' => Http::response(['products' => []]),
        ]);
    }

    /** @return array<int, array<string, mixed>> */
    private function riceFood(): array
    {
        return [[
            'description' => 'Rice, white, cooked',
            'fdcId' => 111,
            'foodNutrients' => [
                ['nutrientNumber' => '208', 'value' => 130],
                ['nutrientNumber' => '203', 'value' => 2.7],
                ['nutrientNumber' => '204', 'value' => 0.3],
                ['nutrientNumber' => '205', 'value' => 28],
            ],
        ]];
    }

    public function test_a_cyrillic_query_is_searched_in_usda_in_english(): void
    {
        $this->translatorReturns('рис', 'rice');

        // USDA answers for "rice" but not for the raw Cyrillic — so a candidate
        // appearing at all proves the English term reached it.
        $this->fakeUsda(fn (string $query): array => $query === 'rice' ? $this->riceFood() : []);

        $this->post(route('library.recipe.ingredient.search'), ['query' => 'рис'])
            ->assertRedirect(route('library.recipe.ingredient.choose'));

        // USDA was asked in English.
        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'nal.usda.gov')
            && ($request->data()['query'] ?? null) === 'rice');

        $this->get(route('library.recipe.ingredient.choose'))
            ->assertOk()
            ->assertSee('Rice, white, cooked');
    }

    public function test_translation_changes_which_candidates_are_found_not_their_numbers(): void
    {
        $this->translatorReturns('рис', 'rice');
        $this->fakeUsda(fn (string $query): array => $query === 'rice' ? $this->riceFood() : []);

        $this->post(route('library.recipe.ingredient.search'), ['query' => 'рис']);
        $this->post(route('library.recipe.ingredient.add'), ['candidate' => 0, 'grams' => 150]);

        // The promoted item carries USDA's numbers for the food it returned —
        // the translation only decided that USDA was asked "rice".
        $item = FoodItem::query()->where('origin', ProfileOrigin::Usda->value)->sole();
        $this->assertSame(130.0, $item->kcal_per_100g);
        $this->assertSame(28.0, $item->carbs_g_per_100g);
    }

    public function test_a_foreign_word_that_led_to_a_choice_is_found_next_time_without_translating(): void
    {
        // First search: an empty library, so 'рис' is translated to 'rice' for
        // USDA, and the chosen candidate is promoted into the library.
        $this->translatorReturns('рис', 'rice');
        $this->fakeUsda(fn (string $query): array => $query === 'rice' ? $this->riceFood() : []);

        $this->post(route('library.recipe.ingredient.search'), ['query' => 'рис']);
        $this->post(route('library.recipe.ingredient.add'), ['candidate' => 0, 'grams' => 150]);

        $item = FoodItem::query()->where('origin', ProfileOrigin::Usda->value)->sole();

        // The Cyrillic word the person searched by is now an alias of that item —
        // the English USDA label stays its name; the foreign word is learned.
        $this->assertSame('Rice, white, cooked', $item->name);
        $this->assertTrue($item->aliases()->where('name', 'рис')->exists());

        // Second search: bind a translator that fails the test if it is consulted
        // at all. The library already knows 'рис', so tier 1 answers and the
        // translator is never reached.
        $this->app->instance(IngredientTranslator::class, new class implements IngredientTranslator
        {
            public function toEnglish(string $term): ?string
            {
                throw new \RuntimeException('the translator must not be consulted for a word already in the library');
            }
        });

        $this->post(route('library.recipe.ingredient.search'), ['query' => 'рис'])
            ->assertRedirect(route('library.recipe.ingredient.choose'));

        // Found in the library by the learned alias, with no translation.
        $this->get(route('library.recipe.ingredient.choose'))
            ->assertOk()
            ->assertSee('Rice, white, cooked');
    }

    public function test_a_latin_query_is_not_translated(): void
    {
        // No seed for "rice"; a Latin term must not be sent to the translator at
        // all, so USDA is searched with it as typed.
        $this->fakeUsda(fn (string $query): array => $query === 'rice' ? $this->riceFood() : []);

        $this->post(route('library.recipe.ingredient.search'), ['query' => 'rice']);

        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'nal.usda.gov')
            && ($request->data()['query'] ?? null) === 'rice');

        $this->get(route('library.recipe.ingredient.choose'))->assertOk()->assertSee('Rice, white, cooked');
    }

    public function test_search_still_works_when_translation_does_not_apply(): void
    {
        // The fake translates nothing for this term (fail-open / no match). The
        // search must still run — USDA gets the original term, and the library
        // and Open Food Facts answer regardless.
        $existing = FoodItem::factory()->direct(kcal: 89, protein: 1.1, fat: 0.3, carbs: 23)
            ->create(['name' => 'гречка']);

        $this->fakeUsda(fn (string $query): array => []);

        $this->post(route('library.recipe.ingredient.search'), ['query' => 'гречка'])
            ->assertRedirect(route('library.recipe.ingredient.choose'));

        // The library (tier 1) still answered, so the flow is unbroken.
        $this->get(route('library.recipe.ingredient.choose'))->assertOk()->assertSee('гречка');
        $this->assertNotNull($existing->fresh());
    }

    public function test_a_latin_term_makes_no_call_even_with_a_key_configured(): void
    {
        // The gate is separate from the no-key guard: with a key present, a
        // Latin term must still make no network call, while a Cyrillic one does.
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [['content' => ['parts' => [['text' => 'rice']]]]],
            ]),
        ]);

        $translator = new GeminiIngredientTranslator(
            'https://generativelanguage.googleapis.com/v1beta',
            'gemini-x',
            'a-key', // a key IS configured
            app(Repository::class),
        );

        // Latin: gated out before any call.
        $this->assertNull($translator->toEnglish('rice'));
        Http::assertNothingSent();

        // Cyrillic: it does call, and returns the English answer.
        $this->assertSame('rice', $translator->toEnglish('рис'));
        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'generativelanguage'));
    }

    public function test_the_translator_fails_open_without_a_key(): void
    {
        // No key: every call fails open to null rather than throwing, so the
        // search carries on with the untranslated term.
        $translator = new GeminiIngredientTranslator(
            'https://example.test',
            'gemini-x',
            null,
            app(Repository::class),
        );

        $this->assertNull($translator->toEnglish('рис'));
    }

    private function geminiTranslator(): GeminiIngredientTranslator
    {
        return new GeminiIngredientTranslator(
            'https://generativelanguage.googleapis.com/v1beta',
            'gemini-x',
            'a-key',
            app(Repository::class),
        );
    }

    /**
     * A successful Gemini response carrying $english.
     *
     * @return PromiseInterface
     */
    private function geminiOk(string $english)
    {
        return Http::response(['candidates' => [['content' => ['parts' => [['text' => $english]]]]]]);
    }

    public function test_a_busy_model_is_retried_before_the_translation_gives_up(): void
    {
        Sleep::fake();

        // The free tier answers 503/429 in bursts; the call retries and succeeds
        // on the third attempt rather than failing open on the first blip.
        Http::fakeSequence('generativelanguage.googleapis.com/*')
            ->push(['error' => 'overloaded'], 503)
            ->push(['error' => 'rate limited'], 429)
            ->pushResponse($this->geminiOk('rice'));

        $this->assertSame('rice', $this->geminiTranslator()->toEnglish('рис'));
        Http::assertSentCount(3);
    }

    public function test_a_failed_translation_is_not_cached_as_a_miss(): void
    {
        Sleep::fake();

        // The model is down for the whole call.
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response(['error' => 'down'], 503)]);

        $translator = $this->geminiTranslator();

        // Fails open both times — and the second search tries the network afresh
        // rather than being served a cached miss. Three attempts per call, twice,
        // is six requests: proof the failure was never written to the cache.
        $this->assertNull($translator->toEnglish('рис'));
        $this->assertNull($translator->toEnglish('рис'));
        Http::assertSentCount(6);
    }

    public function test_a_successful_translation_is_cached(): void
    {
        Http::fake(['generativelanguage.googleapis.com/*' => $this->geminiOk('rice')]);

        $translator = $this->geminiTranslator();

        // Translated once, then reused from the cache — the second search makes
        // no call. This is the whole point of caching: the success is kept.
        $this->assertSame('rice', $translator->toEnglish('рис'));
        $this->assertSame('rice', $translator->toEnglish('рис'));
        Http::assertSentCount(1);
    }
}
