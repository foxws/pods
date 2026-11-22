<?php

declare(strict_types=1);

namespace Modules\Web\Quadlets\Requests;

use Domain\Applications\DataObjects\RouteData;
use Domain\Quadlets\DataObjects\ManifestData;
use Domain\Quadlets\Exceptions\InvalidManifestException;
use Dotenv\Dotenv;
use Dotenv\Exception\InvalidFileException;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Validator;
use Modules\Web\Secrets\Requests\StoreSecretRequest;

class InstallQuadletsRequest extends QuadletFilesRequest
{
    /**
     * @var array<string, array<string, string|null>>
     */
    protected array $dotenvs = [];

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'application' => ['required', 'string', 'regex:/^[A-Za-z0-9][A-Za-z0-9_.-]{0,127}$/'],
            'secrets' => ['array', 'max:20'],
            'secrets.*.name' => ['required', 'string', 'distinct', 'regex:'.StoreSecretRequest::NamePattern],
            'secrets.*.driver' => ['required', 'string', 'in:file,pass,shell'],
            'secrets.*.value' => ['required', 'string', 'max:524288'],
            'derived_secrets' => ['array', 'max:20'],
            'derived_secrets.*.name' => ['required', 'string', 'distinct', 'regex:'.StoreSecretRequest::NamePattern],
            'derived_secrets.*.from' => ['required', 'string', 'regex:'.StoreSecretRequest::NamePattern],
            'derived_secrets.*.key' => ['required', 'string', 'regex:/^[A-Za-z_][A-Za-z0-9_.]*$/'],
            'start' => ['boolean'],
            'replace' => ['boolean'],
            'edit' => ['boolean'],
            'restart' => ['boolean', 'exclude_unless:edit,true'],
            'source' => ['nullable', 'array', 'prohibited_if_accepted:edit'],
            'source.repository' => FetchQuadletsRequest::repositoryRules('required_with:source'),
            'source.reference' => ['required_with:source', 'string', 'max:255'],
            'source.path' => ['nullable', 'string', 'max:1024'],
            'source.commit' => ['nullable', 'string', 'regex:/^[0-9a-f]{4,64}$/'],
            'source.manifest' => ['nullable', 'string', 'max:65536'],
            'source.manifest_path' => ['nullable', 'string', 'max:1024'],
            ...RouteData::rules('routes'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            ...parent::messages(),
            ...FetchQuadletsRequest::repositoryMessages('source.repository'),
        ];
    }

    /**
     * A derived secret takes its value from a key of another submitted secret, read as a .env file
     * the way Laravel reads one. Errors name the key and the secret, never a value.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $manifest = $this->input('source.manifest');

                if (is_string($manifest)) {
                    try {
                        ManifestData::fromYaml($manifest, (string) $this->input('source.manifest_path', ''));
                    } catch (InvalidManifestException $exception) {
                        $validator->errors()->add('source.manifest', $exception->getMessage());
                    }
                }

                $names = array_column($this->input('secrets', []), 'name');

                foreach ($this->input('derived_secrets', []) as $index => $secret) {
                    if (in_array($secret['name'], $names, true)) {
                        $validator->errors()->add("derived_secrets.{$index}.name", __(':name has a value already.', ['name' => $secret['name']]));

                        continue;
                    }

                    $values = $this->dotenv($secret['from']);

                    if ($values === null) {
                        $validator->errors()->add("derived_secrets.{$index}.from", __(':from must be one of the secrets you enter, and a valid .env file.', ['from' => $secret['from']]));

                        continue;
                    }

                    if (! isset($values[$secret['key']]) || $values[$secret['key']] === '') {
                        $validator->errors()->add("derived_secrets.{$index}.key", __(':key isn\'t set in :from, so :name can\'t be filled in from it.', [
                            'key' => $secret['key'],
                            'from' => $secret['from'],
                            'name' => $secret['name'],
                        ]));
                    }
                }
            },
        ];
    }

    /**
     * The secrets to create: the ones entered, then the ones filled in from a .env file.
     *
     * @return list<array{name: string, driver: string, value: string}>
     */
    public function secrets(): array
    {
        /** @var list<array{name: string, driver: string, value: string}> $secrets */
        $secrets = array_values($this->validated('secrets', []));

        /** @var list<array{name: string, from: string, key: string}> $derived */
        $derived = array_values($this->validated('derived_secrets', []));

        foreach ($derived as $secret) {
            $value = ($this->dotenv($secret['from']) ?? [])[$secret['key']] ?? null;

            if ($value !== null) {
                $secrets[] = ['name' => $secret['name'], 'driver' => 'file', 'value' => $value];
            }
        }

        return $secrets;
    }

    /**
     * The hostnames to route to the application through the Pods proxy once it's installed.
     *
     * @return list<array{role: string, host: string, upstream: string, main: bool, via: ?string}>
     */
    public function routes(): array
    {
        /** @var array<array-key, array{role: string, host: string, upstream: string, main?: bool, via?: ?string}> $routes */
        $routes = $this->array('routes');

        return RouteData::forLpod($routes);
    }

    /**
     * Where the files were fetched from, when they came from a Git repository.
     *
     * @return array{repository: string, reference: string, path: string, commit: ?string, manifest: ?string, manifest_path: ?string}|null
     */
    public function source(): ?array
    {
        $source = $this->validated('source');

        if (! is_array($source)) {
            return null;
        }

        return [
            'repository' => (string) $source['repository'],
            'reference' => (string) $source['reference'],
            'path' => trim((string) ($source['path'] ?? ''), '/'),
            'commit' => $source['commit'] ?? null,
            'manifest' => $source['manifest'] ?? null,
            'manifest_path' => $source['manifest_path'] ?? null,
        ];
    }

    /**
     * @return array<string, string|null>|null
     */
    protected function dotenv(string $name): ?array
    {
        if (array_key_exists($name, $this->dotenvs)) {
            return $this->dotenvs[$name];
        }

        foreach ($this->array('secrets') as $secret) {
            if (! is_array($secret) || ($secret['name'] ?? null) !== $name || ! is_string($secret['value'] ?? null)) {
                continue;
            }

            try {
                return $this->dotenvs[$name] = Dotenv::parse($secret['value']);
            } catch (InvalidFileException) {
                return null;
            }
        }

        return null;
    }
}
