<?php

declare(strict_types=1);

namespace App\Nutrition;

use App\Models\FoodItem;
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
     * Build a pending payload for one food name.
     *
     * @return array{name: string, grams: float, candidates: list<array<string, mixed>>}
     */
    public function pendingForName(string $name, float $grams = 100.0, ?NutrientProfile $estimate = null): array
    {
        $resolution = $this->resolver->resolve($name, $estimate);

        $candidates = [];
        foreach ($resolution->candidates() as $match) {
            $candidates[] = $this->encodeCandidate($match);
        }

        return [
            'name' => $name,
            'grams' => $grams,
            'candidates' => $candidates,
        ];
    }

    /**
     * Build pending payloads for every recognised item in a photo.
     *
     * @param  list<RecognisedItem>  $items
     * @return list<array{name: string, grams: float, candidates: list<array<string, mixed>>}>
     */
    public function pendingForRecognised(array $items): array
    {
        $pending = [];
        foreach ($items as $item) {
            $pending[] = $this->pendingForName($item->name, $item->estimatedGrams, $item->estimatedProfile);
        }

        return $pending;
    }

    /**
     * Persist one logged entry from a server-side candidate the user picked.
     *
     * @param  array<string, mixed>  $candidate  one entry from a pending payload
     */
    public function commit(array $candidate, string $name, float $grams, MealType $meal, CarbonInterface $loggedAt): MealEntry
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

        // Confirming a real (non-estimated) match that is not already in the
        // library promotes it there, so lower tiers get used less over time.
        // Estimates are never promoted — they must never become "verified".
        if ($foodItemId === null && $source !== NutrientSource::Estimated) {
            $foodItemId = $this->promoteToLibrary($name, $profile, $source);
        }

        $entry = MealEntry::fromPortion($profile->forGrams($grams), $name, $meal, $loggedAt, $foodItemId);
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
    ): MealEntry {
        $foodItemId = $this->promoteToLibrary($name, $profile, NutrientSource::Manual);

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
            // A personal-library match carries the item id in externalId.
            'food_item_id' => $match->source() === NutrientSource::PersonalLibrary && $match->externalId !== null
                ? (int) $match->externalId
                : null,
            'external_id' => $match->externalId,
        ];
    }

    private function promoteToLibrary(string $name, NutrientProfile $profile, NutrientSource $source): int
    {
        $origin = match ($source) {
            NutrientSource::Usda => ProfileOrigin::Usda,
            NutrientSource::OpenFoodFacts => ProfileOrigin::OpenFoodFacts,
            default => ProfileOrigin::Manual,
        };

        $item = FoodItem::create([
            'name' => $name,
            'kind' => FoodItemKind::Direct->value,
            'origin' => $origin->value,
            'kcal_per_100g' => $profile->kcal,
            'protein_g_per_100g' => $profile->proteinG,
            'fat_g_per_100g' => $profile->fatG,
            'carbs_g_per_100g' => $profile->carbsG,
        ]);

        return $item->id;
    }
}
