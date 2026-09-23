<?php

use App\Http\Middleware\EnsureTeamPermission;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SetTeamUrlDefaults;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->alias([
            'team.permission' => EnsureTeamPermission::class,
        ]);

        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
            SetTeamUrlDefaults::class,
        ]);

        // Laravel's own `ApplicationBuilder::withMiddleware()` registers a
        // default `redirectGuestsTo(fn () => route('login'))` itself,
        // inside the very `afterResolving(HttpKernel::class, ...)`
        // callback that then invokes this closure — immediately before
        // it, in the same call. That default has to be overridden right
        // here, not from a service provider's `boot()`: a provider boots
        // *before* that `afterResolving` callback fires when serving a
        // real HTTP request (so a provider-registered override wins) but
        // *after* it under PHPUnit's test bootstrap, where the kernel is
        // resolved earlier (so the same provider code silently loses
        // there instead, and only in tests). Registering here runs
        // synchronously after Laravel's own default in every context, so
        // it always wins. The `admin` guard has no team and no
        // `dashboard`-route-style fallback of its own, so it needs
        // `admin.login`/`admin.dashboard` explicitly; every other guard's
        // branch reproduces Laravel's own original default exactly
        // (`route('login')` for a guest, `route('dashboard')` — reusing
        // whatever `URL::defaults` the `SetTeamUrlDefaults` middleware
        // above already populated for that user — for the
        // already-authenticated-guest case).
        $middleware->redirectTo(
            guests: fn (Request $request): string => $request->is('admin', 'admin/*') ? route('admin.login') : route('login'),
            users: fn (Request $request): string => $request->is('admin', 'admin/*') ? route('admin.dashboard') : route('dashboard'),
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
