<?php

declare(strict_types=1);

namespace App\Nutrition;

use App\Models\Recognition;
use App\Nutrition\Exceptions\DailyLimitReachedException;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * How many recognitions one person may ask for in a day.
 *
 * The quota exists to protect one thing: the owner's API key. It is a single
 * shared credential with a single bill and a single rate limit behind it, and
 * every account on the installation spends from it.
 *
 * **A recognition is counted when it is asked for, not when it succeeds.** That
 * is the deliberate choice, and it is the less generous of the two. A call that
 * comes back empty, malformed or refused costs the key exactly what a good one
 * costs — so charging only for successes would leave the key unprotected in
 * precisely the case where something is going wrong and requests are being
 * retried. The cost of the choice is real and falls on the person: a bad
 * afternoon at the provider can eat an allowance that produced nothing. The
 * refusal says which limit was reached and when it lifts, so at least it is
 * legible.
 */
final class RecognitionQuota
{
    public function limit(): int
    {
        $limit = (int) config('nutrition.recognition.daily_limit', 0);

        // A negative setting is nonsense; zero is a deliberate off switch, and
        // is read that way rather than as "no limit". A misread configuration
        // that turns a limit off is the failure this project cannot afford.
        return max(0, $limit);
    }

    /** How many this person has asked for since midnight. */
    public function usedToday(): int
    {
        return Recognition::query()->where('created_at', '>=', $this->midnight())->count();
    }

    public function remainingToday(): int
    {
        return max(0, $this->limit() - $this->usedToday());
    }

    /**
     * Take one from today's allowance, or refuse.
     *
     * One statement. The row is written only if the count the insert takes for
     * itself is under the limit, and the verdict is whether a row appeared —
     * the same shape the invitation code uses, and for the same reason. Asking
     * how many have been used and then writing would leave a gap: two uploads
     * submitted together both read the same count, both find room, and both
     * proceed. SQLite serialises writers, so with the condition inside the
     * insert the second one simply writes nothing.
     *
     * The owner is passed rather than left to the model's `creating` hook,
     * because this does not go through the model. A null there would be caught
     * by the column, which is NOT NULL — nothing gets claimed on nobody's
     * behalf.
     *
     * @throws DailyLimitReachedException
     */
    public function claimOne(): void
    {
        $now = CarbonImmutable::now()->toDateTimeString();

        // `cast(? as integer)` is not decoration. SQLite orders integers before
        // text, so a limit that arrived as a string would make `count(*) < ?`
        // true for every count there will ever be — the guard would be gone and
        // nothing would look wrong. The driver types it correctly today; the
        // cast means it does not have to.
        $claimed = DB::affectingStatement(
            'insert into recognitions (user_id, created_at, updated_at)
             select ?, ?, ?
             where (select count(*) from recognitions where user_id = ? and created_at >= ?)
                   < cast(? as integer)',
            [Auth::id(), $now, $now, Auth::id(), $this->midnight()->toDateTimeString(), $this->limit()],
        );

        if ($claimed !== 1) {
            throw new DailyLimitReachedException($this->limit());
        }
    }

    /**
     * Everybody's recognitions today, as one number.
     *
     * Counted straight from the table rather than through the model, because
     * this is deliberately not a read of anybody's rows: it is the load on the
     * owner's key, and the owner sees a total and nothing else — not who, not
     * what, not when.
     */
    public function everybodysToday(): int
    {
        return DB::table('recognitions')->where('created_at', '>=', $this->midnight())->count();
    }

    private function midnight(): CarbonImmutable
    {
        return CarbonImmutable::now()->startOfDay();
    }
}
