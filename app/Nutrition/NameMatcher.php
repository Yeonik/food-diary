<?php

declare(strict_types=1);

namespace App\Nutrition;

/**
 * Compares food names the loose way a person would: by the meaningful words they
 * share, not by exact string equality. A vision model phrases the same package
 * differently each photo, so a stored name is rarely reproduced verbatim.
 *
 * The stop list is deliberately tiny — only connectives that carry no product
 * meaning. Words that look like filler but distinguish variants ("без", "сахара",
 * "sugar", "free", "0") are kept: dropping them would make a full-sugar and a
 * sugar-free product look identical, and their calories are not.
 */
final class NameMatcher
{
    /**
     * Pure glue: words that never distinguish one product from another. Anything
     * that could separate two variants is intentionally absent.
     *
     * @var list<string>
     */
    private const STOP_WORDS = [
        'со', 'с', 'и', 'в', 'на', 'по', 'из', 'от', 'для', 'к', 'у',
        'the', 'a', 'an', 'of', 'and', 'or', 'with', 'in', 'on', 'for', 'to', 'by',
    ];

    /**
     * The meaningful, de-duplicated tokens of a name: lower-cased, split on any
     * non-alphanumeric, glue removed. Single characters are dropped unless they
     * are digits, so "0" (as in "0%") survives while a stray "с" does not.
     *
     * @return list<string>
     */
    public function significantTokens(string $name): array
    {
        $tokens = [];

        foreach ($this->pieces($name) as $piece) {
            if (in_array($piece, self::STOP_WORDS, true)) {
                continue;
            }

            if (mb_strlen($piece) < 2 && ! ctype_digit($piece)) {
                continue;
            }

            if (! in_array($piece, $tokens, true)) {
                $tokens[] = $piece;
            }
        }

        return $tokens;
    }

    /**
     * How many meaningful tokens two names share.
     */
    public function overlap(string $a, string $b): int
    {
        $shared = array_intersect($this->significantTokens($a), $this->significantTokens($b));

        return count($shared);
    }

    /**
     * The meaningful tokens two names share, for showing the user why a loose
     * match surfaced.
     *
     * @return list<string>
     */
    public function sharedTokens(string $a, string $b): array
    {
        return array_values(array_intersect($this->significantTokens($a), $this->significantTokens($b)));
    }

    /**
     * True when one normalised name contains the other as a whole run of tokens —
     * a strong signal that survives a short stored name ("Молоко") sitting inside
     * a longer recognised one ("Молоко 0%").
     */
    public function contains(string $a, string $b): bool
    {
        $na = $this->normalised($a);
        $nb = $this->normalised($b);

        if ($na === '' || $nb === '') {
            return false;
        }

        return str_contains(" {$na} ", " {$nb} ") || str_contains(" {$nb} ", " {$na} ");
    }

    /**
     * Lower-cased, non-alphanumeric collapsed to single spaces. Used for whole-run
     * containment; keeps every word (no stop list) so containment stays faithful.
     */
    private function normalised(string $name): string
    {
        return trim(implode(' ', $this->pieces($name)));
    }

    /**
     * @return list<string>
     */
    private function pieces(string $name): array
    {
        $lowered = mb_strtolower($name);
        $spaced = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $lowered) ?? '';

        $pieces = preg_split('/\s+/', trim($spaced)) ?: [];

        return array_values(array_filter($pieces, static fn (string $p): bool => $p !== ''));
    }
}
