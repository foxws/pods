<?php

declare(strict_types=1);

namespace Domain\Applications\DataObjects;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The command that tells whether an on-demand application has work left, run in one of its containers.
 */
#[TypeScript]
class OnDemandIdleCheckData extends Data
{
    public function __construct(
        public string $container,
        public ?string $user,
        public string $command,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromLpod(array $payload): self
    {
        return new self(
            container: (string) $payload['container'],
            user: $payload['user'] ?? null,
            command: (string) $payload['command'],
        );
    }
}
