<?php

declare(strict_types=1);

use Domain\Environments\Models\Environment;
use Domain\Volumes\Commands\PruneExportsCommand;
use Domain\Volumes\Enums\VolumeExportStatus;
use Domain\Volumes\Models\VolumeExport;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('backups');
    config(['services.pods.volume_exports_kept' => 2]);

    $this->environment = Environment::factory()->create();
});

it('keeps the newest archives of each volume', function () {
    [$oldest, $older, $newest] = VolumeExport::factory()->for($this->environment)->completed()->count(3)->create();
    $other = VolumeExport::factory()->for($this->environment)->completed()->create(['volume' => 'vaultwarden-data']);

    $this->artisan(PruneExportsCommand::class)->assertSuccessful();

    $this->assertModelMissing($oldest);
    Storage::disk('backups')->assertMissing($oldest->path);
    $this->assertModelExists($older);
    $this->assertModelExists($newest);
    $this->assertModelExists($other);
});

it('removes failed exports after a week', function () {
    $old = VolumeExport::factory()->for($this->environment)->failed()->create(['created_at' => now()->subDays(8)]);
    $recent = VolumeExport::factory()->for($this->environment)->failed()->create();

    $this->artisan(PruneExportsCommand::class)->assertSuccessful();

    $this->assertModelMissing($old);
    $this->assertModelExists($recent);
});

it('fails exports whose worker went away', function () {
    $stale = VolumeExport::factory()->for($this->environment)->create(['status' => VolumeExportStatus::Running, 'created_at' => now()->subHours(3)]);
    $running = VolumeExport::factory()->for($this->environment)->create(['status' => VolumeExportStatus::Running]);

    $this->artisan(PruneExportsCommand::class)->assertSuccessful();

    expect($stale->refresh()->status)->toBe(VolumeExportStatus::Failed)
        ->and($running->refresh()->status)->toBe(VolumeExportStatus::Running);
});

it('removes the archives of hosts that are gone', function () {
    $kept = VolumeExport::factory()->for($this->environment)->completed()->create();
    Storage::disk('backups')->put('01JREMOVEDHOST0000000000000/immich-library/archive.tar', 'archive');

    $this->artisan(PruneExportsCommand::class)->assertSuccessful();

    Storage::disk('backups')->assertMissing('01JREMOVEDHOST0000000000000');
    Storage::disk('backups')->assertExists($kept->path);
});
