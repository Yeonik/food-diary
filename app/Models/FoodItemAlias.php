<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A name a {@see FoodItem} has also been recognised by. Accumulated only when the
 * user confirms a recognised dish against the item, so lookups match the ways the
 * model actually phrases the product without a bad guess ever being recorded.
 *
 * @property int $id
 * @property int $food_item_id
 * @property string $name
 */
class FoodItemAlias extends Model
{
    use BelongsToUser;

    protected $fillable = [
        'user_id',
        'food_item_id',
        'name',
    ];

    /**
     * @return BelongsTo<FoodItem, $this>
     */
    public function foodItem(): BelongsTo
    {
        return $this->belongsTo(FoodItem::class);
    }
}
