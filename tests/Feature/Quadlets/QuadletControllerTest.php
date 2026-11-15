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
