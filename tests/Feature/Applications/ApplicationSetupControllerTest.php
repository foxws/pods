<?php

declare(strict_types=1);

use Domain\Applications\Models\Installation;
use Domain\Environments\Models\Environment;
use Domain\Users\Models\User;
use Integrations\Ssh\HostResult;
use Modules\Web\Applications\Controllers\ApplicationSetupController;
use Modules\Web\Quadlets\Controllers\QuadletFetchController;

beforeEach(function () {
    $this->owner = User::factory()->create()->assignRole('super-admin');
    $this->environment = Environment::factory()->create();
    $this->installation = Installation::factory()->for($this->environment)->fromGit()->create([
        'application' => 'immich',
        'manifest' => <<<'YAML'
            version: 1
            name: immich
            after_install:
              migrate:
                label: Migrate the database
                container: immich-server
                user: node
                workdir: /usr/src/app
                command: npm run   migrate
              admin:
                label: Create the first admin
                container: immich-server
                command: immich-admin create-admin
                inputs:
                  email:
                    label: Email
                    type: email
                    option: --email
                  password:
                    label: Password
                    type: password
              missing:
                container: immich-worker
                command: "true"
            after_update:
              clear-cache:
                label: Clear the cache
                container: immich-server
                command: immich-admin clear-cache
            YAML,
    ]);
});

/**
 * lpod's containers listing, with the server container named the way Quadlet names it by default.
 */
function setupContainers(): string
{
    $payload = json_decode(lpodFixture('containers'), true);
    $payload['containers'][0]['name'] = 'systemd-immich-server';

    return json_encode($payload);
}

it('runs a setup step in the container of its quadlet, and remembers that it succeeded', function () {
    $lpod = fakeLpod()->respond('containers --json', setupContainers());

    $this->actingAs($this->owner)
        ->post(action([ApplicationSetupController::class, 'store'], [$this->environment, 'immich', 'migrate']))
        ->assertRedirect()
        ->assertInertiaFlash('type', 'success')
        ->assertInertiaFlash('setup', [
            'step' => 'migrate',
            'exit_code' => 0,
            'output' => 'INFO  Application installed.',
        ]);

    $lpod->assertRan(fn (array $arguments, ?string $input): bool => $input === null && $arguments === [
        'container', 'systemd-immich-server', 'exec', '--user', 'node', '--workdir', '/usr/src/app', '--json', '--', 'npm', 'run', 'migrate',
    ]);
    expect($this->installation->refresh()->setup)
        ->toHaveKey('migrate.exit_code', 0)
        ->toHaveKey('migrate.ran_at');
});

it('runs a step of after_update the same way', function () {
    $lpod = fakeLpod()->respond('containers --json', setupContainers());

    $this->actingAs($this->owner)
        ->post(action([ApplicationSetupController::class, 'store'], [$this->environment, 'immich', 'clear-cache']))
        ->assertInertiaFlash('type', 'success');

    $lpod->assertRan(['container', 'systemd-immich-server', 'exec', '--json', '--', 'immich-admin', 'clear-cache']);
    expect($this->installation->refresh()->setup)->toHaveKey('clear-cache.exit_code', 0);
});

it('passes an input with an option in the arguments, and a password on stdin only', function () {
    $lpod = fakeLpod()->respond('containers --json', setupContainers());

    $this->actingAs($this->owner)
        ->post(action([ApplicationSetupController::class, 'store'], [$this->environment, 'immich', 'admin']), [
            'inputs' => ['email' => 'admin@example.com', 'password' => 'hunter2-secret'],
        ])
        ->assertInertiaFlash('type', 'success');

    $lpod->assertRan(fn (array $arguments, ?string $input): bool => $input === "hunter2-secret\n" && $arguments === [
        'container', 'systemd-immich-server', 'exec', '--stdin', '--json', '--', 'immich-admin', 'create-admin', '--email=admin@example.com',
    ]);
    expect($this->installation->refresh()->getRawOriginal('setup'))->not->toContain('hunter2-secret');
});

it('keeps a value that starts with a dash inside its option', function () {
    $lpod = fakeLpod()->respond('containers --json', setupContainers());
    $this->installation->update(['manifest' => "after_install:\n  admin:\n    container: immich-server\n    command: immich-admin create-admin\n    inputs:\n      name:\n        option: --name\n"]);

    $this->actingAs($this->owner)
        ->post(action([ApplicationSetupController::class, 'store'], [$this->environment, 'immich', 'admin']), [
            'inputs' => ['name' => '--super-admin'],
        ]);

    $lpod->assertRan(fn (array $arguments): bool => array_slice($arguments, -1) === ['--name=--super-admin']);
});

