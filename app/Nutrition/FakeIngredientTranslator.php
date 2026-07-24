<?php

declare(strict_types=1);

namespace App\Nutrition;

use App\Nutrition\Contracts\IngredientTranslator;

/**
 * The translator the test suite runs against, so CI needs no key and makes no
 * call — the same seam the recogniser has.
 *
 * By default it translates nothing (returns null), so every test that does not
 * care about translation behaves exactly as it did before this seam existed. A
 * test that does care seeds a small map with {@see with()}.
 */
final class FakeIngredientTranslator implements IngredientTranslator
{
    /** @var array<string, string> lower-cased term => English */
    private array $map = [];

    /**
     * Seed one translation. Returns $this so seeding chains.
     */
    public function with(string $term, string $english): self
    {
        $this->map[mb_strtolower(trim($term))] = $english;

        return $this;
    }

    public function toEnglish(string $term): ?string
    {
        return $this->map[mb_strtolower(trim($term))] ?? null;
    }
}
