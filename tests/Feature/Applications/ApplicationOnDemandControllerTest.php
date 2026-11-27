<?php

declare(strict_types=1);

use Domain\Applications\Actions\SuggestOnDemandSettings;
use Domain\Applications\DataObjects\ApplicationData;
use Domain\Applications\DataObjects\QuadletFileData;
use Domain\Applications\Models\Installation;
use Domain\Environments\Models\Environment;
use Domain\Quadlets\DataObjects\ManifestData;
use Domain\Users\Models\User;
use Integrations\Ssh\HostResult;
use Modules\Web\Applications\Controllers\ApplicationController;
use Modules\Web\Applications\Controllers\ApplicationOnDemandController;
use Modules\Web\Quadlets\Controllers\QuadletFetchController;

beforeEach(function () {
    $this->owner = User::factory()->create()->assignRole('super-admin');
    $this->environment = Environment::factory()->create();
});

/**
 * The Quadlet files of a Laravel application with a Horizon worker and a scheduler, as lpod APP files prints them.
 */
function laravelApplicationFiles(string $scheduleCommand = 'Environment=APP_COMMAND="php /app/artisan schedule:work"'): string
{
    $file = fn (string $name, string $content): array => ['name' => $name, 'path' => "/home/dev/.config/containers/systemd/hello/{$name}", 'content' => $content];

    return json_encode(['contract' => 1, 'files' => [
        $file('hello.container', "[Container]\nImage=ghcr.io/example/hello:main\nExposeHostPort=8000\n"),
        $file('hello.container.d/lpod-ondemand.conf', "[Container]\nPublishPort=\nPublishPort=127.0.0.1:18000:8000\n"),
        $file('hello-horizon.container', "[Container]\nEnvironment=APP_COMMAND=\"php -d variables_order=EGPCS /app/artisan horizon\"\n"),
        $file('hello-schedule.container', "[Container]\n{$scheduleCommand}\n"),
        $file('hello-valkey.container', "[Container]\nImage=docker.io/valkey/valkey:8\n"),
    ]]);
}

function laravelApplicationStatus(): string
{
    $status = json_decode(lpodFixture('app-status'), true);
    $status['application'] = [...$status['application'], 'name' => 'hello', 'unit' => 'hello.service', 'kind' => 'container'];

    return json_encode($status);
}

it('shows whether an application is on demand', function () {
    $lpod = fakeLpod();

    $this->actingAs($this->owner)
        ->get(action([ApplicationController::class, 'show'], [$this->environment, 'hello']))
        ->assertInertia(fn ($page) => $page->loadDeferredProps(fn ($reload) => $reload
            ->where('ondemand.data.enabled', true)
            ->where('ondemand.data.awake', false)
            ->where('ondemand.data.settings.port', 8000)
            ->where('ondemand.data.settings.workers', ['hello-horizon'])
            ->where('ondemand.data.settings.schedule.at', ['*:00', '01:00', '02:00'])));

    $lpod->assertRan(['hello', 'ondemand', 'status', '--json']);
});

it('suggests only the ports for an application that is not Laravel', function () {
    fakeLpod();

    $this->actingAs($this->owner)
        ->get(action([ApplicationController::class, 'show'], [$this->environment, 'immich']))
        ->assertInertia(fn ($page) => $page->loadDeferredProps('details', fn ($reload) => $reload
            ->where('ondemandSuggestion.data.laravel', false)
            ->where('ondemandSuggestion.data.settings.port', 2283)
            ->where('ondemandSuggestion.data.settings.app_port', 2283)
            ->where('ondemandSuggestion.data.settings.workers', [])
            ->where('ondemandSuggestion.data.settings.schedule', null)));
});

