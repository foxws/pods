<?php

declare(strict_types=1);

namespace Domain\Quadlets\DataObjects;

use Domain\Quadlets\Exceptions\InvalidManifestException;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Quadlet files fetched from a Git repository, before they are installed, and the
 * repository's pods.yml. A pods.yml Pods can't read is reported in $manifest_error
 * instead of failing the fetch: the files can still be installed without it.
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
        public ?ManifestData $manifest,
        public ?string $manifest_error,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromLpod(array $payload): self
    {
        $manifest = null;
        $manifestError = null;

        if (is_string($payload['manifest'] ?? null)) {
            try {
                $manifest = ManifestData::fromYaml($payload['manifest'], (string) ($payload['manifest_path'] ?? ''));
            } catch (InvalidManifestException $exception) {
                $manifestError = $exception->getMessage();
            }
        }

        return new self(
            commit: $payload['commit'] ?? null,
            files: array_values(array_map(fn (array $file): array => [
                'name' => (string) $file['name'],
                'content' => (string) $file['content'],
            ], $payload['files'] ?? [])),
            manifest: $manifest,
            manifest_error: $manifestError,
        );
    }
}
