<?php

declare(strict_types=1);

use Domain\Applications\Models\Installation;
use Domain\Environments\Models\Environment;
use Domain\Users\Models\User;
use Integrations\Ssh\HostResult;
use Modules\Web\Applications\Controllers\ApplicationController;
use Modules\Web\Applications\Controllers\ApplicationUpdateController;

beforeEach(function () {
    $this->owner = User::factory()->create()->assignRole('super-admin');
    $this->environment = Environment::factory()->create();
});

/**
 * What lpod quadlet fetch prints for immich at another tag: its pod and server, without the files the host has besides.
 *
 * @param  array<string, string>  $files
 */
function immichRelease(array $files = []): string
{
    $files = $files ?: [
        'immich.pod' => "[Pod]\nPublishPort=2283:2283\n",
        'immich-server.container' => "[Container]\nImage=ghcr.io/immich-app/immich-server:v3.2.0\nSecret=immich-db-password,type=env,target=DB_PASSWORD\n",
    ];

    return json_encode([
        'contract' => 1,
        'commit' => 'c41e9a2',
        'manifest' => "version: 1\nname: immich\nlabel: Immich\n",
        'manifest_path' => 'immich',
        'files' => array_map(fn (string $name, string $content): array => ['name' => $name, 'content' => $content], array_keys($files), $files),
    ]);
}

it('offers the newer tags of the repository an application was installed at', function (string $reference, array $tags) {
    fakeLpod();
    Installation::factory()->for($this->environment)->fromGit()->create(['application' => 'immich', 'reference' => $reference]);

    $this->actingAs($this->owner)
        ->get(action([ApplicationController::class, 'show'], [$this->environment, 'immich']))
        ->assertInertia(fn ($page) => $page->loadDeferredProps('updates', fn ($reload) => $reload
            ->where('updates.data', fn ($updates): bool => collect($updates)->pluck('name')->all() === $tags)));
})->with([
    'a release, without release candidates' => ['v3.1.0', ['v3.2.0']],
    'a release candidate, with the newer ones' => ['v3.2.0-rc.0', ['v3.2.0', 'v3.2.0-rc.1']],
    'the newest release' => ['v3.2.0', []],
    'a branch' => ['main', []],
]);

it('offers no update to an application that did not come from git', function () {
    $lpod = fakeLpod();
    Installation::factory()->for($this->environment)->create(['application' => 'immich']);

    $this->actingAs($this->owner)
        ->get(action([ApplicationController::class, 'show'], [$this->environment, 'immich']))
        ->assertInertia(fn ($page) => $page->loadDeferredProps('updates', fn ($reload) => $reload->where('updates.data', [])));

    expect(collect($lpod->recorded())->pluck('arguments')->contains(fn (array $arguments): bool => $arguments[1] === 'tags'))->toBeFalse();
});

it('stops the application, installs the files of the tag over its files and keeps the new source', function () {
    $lpod = fakeLpod()
        ->respond('quadlet fetch *', immichRelease())
        ->respond('immich files --json', lpodFiles([
            ...lpodFixtureFiles(),
            'immich-ml.container' => "[Container]\n",
            'immich.pod.d/lpod-ondemand.conf' => "[Pod]\n",
        ]))
        ->respond('immich down --json', lpodFixture('ok'));
    $installation = Installation::factory()->for($this->environment)->fromGit()->create(['application' => 'immich', 'reference' => 'v3.1.0']);

    $this->actingAs($this->owner)
        ->post(action([ApplicationUpdateController::class, 'store'], [$this->environment, 'immich']), ['tag' => 'v3.2.0'])
        ->assertRedirect()
        ->assertInertiaFlash('type', 'success')
        ->assertInertiaFlash('description', 'Its units are starting with the new files. These files aren\'t in v3.2.0 and stay on the host: immich-ml.container.');

    $lpod->assertRan(['quadlet', 'fetch', 'https://github.com/foxws/quadlets', '--ref', 'v3.2.0', '--path', 'immich', '--json']);
    $lpod->assertRan(['immich', 'down', '--json']);
    $lpod->assertRan(['quadlet', 'install', 'immich', '--replace', '--start', '--stdin', '--json']);
    expect(collect($lpod->recorded())->pluck('arguments')->search(['immich', 'down', '--json']))
        ->toBeLessThan(collect($lpod->recorded())->pluck('arguments')->search(['quadlet', 'install', 'immich', '--replace', '--start', '--stdin', '--json']));
    expect($installation->refresh())
        ->reference->toBe('v3.2.0')
        ->commit->toBe('c41e9a2')
        ->path->toBe('immich')
        ->manifest->toBe("version: 1\nname: immich\nlabel: Immich\n")
        ->checksums->toHaveKeys(['immich.pod', 'immich-server.container']);
});