it('suggests the workers and the scheduler of a Laravel application', function () {
    $lpod = fakeLpod()
        ->respond('hello status --json', laravelApplicationStatus())
        ->respond('hello files --json', laravelApplicationFiles());

    $this->actingAs($this->owner)
        ->get(action([ApplicationController::class, 'show'], [$this->environment, 'hello']))
        ->assertInertia(fn ($page) => $page->loadDeferredProps('details', fn ($reload) => $reload
            ->where('files.data.0.name', 'hello.container')
            ->where('ondemandSuggestion.data.from_manifest', false)
            ->where('ondemandSuggestion.data.settings.port', 8001)
            ->where('ondemandSuggestion.data.laravel', true)
            ->where('ondemandSuggestion.data.containers', ['hello-horizon', 'hello-schedule', 'hello-valkey'])
            ->where('ondemandSuggestion.data.settings.app_port', 8000)
            ->where('ondemandSuggestion.data.settings.workers', ['hello-horizon'])
            ->where('ondemandSuggestion.data.settings.schedule.container', 'hello-schedule')
            ->where('ondemandSuggestion.data.settings.schedule.at', ['*:00'])
            ->where('ondemandSuggestion.data.settings.schedule.environment', ['APP_COMMAND' => 'php /app/artisan schedule:run'])));

    expect(collect($lpod->recorded())->where('arguments', ['hello', 'files', '--json']))->toHaveCount(1);
});

it('does not suggest a scheduler that runs schedule:work through Exec=', function () {
    fakeLpod()
        ->respond('hello status --json', laravelApplicationStatus())
        ->respond('hello files --json', laravelApplicationFiles('Exec=php artisan schedule:work'));

    $this->actingAs($this->owner)
        ->get(action([ApplicationController::class, 'show'], [$this->environment, 'hello']))
        ->assertInertia(fn ($page) => $page->loadDeferredProps('details', fn ($reload) => $reload
            ->where('ondemandSuggestion.data.laravel', true)
            ->where('ondemandSuggestion.data.settings.schedule', null)));
});

it('suggests the ondemand section of the stored pods.yml over the files', function () {
    fakeLpod()
        ->respond('hello status --json', laravelApplicationStatus())
        ->respond('hello files --json', laravelApplicationFiles());
    Installation::factory()->for($this->environment)->create([
        'application' => 'hello',
        'manifest' => <<<'YAML'
            ondemand:
              port: 8000
              app_port: 8080
              idle_timeout: 60min
              workers: [hello-horizon]
              idle_check:
                container: hello-horizon
                user: docker
                command: php /app/artisan app:idle
              schedule:
                container: hello-schedule
                at: ["*:00", "01:00"]
                environment:
                  APP_COMMAND: php /app/artisan schedule:run
            YAML,
    ]);

    $this->actingAs($this->owner)
        ->get(action([ApplicationController::class, 'show'], [$this->environment, 'hello']))
        ->assertInertia(fn ($page) => $page->loadDeferredProps('details', fn ($reload) => $reload
            ->where('ondemandSuggestion.data.from_manifest', true)
            ->where('ondemandSuggestion.data.containers', ['hello-horizon', 'hello-schedule', 'hello-valkey'])
            ->where('ondemandSuggestion.data.settings.port', 8000)
            ->where('ondemandSuggestion.data.settings.app_port', 8080)
            ->where('ondemandSuggestion.data.settings.idle_timeout', '60min')
            ->where('ondemandSuggestion.data.settings.idle_check', ['container' => 'hello-horizon', 'user' => 'docker', 'command' => 'php /app/artisan app:idle'])
            ->where('ondemandSuggestion.data.settings.schedule.at', ['*:00', '01:00'])));
});

it('reads a port the pods.yml leaves out from the files, and shows the workers it names', function () {
    fakeLpod();
    Installation::factory()->for($this->environment)->create([
        'application' => 'immich',
        'manifest' => "ondemand:\n  port: 2283\n  workers: [immich-ml]\n",
    ]);

    $this->actingAs($this->owner)
        ->get(action([ApplicationController::class, 'show'], [$this->environment, 'immich']))
        ->assertInertia(fn ($page) => $page->loadDeferredProps('details', fn ($reload) => $reload
            ->where('ondemandSuggestion.data.from_manifest', true)
            ->where('ondemandSuggestion.data.laravel', true)
            ->where('ondemandSuggestion.data.settings.app_port', 2283)
            ->where('ondemandSuggestion.data.settings.idle_timeout', '10min')
            ->where('ondemandSuggestion.data.settings.workers', ['immich-ml'])));
});

