<?php

declare(strict_types=1);

namespace App\Nutrition;

use App\Models\FoodItem;
use App\Models\FoodItemAlias;
use App\Models\MealEntry;
use Carbon\CarbonInterface;

/**
 * Turns recognised or searched foods into a confirmable payload, then into
 * logged entries.
 *
 * The critical rule lives here: the nutrient numbers in the payload are all
 * computed server-side from the resolution ladder, and the commit step reads
 * them from that payload — never from the submitted form. The user chooses a
 * candidate, a weight, and a meal; the model (or a tampered request) can never
 * inject a nutrient value presented as fact.
 */
class MealLogService
{
    public function __construct(private readonly FoodResolver $resolver) {}

    /**
     * Whether the personal library already answers this raw term — by a name or
     * by an alias it learned from a past search. When it does, tier 1 will
     * surface the item and the term does not need translating for USDA, so a
     * foreign word searched a second time finds its item without a translation.
     */
    public function libraryKnows(string $term): bool
    {
        return $this->resolver->libraryMatches(new SearchTerms($term)) !== [];
    }

    /**
     * Build a pending payload for one or two search terms. The English and
     * native names are carried through so the commit step can store both on a
     * promoted library item, or backfill the one a matched item is missing.
     *
     * @return array{name: string, grams: float, english: string, native: string|null, candidates: list<array<string, mixed>>}
     */
    public function pendingForTerms(SearchTerms $terms, float $grams = 100.0, ?NutrientProfile $estimate = null): array
    {
        $resolution = $this->resolver->resolve($terms, $estimate);

        $candidates = [];
        foreach ($resolution->candidates() as $match) {
            $candidates[] = $this->encodeCandidate($match);
        }

        return [
            'name' => $terms->display(),
            'grams' => $grams,
            'english' => $terms->english,
            'native' => $terms->native,
            'candidates' => $candidates,
        ];
    }

    /**
     * Build a pending payload for one hand-typed food name (the manual path).
     *
     * @return array{name: string, grams: float, english: string, native: string|null, candidates: list<array<string, mixed>>}
     */
    public function pendingForName(string $name, float $grams = 100.0, ?NutrientProfile $estimate = null): array
    {
        return $this->pendingForTerms(new SearchTerms($name), $grams, $estimate);
    }

    /**
     * Build a single-product payload for the barcode path: one Open Food Facts
     * match, resolved from a code, carried through the session so the commit
     * reads its numbers from here and never from the form.
     *
     * @return array{name: string, grams: float, candidate: array<string, mixed>}
     */
    public function pendingForProduct(NutrientMatch $match, float $grams = 100.0): array
    {
        return [
            'name' => $match->description,
            'grams' => $grams,
            'candidate' => $this->encodeCandidate($match),
        ];
    }

    /**
     * Build pending payloads for every recognised item in a photo.
     *
     * @param  list<RecognisedItem>  $items
     * @return list<array{name: string, grams: float, english: string, native: string|null, candidates: list<array<string, mixed>>}>
     */
    public function pendingForRecognised(array $items): array
    {
        $pending = [];
        foreach ($items as $item) {
            $pending[] = $this->pendingForTerms($item->searchTerms(), $item->estimatedGrams, $item->estimatedProfile);
        }

        return $pending;
    }

