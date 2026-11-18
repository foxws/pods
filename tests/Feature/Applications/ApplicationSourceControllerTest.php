<?php

declare(strict_types=1);

use Domain\Applications\Models\Installation;
use Domain\Environments\Models\Environment;
use Domain\Users\Models\User;
use Integrations\Ssh\HostResult;
use Modules\Web\Applications\Controllers\ApplicationSourceController;

beforeEach(function () {
    $this->owner = User::factory()->create()->assignRole('super-admin');
    $this->environment = Environment::factory()->create();
    $this->source = [
        'repository' => 'https://github.com/foxws/quadlets',
        'reference' => 'v3.1.0',
        'path' => 'immich/',
    ];
});

/**
 * What lpod quadlet fetch prints for a source with these files.
 *
 * @param  array<string, string>  $files
 */
function fetchedSource(array $files): string
{
    return json_encode([
        'contract' => 1,
        'commit' => 'c41e9a2',
        'manifest' => "version: 1\nname: immich\nlabel: Immich\n",
        'manifest_path' => 'immich',
        'files' => array_map(fn (string $name, string $content): array => ['name' => $name, 'content' => $content], array_keys($files), $files),
    ]);
}

it('links an application installed outside Pods to the source its files came from', function () {
    $lpod = fakeLpod()
        ->respond('quadlet fetch *', fetchedSource(lpodFixtureFiles()))
        ->respond('immich files --json', lpodFiles([
            ...lpodFixtureFiles(),
            'immich.pod.d/lpod-ondemand.conf' => "[Pod]\n",
        ]));

    $this->actingAs($this->owner)
        ->put(action([ApplicationSourceController::class, 'update'], [$this->environment, 'immich']), $this->source)
        ->assertRedirect()
        ->assertInertiaFlash('type', 'success')
        ->assertInertiaFlash('title', 'immich is linked to v3.1.0');

    $lpod->assertRan(['quadlet', 'fetch', 'https://github.com/foxws/quadlets', '--ref', 'v3.1.0', '--path', 'immich', '--json']);
    expect($this->environment->installations()->sole())
        ->repository->toBe('https://github.com/foxws/quadlets')
        ->reference->toBe('v3.1.0')
        ->path->toBe('immich')
        ->commit->toBe('c41e9a2')
        ->manifest()->label->toBe('Immich')
        ->changes->toBeNull()
        ->installed_at->toBeNull();
});

it('shows the files that differ from the source as changed outside Pods, and keeps the setup', function () {
    fakeLpod()
        ->respond('quadlet fetch *', fetchedSource([
            'immich.pod' => lpodFixtureFiles()['immich.pod'],
            'immich-server.container' => "[Container]\nImage=ghcr.io/immich-app/immich-server:v3.1.0\n",
            'immich-ml.container' => "[Container]\n",
        ]))
        ->respond('immich files --json', lpodFiles([
            ...lpodFixtureFiles(),
            'immich-redis.container' => "[Container]\n",
        ]));
    $installation = Installation::factory()->for($this->environment)->create([
        'application' => 'immich',
        'setup' => ['install' => ['exit_code' => 0, 'ran_at' => now()->toIso8601String()]],
    ]);

    $this->actingAs($this->owner)
        ->put(action([ApplicationSourceController::class, 'update'], [$this->environment, 'immich']), $this->source)
        ->assertInertiaFlash('type', 'success');

    expect($installation->refresh())
        ->repository->toBe('https://github.com/foxws/quadlets')
        ->changes->toBe(['modified' => ['immich-server.container'], 'added' => ['immich-redis.container'], 'removed' => ['immich-ml.container']])
        ->setup->toHaveKey('install');
});

it('links nothing when the source has no Quadlet files', function () {
    fakeLpod()->respond('quadlet fetch *', fetchedSource([]));

    $this->actingAs($this->owner)
        ->put(action([ApplicationSourceController::class, 'update'], [$this->environment, 'immich']), $this->source)
        ->assertInertiaFlash('type', 'error')
        ->assertInertiaFlash('description', 'v3.1.0 has no Quadlet files in immich/.');

    expect(Installation::query()->count())->toBe(0);
});

it('links nothing when lpod cannot read the application or the source', function (string $pattern) {
    fakeLpod()->respond($pattern, new HostResult(1, '', 'Something went wrong'));

    $this->actingAs($this->owner)
        ->put(action([ApplicationSourceController::class, 'update'], [$this->environment, 'immich']), $this->source)
        ->assertInertiaFlash('type', 'error');

    expect(Installation::query()->count())->toBe(0);
})->with([
    'the application' => ['immich files --json'],
    'the source' => ['quadlet fetch *'],
]);

it('validates the source', function (array $source, string $field) {
    $lpod = fakeLpod();

    $this->actingAs($this->owner)
        ->put(action([ApplicationSourceController::class, 'update'], [$this->environment, 'immich']), [...$this->source, ...$source])
        ->assertSessionHasErrors($field);

    $lpod->assertNothingRan();
})->with([
    'a repository that is no Git URL' => [['repository' => 'file:///etc'], 'repository'],
    'a reference that is an option' => [['reference' => '--upload-pack=touch'], 'reference'],
    'a path outside the repository' => [['path' => '../secrets'], 'path'],
]);

it('forbids users who are not the owner', function () {
    $lpod = fakeLpod();

    $this->actingAs(User::factory()->create())
        ->put(action([ApplicationSourceController::class, 'update'], [$this->environment, 'immich']), $this->source)
        ->assertForbidden();

    $lpod->assertNothingRan();
});
