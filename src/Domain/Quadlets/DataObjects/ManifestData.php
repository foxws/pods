<?php

declare(strict_types=1);

namespace Domain\Quadlets\DataObjects;

use Domain\Quadlets\Exceptions\InvalidManifestException;
use Domain\Secrets\DataObjects\SecretData;
use Illuminate\Support\Facades\Validator;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * A pods.yml. It only tells Pods how to describe, offer and install the Quadlet files next
 * to it: it never changes their contents. It sits at a repository's root, or in a directory
 * of its own for each application of a catalog repository; the paths of its sources are
 * relative to that directory, and are kept here relative to the repository's root.
 */
#[TypeScript]
class ManifestData extends Data
{
    public const int Version = 1;

    /**
     * @param  list<ManifestSourceData>  $sources
     * @param  list<ManifestSecretData>  $secrets
     */
    public function __construct(
        public ?string $name,
        public ?string $label,
        public ?string $description,
        public ?string $author,
        public ?string $icon,
        public ?string $category,
        public ?string $website,
        public ?string $repository,
        public ?string $notes,
        public string $directory,
        public array $sources,
        public array $secrets,
    ) {}

    /**
     * @param  string  $directory  where the pods.yml is in its repository, '' for the root
     *
     * @throws InvalidManifestException
     */
    public static function fromYaml(string $yaml, string $directory = ''): self
    {
        try {
            $manifest = Yaml::parse($yaml);
        } catch (ParseException $exception) {
            throw InvalidManifestException::because($exception->getMessage());
        }

        if (! is_array($manifest)) {
            throw InvalidManifestException::because('it has no keys.');
        }

        if (($manifest['version'] ?? self::Version) !== self::Version) {
            throw InvalidManifestException::unsupportedVersion($manifest['version'], self::Version);
        }

        self::ensureIsValid($manifest);

        /** @var array<string, array<string, mixed>> $sources */
        $sources = $manifest['sources'] ?? [];

        /** @var array<string, array<string, mixed>> $secrets */
        $secrets = $manifest['secrets'] ?? [];

        $directory = trim($directory, '/');

        return new self(
            name: $manifest['name'] ?? null,
            label: $manifest['label'] ?? null,
            description: $manifest['description'] ?? null,
            author: $manifest['author'] ?? null,
            icon: $manifest['icon'] ?? null,
            category: $manifest['category'] ?? null,
            website: $manifest['website'] ?? null,
            repository: $manifest['repository'] ?? null,
            notes: $manifest['notes'] ?? null,
            directory: $directory,
            sources: array_map(fn (string $name, array $source): ManifestSourceData => new ManifestSourceData(
                name: $name,
                path: ltrim("{$directory}/".trim((string) $source['path'], '/'), '/'),
                description: $source['description'] ?? null,
            ), array_keys($sources), $sources),
            secrets: array_map(fn (string $name, array $secret): ManifestSecretData => new ManifestSecretData(
                name: $name,
                description: $secret['description'] ?? null,
                format: $secret['format'] ?? 'text',
                generate: (bool) ($secret['generate'] ?? false),
                from: $secret['from'] ?? null,
                key: $secret['key'] ?? null,
            ), array_keys($secrets), $secrets),
        );
    }

    /**
     * @param  array<mixed>  $manifest
     *
     * @throws InvalidManifestException
     */
    protected static function ensureIsValid(array $manifest): void
    {
        $validator = Validator::make($manifest, [
            'name' => ['nullable', 'string', 'regex:/^[A-Za-z0-9][A-Za-z0-9_.-]{0,127}$/'],
            'label' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
            'author' => ['nullable', 'string', 'max:100'],
            'icon' => ['nullable', 'string', 'max:100', 'regex:/^i-[a-z0-9]+(-[a-z0-9]+)+$/'],
            'category' => ['nullable', 'string', 'max:50'],
            'website' => ['nullable', 'string', 'max:2048', 'url:https,http'],
            'repository' => ['nullable', 'string', 'max:2048', 'url:https,http'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'sources' => ['nullable', 'array', 'max:20'],
            'sources.*' => ['array'],
            'sources.*.path' => ['required', 'string', 'max:255', 'regex:#^[A-Za-z0-9._-][A-Za-z0-9._/-]*$#', 'not_regex:#\.\.#'],
            'sources.*.description' => ['nullable', 'string', 'max:500'],
            'secrets' => ['nullable', 'array', 'max:50'],
            'secrets.*' => ['array'],
            'secrets.*.description' => ['nullable', 'string', 'max:500'],
            'secrets.*.format' => ['nullable', 'string', 'in:text,dotenv'],
            'secrets.*.generate' => ['nullable', 'boolean'],
            'secrets.*.from' => ['nullable', 'string', 'required_with:secrets.*.key'],
            'secrets.*.key' => ['nullable', 'string', 'required_with:secrets.*.from', 'regex:/^[A-Za-z_][A-Za-z0-9_.]*$/'],
        ]);

        if ($validator->fails()) {
            throw InvalidManifestException::because($validator->errors()->first());
        }

        foreach (array_keys($manifest['sources'] ?? []) as $name) {
            if (! preg_match('/^[A-Za-z0-9][A-Za-z0-9_.-]{0,63}$/', (string) $name)) {
                throw InvalidManifestException::because("\"{$name}\" isn't a valid source name.");
            }
        }

        $secrets = $manifest['secrets'] ?? [];

        foreach ($secrets as $name => $secret) {
            if (! preg_match(SecretData::NamePattern, (string) $name)) {
                throw InvalidManifestException::because("\"{$name}\" isn't a valid secret name.");
            }

            $from = $secret['from'] ?? null;

            if ($from !== null && ($secrets[$from]['format'] ?? null) !== 'dotenv') {
                throw InvalidManifestException::because("secret \"{$name}\" reads from \"{$from}\", which isn't a secret with format: dotenv.");
            }
        }
    }
}