it('only updates to a newer tag of the repository', function () {
    $lpod = fakeLpod();
    Installation::factory()->for($this->environment)->fromGit()->create(['application' => 'immich', 'reference' => 'v3.1.0']);

    $this->actingAs($this->owner)
        ->post(action([ApplicationUpdateController::class, 'store'], [$this->environment, 'immich']), ['tag' => 'v3.0.0'])
        ->assertSessionHasErrors(['tag' => 'Pick one of the newer tags of the repository.']);

    $lpod->assertRan(['quadlet', 'tags', 'https://github.com/foxws/quadlets', '--json']);
    expect(collect($lpod->recorded())->pluck('arguments')->contains(fn (array $arguments): bool => in_array('fetch', $arguments, true)))->toBeFalse();
});

it('leaves the application running when the tag cannot be installed', function (Closure $respond, string $error) {
    $lpod = fakeLpod();
    $respond($lpod);
    $installation = Installation::factory()->for($this->environment)->fromGit()->create(['application' => 'immich', 'reference' => 'v3.1.0']);

    $this->actingAs($this->owner)
        ->post(action([ApplicationUpdateController::class, 'store'], [$this->environment, 'immich']), ['tag' => 'v3.2.0'])
        ->assertInertiaFlash('type', 'error')
        ->assertInertiaFlash('description', $error);

    expect(collect($lpod->recorded())->pluck('arguments')->contains(fn (array $arguments): bool => in_array('down', $arguments, true) || in_array('install', $arguments, true)))->toBeFalse()
        ->and($installation->refresh()->reference)->toBe('v3.1.0');
})->with([
    'a secret the host lacks' => [
        fn ($lpod) => $lpod->respond('quadlet fetch *', immichRelease(['immich.pod' => "[Pod]\n", 'immich-server.container' => "[Container]\nSecret=immich-jwt-secret\n"])),
        'v3.2.0 uses secrets this host doesn\'t have yet: immich-jwt-secret. Create them on the Secrets page first.',
    ],
    'files that fail the dry run' => [
        fn ($lpod) => $lpod->respond('quadlet fetch *', immichRelease())->respond('quadlet check --stdin --json', json_encode(['contract' => 1, 'valid' => false, 'errors' => ['immich.pod: unknown key Foo'], 'units' => []])),
        'The files of v3.2.0 don\'t pass podman quadlet --dryrun: immich.pod: unknown key Foo',
    ],
    'a tag without files at the path' => [
        fn ($lpod) => $lpod->respond('quadlet fetch *', json_encode(['contract' => 1, 'commit' => 'c41e9a2', 'manifest' => null, 'manifest_path' => null, 'files' => []])),
        'v3.2.0 has no Quadlet files in immich/.',
    ],
    'a fetch lpod cannot do' => [
        fn ($lpod) => $lpod->respond('quadlet fetch *', new HostResult(1, '', 'fatal: Remote branch v3.2.0 not found')),
        'lpod quadlet fetch https://github.com/foxws/quadlets --ref v3.2.0 --path immich --json failed: fatal: Remote branch v3.2.0 not found',
    ],
]);

it('forbids users who are not the owner to update', function () {
    $lpod = fakeLpod();
    Installation::factory()->for($this->environment)->fromGit()->create(['application' => 'immich', 'reference' => 'v3.1.0']);

    $this->actingAs(User::factory()->create())
        ->post(action([ApplicationUpdateController::class, 'store'], [$this->environment, 'immich']), ['tag' => 'v3.2.0'])
        ->assertForbidden();

    $lpod->assertNothingRan();
});
