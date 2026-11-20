<?php

declare(strict_types=1);

use Domain\Environments\Models\Environment;
use Domain\Users\Models\User;
use Integrations\Ssh\HostResult;
use Modules\Web\Volumes\Controllers\VolumeController;
use Modules\Web\Volumes\Controllers\VolumePruneController;

beforeEach(function () {
    $this->owner = User::factory()->create()->assignRole('super-admin');
    $this->environment = Environment::factory()->create();
});

it('lists the volumes', function () {
    fakeLpod();

    $this->actingAs($this->owner)
        ->get(action([VolumeController::class, 'index'], $this->environment))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('Volumes/VolumeIndex')
            ->loadDeferredProps(fn ($reload) => $reload
                ->has('volumes.data', 6)
                ->where('volumes.data.3.type', 'nfs')->where('volumes.data.4.type', 'device')->where('volumes.data.5.applications', [])));
});

it('gives the sizes of the volumes in a group of their own', function () {
    $lpod = fakeLpod();

    $this->actingAs($this->owner)
        ->get(action([VolumeController::class, 'index'], $this->environment))
        ->assertInertia(fn ($page) => $page
            ->missing('sizes')
            ->loadDeferredProps('sizes', fn ($reload) => $reload
                ->where('sizes.data.immich-library', 48318382080)
                ->where('sizes.data.vaultwarden-data', 2359296)
                ->missing('sizes.data./var/mnt/photos')
                ->where('sizes.error', null)));

    $lpod->assertRan(['volumes', 'sizes', '--json']);
});

it('gives no sizes when the host lpod has no volumes sizes yet', function () {
    // An older lpod answers "volumes sizes" with its plain volume list.
    fakeLpod()->respond('volumes sizes --json', json_encode(['contract' => 1, 'volumes' => []]));

    $this->actingAs($this->owner)
        ->get(action([VolumeController::class, 'index'], $this->environment))
        ->assertInertia(fn ($page) => $page
            ->loadDeferredProps('sizes', fn ($reload) => $reload->where('sizes.data', [])->where('sizes.error', null)));
});

it('still lists the volumes when their sizes fail', function () {
    fakeLpod()->respond('volumes sizes --json', new HostResult(1, '', 'du: cannot access'));

    $this->actingAs($this->owner)
        ->get(action([VolumeController::class, 'index'], $this->environment))
        ->assertInertia(fn ($page) => $page
            ->loadDeferredProps(['default', 'sizes'], fn ($reload) => $reload
                ->has('volumes.data', 6)
                ->where('sizes.data', null)
                ->where('sizes.error', 'lpod volumes sizes --json failed: du: cannot access')));
});

it('removes a volume', function () {
    $lpod = fakeLpod();

    $this->actingAs($this->owner)
        ->delete(action([VolumeController::class, 'destroy'], [$this->environment, 'uptime-kuma-data']))
        ->assertRedirect()
        ->assertInertiaFlash('type', 'success');

    $lpod->assertRan(['volume', 'rm', 'uptime-kuma-data', '--json']);
});

it('flashes the error when a volume is in use', function () {
    fakeLpod()->respond('volume rm * --json', new HostResult(2, '', 'Error: uptime-kuma-data is being used by a container'));

    $this->actingAs($this->owner)
        ->delete(action([VolumeController::class, 'destroy'], [$this->environment, 'uptime-kuma-data']))
        ->assertRedirect()
        ->assertInertiaFlash('type', 'error');
});

it('rejects volume names that look like options', function () {
    $lpod = fakeLpod();

    $this->actingAs($this->owner)
        ->delete("/actions/environments/{$this->environment->ulid}/volumes/--force")
        ->assertNotFound();

    $lpod->assertNothingRan();
});

it('prunes unused volumes', function () {
    $lpod = fakeLpod();

    $this->actingAs($this->owner)
        ->post(action(VolumePruneController::class, $this->environment))
        ->assertRedirect()
        ->assertInertiaFlash('type', 'success');

    $lpod->assertRan(['volumes', 'prune', '--json']);
});

it('forbids users who are not the owner', function () {
    $lpod = fakeLpod();

    $this->actingAs(User::factory()->create())
        ->delete(action([VolumeController::class, 'destroy'], [$this->environment, 'uptime-kuma-data']))
        ->assertForbidden();

    $lpod->assertNothingRan();
});
