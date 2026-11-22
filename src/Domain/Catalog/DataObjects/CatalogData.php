<?php

declare(strict_types=1);

namespace Domain\Catalog\DataObjects;

use Domain\Quadlets\DataObjects\ManifestData;
use Domain\Quadlets\Exceptions\InvalidManifestException;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The applications of one or more catalog repositories. An application whose pods.yml Pods
 * can't read is left out and named in $errors, and a repository the host can't read in
 * $failures, so the rest can still be installed.
 */
#[TypeScript]
class CatalogData extends Data
{
    /**
     * @param  list<CatalogApplicationData>  $applications
     * @param  list<string>  $errors
     * @param  list<string>  $failures
     */
    public function __construct(
        public array $applications,
        public array $errors,
        public array $failures = [],
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromLpod(array $payload, string $repository, string $reference): self
    {
        $applications = [];
        $errors = [];

        /** @var list<array{path: string, manifest: string}> $entries */
        $entries = $payload['applications'] ?? [];

        foreach ($entries as $entry) {
            $path = (string) $entry['path'];

            try {
                $manifest = ManifestData::fromYaml((string) $entry['manifest'], $path);
            } catch (InvalidManifestException $exception) {
                $errors[] = mb_scrub("{$path}: {$exception->getMessage()}");

                continue;
            }

            // YAML's !!binary and .inf parse, but would fail the JSON response with every repository in it.
            if (json_encode($manifest->toArray()) === false) {
                $errors[] = "{$path}: pods.yml is ignored: it has values that can't be shown as text.";

                continue;
            }

            $applications[] = new CatalogApplicationData(
                name: $manifest->name ?? $path,
                repository: $repository,
                reference: $reference,
                path: $path,
                manifest: $manifest,
            );
        }

        return new self($applications, $errors);
    }

    /**
     * Names the repository in each error, for a catalog of several.
     */
    public function withSourceInErrors(string $repository): self
    {
        return new self(
            $this->applications,
            array_map(fn (string $error): string => "{$repository}, {$error}", $this->errors),
        );
    }

    public function merge(self $other): self
    {
        return new self(
            [...$this->applications, ...$other->applications],
            [...$this->errors, ...$other->errors],
        );
    }
}
