<?php

declare(strict_types=1);

namespace Domain\Environments\DataObjects;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Something that happened to a container or image on the host, from podman events.
 */
#[TypeScript]
class HostEventData extends Data
{
    public function __construct(
        public ?string $time,
        public string $type,
        public string $action,
        public string $name,
        public ?string $image,
        public ?string $application,
        public ?int $exit_code,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromLpod(array $payload): self
    {
        return new self(
            time: $payload['time'] ?? null,
            type: (string) $payload['type'],
            action: (string) $payload['action'],
            name: (string) $payload['name'],
            image: $payload['image'] ?? null,
            application: $payload['application'] ?? null,
            exit_code: isset($payload['exit_code']) ? (int) $payload['exit_code'] : null,
        );
    }
}
