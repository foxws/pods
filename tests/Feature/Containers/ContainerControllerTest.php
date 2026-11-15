<?php

declare(strict_types=1);

use Domain\Containers\Enums\ContainerOperation;
use Domain\Environments\Models\Environment;
use Domain\Users\Models\User;
use Integrations\Ssh\HostResult;
use Modules\Web\Containers\Controllers\ContainerController;
use Modules\Web\Containers\Controllers\ContainerOperationController;

beforeEach(function () {
    $this->owner = User::factory()->create()->assignRole('super-admin');
    $this->environment = Environment::factory()->create();
});

it('lists every container, including ones no quadlet manages', function () {
    fakeLpod();

    $this->actingAs($this->owner)
        ->get(action([ContainerController::class, 'index'], $this->environment))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('Containers/ContainerIndex')
            ->loadDeferredProps(fn ($reload) => $reload
                ->has('containers.data', 6)
                ->where('containers.data.0.application', 'immich')
                ->where('containers.data.3.exit_code', 125)
                ->where('containers.data.5.name', 'reverent_hopper')
                ->where('containers.data.5.quadlet', null)
                ->where('host.data.containers_running', 12)));
});

it('shows a container with its mounts, secrets and logs', function () {
    $lpod = fakeLpod();

    $this->actingAs($this->owner)
        ->get(action([ContainerController::class, 'show'], [$this->environment, 'immich-server']))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('Containers/ContainerShow')
            ->where('name', 'immich-server')
            ->loadDeferredProps(['default', 'details'], fn ($reload) => $reload
                ->where('container.data.container.unit', 'immich-server.service')
                ->where('container.data.memory_limit', 4294967296)
                ->has('container.data.mounts', 2)
                ->where('container.data.secrets.0', ['name' => 'immich-db-password', 'type' => 'env', 'target' => 'DB_PASSWORD'])
                ->has('logs.data', 5)));

    $lpod->assertRan(['container', 'immich-server', 'inspect', '--json']);
});

it('shows the add container form with the networks and secrets to pick from', function () {
    fakeLpod();

    $this->actingAs($this->owner)
        ->get(action([ContainerController::class, 'create'], $this->environment))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('Containers/ContainerCreate')
            ->loadDeferredProps(fn ($reload) => $reload
                ->has('networks.data', 5)
                ->has('secrets.data', 6)
                ->has('applications.data', 6)));
});

it('runs a container operation', function (ContainerOperation $operation) {
    $lpod = fakeLpod();

    $this->actingAs($this->owner)
        ->post(action(ContainerOperationController::class, [$this->environment, 'reverent_hopper', $operation]))
        ->assertRedirect()
        ->assertInertiaFlash('type', 'success');

    $lpod->assertRan(['container', 'reverent_hopper', $operation->value, '--json']);
})->with(ContainerOperation::cases());

it('flashes the error when a container operation fails', function () {
    fakeLpod()->respond('container * stop --json', new HostResult(1, '', 'Error: no container with name or ID "ghost" found'));

    $this->actingAs($this->owner)
        ->post(action(ContainerOperationController::class, [$this->environment, 'ghost', 'stop']))
        ->assertRedirect()
        ->assertInertiaFlash('type', 'error');
});

it('rejects container names that look like options', function () {
    $lpod = fakeLpod();

    $this->actingAs($this->owner)
        ->post("/actions/environments/{$this->environment->ulid}/containers/--all/stop")
        ->assertNotFound();

    $lpod->assertNothingRan();
});

it('forbids users who are not the owner', function () {
    $lpod = fakeLpod();

    $this->actingAs(User::factory()->create())
        ->post(action(ContainerOperationController::class, [$this->environment, 'immich-server', 'stop']))
        ->assertForbidden();

    $this->actingAs(User::factory()->create())
        ->get(action([ContainerController::class, 'index'], $this->environment))
        ->assertForbidden();

    $lpod->assertNothingRan();
});
