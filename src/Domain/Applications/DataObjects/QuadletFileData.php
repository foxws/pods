<?php

declare(strict_types=1);

namespace Domain\Applications\DataObjects;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class QuadletFileData extends Data
{
    public function __construct(
        public string $name,
        public string $path,
        public string $content,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromLpod(array $payload): self
    {
        return new self(
            name: (string) $payload['name'],
            path: (string) $payload['path'],
            content: (string) $payload['content'],
        );
    }
}
