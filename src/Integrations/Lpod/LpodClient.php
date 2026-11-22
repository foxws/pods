<?php

declare(strict_types=1);

namespace Integrations\Lpod;

use Domain\Applications\DataObjects\ApplicationData;
use Domain\Applications\DataObjects\ApplicationDetailData;
use Domain\Applications\DataObjects\HostStatsData;
use Domain\Applications\DataObjects\JournalEntryData;
use Domain\Applications\DataObjects\OnDemandData;
use Domain\Applications\DataObjects\OnDemandSettingsData;
use Domain\Applications\DataObjects\ProxyRoutesData;
use Domain\Applications\DataObjects\ProxySettingsData;
use Domain\Applications\DataObjects\QuadletFileData;
use Domain\Applications\DataObjects\TimerData;
use Domain\Catalog\DataObjects\CatalogData;
use Domain\Containers\DataObjects\ContainerDetailData;
use Domain\Containers\DataObjects\ContainerExecResultData;
use Domain\Containers\DataObjects\ContainerStatsData;
use Domain\Containers\DataObjects\HostContainerData;
use Domain\Containers\Enums\ContainerOperation;
use Domain\Environments\DataObjects\HostEventData;
use Domain\Environments\DataObjects\HostSettingsData;
use Domain\Environments\Events\EnvironmentChanged;
use Domain\Environments\Models\Environment;
use Domain\Images\DataObjects\ImageData;
use Domain\Networks\DataObjects\NetworkData;
use Domain\Pods\DataObjects\PodData;
use Domain\Quadlets\DataObjects\GitTagData;
use Domain\Quadlets\DataObjects\ManifestTimerData;
use Domain\Quadlets\DataObjects\QuadletCheckData;
use Domain\Quadlets\DataObjects\QuadletSourceData;
use Domain\Secrets\DataObjects\SecretData;
use Domain\Shared\DataObjects\PruneResultData;
use Domain\Volumes\DataObjects\HostVolumeData;
use Illuminate\Support\Facades\Cache;
use Integrations\Lpod\Exceptions\LpodException;
use Integrations\Lpod\Exceptions\UnsupportedCommandException;
use Integrations\Lpod\Exceptions\UnsupportedContractException;
use Integrations\Ssh\Contracts\RunsHostCommands;
use Integrations\Ssh\HostResult;
use SensitiveParameter;

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
     * Whether the Pods proxy is installed and running, and every application's routes.
     */
    public function proxyRoutes(Environment $environment): ProxyRoutesData
    {
        return ProxyRoutesData::fromLpod($this->cached($environment, 'proxy.routes', ['proxy', 'routes', '--json']));
    }

    /**
     * How the Pods proxy gets its certificates, its ports and its image.
     */
    public function proxySettings(Environment $environment): ProxySettingsData
    {
        return ProxySettingsData::fromLpod($this->cached($environment, 'proxy.settings', ['proxy', 'settings', '--json']));
    }

    /**
     * Writes the proxy's Caddyfile and ports from the settings, rewrites every application's routes
     * for the mode, and restarts the proxy. The DNS token travels on stdin with the settings, into
     * a Podman secret; without one, lpod keeps the token it has.
     *
     * @param  array{mode: string, email: ?string, domain: ?string, dns_provider: ?string, resolvers: ?string, http_port: int, https_port: ?int, image: ?string}  $settings
     * @return string|null where lpod kept the hand-written Caddyfile it replaced
     */
    public function updateProxySettings(Environment $environment, array $settings, #[SensitiveParameter] ?string $dnsToken = null): ?string
    {
        $input = $dnsToken !== null ? [...$settings, 'dns_token' => $dnsToken] : $settings;

        $payload = $this->json($environment, ['proxy', 'settings', 'set', '--stdin', '--json'], input: (string) json_encode($input, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES), timeout: 120);

        $this->forget($environment, 'proxy.settings', 'proxy.routes', 'secrets');

        return is_string($payload['backup'] ?? null) ? $payload['backup'] : null;
    }

    /**
     * The root certificate (PEM) of the proxy's local certificate authority, or null until Caddy
     * has made one or while the proxy isn't running.
     */
    public function proxyCertificate(Environment $environment): ?string
    {
        $payload = $this->json($environment, ['proxy', 'certificate', '--json']);

        return is_string($payload['certificate'] ?? null) ? $payload['certificate'] : null;
    }

    /**
     * Replaces the hostnames the Pods proxy sends to the application, and applies them: lpod reloads
     * Caddy, or restarts the proxy when the networks it joins change. No routes removes them.
     *
     * @param  list<array{role: string, host: string, upstream: string, main: bool, via: ?string}>  $routes
     */
    public function setRoutes(Environment $environment, string $application, array $routes): void
    {
        $this->json($environment, [$application, 'routes', 'set', '--stdin', '--json'], input: (string) json_encode(['routes' => $routes], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES), timeout: 120);

        $this->forget($environment, 'proxy.routes');
    }

    /**
     * The systemd timers that start containers of the application on a schedule.
     *
     * @return list<TimerData>
     */
    public function timers(Environment $environment, string $application): array
    {
        $payload = $this->cached($environment, "apps.{$application}.timers", [$application, 'timers', '--json']);

        return array_map(TimerData::fromLpod(...), array_values($payload['timers'] ?? []));
    }

    /**
     * Replaces the application's timers, and enables them. No timers removes them.
     *
     * @param  list<ManifestTimerData>  $timers
     */
    public function setTimers(Environment $environment, string $application, array $timers): void
    {
        $input = ['timers' => array_map(fn (ManifestTimerData $timer): array => $timer->forLpod(), $timers)];

        $this->json($environment, [$application, 'timers', 'set', '--stdin', '--json'], input: (string) json_encode($input, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES), timeout: 120);

        $this->forget($environment, "apps.{$application}.timers");
    }

    /**
     * Starts the container of one of the application's timers now, without waiting for it.
     */
    public function runTimer(Environment $environment, string $application, string $timer): void
    {
        $this->json($environment, [$application, 'timers', 'run', $timer, '--json']);

        $this->forget($environment, "apps.{$application}.timers", 'apps', "apps.{$application}", 'host');
    }

    /**
     * Removes some of the application's files: Quadlets, whose units stop, and drop-ins next to them.
     *
     * @param  list<string>  $names  as files() names them
     * @return list<string> the names lpod removed
     */
    public function removeFiles(Environment $environment, string $application, array $names): array
    {
        $payload = $this->json($environment, [$application, 'files', 'remove', ...$names, '--json'], timeout: 120);

        $this->forget($environment, 'apps', "apps.{$application}", 'host', 'containers', 'pods', 'volumes', 'networks');

        return array_values(array_map(strval(...), $payload['removed'] ?? []));
    }

    /**
     * Writes a drop-in next to one of the application's Quadlets, replacing the one with that name.
     * The content travels on stdin. Running units pick it up when they restart. An lpod without
     * "files write" ignores it and lists the files instead, so there's no file in its reply.
     *
     * @param  string  $name  as files() names it, such as hello.container.d/memory.local.conf
     *
     * @throws LpodException
     */
    public function writeFile(Environment $environment, string $application, string $name, #[SensitiveParameter] string $content): QuadletFileData
    {
        $payload = $this->json($environment, [$application, 'files', 'write', $name, '--stdin', '--json'], input: $content);

        if (! is_array($payload['file'] ?? null)) {
            throw LpodException::unsupportedCommand("{$application} files write");
        }

        $this->forget($environment, 'apps', "apps.{$application}", 'host', 'containers', 'pods', 'volumes', 'networks', 'secrets');

        return QuadletFileData::fromLpod($payload['file']);
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

    public function createSecret(Environment $environment, string $name, string $driver, #[SensitiveParameter] string $value, bool $replace = false): void
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

    public function onDemand(Environment $environment, string $application): OnDemandData
    {
        $payload = $this->cached($environment, "apps.{$application}.ondemand", [$application, 'ondemand', 'status', '--json']);

        return OnDemandData::fromLpod($payload['ondemand']);
    }

    /**
     * Lets a socket start the application on its first request, and its units sleep when idle. lpod stops
     * every unit of the application first, so the app starts on the next request with its new port.
     */
    public function enableOnDemand(Environment $environment, string $application, OnDemandSettingsData $settings): void
    {
        $this->json($environment, [$application, 'ondemand', 'enable', ...$settings->toLpodArguments(), '--json'], timeout: 120);

        $this->forget($environment, 'apps', "apps.{$application}", "apps.{$application}.ondemand", 'pods', 'host');
    }

    /**
     * Removes the on-demand units, and starts the application again, now at boot too.
     */
    public function disableOnDemand(Environment $environment, string $application): void
    {
        $this->json($environment, [$application, 'ondemand', 'disable', '--json'], timeout: 120);

        $this->forget($environment, 'apps', "apps.{$application}", "apps.{$application}.ondemand", 'pods', 'host');
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

        $this->forget($environment, 'apps', "apps.{$application}", 'host', 'containers', 'pods', 'images', 'volumes', 'networks', 'secrets');
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
    /**
     * @return list<PodData>
     */
    public function pods(Environment $environment): array
    {
        $payload = $this->cached($environment, 'pods', ['pods', '--json']);

        return array_map(PodData::fromLpod(...), array_values($payload['pods'] ?? []));
    }

    public function containerStats(Environment $environment, string $container): ContainerStatsData
    {
        $payload = $this->json($environment, ['container', $container, 'stats', '--json']);

        return ContainerStatsData::fromLpod($payload['stats'] ?? []);
    }

    /**
     * Removes every stopped container. Quadlet recreates its containers when their units start.
     */
    public function pruneContainers(Environment $environment): PruneResultData
    {
        $result = PruneResultData::fromLpod($this->json($environment, ['containers', 'prune', '--json'], timeout: 120));

        $this->forget($environment, 'containers', 'pods', 'host');

        return $result;
    }

    /**
     * What happened to containers and images in the last day, newest first.
     *
     * @return list<HostEventData>
     */
    public function events(Environment $environment, int $limit = 20): array
    {
        $payload = $this->json($environment, ['events', '--json', '--since', '24h', '--limit', (string) $limit]);

        return array_map(HostEventData::fromLpod(...), array_values($payload['events'] ?? []));
    }

    /**
     * Runs a command in a running container. A failing command doesn't throw: its exit code is in the result.
     * The input goes on the command's stdin, so a password never shows in the arguments.
     *
     * @param  list<string>  $command
     */
    public function exec(Environment $environment, string $container, array $command, #[SensitiveParameter] ?string $input = null, ?string $user = null, ?string $workdir = null, int $timeout = 600): ContainerExecResultData
    {
        return ContainerExecResultData::fromLpod($this->json($environment, [
            'container', $container, 'exec',
            ...($input !== null ? ['--stdin'] : []),
            ...($user !== null ? ['--user', $user] : []),
            ...($workdir !== null ? ['--workdir', $workdir] : []),
            '--json', '--', ...$command,
        ], input: $input, timeout: $timeout));
    }

    public function operateContainer(Environment $environment, string $container, ContainerOperation $operation): void
    {
        $this->json($environment, ['container', $container, $operation->value, '--json']);

        $this->forget($environment, 'containers', "containers.{$container}", 'apps', 'pods', 'host');
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

    /**
     * The disk space each named and anonymous volume uses, in bytes, by name. lpod walks every file
     * for it, so it's kept for 10 minutes, and given longer than other reads. Writes don't forget it:
     * applications change their volumes' sizes all the time anyway. A host whose lpod has no
     * "volumes sizes" yet answers with its plain volume list, which has no sizes.
     *
     * @return array<string, int|null>
     */
    public function volumeSizes(Environment $environment): array
    {
        $payload = Cache::flexible(
            $this->cacheKey($environment, 'volumes.sizes'),
            [600, 3600],
            fn (): array => $this->json($environment, ['volumes', 'sizes', '--json'], timeout: 300),
        );

        $sizes = [];

        foreach (is_array($payload['sizes'] ?? null) ? $payload['sizes'] : [] as $entry) {
            if (is_array($entry) && is_string($entry['name'] ?? null)) {
                $sizes[$entry['name']] = is_int($entry['size'] ?? null) ? $entry['size'] : null;
            }
        }

        return $sizes;
    }

    /**
     * Streams the volume's contents as a tar archive into the file at the path. With $stop, lpod
     * stops the applications whose running containers use the volume first, and starts them
     * again afterwards. lpod refuses volumes backed by a folder or device on the host, and NFS.
     */
    public function exportVolume(Environment $environment, string $volume, string $path, bool $stop = false, int $timeout = 3600): void
    {
        $arguments = ['volume', 'export', $volume, ...($stop ? ['--stop'] : []), '--json'];

        $this->verify($environment, $arguments, $this->runner->download($environment, $arguments, $path, $timeout));

        if ($stop) {
            $this->forget($environment, 'apps', 'host', 'containers', 'pods');
        }
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
     * Falls back to what lpod host reports when the host's lpod has no settings command yet. Those settings can't be changed.
     */
    public function hostSettings(Environment $environment): HostSettingsData
    {
        try {
            $payload = $this->cached($environment, 'settings', ['settings', '--json']);
        } catch (UnsupportedCommandException) {
            return HostSettingsData::fromHostStats($this->host($environment));
        }

        return HostSettingsData::fromLpod($payload['settings']);
    }

    /**
     * Configures the podman-auto-update timer and lingering of the user lpod runs as.
     */
    public function updateHostSettings(Environment $environment, bool $autoUpdate, string $schedule, bool $rollback, bool $linger): void
    {
        $this->json($environment, [
            'settings', 'set',
            '--auto-update', $autoUpdate ? 'on' : 'off',
            '--schedule', $schedule,
            '--rollback', $rollback ? 'on' : 'off',
            '--linger', $linger ? 'on' : 'off',
            '--json',
        ]);

        $this->forget($environment, 'settings', 'host');
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

        $this->forget($environment, 'apps', "apps.{$application}", 'host', 'containers', 'pods', 'volumes', 'networks', 'secrets');
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
     * The applications of a catalog repository: every directory at its root with a pods.yml.
     * Kept for a few minutes, because the host clones the repository to read them.
     */
    public function catalog(Environment $environment, string $repository, string $reference): CatalogData
    {
        $key = 'catalog.'.hash('xxh128', "{$repository}#{$reference}");

        return CatalogData::fromLpod(Cache::flexible(
            $this->cacheKey($environment, $key),
            [300, 3600],
            fn (): array => $this->json($environment, ['quadlet', 'catalog', $repository, '--ref', $reference, '--json'], timeout: 120),
        ), $repository, $reference);
    }

    /**
     * The tags of a Git repository, newest version first. Kept for a few minutes like the catalog,
     * because every application page installed from that repository asks.
     *
     * @return list<GitTagData>
     */
    public function tags(Environment $environment, string $repository): array
    {
        $payload = Cache::flexible(
            $this->cacheKey($environment, 'tags.'.hash('xxh128', $repository)),
            [300, 3600],
            fn (): array => $this->json($environment, ['quadlet', 'tags', $repository, '--json'], timeout: 60),
        );

        return array_map(GitTagData::fromLpod(...), array_values($payload['tags'] ?? []));
    }

    /**
     * The newest commit (in full) of a branch of a repository, or null when it has no such branch.
     * Kept for a couple of minutes, because the host asks the Git server for it.
     */
    public function branchHead(Environment $environment, string $repository, string $branch): ?string
    {
        $payload = Cache::flexible(
            $this->cacheKey($environment, 'head.'.hash('xxh128', $repository."\n".$branch)),
            [120, 600],
            fn (): array => $this->json($environment, ['quadlet', 'head', $repository, '--ref', $branch, '--json'], timeout: 60),
        );

        return is_string($payload['commit'] ?? null) ? $payload['commit'] : null;
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

    /**
     * Stops every unit of the application and waits for systemd, so a start right after it isn't
     * merged into the pending stop, the way updating stops the units before installing new files.
     */
    public function stopAndWait(Environment $environment, string $application): void
    {
        $this->json($environment, [$application, 'down', '--json'], timeout: 180);

        $this->forget($environment, 'apps', "apps.{$application}", "apps.{$application}.ondemand", 'pods', 'host');
    }

    /**
     * Stops every unit of the application right away: lpod queues the stop, kills the processes
     * and clears their failed state. It waits up to 10 seconds for them, so no --no-block.
     */
    public function forceStop(Environment $environment, string $application): void
    {
        $this->json($environment, [$application, 'down', '--force', '--json']);

        $this->forget($environment, 'apps', "apps.{$application}", "apps.{$application}.ondemand", 'pods', 'host');
    }

    protected function lifecycle(Environment $environment, string $application, string $operation): void
    {
        $this->json($environment, [$application, $operation, '--no-block', '--json']);

        $this->forget($environment, 'apps', "apps.{$application}", "apps.{$application}.ondemand", 'pods', 'host');
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

    /**
     * Forgets what a write changed, and tells the host's open pages to reload their live props.
     */
    protected function forget(Environment $environment, string ...$keys): void
    {
        foreach ($keys as $key) {
            Cache::forget($this->cacheKey($environment, $key));
        }

        $this->broadcastChanged($environment);
    }

    /**
     * Broadcasts once per request and host, after the response: a write that runs several lpod
     * commands sends one event. The tab that made the change has its own redirect, so it's left
     * out, and a broadcaster that can't be reached never fails the write.
     */
    protected function broadcastChanged(Environment $environment): void
    {
        $event = new EnvironmentChanged($environment);
        $event->dontBroadcastToCurrentUser();

        defer(fn () => rescue(fn () => event($event)), "pods.environment-changed.{$environment->ulid}");
    }

    protected function cacheKey(Environment $environment, string $key): string
    {
        return "pods.lpod.{$environment->ulid}.{$key}";
    }

    /**
     * Installs another lpod over the one on the host, for the user Pods connects as: a release such as
     * v4.0.0, or a branch such as main. lpod before 4.0.0-rc.2 prints the installer's text even with
     * --json, so only the exit code counts here; verify the host afterwards to read its new version.
     */
    public function selfUpdate(Environment $environment, string $version): void
    {
        $this->execute($environment, ['self-update', $version, '--json'], timeout: 180);
    }

    /**
     * @param  list<string>  $arguments
     * @return array<string, mixed>
     */
    protected function json(Environment $environment, array $arguments, #[SensitiveParameter] ?string $input = null, int $timeout = 30): array
    {
        $command = implode(' ', $arguments);

        $result = $this->execute($environment, $arguments, $input, $timeout);

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

    /**
     * Runs lpod, and turns a host it can't reach, a missing lpod and a failed command into an exception.
     *
     * @param  list<string>  $arguments
     */
    protected function execute(Environment $environment, array $arguments, #[SensitiveParameter] ?string $input = null, int $timeout = 30): HostResult
    {
        return $this->verify($environment, $arguments, $this->runner->run($environment, $arguments, $input, $timeout));
    }

    /**
     * @param  list<string>  $arguments
     */
    protected function verify(Environment $environment, array $arguments, HostResult $result): HostResult
    {
        $command = implode(' ', $arguments);

        if ($result->exitCode === 255) {
            throw LpodException::unreachable($environment, $result);
        }

        if ($result->exitCode === 127 && preg_match('/\bssh: (command )?not found/', $result->errorOutput) === 1) {
            throw LpodException::sshClientMissing();
        }

        if ($result->exitCode === 127) {
            throw LpodException::notInstalled($environment);
        }

        if (! $result->successful() && preg_match('/\bunknown (shorthand )?(flag|command)\b/', $result->errorOutput) === 1) {
            throw LpodException::unsupportedCommand($command);
        }

        if (! $result->successful()) {
            throw LpodException::commandFailed($command, $result);
        }

        return $result;
    }
}
