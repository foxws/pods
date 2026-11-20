<?php

declare(strict_types=1);

namespace Domain\Volumes\Jobs;

use Domain\Volumes\Enums\VolumeExportStatus;
use Domain\Volumes\Models\VolumeExport;
use Domain\Volumes\Notifications\VolumeExportFinished;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Integrations\Lpod\Exceptions\LpodException;
use Integrations\Lpod\LpodClient;
use Throwable;

/**
 * Streams a volume from its host into an archive on the backups disk, which can take far longer
 * than a request may, and tells the user who asked how it went.
 */
class ExportVolume implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout;

    public bool $failOnTimeout = true;

    public function __construct(
        public VolumeExport $export,
    ) {
        // lpod's own timeout comes first, so a slow export fails with lpod's error rather than the worker's.
        $this->timeout = static::exportTimeout() + 60;
    }

    public static function exportTimeout(): int
    {
        return (int) config('services.pods.volume_export_timeout');
    }

    public function handle(LpodClient $lpod): void
    {
        $export = $this->export;
        $path = $export->archivePath();

        $export->update([
            'status' => VolumeExportStatus::Running,
            'started_at' => now(),
        ]);

        VolumeExport::disk()->makeDirectory(dirname($path));

        try {
            $lpod->exportVolume($export->environment, $export->volume, VolumeExport::disk()->path($path), $export->stop_applications, static::exportTimeout());
        } catch (LpodException $exception) {
            // An export lpod refused or couldn't finish is an outcome to show, not a job to retry.
            $this->failed($exception);

            return;
        }

        $export->update([
            'status' => VolumeExportStatus::Completed,
            'path' => $path,
            'size' => VolumeExport::disk()->size($path),
            'finished_at' => now(),
        ]);

        $this->notify();
    }

    /**
     * Also runs when the job times out or the worker gives up on it, so an export never stays
     * running, and a partial archive is never kept.
     */
    public function failed(?Throwable $exception): void
    {
        VolumeExport::disk()->delete($this->export->archivePath());

        $this->export->update([
            'status' => VolumeExportStatus::Failed,
            'path' => null,
            'size' => null,
            'error' => $exception?->getMessage() ?: __('The export stopped before it finished.'),
            'finished_at' => now(),
        ]);

        $this->notify();
    }

    protected function notify(): void
    {
        // Reverb may be asleep with an on-demand Pods; the Volumes page shows the outcome anyway.
        rescue(fn () => $this->export->user?->notify(new VolumeExportFinished($this->export)), report: false);
    }
}
