<?php

declare(strict_types=1);

namespace Integrations\Lpod;

use Domain\Applications\DataObjects\ApplicationData;
use Domain\Applications\DataObjects\ApplicationDetailData;
use Domain\Applications\DataObjects\HostStatsData;
use Domain\Applications\DataObjects\JournalEntryData;
use Domain\Applications\DataObjects\QuadletFileData;
use Domain\Containers\DataObjects\ContainerDetailData;
use Domain\Containers\DataObjects\HostContainerData;
use Domain\Containers\Enums\ContainerOperation;
use Domain\Environments\Models\Environment;
use Domain\Images\DataObjects\ImageData;
use Domain\Networks\DataObjects\NetworkData;
use Domain\Quadlets\DataObjects\QuadletCheckData;
use Domain\Quadlets\DataObjects\QuadletSourceData;
use Domain\Secrets\DataObjects\SecretData;
use Domain\Shared\DataObjects\PruneResultData;
use Domain\Volumes\DataObjects\HostVolumeData;
use Illuminate\Support\Facades\Cache;
use Integrations\Lpod\Exceptions\LpodException;
use Integrations\Lpod\Exceptions\UnsupportedContractException;
use Integrations\Ssh\Contracts\RunsHostCommands;

/**
 * Talks to lpod on an environment's host, through its JSON contract.
 */
class LpodClient
{
    public const int Contract = 1;

    public function __construct(
        protected RunsHostCommands $runner,
    ) {}

    /**
     * @return array{contract: int, lpod: string, podman: string, systemd: string, scope: string, user: string, hostname: string}
     */
    public function version(Environment $environment): array
    {
        /** @var array{contract: int, lpod: string, podman: string, systemd: string, scope: string, user: string, hostname: string} */
        return $this->json($environment, ['version', '--json']);
    }

    public function host(Environment $environment): HostStatsData
    {
        return HostStatsData::fromLpod($this->cached($environment, 'host', ['host', '--json']));
    }

    /**
     * @return list<ApplicationData>
     */
    public function applications(Environment $environment): array
    {
        $payload = $this->cached($environment, 'apps', ['apps', '--json']);

        return array_map(ApplicationData::fromLpod(...), array_values($payload['applications'] ?? []));
    }

    public function application(Environment $environment, string $application): ApplicationDetailData
    {
        $payload = $this->cached($environment, "apps.{$application}", [$application, 'status', '--json']);

        return ApplicationDetailData::fromLpod($payload['application']);
    }

    /**
     * @return list<QuadletFileData>
     */
    public function files(Environment $environment, string $application): array
    {
        $payload = $this->json($environment, [$application, 'files', '--json']);

        return array_map(QuadletFileData::fromLpod(...), array_values($payload['files'] ?? []));
    }

    /**
     * @return list<JournalEntryData>
     */
    public function logs(Environment $environment, string $application, int $lines = 100): array
    {
        $payload = $this->json($environment, [$application, 'logs', '--json', '--lines', (string) $lines]);

        return array_map(JournalEntryData::fromLpod(...), array_values($payload['entries'] ?? []));
    }

    /**
     * Gives the existing secrets, followed by the ones a Quadlet references but that don't exist.
     *
     * @return list<SecretData>
     */
    public function secrets(Environment $environment): array
    {
        $payload = $this->cached($environment, 'secrets', ['secrets', '--json']);

        return [
            ...array_map(fn (array $secret): SecretData => SecretData::fromLpod($secret), array_values($payload['secrets'] ?? [])),
            ...array_map(fn (array $secret): SecretData => SecretData::fromLpod($secret, missing: true), array_values($payload['missing'] ?? [])),
        ];
    }

    public function createSecret(Environment $environment, string $name, string $driver, string $value, bool $replace = false): void
    {
        $this->json($environment, [
            'secret', 'create', $name, '--driver', $driver, ...($replace ? ['--replace'] : []), '--stdin', '--json',
        ], input: $value);

        $this->forget($environment, 'secrets');
    }

    public function addSecretToApplication(Environment $environment, string $application, string $secret, string $type, string $target): void
    {
        $this->json($environment, [$application, 'secret', 'add', $secret, '--type', $type, '--target', $target, '--json']);

        $this->forget($environment, 'secrets', "apps.{$application}");
    }

    public function deleteSecret(Environment $environment, string $name): void
    {
        $this->json($environment, ['secret', 'rm', $name, '--json']);

        $this->forget($environment, 'secrets');
    }

