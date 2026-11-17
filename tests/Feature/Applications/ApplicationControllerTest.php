<?php

declare(strict_types=1);

use Domain\Environments\Models\Environment;
use Domain\Users\Models\User;
use Integrations\Ssh\HostResult;
use Modules\Web\Applications\Controllers\ApplicationController;

beforeEach(function () {
    $this->owner = User::factory()->create()->assignRole('super-admin');
    $this->environment = Environment::factory()->create();
});

it('redirects guests to the login page', function () {
    $this->get(action([ApplicationController::class, 'index'], $this->environment))
        ->assertRedirect(route('login'));
});

it('forbids users who are not the owner', function () {
    $lpod = fakeLpod();

    $this->actingAs(User::factory()->create())
        ->get(action([ApplicationController::class, 'index'], $this->environment))
        ->assertForbidden();

    $lpod->assertNothingRan();
});

it('lists the applications and host stats of an environment', function () {
    fakeLpod();

    $this->actingAs($this->owner)
        ->get(action([ApplicationController::class, 'index'], $this->environment))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('Applications/ApplicationIndex')
            ->where('environment.id', $this->environment->ulid)
            ->missing('applications')
            ->loadDeferredProps(['default', 'images'], fn ($reload) => $reload
                ->has('applications.data', 6)
                ->where('images.data.3.update', '13')
                ->where('applications.data.0.name', 'immich')
                ->where('applications.data.0.description', 'Self-hosted photo library')
                ->where('applications.data.4.status', 'failed')
                ->where('applications.data.4.error', 'Secret ha-mqtt-password does not exist')
                ->where('applications.error', null)
                ->where('host.data.containers_running', 12)
                ->where('host.data.cpu_cores', 8)));
});

it('shows the error in place when lpod fails', function () {
    fakeLpod()->respond('apps --json', new HostResult(255, '', 'ssh: connect to host podman-01 port 22: Connection refused'));

    $this->actingAs($this->owner)
        ->get(action([ApplicationController::class, 'index'], $this->environment))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page->loadDeferredProps(fn ($reload) => $reload
            ->where('applications.data', null)
            ->where('applications.error', fn (string $error) => str_contains($error, 'Connection refused'))));
});

it('refuses an lpod that speaks another contract', function () {
    fakeLpod()->respond('apps --json', '{"contract": 2, "applications": []}');

    $this->actingAs($this->owner)
        ->get(action([ApplicationController::class, 'index'], $this->environment))
        ->assertInertia(fn ($page) => $page->loadDeferredProps(fn ($reload) => $reload
            ->where('applications.data', null)
            ->where('applications.error', fn (string $error) => str_contains($error, 'contract 2'))));
});

it('shows an application with its containers, files and journal', function () {
    $lpod = fakeLpod();

    $this->actingAs($this->owner)
        ->get(action([ApplicationController::class, 'show'], [$this->environment, 'immich']))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('Applications/ApplicationShow')
            ->where('name', 'immich')
            ->loadDeferredProps(['default', 'details'], fn ($reload) => $reload
                ->where('application.data.application.unit', 'immich-pod.service')
                ->has('application.data.items', 4)
                ->where('application.data.secrets', ['immich-db-password'])
                ->where('files.data.0.name', 'immich-server.container')
                ->has('logs.data', 5)
                ->has('secrets.data', 6)));

    $lpod->assertRan(['immich', 'status', '--json']);
});

it('does not route application names that could be read as options', function () {
    $lpod = fakeLpod();

    $this->actingAs($this->owner)
        ->get("/environments/{$this->environment->ulid}/applications/-rf")
        ->assertNotFound();

    $lpod->assertNothingRan();
});

it('shows the install page with the secrets that already exist', function () {
    fakeLpod();

    $this->actingAs($this->owner)
        ->get(action([ApplicationController::class, 'create'], $this->environment))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('Applications/ApplicationCreate')
            ->loadDeferredProps(fn ($reload) => $reload->has('secrets.data', 6)));
});

it('offers the applications of the catalog repositories on the install page', function () {
    $lpod = fakeLpod();

    $this->actingAs($this->owner)
        ->get(action([ApplicationController::class, 'create'], $this->environment))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->missing('catalog')
            ->loadDeferredProps('catalog', fn ($reload) => $reload
                ->has('catalog.data.applications', 2)
                ->where('catalog.data.applications.0.name', 'jellyfin')
                ->where('catalog.data.applications.0.repository', 'https://github.com/foxws/quadlets')
                ->where('catalog.data.applications.0.path', 'jellyfin')
                ->where('catalog.data.applications.0.manifest.author', 'francoism90')
                ->where('catalog.data.applications.0.manifest.icon', 'i-lucide-clapperboard')
                ->where('catalog.data.applications.0.manifest.sources', [])
                ->where('catalog.data.applications.1.manifest.label', 'MariaDB')
                ->where('catalog.data.applications.1.manifest.sources.0.path', 'mariadb/production')
                ->where('catalog.data.errors', ["scratch: pods.yml is ignored: it's version 2, but Pods reads version 1. Update Pods."])
                ->where('catalog.error', null)));

    $lpod->assertRan(['quadlet', 'catalog', 'https://github.com/foxws/quadlets', '--ref', 'main', '--json']);
});

it('shows why the catalog could not be read', function () {
    fakeLpod()->respond('quadlet catalog *', new HostResult(1, '', 'fatal: unable to access the repository'));

    $this->actingAs($this->owner)
        ->get(action([ApplicationController::class, 'create'], $this->environment))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->loadDeferredProps('catalog', fn ($reload) => $reload
                ->where('catalog.data', null)
                ->whereType('catalog.error', 'string')));
});

it('removes an application and the data it owns once confirmed', function () {
    $lpod = fakeLpod();

    $this->actingAs($this->owner)
        ->delete(action([ApplicationController::class, 'destroy'], [$this->environment, 'immich']), [
            'confirmation' => 'immich',
            'volumes' => true,
            'secrets' => true,
        ])
        ->assertRedirect(action([ApplicationController::class, 'index'], $this->environment))
        ->assertInertiaFlash('type', 'success');

    $lpod->assertRan(['immich', 'remove', '--volumes', '--secrets', '--json']);
});

it('does not remove an application without typing its name', function () {
    $lpod = fakeLpod();

    $this->actingAs($this->owner)
        ->delete(action([ApplicationController::class, 'destroy'], [$this->environment, 'immich']), ['confirmation' => 'immic'])
        ->assertSessionHasErrors(['confirmation' => 'Type the name of the application to confirm.']);

    $lpod->assertNothingRan();
});

it('forbids users who are not the owner to remove an application', function () {
    $lpod = fakeLpod();

    $this->actingAs(User::factory()->create())
        ->delete(action([ApplicationController::class, 'destroy'], [$this->environment, 'immich']), ['confirmation' => 'immich'])
        ->assertForbidden();

    $lpod->assertNothingRan();
});
