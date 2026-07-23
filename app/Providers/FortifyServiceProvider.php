<?php

declare(strict_types=1);

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\UpdateUserPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Fortify\Fortify;

/**
 * Wires Fortify to this application: our two actions, and our views.
 *
 * Fortify owns the credential check, the session, the throttle and the CSRF
 * handling. What is left here is the parts that are genuinely ours — which
 * screens to render and what a new user must satisfy.
 */
class FortifyServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Fortify::createUsersUsing(CreateNewUser::class);
        Fortify::updateUserPasswordsUsing(UpdateUserPassword::class);

        Fortify::loginView(fn () => view('auth.login'));
        Fortify::registerView(fn () => view('auth.register'));

        // Five attempts a minute per email-and-IP pair. Keyed on both, so one
        // person guessing at one address cannot lock out everyone behind a
        // shared address, and a spray across many addresses from one place is
        // still limited by the IP half.
        RateLimiter::for('login', function (Request $request): Limit {
            $email = Str::lower((string) $request->input(Fortify::username(), ''));

            return Limit::perMinute(5)->by($email.'|'.$request->ip());
        });
    }
}
