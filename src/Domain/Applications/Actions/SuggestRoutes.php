<?php

declare(strict_types=1);

namespace Domain\Applications\Actions;

use Domain\Applications\DataObjects\ApplicationRoutesData;
use Domain\Applications\DataObjects\ProxyRoutesData;
use Domain\Applications\DataObjects\RouteData;
use Domain\Applications\DataObjects\RouteSuggestionData;
use Domain\Quadlets\DataObjects\ManifestData;
use Domain\Quadlets\DataObjects\ManifestRouteData;

class SuggestRoutes
{
    /**
     * Local names go under home.arpa, the domain RFC 8375 sets aside for home networks.
     */
    public const string Domain = 'home.arpa';

    /**
     * The routes an application has, followed by the ones its pods.yml names that it doesn't
     * have yet. Each gets a suggested hostname: the application's name for the main route
     * (stry.home.arpa), and the role in front of it for the others (websockets.stry.home.arpa).
     */
    public function __invoke(string $application, ProxyRoutesData $proxy, ?ManifestData $manifest = null): ApplicationRoutesData
    {
        $current = collect($proxy->of($application))->keyBy('role');
        $named = collect($manifest->routes ?? [])->keyBy('role');

        $suggestions = array_values($current
            ->map(fn (RouteData $route): RouteSuggestionData => new RouteSuggestionData(
                role: $route->role,
                description: $named->get($route->role)?->description,
                upstream: $named->get($route->role)->upstream ?? $route->upstream,
                main: $route->main,
                host: $route->host,
                suggested_host: self::suggestedHost($application, $route->role, $route->main),
                target: $route->target,
                from_manifest: $named->has($route->role),
            ))
            ->merge($named
                ->reject(fn (ManifestRouteData $route): bool => $current->has($route->role))
                ->map(fn (ManifestRouteData $route): RouteSuggestionData => new RouteSuggestionData(
                    role: $route->role,
                    description: $route->description,
                    upstream: $route->upstream,
                    main: $route->main && $current->isEmpty(),
                    host: null,
                    suggested_host: self::suggestedHost($application, $route->role, $route->main),
                    target: null,
                    from_manifest: true,
                )))
            ->all());

        return new ApplicationRoutesData(
            proxy_installed: $proxy->installed,
            proxy_running: $proxy->running,
            routes: $suggestions,
        );
    }

    public static function suggestedHost(string $application, string $role, bool $main): string
    {
        $name = strtolower((string) preg_replace('/[^A-Za-z0-9-]+/', '-', $application));

        return $main ? "{$name}.".self::Domain : "{$role}.{$name}.".self::Domain;
    }
}
