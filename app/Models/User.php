<?php

declare(strict_types=1);

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;

/**
 * @property bool $is_owner
 * @property Carbon|null $suspended_at
 */
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_owner' => 'boolean',
            'suspended_at' => 'datetime',
        ];
    }

    /**
     * The one person who administers this installation.
     *
     * `is_owner` is deliberately absent from the fillable list above: it is set
     * in exactly one place, where the owner is brought into being from
     * configuration.
     *
     * It is the second lock, not the first. Registration builds its own array of
     * three named keys, so a form field called `is_owner` reaches nothing today
     * — but the day somebody writes `create($input)`, this is what stops the
     * field from meaning anything. Both were checked by taking each away in turn.
     */
    public function isOwner(): bool
    {
        return $this->is_owner === true;
    }

    /**
     * Kept, but not let in.
     *
     * Suspension is reversible and takes nothing away: every record the account
     * owns stays exactly where it is, and lifting it restores access without the
     * person doing anything. It is the answer to "not right now", which deleting
     * an account is not.
     *
     * Like {@see isOwner()} this reads the column rather than deriving the state
     * from anything else, and it is asked on every authenticated request, so
     * suspending somebody who is signed in takes effect on their next one.
     */
    public function isSuspended(): bool
    {
        return $this->suspended_at !== null;
    }
}
