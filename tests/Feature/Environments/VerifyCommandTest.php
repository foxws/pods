<?php

declare(strict_types=1);

use Domain\Environments\Models\Environment;
use Integrations\Ssh\HostResult;

it('stores the facts lpod reports about the host', function () {
    $environment = Environment::factory()->create(['name' => 'podman-01']);
    fakeLpod();

    $this->artisan('pods:verify', ['environment' => 'podman-01'])
        ->expectsOutputToContain('podman-01: lpod 4.0.0, Podman 6.1.0, systemd 258, rootless as blue')
        ->assertSuccessful();

    expect($environment->refresh())
        ->facts->toMatchArray(['contract' => 1, 'scope' => 'rootless'])
        ->last_seen_at->not->toBeNull();
});

it('explains an ssh key the host does not accept', function () {
    $environment = Environment::factory()->create(['name' => 'podman-01', 'user' => 'blue']);
    fakeLpod()->respond('version --json', new HostResult(255, '', 'blue@podman-01: Permission denied (publickey).'));

    $this->artisan('pods:verify')
        ->expectsOutputToContain('Add the public key of Pods to ~blue/.ssh/authorized_keys')
        ->assertFailed();

    expect($environment->refresh()->last_seen_at)->toBeNull();
});

it('explains a host without lpod', function () {
    Environment::factory()->create(['name' => 'podman-01']);
    fakeLpod()->respond('version --json', new HostResult(127, '', 'bash: line 1: lpod: command not found'));

    $this->artisan('pods:verify')
        ->expectsOutputToContain('lpod was not found')
        ->assertFailed();
});

it('refuses an lpod with an unknown contract', function () {
    Environment::factory()->create(['name' => 'podman-01']);
    fakeLpod()->respond('version --json', lpodFixture('unsupported-version'));

    $this->artisan('pods:verify')
        ->expectsOutputToContain('Pods needs contract 1')
        ->assertFailed();
});

it('fails when no environment matches', function () {
    Environment::factory()->create(['name' => 'podman-01']);
    $lpod = fakeLpod();

    $this->artisan('pods:verify', ['environment' => 'podman-02'])->assertFailed();

    $lpod->assertNothingRan();
});
