<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Nutrition\SearchTerms;
use PHPUnit\Framework\TestCase;

class SearchTermsTest extends TestCase
{
    public function test_display_prefers_the_native_name_when_present(): void
    {
        $terms = new SearchTerms('Pobeda chocolate', 'Победа');

        $this->assertSame('Победа', $terms->display());
        $this->assertSame('Pobeda chocolate', $terms->alt());
        $this->assertSame(['Pobeda chocolate', 'Победа'], $terms->all());
    }

    public function test_a_single_name_has_no_native_counterpart(): void
    {
        $terms = new SearchTerms('Greek yoghurt');

        $this->assertSame('Greek yoghurt', $terms->display());
        $this->assertNull($terms->alt());
        $this->assertSame(['Greek yoghurt'], $terms->all());
    }

    public function test_a_native_name_equal_to_english_collapses_to_one_term(): void
    {
        // The model sometimes echoes the same string in both fields; it must not
        // become two identical lookups or a pointless alt name.
        $terms = new SearchTerms('Espresso', 'espresso');

        $this->assertSame(['Espresso'], $terms->all());
        $this->assertNull($terms->alt());
    }

    public function test_blank_native_is_ignored(): void
    {
        $terms = new SearchTerms('Bread', '   ');

        $this->assertSame('Bread', $terms->display());
        $this->assertSame(['Bread'], $terms->all());
    }
}
