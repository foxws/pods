<?php

declare(strict_types=1);

namespace Domain\Applications\Actions;

use Domain\Applications\Models\Installation;
use Domain\Environments\Models\Environment;

class TrackInstallation
{
    /**
     * Remembers the files Pods just installed, and where they came from. Installing again
     * (with --replace) starts over, so a source the new files didn't come from is dropped.
     *
     * @param  list<array{name: string, content: string}>  $files
     * @param  array{repository: string, reference: string, path: string, commit: ?string, manifest: ?string, manifest_path: ?string}|null  $source
     */
    public function __invoke(Environment $environment, string $application, array $files, ?array $source = null): Installation
    {
        return Installation::query()->updateOrCreate([
            'environment_id' => $environment->getKey(),
            'application' => $application,
        ], [
            'repository' => $source['repository'] ?? null,
            'reference' => $source['reference'] ?? null,
            'path' => $source['path'] ?? null,
            'commit' => $source['commit'] ?? null,
            'manifest' => $source['manifest'] ?? null,
            'manifest_path' => $source['manifest_path'] ?? null,
            'checksums' => Installation::checksumsOf($files),
            'changes' => null,
            'updated_from' => null,
            'installed_at' => now(),
            'checked_at' => now(),
        ]);
    }

    /**
     * Updates the checksums of files edited in Pods. The application's other files, its source
     * and what was changed outside Pods stay; an application Pods doesn't track stays untracked,
     * and so do local drop-ins.
     *
     * @param  list<array{name: string, content: string}>  $files
     */
    public function edited(Environment $environment, string $application, array $files): void
    {
        $checksums = Installation::checksumsOf(array_filter($files, fn (array $file): bool => SyncInstallation::isTracked($file['name'])));

        $this->updateChecksums($environment, $application, fn (array $installed): array => [...$installed, ...$checksums], array_keys($checksums));
    }

    /**
     * Forgets the checksums of files removed in Pods, so they don't count as removed outside Pods.
     *
     * @param  list<string>  $names
     */
    public function removed(Environment $environment, string $application, array $names): void
    {
        $this->updateChecksums($environment, $application, fn (array $installed): array => array_diff_key($installed, array_flip($names)), $names);
    }

    /**
     * @param  callable(array<string, string>): array<string, string>  $checksums
     * @param  list<string>  $names  the files that are no longer changed outside Pods
     */
    protected function updateChecksums(Environment $environment, string $application, callable $checksums, array $names): void
    {
        $installation = $environment->installations()->firstWhere('application', $application);

        if ($installation === null || $names === []) {
            return;
        }

        $changes = collect($installation->changes ?? [])
            ->map(fn (array $changed): array => array_values(array_diff($changed, $names)));

        $installation->update([
            'checksums' => $checksums($installation->checksums),
            'changes' => $changes->flatten()->isEmpty() ? null : $changes->all(),
        ]);
    }
}
