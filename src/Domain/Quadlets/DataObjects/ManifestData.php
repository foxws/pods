<?php

declare(strict_types=1);

namespace Domain\Quadlets\DataObjects;

use Domain\Applications\DataObjects\OnDemandSettingsData;
use Domain\Applications\DataObjects\RouteData;
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
     * @param  list<ManifestSetupStepData>  $after_install  the steps to run once after installing it
     * @param  list<ManifestSetupStepData>  $after_update  the steps to run after each update, such as migrating the database
     * @param  list<ManifestRouteData>  $routes  the upstreams the Pods proxy can give a hostname; the first is the main one unless another says main
     * @param  OnDemandSettingsData|null  $ondemand  what to fill the on-demand switch in with; its keys match lpod's ondemand status
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
        public array $after_install,
        public array $after_update,
        public ?OnDemandSettingsData $ondemand,
        public array $routes,
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
            after_install: self::setupSteps($manifest['after_install'] ?? []),
            after_update: self::setupSteps($manifest['after_update'] ?? []),
            ondemand: is_array($manifest['ondemand'] ?? null) ? OnDemandSettingsData::fromLpod($manifest['ondemand']) : null,
            routes: self::routes($manifest['routes'] ?? []),
        );
    }

    /**
     * @param  array<string, array<string, mixed>>  $routes  checked by ensureIsValid()
     * @return list<ManifestRouteData>
     */
    protected static function routes(array $routes): array
    {
        $chosen = in_array(true, array_map(fn (array $route): bool => ($route['main'] ?? false) === true, $routes), true);
        $parsed = [];

        foreach ($routes as $role => $route) {
            $isMain = $chosen ? ($route['main'] ?? false) === true : $parsed === [];

            $parsed[] = new ManifestRouteData(
                role: $role,
                upstream: (string) $route['upstream'],
                description: $route['description'] ?? null,
                main: $isMain,
                via: $isMain ? null : ($route['via'] ?? null),
            );
        }

        return $parsed;
    }

    /**
     * The setup step with this name, after installing or after an update.
     */
    public function setupStep(string $name): ?ManifestSetupStepData
    {
        foreach ([...$this->after_install, ...$this->after_update] as $step) {
            if ($step->name === $name) {
                return $step;
            }
        }

        return null;
    }

    /**
     * @param  array<string, array<string, mixed>>  $steps  checked by ensureIsValid()
     * @return list<ManifestSetupStepData>
     */
    protected static function setupSteps(array $steps): array
    {
        return array_map(fn (string $name, array $step): ManifestSetupStepData => new ManifestSetupStepData(
            name: $name,
            label: $step['label'] ?? $name,
            description: $step['description'] ?? null,
            container: $step['container'],
            user: $step['user'] ?? null,
            workdir: $step['workdir'] ?? null,
            command: $step['command'],
            inputs: self::setupInputs($step['inputs'] ?? []),
        ), array_keys($steps), $steps);
    }

    /**
     * Validation rules for a list of setup steps.
     *
     * @return array<string, list<string>>
     */
    protected static function setupStepRules(string $key): array
    {
        return [
            $key => ['nullable', 'array', 'max:20'],
            "{$key}.*" => ['array'],
            "{$key}.*.label" => ['nullable', 'string', 'max:100'],
            "{$key}.*.description" => ['nullable', 'string', 'max:500'],
            "{$key}.*.container" => ['required', 'string', 'regex:/^[A-Za-z0-9][A-Za-z0-9_.-]{0,127}$/'],
            "{$key}.*.user" => ['nullable', 'string', 'max:64', 'regex:/^[A-Za-z0-9_][A-Za-z0-9_.:-]*$/'],
            "{$key}.*.workdir" => ['nullable', 'string', 'max:255', 'regex:#^/[^\r\n]*$#'],
            "{$key}.*.command" => ['required', 'string', 'max:500', 'not_regex:/[\r\n]/'],
            "{$key}.*.inputs" => ['nullable', 'array', 'max:10'],
            "{$key}.*.inputs.*" => ['array'],
            "{$key}.*.inputs.*.label" => ['nullable', 'string', 'max:100'],
            "{$key}.*.inputs.*.description" => ['nullable', 'string', 'max:500'],
            "{$key}.*.inputs.*.type" => ['nullable', 'string', 'in:text,email,password'],
            "{$key}.*.inputs.*.option" => ['nullable', 'string', 'regex:/^--[A-Za-z0-9][A-Za-z0-9_-]*$/'],
        ];
    }

    /**
     * @param  array<string, array<string, mixed>>  $inputs  checked by ensureStepIsValid()
     * @return list<ManifestSetupInputData>
     */
    protected static function setupInputs(array $inputs): array
    {
        return array_map(fn (string $name, array $input): ManifestSetupInputData => new ManifestSetupInputData(
            name: $name,
            label: $input['label'] ?? $name,
            description: $input['description'] ?? null,
            type: $input['type'] ?? 'text',
            option: $input['option'] ?? null,
        ), array_keys($inputs), $inputs);
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
            ...self::setupStepRules('after_install'),
            ...self::setupStepRules('after_update'),
            'routes' => ['nullable', 'array', 'max:50'],
            'routes.*' => ['array'],
            'routes.*.upstream' => ['required', 'string', 'regex:'.RouteData::UpstreamPattern],
            'routes.*.description' => ['nullable', 'string', 'max:500'],
            'routes.*.main' => ['nullable', 'boolean'],
            'routes.*.via' => ['nullable', 'string', 'in:main'],
            'ondemand' => ['nullable', 'array'],
            'ondemand.port' => ['nullable', 'integer', 'between:1,55535'],
            'ondemand.app_port' => ['nullable', 'integer', 'between:1,65535'],
            'ondemand.listen' => ['nullable', 'ip'],
            'ondemand.idle_timeout' => ['nullable', 'string', 'max:32', 'regex:'.OnDemandSettingsData::TimeSpanPattern],
            'ondemand.workers' => ['nullable', 'array', 'list', 'max:20', 'required_with:ondemand.idle_check'],
            'ondemand.workers.*' => ['string', 'distinct', 'regex:'.OnDemandSettingsData::ContainerPattern],
            'ondemand.idle_check' => ['nullable', 'array'],
            'ondemand.idle_check.container' => ['required_with:ondemand.idle_check', 'string', 'regex:'.OnDemandSettingsData::ContainerPattern],
            'ondemand.idle_check.user' => ['nullable', 'string', 'max:64', 'regex:'.OnDemandSettingsData::UserPattern],
            'ondemand.idle_check.command' => ['required_with:ondemand.idle_check', 'string', 'max:500', 'not_regex:/[\r\n]/'],
            'ondemand.schedule' => ['nullable', 'array'],
            'ondemand.schedule.container' => ['required_with:ondemand.schedule', 'string', 'regex:'.OnDemandSettingsData::ContainerPattern],
            'ondemand.schedule.at' => ['required_with:ondemand.schedule', 'array', 'list', 'max:24'],
            'ondemand.schedule.at.*' => ['string', 'distinct', 'regex:'.OnDemandSettingsData::ScheduleAtPattern],
            'ondemand.schedule.environment' => ['nullable', 'array', 'max:20'],
            'ondemand.schedule.environment.*' => ['string', 'max:500', 'not_regex:/[\r\n$]/'],
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

        foreach (array_keys($manifest['routes'] ?? []) as $role) {
            if (! preg_match(RouteData::RolePattern, (string) $role)) {
                throw InvalidManifestException::because("\"{$role}\" isn't a valid route role: use lowercase letters, digits, - and _.");
            }
        }

        /** @var array<string, array<string, mixed>> $routes */
        $routes = $manifest['routes'] ?? [];

        if (count(array_filter($routes, fn (array $route): bool => ($route['main'] ?? false) === true)) > 1) {
            throw InvalidManifestException::because('only one route can be the main one.');
        }

        foreach (array_keys($manifest['ondemand']['schedule']['environment'] ?? []) as $key) {
            if (! preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', (string) $key)) {
                throw InvalidManifestException::because("\"{$key}\" isn't a valid environment variable of the on-demand scheduler.");
            }
        }

        $sharedNames = array_intersect(array_keys($manifest['after_install'] ?? []), array_keys($manifest['after_update'] ?? []));

        if ($sharedNames !== []) {
            throw InvalidManifestException::because('setup step "'.reset($sharedNames).'" is in both after_install and after_update: give each step its own name.');
        }

        foreach ([...$manifest['after_install'] ?? [], ...$manifest['after_update'] ?? []] as $name => $step) {
            self::ensureStepIsValid((string) $name, $step['inputs'] ?? []);
        }
    }

    /**
     * Only one input can go on stdin, and a password always does, so it never shows in the arguments.
     *
     * @param  array<array-key, array<string, mixed>>  $inputs
     *
     * @throws InvalidManifestException
     */
    protected static function ensureStepIsValid(string $name, array $inputs): void
    {
        if (! preg_match('/^[A-Za-z0-9][A-Za-z0-9_.-]{0,63}$/', $name)) {
            throw InvalidManifestException::because("\"{$name}\" isn't a valid setup step name.");
        }

        foreach ($inputs as $inputName => $input) {
            if (! preg_match('/^[A-Za-z][A-Za-z0-9_]{0,63}$/', (string) $inputName)) {
                throw InvalidManifestException::because("\"{$inputName}\" isn't a valid input name of setup step \"{$name}\".");
            }

            if (($input['type'] ?? null) === 'password' && isset($input['option'])) {
                throw InvalidManifestException::because("input \"{$inputName}\" of setup step \"{$name}\" is a password, so it goes on stdin and can't have an option.");
            }
        }

        $onStdin = array_filter($inputs, fn (array $input): bool => ! isset($input['option']));

        if (count($onStdin) > 1) {
            throw InvalidManifestException::because("setup step \"{$name}\" has more than one input without an option, and only one can go on stdin.");
        }
    }
}
