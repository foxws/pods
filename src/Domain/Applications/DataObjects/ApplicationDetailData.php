<?php

declare(strict_types=1);

namespace Domain\Applications\DataObjects;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class ApplicationDetailData extends Data
{
    /**
     * @param  list<VolumeData>  $volumes
     * @param  list<string>  $secrets
     * @param  list<ContainerData>  $items
     */
    public function __construct(
        public ApplicationData $application,
        public ?string $path,
        public array $volumes,
        public array $secrets,
        public array $items,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromLpod(array $payload): self
    {
        return new self(
            application: ApplicationData::fromLpod($payload),
            path: $payload['path'] ?? null,
            volumes: array_map(VolumeData::fromLpod(...), array_values($payload['volumes'] ?? [])),
            secrets: array_values(array_map(strval(...), $payload['secrets'] ?? [])),
            items: array_map(ContainerData::fromLpod(...), array_values($payload['items'] ?? [])),
        );
    }
}