    /**
     * Stops the application and removes its Quadlet files, plus the data it owns when asked.
     */
    public function removeApplication(Environment $environment, string $application, bool $volumes = false, bool $images = false, bool $secrets = false): void
    {
        $this->json($environment, [
            $application, 'remove',
            ...($volumes ? ['--volumes'] : []),
            ...($images ? ['--images'] : []),
            ...($secrets ? ['--secrets'] : []),
            '--json',
        ]);

        $this->forget($environment, 'apps', "apps.{$application}", 'host', 'containers', 'images', 'volumes', 'networks', 'secrets');
    }

    /**
     * @return list<HostContainerData>
     */
    public function containers(Environment $environment): array
    {
        $payload = $this->cached($environment, 'containers', ['containers', '--json']);

        return array_map(HostContainerData::fromLpod(...), array_values($payload['containers'] ?? []));
    }

    public function container(Environment $environment, string $container): ContainerDetailData
    {
        $payload = $this->cached($environment, "containers.{$container}", ['container', $container, 'inspect', '--json']);

        return ContainerDetailData::fromLpod($payload['container']);
    }

    /**
     * @return list<JournalEntryData>
     */
    public function containerLogs(Environment $environment, string $container, int $lines = 200): array
    {
        $payload = $this->json($environment, ['container', $container, 'logs', '--json', '--lines', (string) $lines]);

        return array_map(JournalEntryData::fromLpod(...), array_values($payload['entries'] ?? []));
    }

    /**
     * Runs the operation through the container's systemd unit, or podman itself when no Quadlet manages it.
     */
    public function operateContainer(Environment $environment, string $container, ContainerOperation $operation): void
    {
        $this->json($environment, ['container', $container, $operation->value, '--json']);

        $this->forget($environment, 'containers', "containers.{$container}", 'apps', 'host');
    }

    /**
     * @return list<ImageData>
     */
    public function images(Environment $environment): array
    {
        $payload = $this->cached($environment, 'images', ['images', '--json']);

        return array_map(ImageData::fromLpod(...), array_values($payload['images'] ?? []));
    }

    public function pullImage(Environment $environment, string $reference): void
    {
        $this->json($environment, ['image', 'pull', $reference, '--json'], timeout: 600);

        $this->forget($environment, 'images', 'host');
    }

    public function removeImage(Environment $environment, string $image): void
    {
        $this->json($environment, ['image', 'rm', $image, '--json']);

        $this->forget($environment, 'images', 'host');
    }

    public function pruneImages(Environment $environment): PruneResultData
    {
        $result = PruneResultData::fromLpod($this->json($environment, ['images', 'prune', '--json'], timeout: 300));

        $this->forget($environment, 'images', 'host');

        return $result;
    }

    /**
     * Asks podman auto-update which images have a newer version, without pulling them.
     */
    public function checkForUpdates(Environment $environment): void
    {
        $this->json($environment, ['auto-update', '--dry-run', '--json'], timeout: 300);

        $this->forget($environment, 'images', 'apps');
    }

    /**
     * @return list<HostVolumeData>
     */
    public function volumes(Environment $environment): array
    {
        $payload = $this->cached($environment, 'volumes', ['volumes', '--json']);

        return array_map(HostVolumeData::fromLpod(...), array_values($payload['volumes'] ?? []));
    }

    public function removeVolume(Environment $environment, string $volume): void
    {
        $this->json($environment, ['volume', 'rm', $volume, '--json']);

        $this->forget($environment, 'volumes', 'host');
    }

    public function pruneVolumes(Environment $environment): PruneResultData
    {
        $result = PruneResultData::fromLpod($this->json($environment, ['volumes', 'prune', '--json'], timeout: 300));

        $this->forget($environment, 'volumes', 'host');

        return $result;
    }

    /**
     * @return list<NetworkData>
     */
    public function networks(Environment $environment): array
    {
        $payload = $this->cached($environment, 'networks', ['networks', '--json']);

        return array_map(NetworkData::fromLpod(...), array_values($payload['networks'] ?? []));
    }

    public function removeNetwork(Environment $environment, string $network): void
    {
        $this->json($environment, ['network', 'rm', $network, '--json']);

        $this->forget($environment, 'networks');
    }

    public function pruneNetworks(Environment $environment): PruneResultData
    {
        $result = PruneResultData::fromLpod($this->json($environment, ['networks', 'prune', '--json']));

        $this->forget($environment, 'networks');

        return $result;
    }

