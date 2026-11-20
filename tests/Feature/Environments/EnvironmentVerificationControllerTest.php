<?php

declare(strict_types=1);

use Domain\Environments\Models\Environment;
use Domain\Users\Models\User;
use Integrations\Ssh\HostResult;
use Modules\Web\Environments\Controllers\EnvironmentVerificationController;

beforeEach(function () {
    $this->owner = User::factory()->create()->assignRole('super-admin');
    $this->environment = Environment::factory()->create([
        'name' => 'podman-01',
        'facts' => ['contract' => 1, 'lpod' => '4.0.0', 'podman' => '6.0.2'],
        'last_seen_at' => now()->subWeek(),
    ]);
});

it('stores the versions the host reports now', function () {
    $lpod = fakeLpod();

    $this->actingAs($this->owner)
        ->post(action([EnvironmentVerificationController::class, 'store'], $this->environment))
        ->assertRedirect()
        ->assertInertiaFlash('type', 'success')
        ->assertInertiaFlash('title', 'podman-01 checked')
        ->assertInertiaFlash('description', 'lpod 4.0.0, Podman 6.1.0, systemd 258.');

    $lpod->assertRan(['version', '--json']);
    expect($this->environment->refresh())
        ->facts->toMatchArray(['podman' => '6.1.0'])
        ->last_seen_at->toBeGreaterThan(now()->subMinute());
});

it('keeps the stored versions when the host does not answer', function () {
    fakeLpod()->respond('version --json', new HostResult(255, '', 'ssh: connect to host podman-01 port 22: Connection refused'));

    $this->actingAs($this->owner)
        ->post(action([EnvironmentVerificationController::class, 'store'], $this->environment))
        ->assertRedirect()
        ->assertInertiaFlash('type', 'error')
        ->assertInertiaFlash('title', 'podman-01 not checked');

    expect($this->environment->refresh()->facts['podman'])->toBe('6.0.2');
});

it('forbids users who are not the owner', function () {
    $lpod = fakeLpod();

    $this->actingAs(User::factory()->create())
        ->post(action([EnvironmentVerificationController::class, 'store'], $this->environment))
        ->assertForbidden();

    expect($lpod->recorded())->toBeEmpty();
});
