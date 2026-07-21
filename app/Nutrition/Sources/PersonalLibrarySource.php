<?php

declare(strict_types=1);

namespace App\Nutrition\Sources;

use App\Models\FoodItem;
use App\Nutrition\Contracts\NutritionSource;
use App\Nutrition\NameMatcher;
use App\Nutrition\NutrientMatch;
use App\Nutrition\NutrientSource;
use App\Nutrition\RecipeCalculator;
use App\Nutrition\SearchTerms;
use Illuminate\Support\Collection;

/**
 * Tier one, always consulted first: foods the user has already confirmed,
 * corrected, or defined as recipes. These are trusted above any external
 * database because the user verified them.
 *
 * Matching is loose on purpose. A vision model phrases the same package
 * differently each photo and the user may have edited the stored name, so an
 * exact-substring match misses. Instead an item is scored on the meaningful
 * tokens it shares with a recognised term, across its name, its other-language
 * name and every alias it has accrued. Nothing is auto-selected — these become
 * candidates the user picks from — so surfacing a near-match is safe, and each
 * carries the stored string that matched so a loose hit is explainable.
 */
class PersonalLibrarySource implements NutritionSource
{
    /** The most library candidates to offer for one item; a short list, not a dump. */
    private const CANDIDATE_LIMIT = 5;

    /** Fewer shared tokens than this is too weak to offer on token overlap alone. */
    private const MIN_SHARED_TOKENS = 2;

    /** A generous ceiling on rows scored in memory; the library is small. */
    private const PREFILTER_LIMIT = 200;

    public function __construct(
        private readonly RecipeCalculator $calculator,
        private readonly NameMatcher $matcher,
    ) {}

    /**
     * @return list<NutrientMatch>
     */
    public function search(string $name): array
    {
        return $this->matchesFor(new SearchTerms($name));
    }

    /**
     * The best library candidates for one or two recognised terms: scored by
     * shared tokens across each item's names and aliases, capped, each labelled
     * with the stored string it matched.
     *
     * @return list<NutrientMatch>
     */
    public function matchesFor(SearchTerms $terms, int $limit = self::CANDIDATE_LIMIT): array
    {
        $items = $this->prefilter($terms);

        /** @var list<array{item: FoodItem, best: array{contains: bool, overlap: int, via: string}}> $scored */
        $scored = [];

        foreach ($items as $item) {
            $best = $this->bestMatch($terms, $item);
            if ($best !== null) {
                $scored[] = ['item' => $item, 'best' => $best];
            }
        }

        usort($scored, $this->compareScored(...));

        $matches = [];

        foreach (array_slice($scored, 0, $limit) as $row) {
            /** @var FoodItem $item */
            $item = $row['item'];
            $via = $row['best']['via'];

            $matches[] = new NutrientMatch(
                description: $item->name,
                profile: $item->isRecipe() ? $this->calculator->profileFor($item) : $item->storedProfile(),
                externalId: (string) $item->id,
                // Only when the match came from something other than the shown
                // name; the name is already on screen, so repeating it is noise.
                matchedVia: strcasecmp($via, $item->name) === 0 ? null : $via,
            );
        }

        return $matches;
    }

    /**
     * Rank: a stronger signal first (containment over token overlap, then more
     * shared tokens), breaking ties toward the shorter, more specific name.
     *
     * @param  array{item: FoodItem, best: array{contains: bool, overlap: int, via: string}}  $x
     * @param  array{item: FoodItem, best: array{contains: bool, overlap: int, via: string}}  $y
     */
    private function compareScored(array $x, array $y): int
    {
        return [$y['best']['contains'], $y['best']['overlap']] <=> [$x['best']['contains'], $x['best']['overlap']]
            ?: mb_strlen($x['item']->name) <=> mb_strlen($y['item']->name)
            ?: strcmp($x['item']->name, $y['item']->name);
    }

    /**
     * Cheaply narrow the table to items sharing at least one token with a term,
     * before scoring the survivors in memory.
     *
     * @return Collection<int, FoodItem>
     */
    private function prefilter(SearchTerms $terms): Collection
    {
        $tokens = [];
        foreach ($terms->all() as $term) {
            $tokens = [...$tokens, ...$this->matcher->significantTokens($term)];
        }

        // No usable tokens (e.g. a term of only glue): fall back to the raw terms.
        if ($tokens === []) {
            $tokens = $terms->all();
        }

        return FoodItem::query()
            ->with('aliases')
            ->where(function ($query) use ($tokens): void {
                foreach ($tokens as $token) {
                    $query->orWhere('name', 'like', '%'.$token.'%')
                        ->orWhere('alt_name', 'like', '%'.$token.'%')
                        ->orWhereHas('aliases', fn ($alias) => $alias->where('name', 'like', '%'.$token.'%'));
                }
            })
            ->limit(self::PREFILTER_LIMIT)
            ->get();
    }

    /**
     * The strongest match between any recognised term and any name the item is
     * known by. Returns null when nothing clears the threshold.
     *
     * @return array{contains: bool, overlap: int, via: string}|null
     */
    private function bestMatch(SearchTerms $terms, FoodItem $item): ?array
    {
        $strings = [];
        foreach ([$item->name, $item->alt_name, ...$item->aliases->pluck('name')->all()] as $string) {
            if (is_string($string) && trim($string) !== '') {
                $strings[] = $string;
            }
        }

        $best = null;

        foreach ($terms->all() as $term) {
            foreach ($strings as $string) {
                $contains = $this->matcher->contains($term, $string);
                $overlap = $this->matcher->overlap($term, $string);

                if (! $contains && $overlap < self::MIN_SHARED_TOKENS) {
                    continue;
                }

                $better = $best === null
                    || ($contains && ! $best['contains'])
                    || ($contains === $best['contains'] && $overlap > $best['overlap']);

                if ($better) {
                    $best = ['contains' => $contains, 'overlap' => $overlap, 'via' => $string];
                }
            }
        }

        return $best;
    }

    public function source(): NutrientSource
    {
        return NutrientSource::PersonalLibrary;
    }
}
