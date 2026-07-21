<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Nutrition\NameMatcher;
use PHPUnit\Framework\TestCase;

class NameMatcherTest extends TestCase
{
    private NameMatcher $matcher;

    protected function setUp(): void
    {
        parent::setUp();
        $this->matcher = new NameMatcher;
    }

    public function test_distinguishing_words_are_never_dropped(): void
    {
        // These look like filler but separate products with different calories.
        $tokens = $this->matcher->significantTokens('Шоколад без сахара 0% sugar free лёгкий');

        foreach (['без', 'сахара', '0', 'sugar', 'free', 'лёгкий'] as $kept) {
            $this->assertContains($kept, $tokens, "'{$kept}' must survive tokenisation");
        }
    }

    public function test_only_glue_words_are_dropped(): void
    {
        $tokens = $this->matcher->significantTokens('Чай с молоком and honey');

        $this->assertContains('чай', $tokens);
        $this->assertContains('молоком', $tokens);
        $this->assertContains('honey', $tokens);
        $this->assertNotContains('с', $tokens);
        $this->assertNotContains('and', $tokens);
    }

    public function test_two_phrasings_of_the_same_product_overlap_strongly(): void
    {
        $stored = 'Победа 100% Charged без добавления сахара Stevia';
        $native = 'Конфеты Победа 100% Charged без добавления сахара со стевией';
        $english = 'Pobeda Charged sugar free chocolate wafer candy with stevia';

        $this->assertGreaterThanOrEqual(2, $this->matcher->overlap($stored, $native));
        $this->assertGreaterThanOrEqual(2, $this->matcher->overlap($stored, $english));
    }

    public function test_different_variants_of_one_brand_do_not_overlap_enough(): void
    {
        // Same brand, genuinely different products — they must stay separable, so
        // the shared brand word alone is not enough to pull one in for the other.
        $sugarFree = 'Победа Charged без сахара';
        $dark = 'Победа горький 72%';

        $this->assertLessThan(2, $this->matcher->overlap($sugarFree, $dark));
    }

    public function test_a_short_name_is_contained_in_a_longer_recognised_one(): void
    {
        $this->assertTrue($this->matcher->contains('Молоко 0%', 'Молоко'));
        $this->assertFalse($this->matcher->contains('Молоко 0%', 'Молоко 3.2%'));
    }

    public function test_shared_tokens_are_reported_for_explanation(): void
    {
        $shared = $this->matcher->sharedTokens('Победа Charged Stevia', 'Pobeda Charged with stevia');

        $this->assertContains('charged', $shared);
        $this->assertContains('stevia', $shared);
    }
}
