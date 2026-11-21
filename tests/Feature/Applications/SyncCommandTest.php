<?php

declare(strict_types=1);

use Domain\Applications\Models\Installation;
use Domain\Environments\Models\Environment;
use Integrations\Ssh\HostResult;

beforeEach(function () {
    $this->environment = Environment::factory()->create(['name' => 'podman-01']);
});

it('keeps an installation whose files are unchanged in sync', function () {
    $this->travelTo('2026-11-17 01:00:00');
    $installation = Installation::factory()->for($this->environment)->withFiles(lpodFixtureFiles())
        ->create(['application' => 'immich', 'checked_at' => '2026-11-16 01:00:00']);
    fakeLpod();

    $this->artisan('pods:sync')
        ->expectsOutputToContain('podman-01: 1 applications checked.')
        ->doesntExpectOutputToContain('changed outside Pods')
        ->assertSuccessful();

    expect($installation->refresh())
        ->changes->toBeNull()
        ->checked_at->toDateTimeString()->toBe('2026-11-17 01:00:00');
});

it('reports the files modified, added and removed outside Pods', function () {
    $installation = Installation::factory()->for($this->environment)->withFiles([
        'immich.pod' => "[Pod]\nPodName=immich\n",
        'immich-server.container' => lpodFixtureFiles()['immich-server.container'],
        'immich-redis.container' => "[Container]\nImage=docker.io/valkey/valkey:8\n",
    ])->create(['application' => 'immich']);
    fakeLpod()->respond('immich files --json', lpodFiles([
        'immich.pod' => "[Pod]\nPodName=immich\nPublishPort=2283:2283\n",
        'immich-server.container' => lpodFixtureFiles()['immich-server.container'],
        'immich-server.container.d/memory.conf' => "[Container]\nMemory=2g\n",
    ]));

    $this->artisan('pods:sync')
        ->expectsOutputToContain('immich has files changed outside Pods: modified immich.pod; added immich-server.container.d/memory.conf; removed immich-redis.container.')
        ->assertSuccessful();

    expect($installation->refresh()->changes)->toBe([
        'modified' => ['immich.pod'],
        'added' => ['immich-server.container.d/memory.conf'],
        'removed' => ['immich-redis.container'],
    ]);
});

it('does not count the drop-ins lpod writes itself as changes', function () {
    $installation = Installation::factory()->for($this->environment)->withFiles(lpodFixtureFiles())->create(['application' => 'immich']);
    fakeLpod()->respond('immich files --json', lpodFiles([
        ...lpodFixtureFiles(),
        'immich.pod.d/lpod-ondemand.conf' => "[Pod]\nPublishPort=127.0.0.1:12283:2283\n",
    ]));

    $this->artisan('pods:sync')->assertSuccessful();

    expect($installation->refresh()->changes)->toBeNull();
});

it('forgets an application that was removed outside Pods', function () {
    $installation = Installation::factory()->for($this->environment)->create(['application' => 'nextcloud']);
    $lpod = fakeLpod();

    $this->artisan('pods:sync')
        ->expectsOutputToContain('podman-01: nextcloud was removed outside Pods.')
        ->assertSuccessful();

    $this->assertModelMissing($installation);
    $lpod->assertRan(['apps', '--json']);
    expect(collect($lpod->recorded())->pluck('arguments')->all())->not->toContain(['nextcloud', 'files', '--json']);
});

it('keeps the installations of a host it cannot reach', function () {
    $installation = Installation::factory()->for($this->environment)->create(['application' => 'immich']);
    fakeLpod()->respond('apps --json', new HostResult(255, '', 'ssh: connect to host podman-01 port 22: Connection refused'));

    $this->artisan('pods:sync')
        ->expectsOutputToContain('Connection refused')
        ->assertFailed();

    $this->assertModelExists($installation);
});

it('only syncs the environment it is given', function () {
    $other = Environment::factory()->create(['name' => 'podman-02']);
    Installation::factory()->for($other)->create(['application' => 'nextcloud']);
    $installation = Installation::factory()->for($this->environment)->create(['application' => 'paperless']);
    fakeLpod();

    $this->artisan('pods:sync', ['environment' => 'podman-02'])->assertSuccessful();

    $this->assertModelExists($installation);
    expect($other->installations()->count())->toBe(0);
});

it('does not count local drop-ins as changes', function () {
    $installation = Installation::factory()->for($this->environment)->withFiles(lpodFixtureFiles())->create(['application' => 'immich']);
    fakeLpod()->respond('immich files --json', lpodFiles([
        ...lpodFixtureFiles(),
        'immich-server.container.d/memory.local.conf' => "[Container]\nMemory=4g\n",
    ]));

    $this->artisan('pods:sync')->assertSuccessful();

    expect($installation->refresh()->changes)->toBeNull();
});
