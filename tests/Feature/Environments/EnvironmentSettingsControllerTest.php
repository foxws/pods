<?php

declare(strict_types=1);

use Domain\Environments\Models\Environment;
use Domain\Users\Models\User;
use Integrations\Ssh\HostResult;
use Modules\Web\Environments\Controllers\EnvironmentSettingsController;

beforeEach(function () {
    $this->owner = User::factory()->create()->assignRole('super-admin');
    $this->environment = Environment::factory()->create();
});

it('shows the auto-update timer and lingering of a host', function () {
    $lpod = fakeLpod();

    $this->actingAs($this->owner)
        ->get(action([EnvironmentSettingsController::class, 'show'], $this->environment))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('Environments/EnvironmentSettings')
            ->loadDeferredProps(fn ($reload) => $reload
                ->where('settings.data.auto_update', true)
                ->where('settings.data.schedule', 'daily')
                ->where('settings.data.rollback', true)
                ->where('settings.data.linger', true)
                ->where('settings.data.editable', true)));

    $lpod->assertRan(['settings', '--json']);
});

it('falls back to the host stats, read-only, when lpod has no settings command yet', function () {
    $lpod = fakeLpod()->respond('settings --json', new HostResult(125, '', 'Error: unknown command "settings" for "lpod"'));

    $this->actingAs($this->owner)
        ->get(action([EnvironmentSettingsController::class, 'show'], $this->environment))
        ->assertInertia(fn ($page) => $page->loadDeferredProps(fn ($reload) => $reload
            ->where('settings.error', null)
            ->where('settings.data.editable', false)
            ->where('settings.data.auto_update', true)
            ->where('settings.data.schedule', null)
            ->where('settings.data.linger', true)
            ->where('settings.data.quadlet_dir', '~/.config/containers/systemd')));

    $lpod->assertRan(['host', '--json']);
});

it('leaves lingering unknown when lpod host does not report it', function () {
    $host = json_decode(lpodFixture('host'), true);
    unset($host['system']);

    fakeLpod()
        ->respond('settings --json', new HostResult(125, '', 'Error: unknown command "settings" for "lpod"'))
        ->respond('host --json', json_encode($host));

    $this->actingAs($this->owner)
        ->get(action([EnvironmentSettingsController::class, 'show'], $this->environment))
        ->assertInertia(fn ($page) => $page->loadDeferredProps(fn ($reload) => $reload
            ->where('settings.data.linger', null)
            ->where('settings.data.quadlet_dir', null)));
});

it('shows the error when lpod fails to read the settings', function () {
    fakeLpod()->respond('settings --json', new HostResult(1, '', 'Failed to connect to bus'));

    $this->actingAs($this->owner)
        ->get(action([EnvironmentSettingsController::class, 'show'], $this->environment))
        ->assertInertia(fn ($page) => $page->loadDeferredProps(fn ($reload) => $reload
            ->where('settings.data', null)
            ->where('settings.error', fn (string $error) => str_contains($error, 'Failed to connect to bus'))));
});

it('updates the settings of a host', function () {
    $lpod = fakeLpod();

    $this->actingAs($this->owner)
        ->from(action([EnvironmentSettingsController::class, 'show'], $this->environment))
        ->put(action([EnvironmentSettingsController::class, 'update'], $this->environment), [
            'auto_update' => true,
            'schedule' => 'Mon..Fri 04:00',
            'rollback' => false,
            'linger' => true,
        ])
        ->assertRedirect(action([EnvironmentSettingsController::class, 'show'], $this->environment))
        ->assertInertiaFlash('type', 'success');

    $lpod->assertRan(['settings', 'set', '--auto-update', 'on', '--schedule', 'Mon..Fri 04:00', '--rollback', 'off', '--linger', 'on', '--json']);
});

it('refuses a schedule that lpod could read as an option', function (string $schedule) {
    $lpod = fakeLpod();

    $this->actingAs($this->owner)
        ->put(action([EnvironmentSettingsController::class, 'update'], $this->environment), [
            'auto_update' => true,
            'schedule' => $schedule,
            'rollback' => true,
            'linger' => true,
        ])
        ->assertSessionHasErrors('schedule');

    $lpod->assertNothingRan();
})->with(['--help', 'daily; reboot', '$(reboot)']);

it('shows the error when lpod cannot save the settings', function () {
    fakeLpod()->respond('settings set *', new HostResult(1, '', 'Failed to enable linger'));

    $this->actingAs($this->owner)
        ->put(action([EnvironmentSettingsController::class, 'update'], $this->environment), [
            'auto_update' => false,
            'schedule' => 'daily',
            'rollback' => true,
            'linger' => true,
        ])
        ->assertRedirect()
        ->assertInertiaFlash('type', 'error');
});

it('forbids users who are not the owner', function () {
    $lpod = fakeLpod();
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(action([EnvironmentSettingsController::class, 'show'], $this->environment))
        ->assertForbidden();

    $this->actingAs($user)
        ->put(action([EnvironmentSettingsController::class, 'update'], $this->environment), [
            'auto_update' => true,
            'schedule' => 'daily',
            'rollback' => true,
            'linger' => true,
        ])
        ->assertForbidden();

    $lpod->assertNothingRan();
});
