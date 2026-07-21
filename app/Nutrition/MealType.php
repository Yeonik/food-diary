<?php

declare(strict_types=1);

namespace App\Nutrition;

/**
 * Entries are grouped by meal in the daily view. This is presentation grouping
 * only — there is no judgement attached to eating at any particular time.
 */
enum MealType: string
{
    case Breakfast = 'breakfast';
    case Lunch = 'lunch';
    case Dinner = 'dinner';
    case Snack = 'snack';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
