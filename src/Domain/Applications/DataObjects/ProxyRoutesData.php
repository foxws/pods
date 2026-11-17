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
     * @param  list<RouteData>  $routes
     */
    public function __construct(
        public bool $installed,
        public bool $running,
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
