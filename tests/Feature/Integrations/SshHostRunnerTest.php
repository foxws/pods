<?php

declare(strict_types=1);

use Domain\Environments\Models\Environment;
use Illuminate\Support\Facades\Process;
use Integrations\Ssh\SshHostRunner;

it('escapes every lpod argument for the remote shell', function () {
    $environment = Environment::factory()->create(['host' => 'podman-01.home.arpa', 'port' => 2222, 'user' => 'blue']);

    $command = app(SshHostRunner::class)->command($environment, ['app', 'logs', "it's; reboot \\"]);

    [$separator, $host, $remote] = array_slice($command, -3);

    expect([$separator, $host])->toBe(['--', 'podman-01.home.arpa'])
        ->and($remote)->toMatch('#^sh -c \'[^\\\\\']*\' pods [A-Za-z0-9+/=]+$#')
        ->and(base64_decode(substr($remote, strrpos($remote, ' ') + 1), true))
        ->toBe('PATH="$HOME/.local/bin:$PATH" exec \'lpod\' \'app\' \'logs\' \'it\'\\\'\'s; reboot \\\'');
});

it('only trusts the pinned host key', function () {
    $environment = Environment::factory()->create(['port' => 2222]);

    $command = implode(' ', app(SshHostRunner::class)->command($environment, ['apps', '--json']));

    expect($command)
        ->toContain('-o StrictHostKeyChecking=yes')
        ->toContain('-o BatchMode=yes')
        ->toContain('-p 2222')
        ->toContain("-l {$environment->user}")
        ->and(file_get_contents(config('services.pods.ssh_directory')."/known_hosts/{$environment->ulid}"))
        ->toBe($environment->host_key.PHP_EOL);
});

it('passes the input on stdin', function () {
    Process::fake();
    $environment = Environment::factory()->create();

    app(SshHostRunner::class)->run($environment, ['secret', 'create', 'name', '--stdin'], 'the-value');

    Process::assertRan(fn ($process) => $process->input === 'the-value'
        && ! str_contains(implode(' ', (array) $process->command), 'the-value'));
});

it('runs the commands to a host over one connection that stays open for a while', function () {
    $environment = Environment::factory()->create();
    $directory = (string) config('services.pods.ssh_control_directory');

    $command = implode(' ', app(SshHostRunner::class)->command($environment, ['apps', '--json']));

    expect($command)
        ->toContain('-o ControlMaster=auto')
        ->toContain("-o ControlPath={$directory}/%C")
        ->toContain('-o ControlPersist=300')
        ->toContain('-o ServerAliveInterval=15')
        ->and(fileperms($directory) & 0777)->toBe(0700);
});

it('opens a connection per command when reuse is turned off', function () {
    config(['services.pods.ssh_control_persist' => 0]);
    $environment = Environment::factory()->create();

    $command = implode(' ', app(SshHostRunner::class)->command($environment, ['apps', '--json']));

    expect($command)->not->toContain('Control');
});
