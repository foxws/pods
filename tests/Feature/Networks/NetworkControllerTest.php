<?php

declare(strict_types=1);

use Domain\Environments\Models\Environment;
use Domain\Users\Models\User;
use Integrations\Ssh\HostResult;
use Modules\Web\Networks\Controllers\NetworkController;
use Modules\Web\Networks\Controllers\NetworkPruneController;

beforeEach(function () {
    $this->owner = User::factory()->create()->assignRole('super-admin');
    $this->environment = Environment::factory()->create();
});

it('lists the networks', function () {
    fakeLpod();

    $this->actingAs($this->owner)
        ->get(action([NetworkController::class, 'index'], $this->environment))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('Networks/NetworkIndex')
            ->loadDeferredProps(fn ($reload) => $reload
                ->has('networks.data', 5)
                ->where('networks.data.0.default', true)->where('networks.data.2.applications', ['vaultwarden', 'jellyfin'])));
});

it('removes a network', function () {
    $lpod = fakeLpod();

    $this->actingAs($this->owner)
        ->delete(action([NetworkController::class, 'destroy'], [$this->environment, 'proxy']))
        ->assertRedirect()
        ->assertInertiaFlash('type', 'success');

    $lpod->assertRan(['network', 'rm', 'proxy', '--json']);
});

it('flashes the error when a network is in use', function () {
    fakeLpod()->respond('network rm * --json', new HostResult(2, '', 'Error: proxy is being used by a container'));

    $this->actingAs($this->owner)
        ->delete(action([NetworkController::class, 'destroy'], [$this->environment, 'proxy']))
        ->assertRedirect()
        ->assertInertiaFlash('type', 'error');
});

it('rejects network names that look like options', function () {
    $lpod = fakeLpod();

    $this->actingAs($this->owner)
        ->delete("/actions/environments/{$this->environment->ulid}/networks/--force")
        ->assertNotFound();

    $lpod->assertNothingRan();
});

it('prunes unused networks', function () {
    $lpod = fakeLpod();

    $this->actingAs($this->owner)
        ->post(action(NetworkPruneController::class, $this->environment))
        ->assertRedirect()
        ->assertInertiaFlash('type', 'success');

    $lpod->assertRan(['networks', 'prune', '--json']);
});

it('forbids users who are not the owner', function () {
    $lpod = fakeLpod();

    $this->actingAs(User::factory()->create())
        ->delete(action([NetworkController::class, 'destroy'], [$this->environment, 'proxy']))
        ->assertForbidden();

    $lpod->assertNothingRan();
});
