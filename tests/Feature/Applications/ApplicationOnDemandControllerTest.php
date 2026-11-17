<?php

declare(strict_types=1);

use Domain\Environments\Models\Environment;
use Domain\Users\Models\User;
use Integrations\Ssh\HostResult;
use Modules\Web\Applications\Controllers\ApplicationController;
use Modules\Web\Applications\Controllers\ApplicationOnDemandController;

beforeEach(function () {
    $this->owner = User::factory()->create()->assignRole('super-admin');
    $this->environment = Environment::factory()->create();
});

/**
 * The Quadlet files of a Laravel application with a Horizon worker and a scheduler, as lpod APP files prints them.
 */
function laravelApplicationFiles(string $scheduleCommand = 'Environment=APP_COMMAND="php /app/artisan schedule:work"'): string
{
    $file = fn (string $name, string $content): array => ['name' => $name, 'path' => "/home/dev/.config/containers/systemd/stry/{$name}", 'content' => $content];

    return json_encode(['contract' => 1, 'files' => [
        $file('stry.container', "[Container]\nImage=ghcr.io/francoism90/stry:main\nExposeHostPort=8000\n"),
        $file('stry.container.d/lpod-ondemand.conf', "[Container]\nPublishPort=\nPublishPort=127.0.0.1:18000:8000\n"),
        $file('stry-horizon.container', "[Container]\nEnvironment=APP_COMMAND=\"php -d variables_order=EGPCS /app/artisan horizon\"\n"),
        $file('stry-schedule.container', "[Container]\n{$scheduleCommand}\n"),
        $file('stry-valkey.container', "[Container]\nImage=docker.io/valkey/valkey:8\n"),
    ]]);
}

function laravelApplicationStatus(): string
{
    $status = json_decode(lpodFixture('app-status'), true);
    $status['application'] = [...$status['application'], 'name' => 'stry', 'unit' => 'stry.service', 'kind' => 'container'];

    return json_encode($status);
}

it('shows whether an application is on demand', function () {
    $lpod = fakeLpod();

    $this->actingAs($this->owner)
        ->get(action([ApplicationController::class, 'show'], [$this->environment, 'stry']))
        ->assertInertia(fn ($page) => $page->loadDeferredProps(fn ($reload) => $reload
            ->where('ondemand.data.enabled', true)
            ->where('ondemand.data.awake', false)
            ->where('ondemand.data.settings.port', 8000)
            ->where('ondemand.data.settings.workers', ['stry-horizon'])
            ->where('ondemand.data.settings.schedule.at', ['*:00', '01:00', '02:00'])));

    $lpod->assertRan(['stry', 'ondemand', 'status', '--json']);
});

it('suggests only the ports for an application that is not Laravel', function () {
    fakeLpod();

    $this->actingAs($this->owner)
        ->get(action([ApplicationController::class, 'show'], [$this->environment, 'immich']))
        ->assertInertia(fn ($page) => $page->loadDeferredProps('details', fn ($reload) => $reload
            ->where('ondemandSuggestion.data.laravel', false)
            ->where('ondemandSuggestion.data.settings.app_port', 2283)
            ->where('ondemandSuggestion.data.settings.workers', [])
            ->where('ondemandSuggestion.data.settings.schedule', null)));
});

it('suggests the workers and the scheduler of a Laravel application', function () {
    $lpod = fakeLpod()
        ->respond('stry status --json', laravelApplicationStatus())
        ->respond('stry files --json', laravelApplicationFiles());

    $this->actingAs($this->owner)
        ->get(action([ApplicationController::class, 'show'], [$this->environment, 'stry']))
        ->assertInertia(fn ($page) => $page->loadDeferredProps('details', fn ($reload) => $reload
            ->where('files.data.0.name', 'stry.container')
            ->where('ondemandSuggestion.data.laravel', true)
            ->where('ondemandSuggestion.data.containers', ['stry-horizon', 'stry-schedule', 'stry-valkey'])
            ->where('ondemandSuggestion.data.settings.app_port', 8000)
            ->where('ondemandSuggestion.data.settings.workers', ['stry-horizon'])
            ->where('ondemandSuggestion.data.settings.schedule.container', 'stry-schedule')
            ->where('ondemandSuggestion.data.settings.schedule.at', ['*:00'])
            ->where('ondemandSuggestion.data.settings.schedule.environment', ['APP_COMMAND' => 'php /app/artisan schedule:run'])));

    expect(collect($lpod->recorded())->where('arguments', ['stry', 'files', '--json']))->toHaveCount(1);
});

it('does not suggest a scheduler that runs schedule:work through Exec=', function () {
    fakeLpod()
        ->respond('stry status --json', laravelApplicationStatus())
        ->respond('stry files --json', laravelApplicationFiles('Exec=php artisan schedule:work'));

    $this->actingAs($this->owner)
        ->get(action([ApplicationController::class, 'show'], [$this->environment, 'stry']))
        ->assertInertia(fn ($page) => $page->loadDeferredProps('details', fn ($reload) => $reload
            ->where('ondemandSuggestion.data.laravel', true)
            ->where('ondemandSuggestion.data.settings.schedule', null)));
});

