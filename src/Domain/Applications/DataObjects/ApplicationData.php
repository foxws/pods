<?php

declare(strict_types=1);

namespace Domain\Applications\DataObjects;

use Domain\Applications\Enums\UnitStatus;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class ApplicationData extends Data
{
    /**
     * @param  list<string>  $ports
     */
    public function __construct(
        public string $name,
        public string $unit,
        public string $kind,
        public UnitStatus $status,
        public ?string $result,
        public ?string $since,
        public ?string $image,
        public int $pods,
        public int $containers,
        public array $ports,
        public ?int $memory,
        public ?string $auto_update,
        public ?string $description,
        public ?string $url,
        public ?string $error,
    ) {}

    /**
     * The TCP ports it publishes on the host: 8080 of 0.0.0.0:8080->80/tcp, and each of a range such as 0.0.0.0:8000-8002->8000-8002/tcp.
     *
     * @return list<int>
     */
    public function hostPorts(): array
    {
        $ports = [];

        foreach ($this->ports as $published) {
            if (preg_match('/:(\d+)(?:-(\d+))?->[\d-]+(?:\/tcp)?$/', $published, $matches) !== 1) {
                continue;
            }

            $first = (int) $matches[1];
            $last = isset($matches[2]) ? (int) $matches[2] : $first;

            array_push($ports, ...range($first, max($first, $last)));
        }

        return $ports;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromLpod(array $payload): self
    {
        /** @var array<string, string> $labels */
        $labels = $payload['labels'] ?? [];

        return new self(
            name: (string) $payload['name'],
            unit: (string) $payload['unit'],
            kind: (string) ($payload['kind'] ?? 'container'),
            status: UnitStatus::fromLpod($payload['status'] ?? null),
            result: $payload['result'] ?? null,
            since: $payload['since'] ?? null,
            image: $payload['image'] ?? null,
            pods: (int) ($payload['pods'] ?? 0),
            containers: (int) ($payload['containers'] ?? 0),
            ports: array_values(array_map(strval(...), $payload['ports'] ?? [])),
            memory: isset($payload['memory']) ? (int) $payload['memory'] : null,
            auto_update: $payload['auto_update'] ?? null,
            description: $labels['io.foxws.pods.description'] ?? null,
            url: $labels['io.foxws.pods.url'] ?? null,
            error: $payload['error'] ?? null,
        );
    }
}
