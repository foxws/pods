<?php

declare(strict_types=1);

use Domain\Environments\Models\Environment;
use Domain\Users\Models\User;
use Integrations\Ssh\HostResult;
use Modules\Web\Applications\Controllers\ApplicationController;
use Modules\Web\Quadlets\Controllers\QuadletCheckController;
use Modules\Web\Quadlets\Controllers\QuadletController;
use Modules\Web\Quadlets\Controllers\QuadletFetchController;

beforeEach(function () {
    $this->owner = User::factory()->create()->assignRole('super-admin');
    $this->environment = Environment::factory()->create();
    $this->files = [
        ['name' => 'uptime-kuma.container', 'content' => "[Container]\nImage=docker.io/louislam/uptime-kuma:2\n"],
        ['name' => 'uptime-kuma-data.volume', 'content' => "[Volume]\n"],
    ];
});

it('installs the files as an application and starts it', function () {
    $lpod = fakeLpod();

    $this->actingAs($this->owner)
        ->post(action([QuadletController::class, 'store'], $this->environment), [
            'application' => 'uptime-kuma',
            'files' => $this->files,
            'start' => true,
        ])
        ->assertRedirect(action([ApplicationController::class, 'show'], [$this->environment, 'uptime-kuma']))
        ->assertInertiaFlash('type', 'success');

    $lpod->assertRan(fn (array $arguments, ?string $input) => $arguments === ['quadlet', 'install', 'uptime-kuma', '--start', '--stdin', '--json']
        && json_decode($input, true) === ['files' => $this->files]);
});

it('creates the secrets the files need before installing, with the values on stdin only', function () {
    $lpod = fakeLpod();

    $this->actingAs($this->owner)->post(action([QuadletController::class, 'store'], $this->environment), [
        'application' => 'nextcloud',
        'files' => $this->files,
        'secrets' => [['name' => 'nextcloud-db-password', 'driver' => 'file', 'value' => 'correct horse battery staple']],
    ]);

    $calls = collect($lpod->recorded())->pluck('arguments')->map(fn (array $arguments) => $arguments[0].' '.$arguments[1]);

    expect($calls->all())->toBe(['secret create', 'quadlet install']);
    $lpod->assertRan(fn (array $arguments, ?string $input) => $arguments[0] === 'secret' && $input === 'correct horse battery staple');

    foreach ($lpod->recorded() as $call) {
        expect(implode(' ', $call['arguments']))->not->toContain('correct horse');
    }
});

it('does not keep secret values as old input after a failed validation', function () {
    fakeLpod();

    $this->actingAs($this->owner)
        ->post(action([QuadletController::class, 'store'], $this->environment), [
            'application' => '--bad',
            'files' => $this->files,
            'secrets' => [['name' => 'db', 'driver' => 'file', 'value' => 'super-secret-value']],
        ])
        ->assertSessionHasErrors('application');

    expect(session()->getOldInput('secrets'))->toBeNull();
});

it('stays on the page when the install fails', function () {
    fakeLpod()->respond('quadlet install *', new HostResult(1, '', 'Error: unsupported key Imagee'));

    $this->actingAs($this->owner)
        ->from('/install')
        ->post(action([QuadletController::class, 'store'], $this->environment), [
            'application' => 'uptime-kuma',
            'files' => $this->files,
        ])
        ->assertRedirect('/install')
        ->assertInertiaFlash('type', 'error');
});

it('rejects file names that are not plain quadlet files', function (string $name) {
    $lpod = fakeLpod();

    $this->actingAs($this->owner)
        ->post(action([QuadletController::class, 'store'], $this->environment), [
            'application' => 'uptime-kuma',
            'files' => [['name' => $name, 'content' => "[Container]\n"]],
        ])
        ->assertSessionHasErrors('files.0.name');

    $lpod->assertNothingRan();
})->with([
    'path traversal' => '../../.bashrc',
    'nested path' => 'systemd/evil.container',
    'unknown extension' => 'install.sh',
    'option-like' => '--replace.container',
]);

it('checks files with a dry run and gives the result as json', function () {
    $lpod = fakeLpod();

    $this->actingAs($this->owner)
        ->postJson(action(QuadletCheckController::class, $this->environment), ['files' => $this->files])
        ->assertOk()
        ->assertExactJson(['data' => ['valid' => true, 'errors' => [], 'units' => ['uptime-kuma.service']], 'error' => null]);

    $lpod->assertRan(fn (array $arguments, ?string $input) => $arguments === ['quadlet', 'check', '--stdin', '--json']
        && json_decode($input, true) === ['files' => $this->files]);
});

it('gives the errors of an invalid file', function () {
    fakeLpod()->respond('quadlet check --stdin --json', lpodFixture('quadlet-check-invalid'));

    $this->actingAs($this->owner)
        ->postJson(action(QuadletCheckController::class, $this->environment), ['files' => $this->files])
        ->assertOk()
        ->assertJsonPath('data.valid', false)
        ->assertJsonPath('data.errors.0', "uptime-kuma.container: unsupported key 'Imagee' in group 'Container'");
});

