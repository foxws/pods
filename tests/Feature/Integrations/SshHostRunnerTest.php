<?php

declare(strict_types=1);

use Domain\Environments\Models\Environment;
use Illuminate\Support\Facades\Process;
use Integrations\Ssh\SshHostRunner;

it('escapes every lpod argument for the remote shell', function () {
    $environment = Environment::factory()->create(['host' => 'podman-01.home.arpa', 'port' => 2222, 'user' => 'blue']);

    $command = app(SshHostRunner::class)->command($environment, ['app', 'logs', "it's; reboot"]);

    expect(array_slice($command, -3))->toBe([
        '--',
        'podman-01.home.arpa',
        'PATH="$HOME/.local/bin:$PATH" \'lpod\' \'app\' \'logs\' \'it\'\\\'\'s; reboot\'',
    ]);
});

it('only trusts the pinned host key', function () {
    $environment = Environment::factory()->create(['port' => 2222]);

    $command = implode(' ', app(SshHostRunner::class)->command($environment, ['apps', '--json']));

    expect($command)
        ->toContain('-o StrictHostKeyChecking=yes')
        ->toContain('-o BatchMode=yes')
        ->toContain('-p 2222')
        ->toContain("-l {$environment->user}")
        ->and(file_get_contents(storage_path("app/private/ssh/known_hosts/{$environment->ulid}")))
        ->toBe($environment->host_key.PHP_EOL);
});

it('passes the input on stdin', function () {
    Process::fake();
    $environment = Environment::factory()->create();

    app(SshHostRunner::class)->run($environment, ['secret', 'create', 'name', '--stdin'], 'the-value');

    Process::assertRan(fn ($process) => $process->input === 'the-value'
        && ! str_contains(implode(' ', (array) $process->command), 'the-value'));
});
