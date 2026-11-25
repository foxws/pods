<?php

declare(strict_types=1);

namespace Domain\Applications\DataObjects;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * A hostname the Pods proxy sends to a container of an application. The target is where it
 * really goes: the upstream, or the application's on-demand socket for its main route.
 */
#[TypeScript]
class RouteData extends Data
{
    /**
     * What a route is for, such as app or websockets.
     */
    public const string RolePattern = '/^[a-z][a-z0-9_-]{0,31}$/';

    /**
     * A container and the port it listens on, such as systemd-hello:8000. A host name or IPv4 address works too,
     * such as host.containers.internal:3000 for an application on no network (never the host's own IP), but IPv6 does not.
     */
    public const string UpstreamPattern = '/^[A-Za-z0-9][A-Za-z0-9_.-]{0,127}:\d{1,5}$/';

    /**
     * A hostname, such as hello.home.arpa.
     */
    public const string HostPattern = '/^(?=.{1,253}$)[A-Za-z0-9]([A-Za-z0-9-]{0,61}[A-Za-z0-9])?(\.[A-Za-z0-9]([A-Za-z0-9-]{0,61}[A-Za-z0-9])?)*$/';

    public function __construct(
        public ?string $application,
        public string $role,
        public string $host,
        public string $upstream,
        public bool $main,
        public ?string $via,
        public string $target,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromLpod(array $payload): self
    {
        return new self(
            application: $payload['application'] ?? null,
            role: (string) $payload['role'],
            host: (string) $payload['host'],
            upstream: (string) $payload['upstream'],
            main: (bool) ($payload['main'] ?? false),
            via: $payload['via'] ?? null,
            target: (string) ($payload['target'] ?? $payload['upstream']),
        );
    }

    /**
     * Validated routes as lpod's "APP routes set" takes them, with hostnames in lowercase and one
     * main route: the first that says so, or else the first. The main route can't go via itself.
     *
     * @param  array<array-key, array{role: string, host: string, upstream: string, main?: bool, via?: ?string}>  $routes
     * @return list<array{role: string, host: string, upstream: string, main: bool, via: ?string}>
     */
    public static function forLpod(array $routes): array
    {
        $routes = array_values($routes);
        $main = array_search(true, array_map(fn (array $route): bool => (bool) ($route['main'] ?? false), $routes), true);

        return array_map(fn (array $route, int $index): array => [
            'role' => $route['role'],
            'host' => strtolower($route['host']),
            'upstream' => $route['upstream'],
            'main' => $index === ($main === false ? 0 : $main),
            'via' => $index === ($main === false ? 0 : $main) ? null : ($route['via'] ?? null),
        ], $routes, array_keys($routes));
    }

    /**
     * The validation rules of a list of routes at $key, as lpod's "APP routes set" takes them.
     *
     * @return array<string, list<string>>
     */
    public static function rules(string $key): array
    {
        return [
            $key => ['array', 'max:50'],
            "{$key}.*.role" => ['required', 'string', 'distinct', 'regex:'.self::RolePattern],
            "{$key}.*.host" => ['required', 'string', 'distinct:ignore_case', 'regex:'.self::HostPattern],
            "{$key}.*.upstream" => ['required', 'string', 'regex:'.self::UpstreamPattern],
            "{$key}.*.main" => ['boolean'],
            "{$key}.*.via" => ['nullable', 'string', 'in:main'],
        ];
    }
}
