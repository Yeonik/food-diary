<?php

declare(strict_types=1);

namespace App\Nutrition\Contracts;

/**
 * Turns a food name written in another language into an English term to search
 * USDA with.
 *
 * USDA indexes English, and it is the source that matters most for a recipe's
 * ingredients — raw foods like rice, butter and carrot, which Open Food Facts (a
 * database of packaged products) barely covers. So a Russian ingredient name
 * with no English term reaches USDA as nothing useful. This translates it.
 *
 * Two things are true of every implementation:
 *
 *   - It is best-effort. {@see toEnglish()} returns null whenever there is
 *     nothing to do or nothing can be done — the term is already Latin, or the
 *     translator is unavailable — and the caller carries on with the original
 *     term. Ingredient search NEVER depends on a translation succeeding.
 *
 *   - It only ever changes WHICH candidates are found, never their numbers. A
 *     translation picks the word USDA is searched with; the nutrient figures
 *     still come from USDA's own record for whatever it returns. A wrong
 *     translation yields candidates the person can reject, not a wrong number.
 */
interface IngredientTranslator
{
    /**
     * An English search term for the given name, or null when translation does
     * not apply (already Latin) or could not be done (unavailable, failed).
     */
    public function toEnglish(string $term): ?string;
}
