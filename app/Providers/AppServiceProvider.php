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
    }
}
