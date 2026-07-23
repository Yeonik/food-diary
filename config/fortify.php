<?php

declare(strict_types=1);

use Laravel\Fortify\Features;

/*
| Authentication is Laravel Fortify, not hand-written. The diary now holds other
| people's food logs on a server, and "it is a personal project" stopped being an
| answer for the part of the system that decides who you are.
|
| Only the config is published. Fortify also offers `fortify-support` (action
| stubs) and `fortify-migrations`; the actions are written by hand in
| app/Actions/Fortify because two of the six stubs are for features we do not
| have, and the migrations are deliberately NOT published — see the features note
| below.
*/

return [

    'guard' => 'web',

    'passwords' => 'users',

    // Sign in by email address. Lowercased on the way in, because SQLite string
    // comparison is case-sensitive and nobody types their address twice the same.
    'username' => 'email',
    'email' => 'email',
    'lowercase_usernames' => true,

    // Where a successful sign-in lands: today's diary, which is the whole app.
    'home' => '/',

    'prefix' => '',
    'domain' => null,
    'middleware' => ['web'],

    /*
    | Five attempts a minute per email-and-IP pair, defined in
    | App\Providers\FortifyServiceProvider. The two-factor and passkey limiters
    | the stub configures are not listed: those features are off, so naming a
    | limiter for them would describe a route that does not exist.
    */

    'limiters' => [
        'login' => 'login',
    ],

    // Fortify registers the GET routes and asks us for the views. The views are
    // ours, from the design kit's .auth block.
    'views' => true,

    /*
    |--------------------------------------------------------------------------
    | Features
    |--------------------------------------------------------------------------
    |
    | What is on, and — more usefully — what is off and why.
    |
    | ON
    |   Sign in and sign out are not features; Fortify always registers them.
    |   registration     — invite-only. The invite check lives in
    |                      App\Actions\Fortify\CreateNewUser, not in a route
    |                      guard, so there is no way to reach the creation of a
    |                      user that skips it.
    |   updatePasswords  — a signed-in person can change their own password.
    |
    | OFF
    |   resetPasswords   — a reset link needs deliverable mail, and this instance
    |                      has none configured. Enabling the feature would
    |                      register /forgot-password and hand out links that go
    |                      to a log file, which is worse than not offering it.
    |                      Until mail exists, the owner resets a password with an
    |                      artisan command; the README says so plainly.
    |   emailVerification — same reason, and nothing here depends on a verified
    |                      address.
    |   updateProfileInformation — nothing to edit yet; the account has an email
    |                      and a password and no profile.
    |   twoFactorAuthentication — not offered, so its columns are not wanted in
    |                      the schema either.
    |   passkeys         — not offered. Note that laravel/passkeys is installed
    |                      regardless: Fortify 1.37 requires it, so it arrives
    |                      whether or not we use it. It is dormant by decision,
    |                      not by accident — with the feature off, Fortify
    |                      registers none of its routes, and because
    |                      `fortify-migrations` is never published, neither the
    |                      passkey table nor the two-factor columns exist. The
    |                      package sits in vendor/ and nothing calls it.
    |
    | Publishing the migrations for features that are off would put dead tables
    | in the schema that nobody would later dare to drop.
    */

    'features' => [
        Features::registration(),
        Features::updatePasswords(),
    ],

];
