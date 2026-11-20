<?php

declare(strict_types=1);

use Domain\Volumes\Enums\VolumeExportStatus;
use Domain\Volumes\Jobs\ExportVolume;
use Domain\Volumes\Models\VolumeExport;
use Domain\Volumes\Notifications\VolumeExportFinished;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Integrations\Ssh\HostResult;

beforeEach(function () {
    Storage::fake('backups');
});

it('streams the volume into an archive and tells the user it is ready', function () {
    $lpod = fakeLpod();
    $export = VolumeExport::factory()->create();

    app()->call([new ExportVolume($export), 'handle']);

    $export->refresh();

    expect($export)
        ->status->toBe(VolumeExportStatus::Completed)
        ->path->toBe($export->archivePath())
        ->size->toBe(strlen('tar archive of the volume'))
        ->finished_at->not->toBeNull();

    Storage::disk('backups')->assertExists($export->path, 'tar archive of the volume');
    $lpod->assertRan(['volume', 'export', 'immich-library', '--json']);
    Notification::assertSentTo($export->user, VolumeExportFinished::class, fn (VolumeExportFinished $notification): bool => $notification->export->is($export));
});

it('stops the applications that use the volume while it exports, when asked', function () {
    $lpod = fakeLpod();
    $export = VolumeExport::factory()->create(['stop_applications' => true]);

    app()->call([new ExportVolume($export), 'handle']);

    $lpod->assertRan(['volume', 'export', 'immich-library', '--stop', '--json']);
});

it('keeps lpod\'s error and no archive when the export fails', function () {
    fakeLpod()->respond('volume export *', new HostResult(1, 'partial', 'jellyfin-library is backed by /var/mnt/media, outside Podman\'s storage, so lpod doesn\'t export it.'));
    $export = VolumeExport::factory()->create(['volume' => 'jellyfin-library']);

    app()->call([new ExportVolume($export), 'handle']);

    $export->refresh();

    expect($export)
        ->status->toBe(VolumeExportStatus::Failed)
        ->path->toBeNull()
        ->error->toContain('outside Podman\'s storage');

    Storage::disk('backups')->assertMissing($export->archivePath());
    Notification::assertSentTo($export->user, VolumeExportFinished::class);
});

it('fails an export the worker gave up on, without its partial archive', function () {
    $export = VolumeExport::factory()->create(['status' => VolumeExportStatus::Running]);
    Storage::disk('backups')->put($export->archivePath(), 'half an archive');

    (new ExportVolume($export))->failed(null);

    expect($export->refresh())
        ->status->toBe(VolumeExportStatus::Failed)
        ->error->toBe('The export stopped before it finished.');

    Storage::disk('backups')->assertMissing($export->archivePath());
});

it('gives lpod its timeout before the worker\'s', function () {
    config(['services.pods.volume_export_timeout' => 1200]);

    expect((new ExportVolume(VolumeExport::factory()->create()))->timeout)->toBe(1260)
        ->and(config('queue.connections.database.retry_after'))->toBeGreaterThan(3600 + 60);
});
