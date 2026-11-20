<?php

declare(strict_types=1);

use Domain\Environments\Models\Environment;
use Domain\Users\Models\User;
use Domain\Volumes\Enums\VolumeExportStatus;
use Domain\Volumes\Jobs\ExportVolume;
use Domain\Volumes\Models\VolumeExport;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Integrations\Ssh\HostResult;
use Modules\Web\Volumes\Controllers\VolumeController;
use Modules\Web\Volumes\Controllers\VolumeExportController;

beforeEach(function () {
    Storage::fake('backups');

    $this->owner = User::factory()->create()->assignRole('super-admin');
    $this->environment = Environment::factory()->create();
});

it('queues an export of a volume', function () {
    $lpod = fakeLpod();

    $this->actingAs($this->owner)
        ->post(action([VolumeExportController::class, 'store'], $this->environment), [
            'volumes' => ['immich-library'],
            'stop_applications' => true,
        ])
        ->assertRedirect()
        ->assertInertiaFlash('type', 'success');

    $export = VolumeExport::sole();

    expect($export)
        ->volume->toBe('immich-library')
        ->status->toBe(VolumeExportStatus::Pending)
        ->stop_applications->toBeTrue()
        ->user_id->toBe($this->owner->id)
        ->environment_id->toBe($this->environment->id);

    Bus::assertDispatched(ExportVolume::class, fn (ExportVolume $job): bool => $job->export->is($export));
    $lpod->assertNothingRan();
});

it('queues an export of every volume of an application in Podman\'s storage', function () {
    fakeLpod();

    // immich also bind-mounts /var/mnt/photos, which isn't a volume.
    $this->actingAs($this->owner)
        ->post(action([VolumeExportController::class, 'store'], $this->environment), ['application' => 'immich'])
        ->assertRedirect()
        ->assertInertiaFlash('type', 'success');

    expect(VolumeExport::pluck('volume')->all())->toBe(['immich-library']);
    Bus::assertDispatched(ExportVolume::class, 1);
});

it('says so when an application has no volume to export', function () {
    fakeLpod();

    // jellyfin's volumes are an NFS share and a folder on the host.
    $this->actingAs($this->owner)
        ->post(action([VolumeExportController::class, 'store'], $this->environment), ['application' => 'jellyfin'])
        ->assertRedirect()
        ->assertInertiaFlash('type', 'warning');

    expect(VolumeExport::count())->toBe(0);
    Bus::assertNothingDispatched();
});

it('flashes the error when the volumes of an application cannot be read', function () {
    fakeLpod()->respond('volumes --json', new HostResult(1, '', 'Error: podman is not running'));

    $this->actingAs($this->owner)
        ->post(action([VolumeExportController::class, 'store'], $this->environment), ['application' => 'immich'])
        ->assertRedirect()
        ->assertInertiaFlash('type', 'error');

    Bus::assertNothingDispatched();
});

it('leaves out a volume that is already being exported', function () {
    fakeLpod();
    VolumeExport::factory()->for($this->environment)->create(['volume' => 'immich-library', 'status' => VolumeExportStatus::Running]);

    $this->actingAs($this->owner)
        ->post(action([VolumeExportController::class, 'store'], $this->environment), ['volumes' => ['immich-library']])
        ->assertRedirect()
        ->assertInertiaFlash('type', 'info');

    expect(VolumeExport::count())->toBe(1);
    Bus::assertNothingDispatched();
});

it('rejects volume names that look like options', function () {
    $this->actingAs($this->owner)
        ->post(action([VolumeExportController::class, 'store'], $this->environment), ['volumes' => ['--all']])
        ->assertSessionHasErrors('volumes.0');

    Bus::assertNothingDispatched();
});

it('forbids users who are not the owner', function () {
    $this->actingAs(User::factory()->create())
        ->post(action([VolumeExportController::class, 'store'], $this->environment), ['volumes' => ['immich-library']])
        ->assertForbidden();

    Bus::assertNothingDispatched();
});

it('lists the exports of the host on the volumes page', function () {
    fakeLpod();
    $export = VolumeExport::factory()->for($this->environment)->completed()->create();
    VolumeExport::factory()->create();

    $this->actingAs($this->owner)
        ->get(action([VolumeController::class, 'index'], $this->environment))
        ->assertInertia(fn ($page) => $page
            ->has('exports', 1)
            ->where('exports.0.id', $export->ulid)
            ->where('exports.0.status', 'completed')
            ->where('exports.0.size', 7)
            ->where('exports.0.download_url', fn (string $url): bool => str_contains($url, 'signature=')));
});

it('downloads an archive from a signed link', function () {
    $export = VolumeExport::factory()->for($this->environment)->completed('tar bytes')->create();

    $url = URL::temporarySignedRoute('actions.environments.volume-exports.download', now()->addDay(), [$this->environment, $export]);

    $response = $this->get($url)->assertSuccessful();

    expect($response->streamedContent())->toBe('tar bytes')
        ->and($response->headers->get('Content-Disposition'))->toContain("immich-library-{$export->created_at->format('Y-m-d-His')}.tar");
});

it('refuses a download link without a valid signature', function () {
    $export = VolumeExport::factory()->for($this->environment)->completed()->create();

    $this->actingAs($this->owner)
        ->get(action([VolumeExportController::class, 'download'], [$this->environment, $export]))
        ->assertForbidden();
});

it('only downloads an export from its own host', function () {
    $export = VolumeExport::factory()->completed()->create();

    $this->get(URL::temporarySignedRoute('actions.environments.volume-exports.download', now()->addDay(), [$this->environment, $export]))
        ->assertNotFound();
});

it('has nothing to download before an export is ready', function () {
    $export = VolumeExport::factory()->for($this->environment)->create(['status' => VolumeExportStatus::Running]);

    $this->get(URL::temporarySignedRoute('actions.environments.volume-exports.download', now()->addDay(), [$this->environment, $export]))
        ->assertNotFound();
});

it('removes an export with its archive', function () {
    $export = VolumeExport::factory()->for($this->environment)->completed()->create();

    $this->actingAs($this->owner)
        ->delete(action([VolumeExportController::class, 'destroy'], [$this->environment, $export]))
        ->assertRedirect()
        ->assertInertiaFlash('type', 'success');

    $this->assertModelMissing($export);
    Storage::disk('backups')->assertMissing($export->path);
});

it('keeps an export that is still running', function () {
    $export = VolumeExport::factory()->for($this->environment)->create(['status' => VolumeExportStatus::Running]);

    $this->actingAs($this->owner)
        ->delete(action([VolumeExportController::class, 'destroy'], [$this->environment, $export]))
        ->assertConflict();

    $this->assertModelExists($export);
});
