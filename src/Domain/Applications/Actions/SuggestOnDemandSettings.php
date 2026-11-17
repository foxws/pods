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
 * Laravel application, its queue workers and its scheduler.
 */
class SuggestOnDemandSettings
{
    /**
     * A container that runs Laravel's queue (or Horizon), and keeps running while the app sleeps.
     */
    protected const string WorkerPattern = '/\bartisan\s+(queue:work|queue:listen|horizon)(?=[\s"\']|$)/m';

    protected const string SchedulerPattern = '/\bartisan\s+schedule:work\b/';

    /**
     * @param  list<QuadletFileData>  $files
     */
    public function __invoke(ApplicationData $application, array $files, ?ManifestData $manifest = null): OnDemandSuggestionData
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

        if ($manifest?->ondemand !== null) {
            return $this->fromManifest($manifest->ondemand, $containerNames, $isLaravel, $main);
        }

        return new OnDemandSuggestionData(
            laravel: $isLaravel,
            containers: $containerNames,
            settings: new OnDemandSettingsData(
                port: null,
                app_port: $main !== null ? $this->appPort($main->content) : null,
                listen: null,
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
     */
    protected function fromManifest(OnDemandSettingsData $settings, array $containers, bool $isLaravel, ?QuadletFileData $main): OnDemandSuggestionData
    {
        $hasBackgroundWork = $settings->workers !== [] || $settings->idle_check !== null || $settings->schedule !== null;

        return new OnDemandSuggestionData(
            laravel: $isLaravel || $hasBackgroundWork,
            containers: $containers,
            settings: new OnDemandSettingsData(
                port: $settings->port,
                app_port: $settings->app_port ?? ($main !== null ? $this->appPort($main->content) : null),
                listen: $settings->listen,
                idle_timeout: $settings->idle_timeout,
                workers: $settings->workers,
                idle_check: $settings->idle_check,
                schedule: $settings->schedule,
            ),
            from_manifest: true,
        );
    }

    /**
     * The main unit is the pod (immich-pod.service is immich.pod) or the container (stry.service is stry.container).
     */
    protected function mainFileName(ApplicationData $application): string
    {
        if ($application->kind === 'pod') {
            return Str::beforeLast($application->unit, '-pod.service').'.pod';
        }

        return Str::beforeLast($application->unit, '.service').'.container';
    }

    /**
     * The port inside the container: ExposeHostPort=8000, or the last part of PublishPort=127.0.0.1:9090:8000.
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