    /**
     * Persist one logged entry from a server-side candidate the user picked.
     *
     * @param  array<string, mixed>  $candidate  one entry from a pending payload
     */
    public function commit(array $candidate, SearchTerms $terms, float $grams, MealType $meal, CarbonInterface $loggedAt): MealEntry
    {
        $source = NutrientSource::from((string) $candidate['source']);

        $profile = new NutrientProfile(
            kcal: (float) $candidate['kcal'],
            proteinG: (float) $candidate['protein'],
            fatG: (float) $candidate['fat'],
            carbsG: (float) $candidate['carbs'],
            source: $source,
        );

        $claimedItemId = is_int($candidate['food_item_id'] ?? null) ? $candidate['food_item_id'] : null;

        // This id is not accepted from the form — it is built server-side by
        // encodeCandidate() from a tier-1 match, and tier 1 already only answers
        // from the signed-in person's own library. The read below is the second
        // lock: through the scoped model, somebody else's item comes back as
        // absent exactly as one that never existed would, so no diary entry can
        // come to reference a library that is not this person's.
        $foodItemId = $claimedItemId !== null && FoodItem::query()->whereKey($claimedItemId)->exists()
            ? $claimedItemId
            : null;

        if ($foodItemId === null && $claimedItemId === null && $source !== NutrientSource::Estimated) {
            // Confirming a real (non-estimated) match that is not already in the
            // library promotes it there — with both names and the source's stable
            // id (an Open Food Facts barcode, a USDA fdcId) — so lower tiers get
            // used less over time. Estimates are never promoted.
            $externalId = is_string($candidate['external_id'] ?? null) ? $candidate['external_id'] : null;
            $foodItemId = $this->promoteToLibrary($terms->display(), $terms->alt(), $externalId, $profile, $source);
        } elseif ($foodItemId !== null) {
            // A library item answered: remember the phrasing that found it as an
            // alias, so the item accrues the ways the model actually names it.
            // Only confirmed phrasings are stored — never a bare recognition.
            $this->recordAliases($foodItemId, $terms);
        }

        // The remaining case — an id was claimed and it is not this person's —
        // logs the portion as a plain snapshot: no link, and nothing promoted
        // either, because numbers attributed to a personal library that is not
        // theirs do not become an item in theirs.

        $entry = MealEntry::fromPortion($profile->forGrams($grams), $terms->display(), $meal, $loggedAt, $foodItemId);
        $entry->save();

        return $entry;
    }

    /**
     * Persist one logged entry from values the user typed themselves — the
     * package-label path. This is the one deliberate exception to "numbers come
     * from the server payload, never the form": here the human *is* the source
     * and is entering the numbers on purpose. They are attributed to
     * {@see NutrientSource::Manual} ("Entered by hand"), never to a database
     * source — so a tampered form can only ever produce an honestly-labelled
     * hand-entered value, never a forged USDA or library number. Verified, it is
     * promoted to the library and resolves from tier 1 next time.
     */
    public function commitManual(
        string $name,
        NutrientProfile $profile,
        float $grams,
        MealType $meal,
        CarbonInterface $loggedAt,
        ?string $barcode = null,
    ): MealEntry {
        // A hand-typed entry carries a single name; there is no second language.
        // A barcode, if the user typed one, becomes the item's stable id — the
        // exact key that makes matching reliable for a product no database knows.
        $foodItemId = $this->promoteToLibrary($name, null, $barcode, $profile, NutrientSource::Manual);

        $entry = MealEntry::fromPortion($profile->forGrams($grams), $name, $meal, $loggedAt, $foodItemId);
        $entry->save();

        return $entry;
    }

    /**
     * @return array<string, mixed>
     */
    private function encodeCandidate(NutrientMatch $match): array
    {
        return [
            'label' => $match->description,
            'source' => $match->source()->value,
            'source_label' => $match->source()->label(),
            'kcal' => $match->profile->kcal,
            'protein' => $match->profile->proteinG,
            'fat' => $match->profile->fatG,
            'carbs' => $match->profile->carbsG,
            'verified' => $match->source()->isVerified(),
            'matched_via' => $match->matchedVia,
            // Open Food Facts thumbnail, shown only on the confirm screen. Never
            // persisted, so the library never hotlinks a third party.
            'image_url' => $match->imageUrl,
            // A personal-library match carries the item id in externalId.
            'food_item_id' => $match->source() === NutrientSource::PersonalLibrary && $match->externalId !== null
                ? (int) $match->externalId
                : null,
            'external_id' => $match->externalId,
        ];
    }

