<?php

declare(strict_types=1);

use Domain\Applications\Models\Installation;
use Domain\Environments\Models\Environment;
use Domain\Users\Models\User;
use Integrations\Ssh\HostResult;
use Modules\Web\Applications\Controllers\ApplicationInstallationController;

beforeEach(function () {
    $this->owner = User::factory()->create()->assignRole('super-admin');
    $this->environment = Environment::factory()->create();
});

it('keeps the files changed outside Pods, and the source', function () {
    $installation = Installation::factory()->for($this->environment)->fromGit()->create([
        'application' => 'immich',
        'checksums' => ['immich.pod' => hash('sha256', "[Pod]\n")],
        'changes' => ['modified' => ['immich.pod'], 'added' => ['immich-server.container'], 'removed' => []],
    ]);
    fakeLpod()->respond('immich files --json', lpodFiles([
        ...lpodFixtureFiles(),
        'immich.pod.d/lpod-ondemand.conf' => "[Pod]\n",
    ]));

    $this->actingAs($this->owner)
        ->put(action([ApplicationInstallationController::class, 'update'], [$this->environment, 'immich']))
        ->assertRedirect()
        ->assertInertiaFlash('type', 'success');

    expect($installation->refresh())
        ->repository->toBe('https://github.com/foxws/quadlets')
        ->changes->toBeNull()
        ->checksums->toBe([
            'immich-server.container' => hash('sha256', lpodFixtureFiles()['immich-server.container']),
            'immich.pod' => hash('sha256', lpodFixtureFiles()['immich.pod']),
        ]);
});

it('starts tracking an application installed outside Pods', function () {
    fakeLpod();

    $this->actingAs($this->owner)
        ->put(action([ApplicationInstallationController::class, 'update'], [$this->environment, 'immich']))
        ->assertInertiaFlash('type', 'success');

    expect($this->environment->installations()->sole())
        ->application->toBe('immich')
        ->repository->toBeNull()
        ->installed_at->toBeNull()
        ->checksums->toHaveKeys(['immich-server.container', 'immich.pod']);
});

it('tracks nothing when lpod cannot read the files', function () {
    fakeLpod()->respond('immich files --json', new HostResult(1, '', 'Application immich not found'));

    $this->actingAs($this->owner)
        ->put(action([ApplicationInstallationController::class, 'update'], [$this->environment, 'immich']))
        ->assertInertiaFlash('type', 'error');

    expect(Installation::query()->count())->toBe(0);
});

it('forbids users who are not the owner', function () {
    $lpod = fakeLpod();

    $this->actingAs(User::factory()->create())
        ->put(action([ApplicationInstallationController::class, 'update'], [$this->environment, 'immich']))
        ->assertForbidden();

    $lpod->assertNothingRan();
});
