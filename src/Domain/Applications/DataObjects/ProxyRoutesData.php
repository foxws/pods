<?php

declare(strict_types=1);

namespace Domain\Applications\DataObjects;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Whether the Pods proxy is installed and running on a host, and the routes of its applications.
 */
#[TypeScript]
class ProxyRoutesData extends Data
{
    /**
     * @param  string  $layout  how the proxy takes routes: "sites" (a site per hostname) or "handlers" (inside a wildcard site)
     * @param  string|null  $domain  the wildcard site's domain in the handlers layout
     * @param  list<RouteData>  $routes
     */
    public function __construct(
        public bool $installed,
        public bool $running,
        public string $layout,
        public ?string $domain,
        public array $routes,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromLpod(array $payload): self
    {
        return new self(
            installed: (bool) ($payload['proxy']['installed'] ?? false),
            running: (bool) ($payload['proxy']['running'] ?? false),
            layout: (string) ($payload['proxy']['layout'] ?? 'sites'),
            domain: $payload['proxy']['domain'] ?? null,
            routes: array_values(array_map(RouteData::fromLpod(...), $payload['routes'] ?? [])),
        );
    }

    /**
     * @return list<RouteData>
     */
    public function of(string $application): array
    {
        return array_values(array_filter($this->routes, fn (RouteData $route): bool => $route->application === $application));
    }
}
