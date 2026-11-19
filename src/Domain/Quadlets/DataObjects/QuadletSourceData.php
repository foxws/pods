<?php

declare(strict_types=1);

namespace Domain\Quadlets\DataObjects;

use Domain\Quadlets\Exceptions\InvalidManifestException;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Quadlet files fetched from a Git repository, before they are installed, and the
 * repository's pods.yml. A pods.yml Pods can't read is reported in $manifest_error
 * instead of failing the fetch: the files can still be installed without it. The pods.yml
 * itself ($manifest_yaml) goes back with the install, so Pods can keep it.
 */
#[TypeScript]
class QuadletSourceData extends Data
{
    /**
     * The repository manifest. It's read when fetching, and never installed.
     */
    public const string ManifestFile = 'pods.yml';

    /**
     * The extensions of Quadlet units and of the files they use, as the install page offers them.
     */
    public const array QuadletExtensions = ['container', 'pod', 'volume', 'network', 'kube', 'image', 'build', 'env', 'yaml', 'yml'];

    /**
     * @param  list<array{name: string, content: string}>  $files
     */
    public function __construct(
        public ?string $commit,
        public array $files,
        public ?ManifestData $manifest,
        public ?string $manifest_error,
        public ?string $manifest_yaml,
        public ?string $manifest_path,
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
            manifest_yaml: $manifest !== null ? $payload['manifest'] : null,
            manifest_path: $manifest !== null ? (string) ($payload['manifest_path'] ?? '') : null,
        );
    }

    /**
     * The fetched files to install: the Quadlet units and the files they use, without pods.yml
     * or the repository's other files, such as a README.md.
     *
     * @return list<array{name: string, content: string}>
     */
    public function quadletFiles(): array
    {
        return array_values(array_filter(
            $this->files,
            fn (array $file): bool => $file['name'] !== self::ManifestFile
                && in_array(pathinfo($file['name'], PATHINFO_EXTENSION), self::QuadletExtensions, true),
        ));
    }
}
