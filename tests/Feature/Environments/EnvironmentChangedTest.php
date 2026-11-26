<?php

declare(strict_types=1);

use Domain\Environments\Events\EnvironmentChanged;
use Domain\Environments\Models\Environment;
use Domain\Users\Models\User;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Event;
use Illuminate\Testing\TestResponse;
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

/**
 * Loads the deferred props of a page, as the browser does after it renders: they name their
 * live listeners in that reload.
 */
function loadDeferred(string $url, string $props): TestResponse
{
    $page = test()->get($url)->inertiaPage();

    return test()->get($url, [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => $page['version'],
        'X-Inertia-Partial-Component' => $page['component'],
        'X-Inertia-Partial-Data' => $props,
    ])->assertSuccessful();
}

it('lets the owner listen on the channels of a host', function (string $topic) {
    useReverb();

    $this->actingAs($this->owner)
        ->post('/broadcasting/auth', ['socket_id' => '1234.5678', 'channel_name' => "private-environments.{$this->environment->ulid}.{$topic}"])
        ->assertSuccessful()
        ->assertJsonStructure(['auth']);
})->with([
    'a list' => 'apps',
    'one application' => 'apps-0e63ac3dd74b7e27',
]);

it('forbids other users from listening on the channels of a host', function () {
    useReverb();

    $this->actingAs(User::factory()->create())
        ->post('/broadcasting/auth', ['socket_id' => '1234.5678', 'channel_name' => "private-environments.{$this->environment->ulid}.apps"])
        ->assertForbidden();
});

it('names a channel after the read it reloads, hashing the name in it', function () {
    $ulid = $this->environment->ulid;

    expect(EnvironmentChanged::channelName($this->environment, 'apps'))->toBe("environments.{$ulid}.apps")
        ->and(EnvironmentChanged::channelName($this->environment, 'apps.hello'))->toBe("environments.{$ulid}.apps-0e63ac3dd74b7e27")
        // A dot in the name would take the channel apart.
        ->and(EnvironmentChanged::channelName($this->environment, 'apps.hello.world'))->toBe("environments.{$ulid}.apps-548dd3196653d14a");
});

it('broadcasts once after a write, on the channels of every read it changed', function () {
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

    $ulid = $this->environment->ulid;

    Event::assertDispatchedTimes(EnvironmentChanged::class, 1);
    Event::assertDispatched(EnvironmentChanged::class, function (EnvironmentChanged $event) use ($ulid): bool {
        $channels = array_map(fn ($channel): string => $channel->name, $event->broadcastOn());

        return in_array("private-environments.{$ulid}.secrets", $channels, true)
            && in_array("private-environments.{$ulid}.apps", $channels, true)
            && in_array("private-environments.{$ulid}.apps-0e63ac3dd74b7e27", $channels, true)
            && ! in_array("private-environments.{$ulid}.settings", $channels, true);
    });
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

it('reloads the props of a list when the reads they show change', function () {
    fakeLpod();
    $ulid = $this->environment->ulid;

    $this->actingAs($this->owner);

    loadDeferred(action([ApplicationController::class, 'index'], $this->environment), 'applications,host,images')
        ->assertJsonPath('liveProps.applications.listeners', [[
            'channel' => ['name' => "environments.{$ulid}.apps", 'type' => 'private'],
            'events' => ['environment.changed'],
        ]])
        ->assertJsonPath('liveProps.host.listeners.0.channel.name', "environments.{$ulid}.host")
        ->assertJsonMissingPath('liveProps.images');
});

it('reloads the props of an application page only on the changes to that application', function () {
    fakeLpod();
    $ulid = $this->environment->ulid;

    $this->actingAs($this->owner);

    loadDeferred(action([ApplicationController::class, 'show'], [$this->environment, 'hello']), 'application,files,logs')
        ->assertJsonPath('liveProps.application.listeners.0.channel.name', "environments.{$ulid}.apps-0e63ac3dd74b7e27")
        ->assertJsonPath('liveProps.files.listeners.0.channel.name', "environments.{$ulid}.apps-0e63ac3dd74b7e27")
        // The logs only reload on the page's own poll.
        ->assertJsonMissingPath('liveProps.logs');
});