it('refuses a pods.yml with on-demand settings lpod would refuse', function (string $ondemand, string $error) {
    fakeLpod()->respond('quadlet fetch *', new HostResult(0, json_encode([
        'contract' => 1,
        'commit' => 'a41f9c2',
        'manifest' => "ondemand:\n{$ondemand}",
        'files' => [],
    ]), ''));

    $this->actingAs($this->owner)
        ->postJson(action(QuadletFetchController::class, $this->environment), [
            'repository' => 'https://codeberg.org/foxws/quadlets.git',
            'reference' => 'main',
        ])
        ->assertJsonPath('data.manifest', null)
        ->assertJsonPath('data.manifest_error', fn (string $message): bool => str_contains($message, $error));
})->with([
    'a port lpod cannot add 10000 to' => ["  port: 60000\n", 'ondemand.port'],
    'an idle timeout that is no time span' => ["  idle_timeout: ten minutes\n", 'ondemand.idle timeout'],
    'an idle check without workers' => ["  idle_check:\n    container: app\n    command: app idle\n", 'ondemand.workers'],
    'a scheduler variable with a $' => ["  schedule:\n    container: app-schedule\n    at: ['*:00']\n    environment:\n      APP_COMMAND: echo \$HOME\n", 'ondemand.schedule.environment.APP_COMMAND'],
    'a scheduler variable that is no name' => ["  schedule:\n    container: app-schedule\n    at: ['*:00']\n    environment:\n      1APP: x\n", 'environment variable'],
]);

it('turns on demand on for an application', function () {
    $lpod = fakeLpod();

    $this->actingAs($this->owner)
        ->put(action([ApplicationOnDemandController::class, 'update'], [$this->environment, 'immich']), [
            'port' => 2283,
            'app_port' => 2283,
            'listen' => null,
            'idle_timeout' => '10min',
        ])
        ->assertRedirect()
        ->assertInertiaFlash('type', 'success');

    $lpod->assertRan(['immich', 'ondemand', 'enable', '--port', '2283', '--app-port', '2283', '--idle-timeout', '10min', '--json']);
});

it('passes the workers, idle check and scheduler of a Laravel application', function () {
    $lpod = fakeLpod();

    $this->actingAs($this->owner)
        ->put(action([ApplicationOnDemandController::class, 'update'], [$this->environment, 'hello']), [
            'port' => 9000,
            'app_port' => 8000,
            'listen' => null,
            'idle_timeout' => '1h',
            'workers' => ['hello-horizon'],
            'idle_container' => 'hello-horizon',
            'idle_user' => 'docker',
            'idle_command' => 'php /app/artisan app:idle',
            'schedule' => 'hello-schedule',
            'schedule_at' => ['*:00', '02:00'],
            'schedule_env' => ['APP_COMMAND=php /app/artisan schedule:run'],
        ])
        ->assertInertiaFlash('type', 'success');

    $lpod->assertRan([
        'hello', 'ondemand', 'enable', '--port', '9000', '--app-port', '8000', '--idle-timeout', '1h',
        '--worker', 'hello-horizon',
        '--idle-container', 'hello-horizon', '--idle-user', 'docker', '--idle-command', 'php /app/artisan app:idle',
        '--schedule', 'hello-schedule', '--schedule-at', '*:00', '--schedule-at', '02:00',
        '--schedule-env', 'APP_COMMAND=php /app/artisan schedule:run',
        '--json',
    ]);
});

it('refuses settings lpod would refuse or misread', function (array $settings, string $field) {
    $lpod = fakeLpod();

    $this->actingAs($this->owner)
        ->put(action([ApplicationOnDemandController::class, 'update'], [$this->environment, 'hello']), [
            'port' => 9000,
            'app_port' => 8000,
            'listen' => null,
            'idle_timeout' => '10min',
            ...$settings,
        ])
        ->assertSessionHasErrors($field);

    $lpod->assertNothingRan();
})->with([
    'a listen address that is no IP address' => [['listen' => '--help'], 'listen'],
    'a socket port without room for the local port' => [['port' => 60000], 'port'],
    'an idle timeout that is no time span' => [['idle_timeout' => '--help'], 'idle_timeout'],
    'an idle check without workers' => [['idle_container' => 'hello-horizon', 'idle_command' => 'true'], 'workers'],
    'a worker name lpod could read as an option' => [['workers' => ['--schedule']], 'workers.0'],
    'a scheduler without times' => [['schedule' => 'hello-schedule'], 'schedule_at'],
    'a time that is not *:MM or HH:MM' => [['schedule' => 'hello-schedule', 'schedule_at' => ['24:00']], 'schedule_at.0'],
    'an environment value with $' => [['schedule' => 'hello-schedule', 'schedule_at' => ['*:00'], 'schedule_env' => ['APP_COMMAND=$(reboot)']], 'schedule_env.0'],
]);

