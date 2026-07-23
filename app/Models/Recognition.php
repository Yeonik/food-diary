<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * A recognition that was asked for: who, and when.
 *
 * Counting these for today is the whole of the daily quota. Nothing about the
 * photo or the result is kept — see the migration for why.
 *
 * @property int $id
 * @property int $user_id
 * @property CarbonImmutable $created_at
 */
class Recognition extends Model
{
    use BelongsToUser;

    /** @var list<string> */
    protected $fillable = ['user_id'];
}
