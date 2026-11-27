<?php

declare(strict_types=1);

namespace Domain\Applications\Actions;

use Domain\Applications\DataObjects\ApplicationData;
use Domain\Applications\DataObjects\OnDemandScheduleData;
use Domain\Applications\DataObjects\OnDemandSettingsData;
use Domain\Applications\DataObjects\OnDemandSuggestionData;
use Domain\Applications\DataObjects\QuadletFileData;
use Domain\Quadlets\DataObjects\ManifestData;
use Illuminate\Support\Str;

/**
 * The on-demand settings to offer for an application. Its stored pods.yml says them in its ondemand
 * section. Without one, Pods reads its Quadlet files: the port its main unit listens on, and for a
 * Laravel application, its queue workers and its scheduler. The socket stays on the address and port
 * the main unit publishes on, unless the pods.yml names them. It takes the first free port from 8000
 * when the unit publishes none, or another application has it.
 */
class SuggestOnDemandSettings
{
    public function __construct(
        protected SuggestSocketPort $suggestSocketPort,
    ) {}

    /**
     * A container that runs Laravel's queue (or Horizon), and keeps running while the app sleeps.
     */
    protected const string WorkerPattern = '/\bartisan\s+(queue:work|queue:listen|horizon)(?=[\s"\']|$)/m';

    protected const string SchedulerPattern = '/\bartisan\s+schedule:work\b/';

    /**
     * @param  list<QuadletFileData>  $files
     * @param  list<ApplicationData>  $applications  Every application on the host, whose ports the socket avoids.
     */
    public function __invoke(ApplicationData $application, array $files, ?ManifestData $manifest = null, array $applications = []): OnDemandSuggestionData
    {
        $mainFileName = $this->mainFileName($application);

        $main = null;
        $isLaravel = false;

        /** @var array<string, QuadletFileData> $containers The other containers, by name. */
        $containers = [];

        // Drop-ins (NAME.container.d/*.conf) are left out: lpod's own on-demand drop-in publishes another port.
        foreach ($files as $file) {
            if (str_contains($file->name, '.d/')) {
                continue;
            }

            $isLaravel = $isLaravel || preg_match('/\bartisan\b/', $file->content) === 1;

            if ($file->name === $mainFileName) {
                $main = $file;
            } elseif (str_ends_with($file->name, '.container')) {
                $containers[Str::beforeLast($file->name, '.container')] = $file;
            }
        }

        $workers = $isLaravel
            ? array_keys(array_filter($containers, fn (QuadletFileData $file): bool => preg_match(self::WorkerPattern, $file->content) === 1))
            : [];

        $containerNames = array_map(strval(...), array_keys($containers));

        $socketPort = fn (): ?int => ($this->suggestSocketPort)(
            $application->name,
            $main !== null ? $this->hostPort($main->content) : null,
            $applications,
        );

        if ($manifest?->ondemand !== null) {
            return $this->fromManifest($manifest->ondemand, $containerNames, $isLaravel, $main, $socketPort);
        }

        return new OnDemandSuggestionData(
            laravel: $isLaravel,
            containers: $containerNames,
            settings: new OnDemandSettingsData(
                port: $socketPort(),
                app_port: $main !== null ? $this->appPort($main->content) : null,
                listen: $main !== null ? $this->listenAddress($main->content) : null,
                idle_timeout: '10min',
                workers: array_map(strval(...), $workers),
                idle_check: null,
                schedule: $isLaravel ? $this->schedule($containers) : null,
            ),
            from_manifest: false,
        );
    }

