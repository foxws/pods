<?php

declare(strict_types=1);

namespace Domain\Shared\DataObjects;

use Spatie\LaravelData\Data;

class PruneResultData extends Data
{
    /**
     * @param  list<string>  $removed
     */
    public function __construct(
        public array $removed,
        public ?int $reclaimed,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromLpod(array $payload): self
    {
        return new self(
            removed: array_values(array_map(strval(...), $payload['removed'] ?? [])),
            reclaimed: isset($payload['reclaimed']) ? (int) $payload['reclaimed'] : null,
        );
    }
}