it('asks for every input of the step before running it', function () {
    $lpod = fakeLpod();

    $this->actingAs($this->owner)
        ->post(action([ApplicationSetupController::class, 'store'], [$this->environment, 'immich', 'admin']), [
            'inputs' => ['email' => 'not an email'],
        ])
        ->assertSessionHasErrors(['inputs.email', 'inputs.password']);

    $lpod->assertNothingRan();
});

it('remembers a step whose command failed, and shows its error', function () {
    fakeLpod()
        ->respond('containers --json', setupContainers())
        ->respond('container * exec *', json_encode(['contract' => 1, 'exit_code' => 1, 'stdout' => '', 'stderr' => "Running migrations\nSQLSTATE[08006] could not connect\n"]));

    $this->actingAs($this->owner)
        ->post(action([ApplicationSetupController::class, 'store'], [$this->environment, 'immich', 'migrate']))
        ->assertInertiaFlash('type', 'error')
        ->assertInertiaFlash('description', 'SQLSTATE[08006] could not connect')
        ->assertInertiaFlash('setup.exit_code', 1);

    expect($this->installation->refresh()->setup)->toHaveKey('migrate.exit_code', 1);
});

it('remembers nothing when lpod cannot run the command', function () {
    fakeLpod()
        ->respond('containers --json', setupContainers())
        ->respond('container * exec *', new HostResult(1, '', "systemd-immich-server isn't running, so nothing can run in it."));

    $this->actingAs($this->owner)
        ->post(action([ApplicationSetupController::class, 'store'], [$this->environment, 'immich', 'migrate']))
        ->assertInertiaFlash('type', 'error')
        ->assertInertiaFlashMissing('setup');

    expect($this->installation->refresh()->setup)->toBeNull();
});

it('runs nothing when the application has no container from the quadlet', function () {
    $lpod = fakeLpod();

    $this->actingAs($this->owner)
        ->post(action([ApplicationSetupController::class, 'store'], [$this->environment, 'immich', 'missing']))
        ->assertInertiaFlash('type', 'error')
        ->assertInertiaFlash('description', 'immich has no container from immich-worker.container on the host.');

    $lpod->assertRan(['containers', '--json']);
    expect(collect($lpod->recorded())->pluck('arguments')->contains(fn (array $arguments): bool => in_array('exec', $arguments, true)))->toBeFalse();
});

it('only runs steps of the stored pods.yml', function (string $application, string $step) {
    $lpod = fakeLpod();

    $this->actingAs($this->owner)
        ->post(action([ApplicationSetupController::class, 'store'], [$this->environment, $application, $step]))
        ->assertNotFound();

    $lpod->assertNothingRan();
})->with([
    'unknown step' => ['immich', 'reboot'],
    'untracked application' => ['vaultwarden', 'migrate'],
]);

it('forbids users who are not the owner', function () {
    $lpod = fakeLpod();

    $this->actingAs(User::factory()->create())
        ->post(action([ApplicationSetupController::class, 'store'], [$this->environment, 'immich', 'migrate']))
        ->assertForbidden();

    $lpod->assertNothingRan();
});

it('refuses a pods.yml whose setup steps could put a password in the arguments, or share a name', function (string $manifest, string $error) {
    fakeLpod()->respond('quadlet fetch *', new HostResult(0, json_encode([
        'contract' => 1,
        'commit' => 'a41f9c2',
        'manifest' => $manifest,
        'files' => [],
    ]), ''));

    $this->actingAs($this->owner)
        ->postJson(action(QuadletFetchController::class, $this->environment), [
            'repository' => 'https://codeberg.org/foxws/quadlets.git',
            'reference' => 'main',
        ])
        ->assertJsonPath('data.manifest', null)
        ->assertJsonPath('data.manifest_error', fn (string $message): bool => str_contains($message, $error));
})->with([
    'password with an option' => ["after_install:\n  admin:\n    container: app\n    command: app create-admin\n    inputs:\n      password:\n        type: password\n        option: --password\n", "can't have an option"],
    'two inputs on stdin' => ["after_install:\n  admin:\n    container: app\n    command: app create-admin\n    inputs:\n      name: {}\n      password:\n        type: password\n", 'only one can go on stdin'],
    'password with an option after an update' => ["after_update:\n  admin:\n    container: app\n    command: app reset-admin\n    inputs:\n      password:\n        type: password\n        option: --password\n", "can't have an option"],
    'a name in both lists' => ["after_install:\n  migrate:\n    container: app\n    command: app migrate\nafter_update:\n  migrate:\n    container: app\n    command: app migrate\n", 'is in both after_install and after_update'],
]);
