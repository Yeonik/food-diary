<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Nutrition\Contracts\IngredientTranslator;
use App\Nutrition\DictionaryIngredientTranslator;
use App\Nutrition\NameMatcher;
use Tests\TestCase;

/**
 * The curated dictionary that answers common ingredients before Gemini — instant,
 * offline, and unaffected by the free tier's 429/503. Only the tail it does not
 * know falls through to the fallback.
 */
class DictionaryIngredientTranslatorTest extends TestCase
{
    /**
     * A fallback that records whether it was consulted, so a test can prove the
     * dictionary answered on its own.
     */
    private function recordingFallback(?string $answer): IngredientTranslator
    {
        return new class($answer) implements IngredientTranslator
        {
            /** @var list<string> */
            public array $calls = [];

            public function __construct(private readonly ?string $answer) {}

            public function toEnglish(string $term): ?string
            {
                $this->calls[] = $term;

                return $this->answer;
            }
        };
    }

    public function test_a_common_ingredient_is_translated_from_the_table_without_the_fallback(): void
    {
        $fallback = $this->recordingFallback('should-not-be-used');
        $translator = new DictionaryIngredientTranslator(new NameMatcher, $fallback);

        $this->assertSame('potato', $translator->toEnglish('картофель'));
        $this->assertSame('rice', $translator->toEnglish('рис'));
        $this->assertSame([], $fallback->calls);
    }

    public function test_yo_and_ye_spellings_reach_the_same_entry(): void
    {
        $translator = new DictionaryIngredientTranslator(new NameMatcher);

        // Stored as "свекла"/"мед"; the ё spellings fold to the same key.
        $this->assertSame('beets', $translator->toEnglish('свёкла'));
        $this->assertSame('honey', $translator->toEnglish('мёд'));
    }

    public function test_a_multiword_phrase_pivots_to_its_known_ingredient(): void
    {
        // #3 C, offline: "сырой картофель" — the modifier is not a food, so the
        // one known word carries the translation. No fallback, no network.
        $fallback = $this->recordingFallback('should-not-be-used');
        $translator = new DictionaryIngredientTranslator(new NameMatcher, $fallback);

        $this->assertSame('potato', $translator->toEnglish('сырой картофель'));
        $this->assertSame('rice', $translator->toEnglish('варёный рис'));
        $this->assertSame([], $fallback->calls);
    }

    public function test_a_curated_compound_is_translated_whole(): void
    {
        $translator = new DictionaryIngredientTranslator(new NameMatcher);

        $this->assertSame('chicken breast', $translator->toEnglish('куриная грудка'));
    }

    public function test_an_unknown_word_falls_through_to_the_fallback(): void
    {
        $fallback = $this->recordingFallback('gemini-answer');
        $translator = new DictionaryIngredientTranslator(new NameMatcher, $fallback);

        $this->assertSame('gemini-answer', $translator->toEnglish('маца'));
        $this->assertSame(['маца'], $fallback->calls);
    }

    public function test_a_phrase_naming_two_foods_is_left_to_the_fallback_not_guessed(): void
    {
        // "рис курица" — two known foods, so the dictionary does not pick one; it
        // defers rather than guess. Without a fallback it simply returns null.
        $fallback = $this->recordingFallback('gemini-answer');
        $translator = new DictionaryIngredientTranslator(new NameMatcher, $fallback);

        $this->assertSame('gemini-answer', $translator->toEnglish('рис курица'));
        $this->assertSame(['рис курица'], $fallback->calls);

        $noFallback = new DictionaryIngredientTranslator(new NameMatcher);
        $this->assertNull($noFallback->toEnglish('рис курица'));
    }
}
