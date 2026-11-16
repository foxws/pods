<?php

declare(strict_types=1);

use Domain\Containers\Enums\ContainerOperation;
use Domain\Environments\Models\Environment;
use Domain\Users\Models\User;
use Integrations\Ssh\HostResult;
use Modules\Web\Containers\Controllers\ContainerController;
use Modules\Web\Containers\Controllers\ContainerOperationController;
use Modules\Web\Containers\Controllers\ContainerPruneController;

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

it('asks to update lpod when it does not know the command yet', function () {
    fakeLpod()->respond('containers --json', new HostResult(125, '', "Error: unknown flag: --json\nSee 'podman --help'"));

    $this->actingAs($this->owner)
        ->get(action([ContainerController::class, 'index'], $this->environment))
        ->assertInertia(fn ($page) => $page->loadDeferredProps(fn ($reload) => $reload
            ->where('containers.data', null)
            ->where('containers.error', 'The lpod on this host doesn\'t support "lpod containers --json" yet. Update lpod to a release that implements the whole of contract 1.')));
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
                ->has('logs.data', 5)
                ->where('quadlet.data.name', 'immich-server.container')));

    $lpod->assertRan(['container', 'immich-server', 'inspect', '--json']);
    $lpod->assertRan(['immich', 'files', '--json']);
});

it('shows no quadlet for a container started outside Quadlet', function () {
    $payload = json_decode(lpodFixture('container-inspect'), true);
    $payload['container'] = [...$payload['container'], 'name' => 'reverent_hopper', 'application' => null, 'quadlet' => null];

    $lpod = fakeLpod()->respond('container * inspect --json', json_encode($payload));

    $this->actingAs($this->owner)
        ->get(action([ContainerController::class, 'show'], [$this->environment, 'reverent_hopper']))
        ->assertInertia(fn ($page) => $page->loadDeferredProps(['default', 'details'], fn ($reload) => $reload
            ->where('quadlet.data', null)
            ->where('quadlet.error', null)));

    expect(collect($lpod->recorded())->pluck('arguments')->filter(fn (array $arguments): bool => in_array('files', $arguments, true)))
        ->toBeEmpty();
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

it('only samples the stats of a container when the page asks for them', function () {
    $lpod = fakeLpod();

    $this->actingAs($this->owner)
        ->get(action([ContainerController::class, 'show'], [$this->environment, 'immich-server']))
        ->assertInertia(fn ($page) => $page
            ->missing('stats')
            ->reloadOnly('stats', fn ($reload) => $reload
                ->where('stats.data.cpu', 6.21)
                ->where('stats.data.memory_limit', 4294967296)
                ->where('stats.data.net_in', 812345678)
                ->where('stats.data.sampled_at', '2026-11-15T12:04:30+00:00')));

    $lpod->assertRan(['container', 'immich-server', 'stats', '--json']);
});

it('prunes the stopped containers', function () {
    $lpod = fakeLpod();

    $this->actingAs($this->owner)
        ->post(action(ContainerPruneController::class, $this->environment))
        ->assertRedirect()
        ->assertInertiaFlash('type', 'success');

    $lpod->assertRan(['containers', 'prune', '--json']);
});

it('forbids pruning containers for users who are not the owner', function () {
    $lpod = fakeLpod();

    $this->actingAs(User::factory()->create())
        ->post(action(ContainerPruneController::class, $this->environment))
        ->assertForbidden();

    $lpod->assertNothingRan();
});
