<?php

declare(strict_types=1);

use Domain\Environments\Models\Environment;
use Domain\Users\Models\User;
use Illuminate\Support\Facades\Process;
use Integrations\Ssh\HostResult;
use Modules\Web\Environments\Controllers\EnvironmentController;
use Modules\Web\Environments\Controllers\EnvironmentOverviewController;

beforeEach(function () {
    $this->owner = User::factory()->create()->assignRole('super-admin');
    $this->hostKey = 'podman-01.home.arpa ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAIOMqqnkVzrm0SdG6UOoqKLsabgH5C9okWi0dh2l9GKJl';
});

it('lists environments with their host key fingerprint', function () {
    Environment::factory()->create(['host_key' => $this->hostKey]);

    $this->actingAs($this->owner)
        ->get(action([EnvironmentController::class, 'index']))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('Environments/EnvironmentIndex')
            ->where('environments.0.fingerprint', 'SHA256:+DiY3wvvV6TuJJhbpZisF/zLDA0zPMSvHdkr4UvCOqU')
            ->missing('environments.0.host_key'));
});

it('pins the host key and connects to a new environment', function () {
    Process::fake(['*ssh-keyscan*' => Process::result("# podman-01.home.arpa:22 SSH-2.0-OpenSSH_10.0\n{$this->hostKey}\n")]);
    fakeLpod();

    $response = $this->actingAs($this->owner)->post(action([EnvironmentController::class, 'store']), [
        'name' => 'podman-01',
        'host' => 'podman-01.home.arpa',
        'port' => 22,
        'user' => 'blue',
    ]);

    $environment = Environment::query()->sole();

    $response->assertRedirect(action(EnvironmentOverviewController::class, $environment));

    expect($environment)
        ->host_key->toBe($this->hostKey)
        ->facts->toMatchArray(['contract' => 1, 'podman' => '6.1.0', 'scope' => 'rootless'])
        ->last_seen_at->not->toBeNull();
});

it('keeps the environment when lpod cannot be reached yet', function () {
    Process::fake(['*ssh-keyscan*' => Process::result($this->hostKey)]);
    fakeLpod()->respond('version --json', new HostResult(255, '', 'Permission denied (publickey).'));

    $this->actingAs($this->owner)
        ->post(action([EnvironmentController::class, 'store']), [
            'name' => 'podman-01',
            'host' => 'podman-01.home.arpa',
            'port' => 22,
            'user' => 'blue',
        ])
        ->assertRedirect(action([EnvironmentController::class, 'index']))
        ->assertInertiaFlash('type', 'warning');

    expect(Environment::query()->sole()->facts)->toBeNull();
});

it('reports a host without an ssh server on the host field', function () {
    Process::fake(['*ssh-keyscan*' => Process::result('', exitCode: 1)]);

    $this->actingAs($this->owner)
        ->post(action([EnvironmentController::class, 'store']), [
            'name' => 'podman-01',
            'host' => 'podman-01.home.arpa',
            'port' => 22,
            'user' => 'blue',
        ])
        ->assertSessionHasErrors('host');

    expect(Environment::query()->count())->toBe(0);
});

it('rejects hosts and users that could be read as ssh options', function (array $input, string $field) {
    Process::fake();

    $this->actingAs($this->owner)
        ->post(action([EnvironmentController::class, 'store']), [
            'name' => 'podman-01',
            'host' => 'podman-01.home.arpa',
            'port' => 22,
            'user' => 'blue',
            ...$input,
        ])
        ->assertSessionHasErrors($field);

    Process::assertNothingRan();
})->with([
    'option host' => [['host' => '-oProxyCommand=reboot'], 'host'],
    'option user' => [['user' => '-oProxyCommand=reboot'], 'user'],
    'out of range port' => [['port' => 70000], 'port'],
]);

it('removes an environment without touching the host', function () {
    $lpod = fakeLpod();
    $environment = Environment::factory()->create();

    $this->actingAs($this->owner)
        ->delete(action([EnvironmentController::class, 'destroy'], $environment))
        ->assertRedirect(action([EnvironmentController::class, 'index']));

    expect(Environment::query()->count())->toBe(0);
    $lpod->assertNothingRan();
});

it('forbids users who are not the owner', function () {
    Process::fake();

    $this->actingAs(User::factory()->create())
        ->post(action([EnvironmentController::class, 'store']), [
            'name' => 'podman-01',
            'host' => 'podman-01.home.arpa',
            'port' => 22,
            'user' => 'blue',
        ])
        ->assertForbidden();

    Process::assertNothingRan();
});
