<?php

declare(strict_types=1);

namespace App\Nutrition;

use App\Models\Recognition;
use App\Nutrition\Exceptions\DailyLimitReachedException;
use Carbon\CarbonImmutable;
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
     * The check and the write are two statements, which is a race — two uploads
     * submitted together could both read the same count and both proceed. It is
     * left as one, knowingly: the worst case is a single call over the limit,
     * which the next second corrects. That is a different kind of thing from an
     * invitation, where two winners would mean an account that should not exist
     * and the write is conditional for exactly that reason. This is a rate, not
     * a permission.
     *
     * @throws DailyLimitReachedException
     */
    public function claimOne(): void
    {
        if ($this->usedToday() >= $this->limit()) {
            throw new DailyLimitReachedException($this->limit());
        }

        // The owner comes from the signed-in person, by the model's own rule.
        Recognition::query()->create([]);
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