it('turns on demand on for an application', function () {
    $lpod = fakeLpod();

    $this->actingAs($this->owner)
        ->put(action([ApplicationOnDemandController::class, 'update'], [$this->environment, 'immich']), [
            'port' => 2283,
            'app_port' => 2283,
            'idle_timeout' => '10min',
        ])
        ->assertRedirect()
        ->assertInertiaFlash('type', 'success');

    $lpod->assertRan(['immich', 'ondemand', 'enable', '--port', '2283', '--app-port', '2283', '--idle-timeout', '10min', '--json']);
});

it('passes the workers, idle check and scheduler of a Laravel application', function () {
    $lpod = fakeLpod();

    $this->actingAs($this->owner)
        ->put(action([ApplicationOnDemandController::class, 'update'], [$this->environment, 'stry']), [
            'port' => 8000,
            'app_port' => 8000,
            'idle_timeout' => '1h',
            'workers' => ['stry-horizon'],
            'idle_container' => 'stry-horizon',
            'idle_user' => 'docker',
            'idle_command' => 'php /app/artisan app:idle',
            'schedule' => 'stry-schedule',
            'schedule_at' => ['*:00', '02:00'],
            'schedule_env' => ['APP_COMMAND=php /app/artisan schedule:run'],
        ])
        ->assertInertiaFlash('type', 'success');

    $lpod->assertRan([
        'stry', 'ondemand', 'enable', '--port', '8000', '--app-port', '8000', '--idle-timeout', '1h',
        '--worker', 'stry-horizon',
        '--idle-container', 'stry-horizon', '--idle-user', 'docker', '--idle-command', 'php /app/artisan app:idle',
        '--schedule', 'stry-schedule', '--schedule-at', '*:00', '--schedule-at', '02:00',
        '--schedule-env', 'APP_COMMAND=php /app/artisan schedule:run',
        '--json',
    ]);
});

it('refuses settings lpod would refuse or misread', function (array $settings, string $field) {
    $lpod = fakeLpod();

    $this->actingAs($this->owner)
        ->put(action([ApplicationOnDemandController::class, 'update'], [$this->environment, 'stry']), [
            'port' => 8000,
            'app_port' => 8000,
            'idle_timeout' => '10min',
            ...$settings,
        ])
        ->assertSessionHasErrors($field);

    $lpod->assertNothingRan();
})->with([
    'a socket port without room for the local port' => [['port' => 60000], 'port'],
    'an idle timeout that is no time span' => [['idle_timeout' => '--help'], 'idle_timeout'],
    'an idle check without workers' => [['idle_container' => 'stry-horizon', 'idle_command' => 'true'], 'workers'],
    'a worker name lpod could read as an option' => [['workers' => ['--schedule']], 'workers.0'],
    'a scheduler without times' => [['schedule' => 'stry-schedule'], 'schedule_at'],
    'a time that is not *:MM or HH:MM' => [['schedule' => 'stry-schedule', 'schedule_at' => ['24:00']], 'schedule_at.0'],
    'an environment value with $' => [['schedule' => 'stry-schedule', 'schedule_at' => ['*:00'], 'schedule_env' => ['APP_COMMAND=$(reboot)']], 'schedule_env.0'],
]);

it('shows the error when lpod cannot turn on demand on', function () {
    fakeLpod()->respond('* ondemand enable *', new HostResult(1, '', 'On-demand needs a container or pod as the main unit'));

    $this->actingAs($this->owner)
        ->put(action([ApplicationOnDemandController::class, 'update'], [$this->environment, 'stry']), [
            'port' => 8000,
            'app_port' => 8000,
            'idle_timeout' => '10min',
        ])
        ->assertInertiaFlash('type', 'error')
        ->assertInertiaFlash('description', 'lpod stry ondemand enable --port 8000 --app-port 8000 --idle-timeout 10min --json failed: On-demand needs a container or pod as the main unit');
});

it('turns on demand off for an application', function () {
    $lpod = fakeLpod();

    $this->actingAs($this->owner)
        ->delete(action([ApplicationOnDemandController::class, 'destroy'], [$this->environment, 'stry']))
        ->assertRedirect()
        ->assertInertiaFlash('type', 'success');

    $lpod->assertRan(['stry', 'ondemand', 'disable', '--json']);
});

it('forbids users who are not the owner to change on demand', function () {
    $lpod = fakeLpod();
    $user = User::factory()->create();

    $this->actingAs($user)
        ->put(action([ApplicationOnDemandController::class, 'update'], [$this->environment, 'stry']), ['port' => 8000, 'app_port' => 8000, 'idle_timeout' => '10min'])
        ->assertForbidden();

    $this->actingAs($user)
        ->delete(action([ApplicationOnDemandController::class, 'destroy'], [$this->environment, 'stry']))
        ->assertForbidden();

    $lpod->assertNothingRan();
});
