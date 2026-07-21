<?php

declare(strict_types=1);

namespace App\Nutrition;

/**
 * The name(s) to look a food up by. Recognition may return the same product in
 * two languages — an English name (which is what USDA indexes) and the name as
 * printed on the packaging (Russian, say), which is what Open Food Facts and the
 * user recognise. Sources decide for themselves which term(s) they want:
 *
 *   - USDA searches {@see $english} only;
 *   - Open Food Facts searches {@see all()} — both, so a Russian product still
 *     matches when the model gave only an English label to search USDA with;
 *   - the user is shown {@see display()} — the native name when there is one.
 *
 * A single hand-typed term becomes {@see $english} with no native counterpart.
 */
final readonly class SearchTerms
{
    public function __construct(
        public string $english,
        public ?string $native = null,
    ) {}

    /**
     * What to show and store: the native name when the model gave one, so the
     * user sees the label they recognise rather than a translation.
     */
    public function display(): string
    {
        return $this->native !== null && trim($this->native) !== ''
            ? trim($this->native)
            : $this->english;
    }

    /**
     * The other known name — the one not being displayed — when it is distinct.
     * This is what a library item's second column is filled with, so the food
     * can later be found by either name.
     */
    public function alt(): ?string
    {
        foreach ($this->all() as $term) {
            if (strcasecmp($term, $this->display()) !== 0) {
                return $term;
            }
        }

        return null;
    }

    /**
     * Every distinct, non-empty term, for a source that can search in any
     * language. Order is stable: English first, then native.
     *
     * @return list<string>
     */
    public function all(): array
    {
        $terms = [];

        foreach ([$this->english, $this->native] as $term) {
            if (! is_string($term)) {
                continue;
            }

            $trimmed = trim($term);
            if ($trimmed === '') {
                continue;
            }

            foreach ($terms as $seen) {
                if (strcasecmp($seen, $trimmed) === 0) {
                    continue 2;
                }
            }

            $terms[] = $trimmed;
        }

        return $terms;
    }
}
