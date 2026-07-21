<?php

declare(strict_types=1);

namespace App\Models;

use App\Nutrition\MealType;
use App\Nutrition\NutrientSource;
use App\Nutrition\PortionTotals;
use Carbon\CarbonInterface;
use Database\Factories\MealEntryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A logged meal, stored as an immutable snapshot. The nutrient columns are the
 * absolute totals frozen at the moment of logging; nothing that happens to the
 * source item afterwards may change them.
 *
 * @property int $id
 * @property CarbonInterface $logged_at
 * @property MealType $meal
 * @property string $name
 * @property float $grams
 * @property float $kcal
 * @property float $protein_g
 * @property float $fat_g
 * @property float $carbs_g
 * @property NutrientSource $source
 * @property int|null $food_item_id
 */
class MealEntry extends Model
{
    /** @use HasFactory<MealEntryFactory> */
    use HasFactory;

    protected $fillable = [
        'logged_at',
        'meal',
        'name',
        'grams',
        'kcal',
        'protein_g',
        'fat_g',
        'carbs_g',
        'source',
        'food_item_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'logged_at' => 'datetime',
            'meal' => MealType::class,
            'source' => NutrientSource::class,
            'grams' => 'float',
            'kcal' => 'float',
            'protein_g' => 'float',
            'fat_g' => 'float',
            'carbs_g' => 'float',
        ];
    }

    /**
     * Build (but do not persist) an entry from a computed portion. The totals
     * are copied in as a snapshot — the caller may keep a `food_item_id` for
     * provenance, but the numbers here never track it afterwards.
     */
    public static function fromPortion(
        PortionTotals $portion,
        string $name,
        MealType $meal,
        CarbonInterface $loggedAt,
        ?int $foodItemId = null,
    ): self {
        return new self([
            'logged_at' => $loggedAt,
            'meal' => $meal->value,
            'name' => $name,
            'grams' => $portion->grams,
            'kcal' => $portion->kcal,
            'protein_g' => $portion->proteinG,
            'fat_g' => $portion->fatG,
            'carbs_g' => $portion->carbsG,
            'source' => $portion->source->value,
            'food_item_id' => $foodItemId,
        ]);
    }

    public function isVerified(): bool
    {
        return $this->source->isVerified();
    }

    /**
     * @return BelongsTo<FoodItem, $this>
     */
    public function foodItem(): BelongsTo
    {
        return $this->belongsTo(FoodItem::class);
    }
}
