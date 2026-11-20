<?php

declare(strict_types=1);

namespace Domain\Volumes\Commands;

use Domain\Environments\Models\Environment;
use Domain\Volumes\Enums\VolumeExportStatus;
use Domain\Volumes\Jobs\ExportVolume;
use Domain\Volumes\Models\VolumeExport;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

use function Laravel\Prompts\info;

class PruneExportsCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'pods:prune-exports';

    /**
     * @var string
     */
    protected $description = 'Remove old volume exports, keeping the newest archives of each volume';

    public function handle(): int
    {
        $this->failUnfinished();

        $removed = $this->pruneOldArchives() + $this->pruneFailed();

        $this->pruneOrphanedFolders();

        info("Removed {$removed} volume exports.");

        return self::SUCCESS;
    }

    /**
     * An export still waiting or running long after it could have finished lost its worker.
     */
    protected function failUnfinished(): void
    {
        VolumeExport::query()
            ->whereIn('status', [VolumeExportStatus::Pending, VolumeExportStatus::Running])
            ->where('created_at', '<', now()->subSeconds(ExportVolume::exportTimeout() * 2))
            ->each(function (VolumeExport $export): void {
                $export->deleteArchive();

                $export->update([
                    'status' => VolumeExportStatus::Failed,
                    'path' => null,
                    'error' => __('The export stopped before it finished.'),
                    'finished_at' => now(),
                ]);
            });
    }

    protected function pruneOldArchives(): int
    {
        $kept = max(1, (int) config('services.pods.volume_exports_kept'));
        $removed = 0;

        VolumeExport::query()
            ->where('status', VolumeExportStatus::Completed)
            ->latest('id')
            ->get()
            ->groupBy(fn (VolumeExport $export): string => "{$export->environment_id}/{$export->volume}")
            ->each(function (Collection $exports) use ($kept, &$removed): void {
                $exports->slice($kept)->each(function (VolumeExport $export) use (&$removed): void {
                    $export->delete();
                    $removed++;
                });
            });

        return $removed;
    }

    protected function pruneFailed(): int
    {
        $removed = 0;

        VolumeExport::query()
            ->where('status', VolumeExportStatus::Failed)
            ->where('created_at', '<', now()->subWeek())
            ->each(function (VolumeExport $export) use (&$removed): void {
                $export->delete();
                $removed++;
            });

        return $removed;
    }

    /**
     * Removing a host removes its exports' rows, but not their folders.
     */
    protected function pruneOrphanedFolders(): void
    {
        $environments = Environment::query()->pluck('ulid')->map(fn ($ulid): string => (string) $ulid)->all();

        foreach (VolumeExport::disk()->directories() as $directory) {
            if (! in_array($directory, $environments, true)) {
                VolumeExport::disk()->deleteDirectory($directory);
            }
        }
    }
}
