<?php

declare(strict_types=1);

use Domain\Environments\Models\Environment;
use Domain\Users\Models\User;
use Integrations\Ssh\HostResult;
use Modules\Web\Environments\Controllers\EnvironmentLpodController;
use Modules\Web\Environments\Controllers\EnvironmentSettingsController;

beforeEach(function () {
    $this->owner = User::factory()->create()->assignRole('super-admin');
    $this->environment = Environment::factory()->create(['facts' => ['contract' => 1, 'lpod' => '4.0.0-rc.1']]);
    config(['services.pods.lpod_version' => 'v4.0.0']);
});

it('offers to update lpod when the host has another version than Pods needs', function (?string $installed, string $required, bool $updateAvailable) {
    fakeLpod();
    config(['services.pods.lpod_version' => $required]);
    $this->environment->update(['facts' => $installed !== null ? ['contract' => 1, 'lpod' => $installed] : null]);

    $this->actingAs($this->owner)
        ->get(action([EnvironmentSettingsController::class, 'show'], $this->environment))
        ->assertInertia(fn ($page) => $page
            ->where('lpod.installed', $installed)
            ->where('lpod.required', $required)
            ->where('lpod.update_available', $updateAvailable));
})->with([
    'an older release' => ['4.0.0-rc.1', 'v4.0.0-rc.2', true],
    'the same release' => ['4.0.0-rc.2', 'v4.0.0-rc.2', false],
    'a newer release, which stays' => ['4.1.0', 'v4.0.0', false],
    'a build from a branch' => ['dev-main', 'v4.0.0', true],
    'the branch Pods needs' => ['dev-main', 'main', false],
    'a host never verified' => [null, 'v4.0.0', true],
]);

it('updates lpod to the version Pods needs and stores what the host reports', function () {
    $lpod = fakeLpod();

    $this->actingAs($this->owner)
        ->put(action([EnvironmentLpodController::class, 'update'], $this->environment))
        ->assertRedirect()
        ->assertInertiaFlash('type', 'success')
        ->assertInertiaFlash('title', 'lpod 4.0.0 installed');

    $lpod->assertRan(['self-update', 'v4.0.0', '--json']);
    expect(collect($lpod->recorded())->pluck('arguments')->search(['self-update', 'v4.0.0', '--json']))
        ->toBeLessThan(collect($lpod->recorded())->pluck('arguments')->search(['version', '--json']));
    expect($this->environment->refresh()->facts['lpod'])->toBe('4.0.0');
});

it('accepts what an older lpod prints instead of json', function () {
    fakeLpod()->respond('self-update * --json', new HostResult(0, "Installing lpod (v4.0.0) to /home/dev/.local/bin\n  Upgraded lpod 4.0.0-rc.1 to lpod 4.0.0\n", ''));

    $this->actingAs($this->owner)
        ->put(action([EnvironmentLpodController::class, 'update'], $this->environment))
        ->assertInertiaFlash('type', 'success');

    expect($this->environment->refresh()->facts['lpod'])->toBe('4.0.0');
});

it('says so when the host still reports the old version', function () {
    fakeLpod()->respond('version --json', json_encode(['contract' => 1, 'lpod' => '4.0.0-rc.1', 'podman' => '6.1.0', 'systemd' => '258', 'scope' => 'rootless', 'user' => 'dev', 'hostname' => 'core']));

    $this->actingAs($this->owner)
        ->put(action([EnvironmentLpodController::class, 'update'], $this->environment))
        ->assertInertiaFlash('type', 'error')
        ->assertInertiaFlash('description', 'The host still reports lpod 4.0.0-rc.1 instead of v4.0.0. Run "lpod self-update v4.0.0" on the host as this user to see why.');
});

it('shows the error when the self-update fails', function () {
    $lpod = fakeLpod()->respond('self-update * --json', new HostResult(1, '', "Could not download lpod v4.0.0: there's no such release."));

    $this->actingAs($this->owner)
        ->put(action([EnvironmentLpodController::class, 'update'], $this->environment))
        ->assertInertiaFlash('type', 'error')
        ->assertInertiaFlash('description', "lpod self-update v4.0.0 --json failed: Could not download lpod v4.0.0: there's no such release.");

    expect(collect($lpod->recorded())->pluck('arguments')->contains(['version', '--json']))->toBeFalse();
});

it('forbids users who are not the owner to update lpod', function () {
    $lpod = fakeLpod();

    $this->actingAs(User::factory()->create())
        ->put(action([EnvironmentLpodController::class, 'update'], $this->environment))
        ->assertForbidden();

    $lpod->assertNothingRan();
});

it('needs the lpod release install.sh installs', function () {
    preg_match('/^LPOD_REF="\$\{LPOD_REF:-(?<version>[^}]+)\}"$/m', file_get_contents(base_path('install.sh')), $matches);

    expect(require base_path('config/services.php'))
        ->toHaveKey('pods.lpod_version', $matches['version'] ?? null);
});
