<?php

declare(strict_types=1);

namespace Domain\Applications\Actions;

use Domain\Applications\DataObjects\ApplicationRoutesData;
use Domain\Applications\DataObjects\ProxyRoutesData;
use Domain\Applications\DataObjects\RouteData;
use Domain\Applications\DataObjects\RouteSuggestionData;
use Domain\Quadlets\DataObjects\ManifestData;
use Domain\Quadlets\DataObjects\ManifestRouteData;
use Foundation\Settings\ProxySettings;

class SuggestRoutes
{
    /**
     * Local names go under home.arpa, the domain RFC 8375 sets aside for home networks.
     */
    public const string Domain = 'home.arpa';

    public function __construct(protected ProxySettings $settings) {}

    /**
     * The routes an application has, followed by the ones its pods.yml names that it doesn't
     * have yet. Each gets a suggested hostname: the application's name for the main route
     * (stry.home.arpa), and the role in front of it for the others (ws.stry.home.arpa), under the
     * domain of the proxy defaults when they have one. Behind a wildcard site, names stay one label
     * under its domain (stry-ws.home.example.com).
     */
    public function __invoke(string $application, ProxyRoutesData $proxy, ?ManifestData $manifest = null): ApplicationRoutesData
    {
        $current = collect($proxy->of($application))->keyBy('role');
        $named = collect($manifest->routes ?? [])->keyBy('role');
        $domain = $this->settings->domain;

        $suggestions = array_values($current
            ->map(fn (RouteData $route): RouteSuggestionData => new RouteSuggestionData(
                role: $route->role,
                description: $named->get($route->role)?->description,
                upstream: $named->get($route->role)->upstream ?? $route->upstream,
                main: $route->main,
                via: $route->via,
                host: $route->host,
                suggested_host: self::suggestedHost($application, $route->role, $route->main, $proxy->domain, $domain),
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
                    via: $route->via,
                    host: null,
                    suggested_host: self::suggestedHost($application, $route->role, $route->main, $proxy->domain, $domain),
                    target: null,
                    from_manifest: true,
                )))
            ->all());

        return new ApplicationRoutesData(
            proxy_installed: $proxy->installed,
            proxy_running: $proxy->running,
            layout: $proxy->layout,
            domain: $proxy->domain,
            default_domain: $domain,
            routes: $suggestions,
        );
    }

    /**
     * @param  string|null  $wildcard  the domain of the proxy's wildcard site, whose certificate only covers one label
     * @param  string|null  $domain  the domain of the proxy defaults, used instead of home.arpa
     */
    public static function suggestedHost(string $application, string $role, bool $main, ?string $wildcard = null, ?string $domain = null): string
    {
        $name = strtolower((string) preg_replace('/[^A-Za-z0-9-]+/', '-', $application));

        if ($wildcard !== null) {
            return $main ? "{$name}.{$wildcard}" : "{$name}-{$role}.{$wildcard}";
        }

        $domain ??= self::Domain;

        return $main ? "{$name}.{$domain}" : "{$role}.{$name}.{$domain}";
    }
}
