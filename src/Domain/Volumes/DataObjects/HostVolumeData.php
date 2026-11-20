<?php

declare(strict_types=1);

namespace Domain\Volumes\DataObjects;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * A named, bind, network or anonymous volume on the host.
 */
#[TypeScript]
class HostVolumeData extends Data
{
    /**
     * @param  list<string>  $containers
     * @param  list<string>  $applications
     */
    public function __construct(
        public string $name,
        public ?string $quadlet,
        public string $driver,
        public ?string $mountpoint,
        public ?int $size,
        public string $type,
        public array $containers,
        public array $applications,
    ) {}

    /**
     * Only volumes in Podman's own storage: lpod refuses one backed by a folder or device on the
     * host, or an NFS share, whose archive would be all of it. Bind mounts aren't volumes.
     */
    public function isExportable(): bool
    {
        return in_array($this->type, ['named', 'anonymous'], true);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromLpod(array $payload): self
    {
        return new self(
            name: (string) $payload['name'],
            quadlet: $payload['quadlet'] ?? null,
            driver: (string) ($payload['driver'] ?? 'local'),
            mountpoint: $payload['mountpoint'] ?? null,
            size: isset($payload['size']) ? (int) $payload['size'] : null,
            type: (string) ($payload['type'] ?? 'named'),
            containers: array_values(array_map(strval(...), $payload['containers'] ?? [])),
            applications: array_values(array_map(strval(...), $payload['applications'] ?? [])),
        );
    }
}
