<?php

declare(strict_types=1);

namespace Domain\Volumes\Actions;

use Domain\Environments\Models\Environment;
use Domain\Users\Models\User;
use Domain\Volumes\Enums\VolumeExportStatus;
use Domain\Volumes\Jobs\ExportVolume;
use Domain\Volumes\Models\VolumeExport;
use Illuminate\Support\Collection;

class QueueVolumeExports
{
    /**
     * Queues an export of each volume, one after another, leaving out volumes that already have
     * one waiting or running.
     *
     * @param  list<string>  $volumes
     * @return Collection<int, VolumeExport> the exports it queued
     */
    public function __invoke(Environment $environment, User $user, array $volumes, bool $stopApplications = false): Collection
    {
        $unfinished = $environment->volumeExports()
            ->whereIn('status', [VolumeExportStatus::Pending, VolumeExportStatus::Running])
            ->pluck('volume')
            ->all();

        return collect($volumes)
            ->unique()
            ->reject(fn (string $volume): bool => in_array($volume, $unfinished, true))
            ->values()
            ->map(function (string $volume) use ($environment, $user, $stopApplications): VolumeExport {
                $export = $environment->volumeExports()->create([
                    'user_id' => $user->getKey(),
                    'volume' => $volume,
                    'stop_applications' => $stopApplications,
                ]);

                ExportVolume::dispatch($export);

                return $export;
            });
    }
}
