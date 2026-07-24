<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Administering invitations: who may join at all.
     *
     * The one ability in the application that is not about a person's own
     * records. It reads the flag rather than an id, a position in a list or a
     * count of accounts — implicit rules of that kind survive until the first
     * restore from backup and then promote somebody nobody chose.
     */
    public const ADMINISTER_INVITES = 'administer-invites';

    /**
     * Administering the accounts that already exist: who stays, and who is let
     * back in.
     *
     * Kept apart from {@see ADMINISTER_INVITES} rather than folded into one
     * "owner" ability, because the two are different powers over different
     * people — one decides who may join, the other reaches into accounts that
     * are already here. They happen to be held by the same person today. A gate
     * named for what it permits keeps saying something true if that ever stops
     * being so; a gate named `administer-invites` guarding a suspend button
     * would not.
     */
    public const ADMINISTER_ACCOUNTS = 'administer-accounts';

    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::define(self::ADMINISTER_INVITES, fn (User $user): bool => $user->isOwner());
        Gate::define(self::ADMINISTER_ACCOUNTS, fn (User $user): bool => $user->isOwner());
    }
}
