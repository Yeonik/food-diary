<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * One invitation to create an account.
 *
 * Deliberately not owner-scoped like the diary models: invitations are the
 * owner's administration of who may join, not one person's data among several.
 * Nothing here is reachable from a signed-in person's screens; the owner's
 * screens come next, and they are gated by the owner's own authorisation.
 *
 * @property int $id
 * @property string $token_hash
 * @property int|null $created_by
 * @property int|null $used_by
 * @property CarbonImmutable|null $expires_at
 * @property CarbonImmutable|null $used_at
 */
class Invite extends Model
{
    /** Characters of cryptographic randomness in a code. */
    private const CODE_LENGTH = 32;

    /** @var list<string> */
    protected $fillable = [
        'token_hash',
        'created_by',
        'used_by',
        'expires_at',
        'used_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'immutable_datetime',
            'used_at' => 'immutable_datetime',
        ];
    }

    /**
     * Create an invitation and return its code.
     *
     * **The only moment the code exists.** It is returned here, stored as a
     * digest, and cannot be read back — so whatever calls this has to show it
     * to the person now or lose it. That is the point: a code sitting in a
     * column is a credential waiting to be read by something that is not the
     * person it was meant for.
     */
    public static function issue(?User $by = null, ?CarbonInterface $expiresAt = null): string
    {
        $code = Str::random(self::CODE_LENGTH);

        static::query()->create([
            'token_hash' => self::digestOf($code),
            'created_by' => $by?->id,
            'expires_at' => $expiresAt,
        ]);

        return $code;
    }

    /**
     * Spend a code on a new account. True if this call is the one that spent it.
     *
     * The whole check is the `where` clause of a single update, and the answer
     * is how many rows it changed. Reading the invitation first and writing to
     * it afterwards would leave a gap between the two in which a second
     * registration reads the same unspent row — both see it free, both proceed,
     * and one code makes two accounts. Here the database decides, once: whoever
     * loses the race changes nothing and is told the code is not valid.
     *
     * Unknown, expired and already spent are one answer on purpose. A refusal
     * that told them apart would confirm to somebody feeding in guesses which
     * of them named a real invitation.
     */
    public static function spend(string $code, User $on): bool
    {
        $now = CarbonImmutable::now();

        $spent = static::query()
            ->where('token_hash', self::digestOf($code))
            ->whereNull('used_at')
            ->where(function (QueryBuilder $unexpired) use ($now): void {
                $unexpired->whereNull('expires_at')->orWhere('expires_at', '>', $now);
            })
            ->update([
                'used_at' => $now,
                'used_by' => $on->id,
            ]);

        return $spent === 1;
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function redeemer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'used_by');
    }

    private static function digestOf(string $code): string
    {
        return hash('sha256', trim($code));
    }
}