it('fetches the files of a git repository', function () {
    $lpod = fakeLpod();

    $this->actingAs($this->owner)
        ->postJson(action(QuadletFetchController::class, $this->environment), [
            'repository' => 'https://codeberg.org/foxws/quadlets.git',
            'reference' => 'main',
            'path' => 'nextcloud/',
        ])
        ->assertOk()
        ->assertJsonPath('data.commit', 'a41f9c2')
        ->assertJsonCount(5, 'data.files');

    $lpod->assertRan(['quadlet', 'fetch', 'https://codeberg.org/foxws/quadlets.git', '--ref', 'main', '--path', 'nextcloud', '--json']);
});

it('returns no manifest for a repository without pods.yml', function () {
    fakeLpod();

    $this->actingAs($this->owner)
        ->postJson(action(QuadletFetchController::class, $this->environment), [
            'repository' => 'https://codeberg.org/foxws/quadlets.git',
            'reference' => 'main',
        ])
        ->assertOk()
        ->assertJsonPath('data.manifest', null)
        ->assertJsonPath('data.manifest_error', null);
});

it('reads the sources and secrets of the repository pods.yml', function () {
    fakeLpod()->respond('quadlet fetch *', new HostResult(0, lpodFixture('quadlet-fetch-manifest'), ''));

    $this->actingAs($this->owner)
        ->postJson(action(QuadletFetchController::class, $this->environment), [
            'repository' => 'https://codeberg.org/foxws/quadlets.git',
            'reference' => 'main',
        ])
        ->assertOk()
        ->assertJsonPath('data.manifest.name', 'nextcloud')
        ->assertJsonPath('data.manifest.sources.0', ['name' => 'stable', 'path' => 'nextcloud', 'description' => 'Nextcloud with PostgreSQL'])
        ->assertJsonPath('data.manifest.secrets.1', [
            'name' => 'nextcloud-db-password',
            'description' => 'Password of the database user',
            'format' => 'text',
            'generate' => true,
            'from' => null,
            'key' => null,
        ])
        ->assertJsonPath('data.manifest_error', null);
});

it('fills secrets in from a dotenv secret of the manifest', function () {
    $manifest = "secrets:\n  app-env:\n    format: dotenv\n  app-db-password:\n    from: app-env\n    key: DB_PASSWORD\n";
    fakeLpod()->respond('quadlet fetch *', new HostResult(0, json_encode(['contract' => 1, 'commit' => 'a41f9c2', 'manifest' => $manifest, 'files' => []]), ''));

    $this->actingAs($this->owner)
        ->postJson(action(QuadletFetchController::class, $this->environment), [
            'repository' => 'https://codeberg.org/foxws/quadlets.git',
            'reference' => 'main',
        ])
        ->assertOk()
        ->assertJsonPath('data.manifest.secrets.0.format', 'dotenv')
        ->assertJsonPath('data.manifest.secrets.1.from', 'app-env')
        ->assertJsonPath('data.manifest.secrets.1.key', 'DB_PASSWORD');
});

it('still returns the files when pods.yml is invalid', function (string $manifest, string $error) {
    fakeLpod()->respond('quadlet fetch *', new HostResult(0, json_encode([
        ...json_decode(lpodFixture('quadlet-fetch'), true),
        'manifest' => $manifest,
    ]), ''));

    $this->actingAs($this->owner)
        ->postJson(action(QuadletFetchController::class, $this->environment), [
            'repository' => 'https://codeberg.org/foxws/quadlets.git',
            'reference' => 'main',
        ])
        ->assertOk()
        ->assertJsonCount(5, 'data.files')
        ->assertJsonPath('data.manifest', null)
        ->assertJsonPath('data.manifest_error', fn (string $message) => str_contains($message, $error));
})->with([
    'not yaml' => ["name: [unclosed\n", 'pods.yml is ignored'],
    'newer version' => ["version: 2\n", "it's version 2, but Pods reads version 1"],
    'path traversal' => ["sources:\n  evil:\n    path: ../../etc\n", 'sources.evil.path'],
    'invalid secret name' => ["secrets:\n  '--rm':\n    description: x\n", 'isn\'t a valid secret name'],
    'from a plain secret' => ["secrets:\n  app-env: {}\n  db:\n    from: app-env\n    key: DB_PASSWORD\n", "which isn't a secret with format: dotenv"],
]);

it('rejects repositories and references that are not plain', function (array $input, string $field) {
    $lpod = fakeLpod();

    $this->actingAs($this->owner)
        ->postJson(action(QuadletFetchController::class, $this->environment), [
            'repository' => 'https://codeberg.org/foxws/quadlets.git',
            'reference' => 'main',
            ...$input,
        ])
        ->assertJsonValidationErrors($field);

    $lpod->assertNothingRan();
})->with([
    'local path' => [['repository' => '/etc'], 'repository'],
    'ext transport' => [['repository' => 'ext::sh -c reboot'], 'repository'],
    'option-like reference' => [['reference' => '--upload-pack=reboot'], 'reference'],
    'path traversal' => [['path' => '../../'], 'path'],
]);

it('forbids users who are not the owner', function () {
    $lpod = fakeLpod();

    $this->actingAs(User::factory()->create())
        ->post(action([QuadletController::class, 'store'], $this->environment), ['application' => 'uptime-kuma', 'files' => $this->files])
        ->assertForbidden();

    $this->actingAs(User::factory()->create())
        ->postJson(action(QuadletCheckController::class, $this->environment), ['files' => $this->files])
        ->assertForbidden();

    $lpod->assertNothingRan();
});
