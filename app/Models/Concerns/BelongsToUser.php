<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

/**
 * A record that belongs to one person: filled in on the way to the database, and
 * invisible to everybody else on the way back.
 *
 * The scope is the mechanism, not a convention. Relying on every controller to
 * remember `where('user_id', ...)` is how applications like this leak — one
 * forgotten clause is enough, and the clause is forgotten in the query nobody
 * reviewed. Here the constraint is on the model, so a query has to work at
 * removing it.
 *
 * It also does more than hide rows. Route model binding resolves `{entry}` and
 * `{item}` through this same scope, so another person's id and an id that never
 * existed are indistinguishable — both simply do not resolve. An error that told
 * the two apart would be an existence oracle, which is why the isolation tests
 * assert that the two answers are identical rather than merely both refusals.
 *
 * **No signed-in person means nobody, not everybody.** An unscoped read from a
 * console context comes back empty rather than handing over the whole table, so
 * the mistake surfaces as missing data — loud, and safe — instead of as a leak.
 * Reading on somebody's behalf outside a request is done by naming them:
 * {@see static::ownedBy()}.
 */
trait BelongsToUser
{
    /** The scope's name, so removing it has to be spelled out. */
    public const OWNER_SCOPE = 'owner';

    public static function bootBelongsToUser(): void
    {
        static::addGlobalScope(self::OWNER_SCOPE, function (Builder $query): void {
            $userId = Auth::id();

            if ($userId === null) {
                // Deliberately unsatisfiable. `where user_id = null` would also
                // match nothing, but by SQL's null semantics rather than by
                // intent, and the next person to read it would wonder.
                $query->whereRaw('1 = 0');

                return;
            }

            $query->where($query->getModel()->qualifyColumn('user_id'), $userId);
        });

        static::creating(function (Model $model): void {
            if ($model->getAttribute('user_id') === null) {
                $model->setAttribute('user_id', Auth::id());
            }
        });
    }

    /**
     * Read as a named person, for the console and the seeders — the places with
     * no session to read from.
     *
     * It takes a person rather than simply lifting the scope, so the escape
     * hatch cannot be used to read across everybody by accident. Eloquent's own
     * `withoutGlobalScope()` still exists and always will; this is the sanctioned
     * path, not a wall.
     *
     * @return Builder<static>
     */
    public static function ownedBy(User|int $user): Builder
    {
        $query = static::query()->withoutGlobalScope(self::OWNER_SCOPE);

        return $query->where(
            $query->getModel()->qualifyColumn('user_id'),
            $user instanceof User ? $user->id : $user,
        );
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
