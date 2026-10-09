<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // the demo has no login: its JSON API is called by the page itself
        $middleware->validateCsrfTokens(except: ['api/*']);
        // free/shared hosts terminate HTTPS at a proxy: trust it so links keep https://
        $middleware->trustProxies(at: '*');
        // v2 admin: guests go to its login page
        // client accounts have their own login; everything else is the office's admin
        $middleware->redirectGuestsTo(fn ($r) => $r->routeIs('v2.account*') ? route('v2.account.login') : route('v2.admin.login'));
        $middleware->redirectUsersTo(fn ($r) => $r->routeIs('v2.account*') ? route('v2.account') : route('v2.admin.dashboard'));
        $middleware->alias(['v2.admin' => \App\Http\Middleware\V2\AdminOnly::class]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
