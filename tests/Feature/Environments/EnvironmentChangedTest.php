<?php

declare(strict_types=1);

use Domain\Environments\Events\EnvironmentChanged;
use Domain\Environments\Models\Environment;
use Domain\Users\Models\User;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Event;
use Modules\Web\Applications\Controllers\ApplicationController;
use Modules\Web\Applications\Controllers\ApplicationOperationController;
use Modules\Web\Quadlets\Controllers\QuadletController;

beforeEach(function () {
    $this->owner = User::factory()->create()->assignRole('super-admin');
    $this->environment = Environment::factory()->create();
});

/**
 * Tests broadcast with the "null" driver, which authorizes every channel, so switch to Reverb
 * and register the channels again on it.
 */
function useReverb(string $host = '127.0.0.1'): void
{
    config([
        'broadcasting.default' => 'reverb',
        'broadcasting.connections.reverb.key' => 'app-key',
        'broadcasting.connections.reverb.secret' => 'app-secret',
        'broadcasting.connections.reverb.app_id' => 'app-id',
        'broadcasting.connections.reverb.options.host' => $host,
        'broadcasting.connections.reverb.options.port' => 1,
        'broadcasting.connections.reverb.options.scheme' => 'http',
    ]);

    Broadcast::forgetDrivers();

    require base_path('routes/channels.php');
}

it('lets the owner listen on the channel of a host', function () {
    useReverb();

    $this->actingAs($this->owner)
        ->post('/broadcasting/auth', ['socket_id' => '1234.5678', 'channel_name' => "private-environments.{$this->environment->ulid}"])
        ->assertSuccessful()
        ->assertJsonStructure(['auth']);
});

it('forbids other users from listening on the channel of a host', function () {
    useReverb();

    $this->actingAs(User::factory()->create())
        ->post('/broadcasting/auth', ['socket_id' => '1234.5678', 'channel_name' => "private-environments.{$this->environment->ulid}"])
        ->assertForbidden();
});

it('broadcasts once on the channel of the host after a write', function () {
    Event::fake([EnvironmentChanged::class]);
    fakeLpod();

    // Creating the secret and installing are two lpod writes in one request.
    $this->actingAs($this->owner)
        ->post(action([QuadletController::class, 'store'], $this->environment), [
            'application' => 'hello',
            'files' => [['name' => 'hello.container', 'content' => "[Container]\nImage=docker.io/library/hello:1\n"]],
            'secrets' => [['name' => 'hello-app-key', 'driver' => 'file', 'value' => 'base64:hello']],
        ])
        ->assertRedirect();

    Event::assertDispatchedTimes(EnvironmentChanged::class, 1);
    Event::assertDispatched(
        EnvironmentChanged::class,
        fn (EnvironmentChanged $event) => $event->broadcastOn()->name === "private-environments.{$this->environment->ulid}",
    );
});

it('still finishes the write when the broadcaster is unreachable', function () {
    useReverb();
    $lpod = fakeLpod();

    $this->actingAs($this->owner)
        ->post(action(ApplicationOperationController::class, [$this->environment, 'hello', 'up']))
        ->assertRedirect()
        ->assertInertiaFlash('type', 'success');

    $lpod->assertRan(['hello', 'up', '--no-block', '--json']);
});

it('reloads the props of a host page when the host changes', function () {
    fakeLpod();
    $url = action([ApplicationController::class, 'index'], $this->environment);

    $page = $this->actingAs($this->owner)->get($url)->inertiaPage();

    // Deferred props name their listeners in the reload that loads them.
    $this->get($url, [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => $page['version'],
        'X-Inertia-Partial-Component' => $page['component'],
        'X-Inertia-Partial-Data' => 'applications,host,images',
    ])
        ->assertSuccessful()
        ->assertJsonPath('liveProps.applications.listeners', [[
            'channel' => ['name' => "environments.{$this->environment->ulid}", 'type' => 'private'],
            'events' => ['environment.changed'],
        ]])
        ->assertJsonPath('liveProps.host.listeners.0.events', ['environment.changed'])
        ->assertJsonMissingPath('liveProps.images');
});
