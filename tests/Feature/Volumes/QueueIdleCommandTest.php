<?php

declare(strict_types=1);

use Domain\Volumes\Commands\QueueIdleCommand;
use Domain\Volumes\Jobs\ExportVolume;
use Domain\Volumes\Models\VolumeExport;
use Illuminate\Support\Facades\Queue;

it('is idle when no job is waiting', function () {
    $this->artisan(QueueIdleCommand::class)->assertSuccessful();
});

it('is busy while a job is waiting or running', function () {
    Queue::push(new ExportVolume(VolumeExport::factory()->create()));

    $this->artisan(QueueIdleCommand::class)->assertFailed();
});
