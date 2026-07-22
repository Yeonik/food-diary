<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\App;

/**
 * Locale-aware number formatting, hand-rolled so the app needs no ext-intl.
 * Russian writes a decimal comma and groups thousands with a non-breaking
 * space (77,5 kg; 1 240 kcal); English uses a point and a comma. The rules are
 * modest, so a small helper is honest here rather than a whole extension.
 */
final class Format
{
    /** Non-breaking narrow space, so grouped thousands never wrap. */
    private const NBSP = "\u{202F}";

    /** @return array{0: string, 1: string} decimal and thousands separators */
    private static function separators(): array
    {
        return App::getLocale() === 'ru'
            ? [',', self::NBSP]
            : ['.', ','];
    }

    private static function number(float $value, int $decimals): string
    {
        [$decimal, $thousands] = self::separators();

        return number_format($value, $decimals, $decimal, $thousands);
    }

    /** Whole calories. */
    public static function kcal(float $value): string
    {
        return self::number(round($value), 0);
    }

    /** Whole grams for a portion. */
    public static function grams(float $value): string
    {
        return self::number(round($value), 0);
    }

    /** A macronutrient amount, one decimal. */
    public static function macro(float $value): string
    {
        return self::number($value, 1);
    }

    /** A body-weight reading, one decimal (77,5 in ru). */
    public static function weight(float $value): string
    {
        return self::number($value, 1);
    }
}
