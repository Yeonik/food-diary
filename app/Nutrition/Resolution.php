<?php

declare(strict_types=1);

namespace App\Nutrition;

/**
 * The outcome of resolving one food name against the source ladder.
 *
 * Library matches and API matches are kept in separate buckets and NOTHING is
 * auto-selected: the interface shows the candidates, each labelled with its
 * source, and the user chooses. A wrong automatic match is worse than a short
 * list. The estimate is offered only when no real source answered at all.
 */
final readonly class Resolution
{
    /**
     * @param  list<NutrientMatch>  $libraryMatches  tier 1, the personal library
     * @param  list<NutrientMatch>  $apiMatches  tier 2, USDA and Open Food Facts, unranked
     * @param  NutrientMatch|null  $estimated  tier 3, only set when tiers 1 and 2 are empty
     * @param  list<ResolutionNotice>  $notices  sources that could not be consulted
     */
    public function __construct(
        public string $query,
        public array $libraryMatches,
        public array $apiMatches,
        public ?NutrientMatch $estimated,
        public array $notices = [],
    ) {}

    /**
     * Which tier answered: 1 personal library, 2 external APIs, 3 estimate/none.
     * This is the value recorded and displayed for the resolution.
     */
    public function answeringTier(): int
    {
        if ($this->libraryMatches !== []) {
            return 1;
        }

        if ($this->apiMatches !== []) {
            return 2;
        }

        return 3;
    }

    public function hasLibraryMatch(): bool
    {
        return $this->libraryMatches !== [];
    }

    /**
     * True when no real source could answer — the entry would be logged as an
     * unverified estimate.
     */
    public function isUnresolved(): bool
    {
        return $this->libraryMatches === [] && $this->apiMatches === [];
    }

    /**
     * All candidate matches for display, library first. The estimate, when
     * present, comes last and stays visually and semantically distinct.
     *
     * @return list<NutrientMatch>
     */
    public function candidates(): array
    {
        $candidates = [...$this->libraryMatches, ...$this->apiMatches];

        if ($this->estimated !== null) {
            $candidates[] = $this->estimated;
        }

        return $candidates;
    }
}
