<?php

declare(strict_types=1);

use Domain\Environments\Models\Environment;
use Illuminate\Support\Facades\Process;
use Integrations\Ssh\HostResult;

beforeEach(function () {
    config(['services.pods.local' => ['host' => 'host.containers.internal', 'port' => 22, 'user' => 'blue']]);

    Process::fake([
        '*ssh-keygen*' => Process::result(),
        '*ssh-keyscan*' => Process::result('host.containers.internal ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAIOMqqnkVzrm0SdG6UOoqKLsabgH5C9okWi0dh2l9GKJl'),
    ]);
});

it('registers the local host and connects to it', function () {
    fakeLpod();

    $this->artisan('pods:install')
        ->expectsOutputToContain('local: lpod 4.0.0, Podman 6.1.0, systemd 258, rootless as blue')
        ->assertSuccessful();

    expect(Environment::query()->sole())
        ->is_local->toBeTrue()
        ->facts->toMatchArray(['contract' => 1]);
});

it('keeps the local host when lpod cannot be reached yet', function () {
    fakeLpod()->respond('version --json', new HostResult(255, '', 'Permission denied (publickey).'));

    $this->artisan('pods:install')
        ->expectsOutputToContain('Run pods:verify once that is fixed')
        ->assertSuccessful();

    expect(Environment::query()->sole()->facts)->toBeNull();
});
