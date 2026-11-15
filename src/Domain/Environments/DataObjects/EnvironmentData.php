<?php

declare(strict_types=1);

namespace Domain\Environments\DataObjects;

use Domain\Environments\Models\Environment;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class EnvironmentData extends Data
{
    public function __construct(
        public string $id,
        public string $name,
        public string $host,
        public int $port,
        public string $user,
        public bool $is_local,
        public ?string $scope,
        public ?string $podman,
        public ?string $lpod,
        public ?string $hostname,
        public ?string $last_seen_at,
    ) {}

    public static function fromModel(Environment $environment): self
    {
        return new self(
            id: (string) $environment->getRouteKey(),
            name: $environment->name,
            host: $environment->host,
            port: $environment->port,
            user: $environment->user,
            is_local: $environment->is_local,
            scope: $environment->facts['scope'] ?? null,
            podman: $environment->facts['podman'] ?? null,
            lpod: $environment->facts['lpod'] ?? null,
            hostname: $environment->facts['hostname'] ?? null,
            last_seen_at: $environment->last_seen_at?->toDateTimeString(),
        );
    }
}
