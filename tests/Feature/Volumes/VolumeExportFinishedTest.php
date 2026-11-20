<?php

declare(strict_types=1);

use Domain\Volumes\Models\VolumeExport;
use Domain\Volumes\Notifications\VolumeExportFinished;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('backups');
});

it('is stored before it is broadcast', function () {
    $export = VolumeExport::factory()->completed()->create();

    expect((new VolumeExportFinished($export))->via($export->user))->toBe(['database', 'broadcast']);
});

it('stores a finished export with a link to the volumes of its host', function () {
    $export = VolumeExport::factory()->completed()->create();

    expect((new VolumeExportFinished($export))->toDatabase($export->user))->toMatchArray([
        'title' => "{$export->volume} is exported",
        'level' => 'success',
        'url' => route('environments.volumes.index', $export->environment, absolute: false),
        'environment' => $export->environment->name,
    ]);
});

it('stores why an export failed', function () {
    $export = VolumeExport::factory()->failed('No volume named immich-library.')->create();

    expect((new VolumeExportFinished($export))->toDatabase($export->user))->toMatchArray([
        'title' => "{$export->volume} wasn't exported",
        'message' => 'No volume named immich-library.',
        'level' => 'error',
    ]);
});