    /**
     * Bring a chosen search candidate into the library and return its id,
     * without logging anything — the recipe builder's need, as against the
     * confirm screen's, which promotes as a side effect of logging an entry.
     *
     * A tier-1 candidate is already a library item: its id is returned as is,
     * and the phrasing that found it is recorded as an alias, exactly as
     * {@see commit()} does. A USDA or Open Food Facts candidate is promoted with
     * its numbers and stable id. An estimate has no honest number and is refused
     * — a recipe ingredient must be a real figure, never the model's guess.
     *
     * The numbers come from the candidate the resolver built server-side and
     * carried through the session, never from the form: the caller passes the
     * candidate array straight from the pending payload.
     *
     * A foreign word the person searched by ({@see $searchedAs}) — Cyrillic, say,
     * when the chosen candidate is a USDA record named in English — is kept as an
     * alias of the item, so the same word finds it in the library next time
     * without a translation. It is carried as its own argument rather than folded
     * into {@see $terms}, because $terms names the item (its English label) and a
     * native term put there would become the item's name instead of an alias.
     *
     * @param  array<string, mixed>  $candidate  one entry from a resolver payload
     * @param  string|null  $searchedAs  a foreign search term to remember as an alias
     *
     * @throws \InvalidArgumentException when the candidate is an estimate
     */
    public function promoteCandidate(array $candidate, SearchTerms $terms, ?string $searchedAs = null): int
    {
        $source = NutrientSource::from((string) $candidate['source']);

        if ($source === NutrientSource::Estimated) {
            throw new \InvalidArgumentException('An estimate cannot become a recipe ingredient.');
        }

        // Already in the library: reuse the id and remember the phrasing.
        $claimedItemId = is_int($candidate['food_item_id'] ?? null) ? $candidate['food_item_id'] : null;
        if ($claimedItemId !== null && FoodItem::query()->whereKey($claimedItemId)->exists()) {
            $this->recordAliases($claimedItemId, $terms);
            $this->recordForeignAlias($claimedItemId, $searchedAs);

            return $claimedItemId;
        }

        $profile = new NutrientProfile(
            kcal: (float) $candidate['kcal'],
            proteinG: (float) $candidate['protein'],
            fatG: (float) $candidate['fat'],
            carbsG: (float) $candidate['carbs'],
            source: $source,
        );

        $externalId = is_string($candidate['external_id'] ?? null) ? $candidate['external_id'] : null;

        $itemId = $this->promoteToLibrary($terms->display(), $terms->alt(), $externalId, $profile, $source);
        $this->recordForeignAlias($itemId, $searchedAs);

        return $itemId;
    }

    /**
     * Remember a foreign word a person searched by as an alias of the item they
     * chose, through the same bounded, deduplicating path a confirmed recognition
     * takes. A null or empty term, or one the item already carries as a name or
     * alias, adds nothing.
     */
    private function recordForeignAlias(int $foodItemId, ?string $searchedAs): void
    {
        if ($searchedAs === null || trim($searchedAs) === '') {
            return;
        }

        $this->recordAliases($foodItemId, new SearchTerms($searchedAs));
    }

    private function promoteToLibrary(string $name, ?string $altName, ?string $externalId, NutrientProfile $profile, NutrientSource $source): int
    {
        $origin = match ($source) {
            NutrientSource::Usda => ProfileOrigin::Usda,
            NutrientSource::OpenFoodFacts => ProfileOrigin::OpenFoodFacts,
            default => ProfileOrigin::Manual,
        };

        $item = FoodItem::create([
            'name' => $name,
            'alt_name' => $altName,
            'external_id' => $externalId,
            'kind' => FoodItemKind::Direct->value,
            'origin' => $origin->value,
            'kcal_per_100g' => $profile->kcal,
            'protein_g_per_100g' => $profile->proteinG,
            'fat_g_per_100g' => $profile->fatG,
            'carbs_g_per_100g' => $profile->carbsG,
        ]);

        return $item->id;
    }

    /** How many learned aliases one item keeps; oldest fall off past this. */
    private const MAX_ALIASES = 10;

    /**
     * Remember each recognised term as an alias of a confirmed library item,
     * unless the item already carries it as a name, alt name or alias (compared
     * case-insensitively). Bounded, so a long tail of phrasings cannot grow
     * without limit. Called only on confirmation — a bad recognition is never
     * recorded, so it can never start pulling in the wrong product.
     */
    private function recordAliases(int $foodItemId, SearchTerms $terms): void
    {
        $item = FoodItem::with('aliases')->find($foodItemId);
        if ($item === null) {
            return;
        }

        $known = [$item->name, $item->alt_name, ...$item->aliases->pluck('name')->all()];
        $known = array_map(
            static fn ($name): string => is_string($name) ? mb_strtolower(trim($name)) : '',
            $known,
        );

        foreach ($terms->all() as $term) {
            if (in_array(mb_strtolower(trim($term)), $known, true)) {
                continue;
            }

            $item->aliases()->create(['name' => $term]);
            $known[] = mb_strtolower(trim($term));
        }

        $this->pruneAliases($item->id);
    }

    private function pruneAliases(int $foodItemId): void
    {
        $keep = FoodItemAlias::query()
            ->where('food_item_id', $foodItemId)
            ->orderByDesc('id')
            ->limit(self::MAX_ALIASES)
            ->pluck('id');

        FoodItemAlias::query()
            ->where('food_item_id', $foodItemId)
            ->whereNotIn('id', $keep)
            ->delete();
    }
}
