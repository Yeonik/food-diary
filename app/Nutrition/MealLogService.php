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

        $foodItemId = is_int($candidate['food_item_id'] ?? null) ? $candidate['food_item_id'] : null;

        if ($foodItemId === null && $source !== NutrientSource::Estimated) {
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
            // A personal-library match carries the item id in externalId.
            'food_item_id' => $match->source() === NutrientSource::PersonalLibrary && $match->externalId !== null
                ? (int) $match->externalId
                : null,
            'external_id' => $match->externalId,
        ];
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
