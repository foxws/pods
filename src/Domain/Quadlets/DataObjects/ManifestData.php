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
 * A repository's pods.yml. It only tells Pods how to offer and install the Quadlet files
 * next to it: it never changes their contents.
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
        public ?string $description,
        public array $sources,
        public array $secrets,
    ) {}

    /**
     * @throws InvalidManifestException
     */
    public static function fromYaml(string $yaml): self
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

        return new self(
            name: $manifest['name'] ?? null,
            description: $manifest['description'] ?? null,
            sources: array_map(fn (string $name, array $source): ManifestSourceData => new ManifestSourceData(
                name: $name,
                path: trim((string) $source['path'], '/'),
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
            'description' => ['nullable', 'string', 'max:500'],
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
