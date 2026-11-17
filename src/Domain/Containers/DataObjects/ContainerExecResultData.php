<?php

declare(strict_types=1);

namespace Domain\Containers\DataObjects;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * What a command run in a container printed, and its exit code. lpod keeps the last 64 KiB of each output.
 */
#[TypeScript]
class ContainerExecResultData extends Data
{
    public function __construct(
        public int $exit_code,
        public string $stdout,
        public string $stderr,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromLpod(array $payload): self
    {
        return new self(
            exit_code: (int) ($payload['exit_code'] ?? 1),
            stdout: (string) ($payload['stdout'] ?? ''),
            stderr: (string) ($payload['stderr'] ?? ''),
        );
    }

    public function successful(): bool
    {
        return $this->exit_code === 0;
    }
}
