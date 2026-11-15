<?php

declare(strict_types=1);

namespace Domain\Applications\DataObjects;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class JournalEntryData extends Data
{
    public function __construct(
        public string $time,
        public string $unit,
        public int $priority,
        public string $message,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromLpod(array $payload): self
    {
        return new self(
            time: (string) $payload['time'],
            unit: (string) $payload['unit'],
            priority: (int) ($payload['priority'] ?? 6),
            message: (string) $payload['message'],
        );
    }
}