it('shows the error when lpod cannot turn on demand on', function () {
    fakeLpod()->respond('* ondemand enable *', new HostResult(1, '', 'On-demand needs a container or pod as the main unit'));

    $this->actingAs($this->owner)
        ->put(action([ApplicationOnDemandController::class, 'update'], [$this->environment, 'hello']), [
            'port' => 9000,
            'app_port' => 8000,
            'listen' => null,
            'idle_timeout' => '10min',
        ])
        ->assertInertiaFlash('type', 'error')
        ->assertInertiaFlash('description', 'lpod hello ondemand enable --port 9000 --app-port 8000 --idle-timeout 10min --json failed: On-demand needs a container or pod as the main unit');
});

it('turns on demand off for an application', function () {
    $lpod = fakeLpod();

    $this->actingAs($this->owner)
        ->delete(action([ApplicationOnDemandController::class, 'destroy'], [$this->environment, 'hello']))
        ->assertRedirect()
        ->assertInertiaFlash('type', 'success');

    $lpod->assertRan(['hello', 'ondemand', 'disable', '--json']);
});

it('forbids users who are not the owner to change on demand', function () {
    $lpod = fakeLpod();
    $user = User::factory()->create();

    $this->actingAs($user)
        ->put(action([ApplicationOnDemandController::class, 'update'], [$this->environment, 'hello']), ['port' => 9000, 'app_port' => 8000, 'listen' => null, 'idle_timeout' => '10min'])
        ->assertForbidden();

    $this->actingAs($user)
        ->delete(action([ApplicationOnDemandController::class, 'destroy'], [$this->environment, 'hello']))
        ->assertForbidden();

    $lpod->assertNothingRan();
});

it('keeps the socket on the address it is given', function () {
    $lpod = fakeLpod();

    $this->actingAs($this->owner)
        ->put(action([ApplicationOnDemandController::class, 'update'], [$this->environment, 'immich']), [
            'port' => 2283,
            'app_port' => 2283,
            'listen' => '127.0.0.1',
            'idle_timeout' => '10min',
        ])
        ->assertInertiaFlash('type', 'success');

    $lpod->assertRan(['immich', 'ondemand', 'enable', '--port', '2283', '--app-port', '2283', '--listen', '127.0.0.1', '--idle-timeout', '10min', '--json']);
});

it('refuses settings that leave the listen address out', function () {
    $lpod = fakeLpod();

    $this->actingAs($this->owner)
        ->put(action([ApplicationOnDemandController::class, 'update'], [$this->environment, 'immich']), ['port' => 2283, 'app_port' => 2283, 'idle_timeout' => '10min'])
        ->assertSessionHasErrors('listen');

    $lpod->assertNothingRan();
});

it('suggests the address the main unit publishes on', function (string $published, ?string $manifest, ?string $listen) {
    $application = new ApplicationData(...[
        ...ApplicationData::fromLpod(json_decode(lpodFixture('app-status'), true)['application'])->all(),
        'kind' => 'container',
        'unit' => 'hello.service',
    ]);
    $files = [new QuadletFileData('hello.container', '/units/hello.container', "[Container]\n{$published}")];

    $suggestion = app(SuggestOnDemandSettings::class)($application, $files, $manifest === null ? null : ManifestData::fromYaml($manifest));

    expect($suggestion->settings->listen)->toBe($listen);
})->with([
    'loopback' => ["PublishPort=127.0.0.1:8080:80\n", null, '127.0.0.1'],
    'loopback over IPv6' => ["PublishPort=[::1]:8080:80\n", null, '::1'],
    'loopback with a random port' => ["PublishPort=127.0.0.1::80/tcp\n", null, '127.0.0.1'],
    'every address' => ["PublishPort=8080:80\n", null, null],
    'every address, named' => ["PublishPort=0.0.0.0:8080:80\n", null, null],
    'one of two on every address' => ["PublishPort=127.0.0.1:8080:80\nPublishPort=8443:443\n", null, null],
    'nothing published' => ["ExposeHostPort=80\n", null, null],
    'a pods.yml over the unit' => ["PublishPort=8080:80\n", "ondemand:\n  listen: 127.0.0.1\n", '127.0.0.1'],
    'a pods.yml without an address' => ["PublishPort=127.0.0.1:8080:80\n", "ondemand:\n  port: 8080\n", '127.0.0.1'],
]);

