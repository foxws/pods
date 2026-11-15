<?php

declare(strict_types=1);

namespace Domain\Quadlets\DataObjects;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Quadlet files fetched from a Git repository, before they are installed.
 */
#[TypeScript]
class QuadletSourceData extends Data
{
    /**
     * @param  list<array{name: string, content: string}>  $files
     */
    public function __construct(
        public ?string $commit,
        public array $files,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromLpod(array $payload): self
    {
        return new self(
            commit: $payload['commit'] ?? null,
            files: array_values(array_map(fn (array $file): array => [
                'name' => (string) $file['name'],
                'content' => (string) $file['content'],
            ], $payload['files'] ?? [])),
        );
    }
}
