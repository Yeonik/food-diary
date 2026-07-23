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

/**
 * @property bool $is_owner
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
}
