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
            'installed_at' => now(),
            'checked_at' => now(),
        ]);
    }

    /**
     * Updates the checksums of files edited in Pods. The application's other files, its source
     * and what was changed outside Pods stay; an application Pods doesn't track stays untracked.
     *
     * @param  list<array{name: string, content: string}>  $files
     */
    public function edited(Environment $environment, string $application, array $files): void
    {
        $installation = $environment->installations()->firstWhere('application', $application);

        if ($installation === null) {
            return;
        }

        $checksums = Installation::checksumsOf($files);
        $changes = collect($installation->changes ?? [])
            ->map(fn (array $names): array => array_values(array_diff($names, array_keys($checksums))));

        $installation->update([
            'checksums' => [...$installation->checksums, ...$checksums],
            'changes' => $changes->flatten()->isEmpty() ? null : $changes->all(),
        ]);
    }
}
