<?php

declare(strict_types=1);

use Domain\Environments\Models\Environment;
use Domain\Users\Models\User;
use Integrations\Ssh\HostResult;
use Modules\Web\Environments\Controllers\EnvironmentOverviewController;

beforeEach(function () {
    $this->owner = User::factory()->create()->assignRole('super-admin');
    $this->environment = Environment::factory()->create();
});

it('redirects guests to the login page', function () {
    $this->get(action(EnvironmentOverviewController::class, $this->environment))
        ->assertRedirect(route('login'));
});

it('forbids users who are not the owner', function () {
    $lpod = fakeLpod();

    $this->actingAs(User::factory()->create())
        ->get(action(EnvironmentOverviewController::class, $this->environment))
        ->assertForbidden();

    $lpod->assertNothingRan();
});

it('shows the host, its applications and its secrets', function () {
    fakeLpod();

    $this->actingAs($this->owner)
        ->get(action(EnvironmentOverviewController::class, $this->environment))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('Environments/EnvironmentOverview')
            ->where('environment.id', $this->environment->ulid)
            ->missing('host')
            ->loadDeferredProps(['default', 'secrets'], fn ($reload) => $reload
                ->where('host.data.cpu_load', [1.42, 0.98, 0.87])
                ->where('host.data.swap_total', 8589934592)
                ->where('host.data.os', 'Fedora Linux 45')
                ->where('host.data.linger', true)
                ->where('host.data.units_failed', 1)
                ->has('applications.data', 6)
                ->where('secrets.data.5.name', 'ha-mqtt-password')
                ->where('secrets.data.5.missing', true)
                ->has('images.data', 6)));
});

it('leaves the system details empty for an lpod that does not report them', function () {
    fakeLpod()->respond('host --json', json_encode([
        'contract' => 1,
        'cpu' => ['cores' => 4, 'usage' => 3.5],
        'memory' => ['used' => 1024, 'total' => 4096],
        'disk' => ['used' => 10, 'total' => 100],
        'containers' => ['running' => 1, 'total' => 1],
        'auto_update' => ['enabled' => false],
    ]));

    $this->actingAs($this->owner)
        ->get(action(EnvironmentOverviewController::class, $this->environment))
        ->assertInertia(fn ($page) => $page->loadDeferredProps(fn ($reload) => $reload
            ->where('host.data.cpu_cores', 4)
            ->where('host.data.cpu_load', null)
            ->where('host.data.swap_total', null)
            ->where('host.data.os', null)
            ->where('host.data.linger', null)
            ->where('host.data.units_active', null)
            ->where('host.data.auto_update_enabled', false)));
});

it('says the ssh client is missing instead of blaming lpod', function () {
    fakeLpod()->respond('host --json', new HostResult(127, '', "sh: 1: exec: ssh: not found\n"));

    $this->actingAs($this->owner)
        ->get(action(EnvironmentOverviewController::class, $this->environment))
        ->assertInertia(fn ($page) => $page->loadDeferredProps(fn ($reload) => $reload
            ->where('host.data', null)
            ->where('host.error', fn (string $error) => str_contains($error, 'no SSH client is installed'))));
});
