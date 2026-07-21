<?php

declare(strict_types=1);

use App\Http\Middleware\EnsureUnlocked;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Trust the platform's TLS-terminating proxy so the app sees requests as
        // HTTPS (Secure session cookie, correct URL scheme).
        //
        // `at: '*'` — trust ANY forwarding proxy — is safe ONLY because on
        // Railway the app cannot be reached except through Railway's own proxy,
        // so a client cannot forge X-Forwarded-For. That header feeds the
        // per-IP unlock throttle: if the app ever became reachable directly
        // (a different host, a misconfigured network), a spoofed X-Forwarded-For
        // would give every request a fresh apparent IP and silently zero out the
        // rate limiter — with no error to notice. Revisit this line on any
        // hosting change; do not carry '*' to somewhere the proxy is not the
        // sole ingress.
        $middleware->trustProxies(at: '*');

        // The access gate wraps every web page; it is a no-op unless a password
        // is configured.
        $middleware->appendToGroup('web', EnsureUnlocked::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
