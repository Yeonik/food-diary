<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\GoalFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * The daily target. Every field is optional. "Remaining" is target minus what
 * has been logged — a plain number, never a verdict — and is only meaningful
 * for the fields that have a target at all.
 *
 * @property int $id
 * @property float|null $daily_kcal
 * @property float|null $protein_g
 * @property float|null $fat_g
 * @property float|null $carbs_g
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
        ];
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
