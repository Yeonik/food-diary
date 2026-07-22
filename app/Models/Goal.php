<?php

declare(strict_types=1);

namespace App\Models;

use App\Nutrition\MealType;
use Database\Factories\GoalFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * The daily target. Every field is optional. "Remaining" is target minus what
 * has been logged — a plain number, never a verdict — and is only meaningful
 * for the fields that have a target at all.
 *
 * This single row doubles as the settings record: it also holds which meals are
 * shown on the Day screen (display-only, see {@see showsMeal()}).
 *
 * @property int $id
 * @property float|null $daily_kcal
 * @property float|null $protein_g
 * @property float|null $fat_g
 * @property float|null $carbs_g
 * @property bool $show_breakfast
 * @property bool $show_lunch
 * @property bool $show_dinner
 * @property bool $show_snack
 */
class Goal extends Model
{
    /** @use HasFactory<GoalFactory> */
    use HasFactory;

    protected $fillable = [
        'daily_kcal',
        'protein_g',
        'fat_g',
        'carbs_g',
        'show_breakfast',
        'show_lunch',
        'show_dinner',
        'show_snack',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'daily_kcal' => 'float',
            'protein_g' => 'float',
            'fat_g' => 'float',
            'carbs_g' => 'float',
            'show_breakfast' => 'boolean',
            'show_lunch' => 'boolean',
            'show_dinner' => 'boolean',
            'show_snack' => 'boolean',
        ];
    }

    /**
     * Whether a meal is shown on the Day screen. Hiding a meal is presentation
     * only — it never removes entries, which still count towards the totals.
     */
    public function showsMeal(MealType $meal): bool
    {
        return (bool) $this->getAttribute('show_'.$meal->value);
    }

    /**
     * Calories remaining against the target, or null when no kcal target is set
     * (in which case the interface shows no "remaining" at all). The result may
     * be negative — that is just a number, presented without judgement.
     */
    public function remainingKcal(float $loggedKcal): ?float
    {
        if ($this->daily_kcal === null) {
            return null;
        }

        return $this->daily_kcal - $loggedKcal;
    }
}