it('suggests the port the main unit publishes while no other application has it, or else the first free one', function (string $unit, array $otherPorts, ?string $manifest, int $port) {
    $application = new ApplicationData(...[
        ...ApplicationData::fromLpod(json_decode(lpodFixture('app-status'), true)['application'])->all(),
        'kind' => 'container',
        'unit' => 'hello.service',
    ]);
    $other = new ApplicationData(...[...$application->all(), 'name' => 'other', 'unit' => 'other.service', 'ports' => $otherPorts]);
    $files = [new QuadletFileData('hello.container', '/units/hello.container', "[Container]\n{$unit}")];

    $suggestion = app(SuggestOnDemandSettings::class)($application, $files, $manifest === null ? null : ManifestData::fromYaml($manifest), [$application, $other]);

    expect($suggestion->settings->port)->toBe($port);
})->with([
    'a published port' => ["PublishPort=127.0.0.1:9393:8000\n", [], null, 9393],
    'a published port without an address' => ["PublishPort=9393:8000/tcp\n", [], null, 9393],
    'nothing published' => ["ExposeHostPort=8000\n", [], null, 8000],
    'a port Podman picks' => ["PublishPort=127.0.0.1::8000\n", [], null, 8000],
    'a published port another application has' => ["PublishPort=9393:8000\n", ['0.0.0.0:9393->80/tcp', '0.0.0.0:8000-8001->8000-8001/tcp'], null, 8002],
    'a port whose local port another application has' => ["ExposeHostPort=8000\n", ['127.0.0.1:18000->8000/tcp'], null, 8001],
    'a port only taken over UDP' => ["ExposeHostPort=8000\n", ['0.0.0.0:8000->8000/udp'], null, 8000],
    'the port of a pods.yml' => ["PublishPort=9393:8000\n", [], "ondemand:\n  port: 8080\n", 8080],
    'a pods.yml without a port' => ["PublishPort=9393:8000\n", [], "ondemand:\n  idle_timeout: 1h\n", 9393],
]);

it('refuses a socket port another application has', function (int $port, string $message) {
    $lpod = fakeLpod()->respond('apps --json', json_encode(['contract' => 1, 'applications' => [
        ...json_decode(lpodFixture('apps'), true)['applications'],
        ['name' => 'sleeper', 'unit' => 'sleeper.service', 'status' => 'inactive', 'ports' => ['127.0.0.1:19000->8000/tcp']],
    ]]));

    $this->actingAs($this->owner)
        ->put(action([ApplicationOnDemandController::class, 'update'], [$this->environment, 'immich']), [
            'port' => $port,
            'app_port' => 2283,
            'listen' => null,
            'idle_timeout' => '10min',
        ])
        ->assertSessionHasErrors(['port' => $message]);

    expect(collect($lpod->recorded())->pluck('arguments')->contains(fn (array $arguments): bool => in_array('enable', $arguments, true)))->toBeFalse();
})->with([
    'the port itself' => [8080, 'vaultwarden publishes port 8080 already.'],
    'its local port' => [9000, 'sleeper publishes port 19000 already, which the app gets on 127.0.0.1.'],
]);

it('leaves a socket port to lpod when the applications cannot be read', function () {
    $lpod = fakeLpod()->respond('apps --json', new HostResult(255, '', 'ssh: connect to host'));

    $this->actingAs($this->owner)
        ->put(action([ApplicationOnDemandController::class, 'update'], [$this->environment, 'immich']), [
            'port' => 8080,
            'app_port' => 2283,
            'listen' => null,
            'idle_timeout' => '10min',
        ])
        ->assertSessionHasNoErrors();

    $lpod->assertRan(['immich', 'ondemand', 'enable', '--port', '8080', '--app-port', '2283', '--idle-timeout', '10min', '--json']);
});
