<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

/**
 * A record that belongs to one person.
 *
 * Right now this only fills the owner in on the way to the database. The other
 * half — constraining every read to the signed-in person — arrives with the
 * global scope in the next commit; the two are separate because writing an
 * owner and refusing to read across owners are separate claims, and each is
 * worth being able to break on its own.
 *
 * Nothing is invented when there is no signed-in person: the attribute stays
 * null and the database refuses the row. A console context that means to write
 * on somebody's behalf has to say whose, which is the loud failure rather than
 * the quiet one.
 */
trait BelongsToUser
{
    public static function bootBelongsToUser(): void
    {
        static::creating(function (Model $model): void {
            if ($model->getAttribute('user_id') === null) {
                $model->setAttribute('user_id', Auth::id());
            }
        });
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