    /**
     * Gives the journal of every unit Pods manages, newest last.
     *
     * @return list<JournalEntryData>
     */
    public function journal(Environment $environment, int $lines = 500, ?int $priority = null, ?string $since = null): array
    {
        $payload = $this->json($environment, [
            'journal', '--json', '--lines', (string) $lines,
            ...($priority !== null ? ['--priority', (string) $priority] : []),
            ...($since !== null ? ['--since', $since] : []),
        ]);

        return array_map(JournalEntryData::fromLpod(...), array_values($payload['entries'] ?? []));
    }

    /**
     * Runs the files through podman quadlet --dryrun on the host. The files travel on stdin.
     *
     * @param  list<array{name: string, content: string}>  $files
     */
    public function checkQuadlets(Environment $environment, array $files): QuadletCheckData
    {
        return QuadletCheckData::fromLpod($this->json($environment, ['quadlet', 'check', '--stdin', '--json'], input: $this->encodeFiles($files)));
    }

    /**
     * Installs the files as one application with podman quadlet install, then reloads systemd.
     *
     * @param  list<array{name: string, content: string}>  $files
     */
    public function installQuadlets(Environment $environment, string $application, array $files, bool $start = true, bool $replace = false): void
    {
        $this->json($environment, [
            'quadlet', 'install', $application,
            ...($replace ? ['--replace'] : []),
            ...($start ? ['--start'] : []),
            '--stdin', '--json',
        ], input: $this->encodeFiles($files), timeout: 600);

        $this->forget($environment, 'apps', "apps.{$application}", 'host', 'containers', 'volumes', 'networks', 'secrets');
    }

    /**
     * Fetches the Quadlet files in a directory of a Git repository, without installing them.
     */
    public function fetchQuadlets(Environment $environment, string $repository, string $reference, string $path = ''): QuadletSourceData
    {
        return QuadletSourceData::fromLpod($this->json($environment, [
            'quadlet', 'fetch', $repository, '--ref', $reference,
            ...($path !== '' ? ['--path', $path] : []),
            '--json',
        ], timeout: 120));
    }

    /**
     * @param  list<array{name: string, content: string}>  $files
     */
    protected function encodeFiles(array $files): string
    {
        return (string) json_encode(['files' => $files], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    }

    public function up(Environment $environment, string $application): void
    {
        $this->lifecycle($environment, $application, 'up');
    }

    public function down(Environment $environment, string $application): void
    {
        $this->lifecycle($environment, $application, 'down');
    }

    public function restart(Environment $environment, string $application): void
    {
        $this->lifecycle($environment, $application, 'restart');
    }

    /**
     * Pulls the newest images of the application, then restarts it.
     */
    public function redeploy(Environment $environment, string $application): void
    {
        $this->lifecycle($environment, $application, 'redeploy');
    }

    protected function lifecycle(Environment $environment, string $application, string $operation): void
    {
        $this->json($environment, [$application, $operation, '--no-block', '--json']);

        $this->forget($environment, 'apps', "apps.{$application}", 'host');
    }

    /**
     * @param  list<string>  $arguments
     * @return array<string, mixed>
     */
    protected function cached(Environment $environment, string $key, array $arguments): array
    {
        $ttl = (int) config('services.pods.cache_ttl');

        return Cache::flexible($this->cacheKey($environment, $key), [$ttl, $ttl * 6], fn (): array => $this->json($environment, $arguments));
    }

    protected function forget(Environment $environment, string ...$keys): void
    {
        foreach ($keys as $key) {
            Cache::forget($this->cacheKey($environment, $key));
        }
    }

    protected function cacheKey(Environment $environment, string $key): string
    {
        return "pods.lpod.{$environment->ulid}.{$key}";
    }

    /**
     * @param  list<string>  $arguments
     * @return array<string, mixed>
     */
    protected function json(Environment $environment, array $arguments, ?string $input = null, int $timeout = 30): array
    {
        $command = implode(' ', $arguments);

        $result = $this->runner->run($environment, $arguments, $input, $timeout);

        if ($result->exitCode === 255) {
            throw LpodException::unreachable($environment, $result);
        }

        if ($result->exitCode === 127 && preg_match('/\bssh: (command )?not found/', $result->errorOutput) === 1) {
            throw LpodException::sshClientMissing();
        }

        if ($result->exitCode === 127) {
            throw LpodException::notInstalled($environment);
        }

        if (! $result->successful()) {
            throw LpodException::commandFailed($command, $result);
        }

        $payload = json_decode($result->output, true);

        if (! is_array($payload)) {
            throw LpodException::invalidJson($command);
        }

        $contract = isset($payload['contract']) ? (int) $payload['contract'] : null;

        if ($contract !== self::Contract) {
            throw UnsupportedContractException::make($contract, self::Contract);
        }

        return $payload;
    }
}