    /**
     * The manifest's settings as they are. A port it leaves out is still read from the main unit, and
     * workers or a scheduler in it show the Laravel settings, whatever the files look like.
     *
     * @param  list<string>  $containers
     * @param  callable(): ?int  $socketPort
     */
    protected function fromManifest(OnDemandSettingsData $settings, array $containers, bool $isLaravel, ?QuadletFileData $main, callable $socketPort): OnDemandSuggestionData
    {
        $hasBackgroundWork = $settings->workers !== [] || $settings->idle_check !== null || $settings->schedule !== null;

        return new OnDemandSuggestionData(
            laravel: $isLaravel || $hasBackgroundWork,
            containers: $containers,
            settings: new OnDemandSettingsData(
                port: $settings->port ?? $socketPort(),
                app_port: $settings->app_port ?? ($main !== null ? $this->appPort($main->content) : null),
                listen: $settings->listen ?? ($main !== null ? $this->listenAddress($main->content) : null),
                idle_timeout: $settings->idle_timeout,
                workers: $settings->workers,
                idle_check: $settings->idle_check,
                schedule: $settings->schedule,
            ),
            from_manifest: true,
        );
    }

    /**
     * The main unit is the pod (immich-pod.service is immich.pod) or the container (hello.service is hello.container).
     */
    protected function mainFileName(ApplicationData $application): string
    {
        if ($application->kind === 'pod') {
            return Str::beforeLast($application->unit, '-pod.service').'.pod';
        }

        return Str::beforeLast($application->unit, '.service').'.container';
    }

    /**
     * The port inside the container: ExposeHostPort=8000, or the last part of PublishPort=127.0.0.1:9393:8000.
     */
    protected function appPort(string $content): ?int
    {
        if (preg_match('/^\s*ExposeHostPort\s*=\s*(\d+)/m', $content, $matches) === 1) {
            return (int) $matches[1];
        }

        if (preg_match('/^\s*PublishPort\s*=\s*(?:\S*:)?(\d+)(?:\/\w+)?\s*$/m', $content, $matches) === 1) {
            return (int) $matches[1];
        }

        return null;
    }

    /**
     * The first TCP port it publishes on the host: 9393 of PublishPort=127.0.0.1:9393:8000 or PublishPort=9393:8000.
     * A port Podman picks itself (PublishPort=127.0.0.1::8000 or PublishPort=8000) isn't one to keep.
     */
    protected function hostPort(string $content): ?int
    {
        preg_match_all('/^\s*PublishPort\s*=\s*(\S+)\s*$/m', $content, $matches);

        foreach ($matches[1] as $published) {
            if (preg_match('/(?:^|:)(\d+):\d+(?:\/tcp)?$/', $published, $parts) === 1) {
                return (int) $parts[1];
            }
        }

        return null;
    }

    /**
     * The address the application publishes on, as in PublishPort=127.0.0.1:9393:8000, or null for every address:
     * when a port is published on all of them, or none is published.
     */
    protected function listenAddress(string $content): ?string
    {
        preg_match_all('/^\s*PublishPort\s*=\s*(\S+)/m', $content, $matches);

        $addresses = [];

        foreach ($matches[1] as $published) {
            $address = preg_match('/^\[?(.+?)\]?:\d*:\d+/', $published, $parts) === 1 ? $parts[1] : '';

            if (filter_var($address, FILTER_VALIDATE_IP) === false || in_array($address, ['0.0.0.0', '::'], true)) {
                return null;
            }

            $addresses[] = $address;
        }

        return $addresses[0] ?? null;
    }

    /**
     * A scheduler that runs schedule:work through APP_COMMAND (as images based on laravel-frankenphp do) can run
     * schedule:run once per due minute instead. One that runs it through Exec= can't, so it isn't suggested.
     *
     * @param  array<string, QuadletFileData>  $containers
     */
    protected function schedule(array $containers): ?OnDemandScheduleData
    {
        foreach ($containers as $name => $file) {
            $content = $file->content;

            if (preg_match(self::SchedulerPattern, $content) !== 1) {
                continue;
            }

            if (preg_match('/^\s*Environment\s*=\s*(["\']?)APP_COMMAND=(.*?)\1\s*$/m', $content, $matches) !== 1) {
                return null;
            }

            $command = trim($matches[2], '"\'');

            return new OnDemandScheduleData(
                container: $name,
                at: ['*:00'],
                environment: ['APP_COMMAND' => (string) preg_replace('/\bschedule:work\b/', 'schedule:run', $command)],
            );
        }

        return null;
    }
}
