<?php

declare(strict_types=1);

use Foundation\Http\Middleware\RedirectToOwnerSetup;
use Foundation\Http\Middleware\SetCacheHeaders;
use Foxws\Essentials\Csp\Middleware\AddTelescopeCspNonce;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Foundation\Http\Middleware\HandlePrecognitiveRequests;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Http\Middleware\CheckAbilities;
use Laravel\Sanctum\Http\Middleware\CheckForAnyAbility;
use Spatie\Csp\AddCspHeaders;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;
use Spatie\ResponseCache\Middlewares\CacheResponse;
use Spatie\ResponseCache\Middlewares\DoNotCacheResponse;
use Support\Inertia\Middleware\HandleInertiaRequests;

$basePath = $_ENV['APP_BASE_PATH'] ?? dirname(__DIR__);

return Application::configure(basePath: $basePath)
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
        then: function () {
            // Inertia action routes (web middleware for session, CSRF, and Inertia handling)
            Route::middleware('web')
                ->prefix('/actions')
                ->name('actions.')
                ->group(base_path('routes/actions.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Whom to trust is in config/trustedproxy.php. X-Forwarded-Host is left out: a client could choose the host
        $middleware->trustProxies(
            headers: Request::HEADER_X_FORWARDED_FOR |
                Request::HEADER_X_FORWARDED_PORT |
                Request::HEADER_X_FORWARDED_PROTO,
        );

        // A new password signs every other session out
        $middleware->authenticateSessions();

        // Global middleware aliases for convenient usage in routes and controllers
        $middleware->alias([
            'abilities' => CheckAbilities::class,
            'ability' => CheckForAnyAbility::class,
            'cache' => SetCacheHeaders::class,
            'cache.bypass' => DoNotCacheResponse::class,
            'csp.telescope' => AddTelescopeCspNonce::class,
            'precognitive' => HandlePrecognitiveRequests::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
            'role' => RoleMiddleware::class,
        ]);

        // Quadlet files and drop-ins travel to the host byte for byte
        $middleware->trimStrings(except: [
            fn (Request $request): bool => $request->is('actions/environments/*/quadlets', 'actions/environments/*/quadlets/*', 'actions/environments/*/applications/*/drop-ins'),
        ]);

        // Configure web middleware
        $middleware->web(append: [
            AddCspHeaders::class,
            RedirectToOwnerSetup::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        // Send guests to the setup page before "auth" sends them to the login page
        $middleware->prependToPriorityList(
            before: AuthenticatesRequests::class,
            prepend: RedirectToOwnerSetup::class,
        );

        // Configure API middleware
        $middleware->api(append: [
            CacheResponse::class,
        ]);

        // Sanctum middleware for API authentication and rate limiting
        $middleware->statefulApi();
        $middleware->redirectGuestsTo(fn () => route('login'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Secret values are never stored, not even as old input after a failed validation
        $exceptions->dontFlash(['value', 'secrets']);

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })
    ->create();
