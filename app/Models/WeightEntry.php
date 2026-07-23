<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Carbon\CarbonInterface;
use Database\Factories\WeightEntryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * One weight reading for a day.
 *
 * @property int $id
 * @property CarbonInterface $recorded_on
 * @property float $weight_kg
 */
class WeightEntry extends Model
{
    /** @use HasFactory<WeightEntryFactory> */
    use BelongsToUser, HasFactory;

    protected $fillable = [
        'user_id',
        'recorded_on',
        'weight_kg',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            // Stored as a plain calendar date (no time component), so that a
            // second reading for the same day updates the first rather than
            // colliding on the unique index.
            'recorded_on' => 'date:Y-m-d',
            'weight_kg' => 'float',
        ];
    }
}
