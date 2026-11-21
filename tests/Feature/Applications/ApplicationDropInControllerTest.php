<?php

declare(strict_types=1);

use Domain\Applications\Models\Installation;
use Domain\Environments\Models\Environment;
use Domain\Users\Models\User;
use Integrations\Ssh\HostResult;
use Modules\Web\Applications\Controllers\ApplicationDropInController;

beforeEach(function () {
    $this->owner = User::factory()->create()->assignRole('super-admin');
    $this->environment = Environment::factory()->create();
});

it('writes a drop-in with its content on stdin', function () {
    $lpod = fakeLpod();

    $this->actingAs($this->owner)
        ->put(action([ApplicationDropInController::class, 'update'], [$this->environment, 'immich']), [
            'name' => 'immich-server.container.d/memory.local.conf',
            'content' => "[Container]\nMemory=4g\n",
        ])
        ->assertRedirect()
        ->assertInertiaFlash('title', 'immich-server.container.d/memory.local.conf saved')
        ->assertInertiaFlash('description', 'immich picks it up when it restarts.');

    $lpod->assertRan(fn (array $arguments, ?string $input): bool => $arguments === ['immich', 'files', 'write', 'immich-server.container.d/memory.local.conf', '--stdin', '--json']
        && $input === "[Container]\nMemory=4g\n");
    expect(collect($lpod->recorded())->pluck('arguments')->contains(['immich', 'restart', '--no-block', '--json']))->toBeFalse();
});

it('restarts the application after saving or removing a drop-in when asked', function (string $method, string $action, array $data) {
    $lpod = fakeLpod();

    $this->actingAs($this->owner)
        ->{$method}(action([ApplicationDropInController::class, $action], [$this->environment, 'immich']), [
            'name' => 'immich-server.container.d/memory.local.conf',
            'restart' => true,
            ...$data,
        ])
        ->assertInertiaFlash('description', 'systemd is restarting immich.');

    $commands = collect($lpod->recorded())->pluck('arguments');
    expect($commands->search(['immich', 'restart', '--no-block', '--json']))->toBeGreaterThan($commands->search(fn (array $arguments): bool => $arguments[1] === 'files'));
})->with([
    'save' => ['put', 'update', ['content' => "[Container]\nMemory=4g\n"]],
    'remove' => ['delete', 'destroy', []],
]);

it('keeps the checksum of a drop-in Pods tracks, but not of a local one', function () {
    $lpod = fakeLpod();
    $installation = Installation::factory()->for($this->environment)->withFiles(lpodFixtureFiles())->create([
        'application' => 'immich',
        'changes' => ['modified' => [], 'added' => ['immich-server.container.d/memory.conf'], 'removed' => []],
    ]);

    foreach (['immich-server.container.d/memory.conf', 'immich-server.container.d/cpu.local.conf'] as $name) {
        $lpod->respond('immich files write *', json_encode([
            'contract' => 1,
            'file' => ['name' => $name, 'path' => "/home/blue/.config/containers/systemd/immich/{$name}", 'content' => "[Container]\nMemory=4g\n"],
        ]));

        $this->actingAs($this->owner)
            ->put(action([ApplicationDropInController::class, 'update'], [$this->environment, 'immich']), ['name' => $name, 'content' => "[Container]\nMemory=4g\n"])
            ->assertInertiaFlash('type', 'success');
    }

    $installation->refresh();
    expect($installation->checksums)
        ->toHaveKey('immich-server.container.d/memory.conf', hash('sha256', "[Container]\nMemory=4g\n"))
        ->not->toHaveKey('immich-server.container.d/cpu.local.conf')
        ->and($installation->changes)->toBeNull();
});

it('removes a drop-in and forgets its checksum', function () {
    $lpod = fakeLpod()->respond('immich files remove *', json_encode(['contract' => 1, 'removed' => ['immich-server.container.d/memory.conf']]));
    $installation = Installation::factory()->for($this->environment)->withFiles([
        ...lpodFixtureFiles(),
        'immich-server.container.d/memory.conf' => "[Container]\nMemory=4g\n",
    ])->create(['application' => 'immich']);

    $this->actingAs($this->owner)
        ->delete(action([ApplicationDropInController::class, 'destroy'], [$this->environment, 'immich']), ['name' => 'immich-server.container.d/memory.conf'])
        ->assertRedirect()
        ->assertInertiaFlash('title', 'immich-server.container.d/memory.conf removed');

    $lpod->assertRan(['immich', 'files', 'remove', 'immich-server.container.d/memory.conf', '--json']);
    expect($installation->refresh()->checksums)->toBe(Installation::checksumsOf(collect(lpodFixtureFiles())->map(fn (string $content, string $name): array => ['name' => $name, 'content' => $content])->values()));
});

it('refuses names that are not a drop-in of a Quadlet, or are lpod\'s own', function (string $name) {
    $lpod = fakeLpod();

    $this->actingAs($this->owner)
        ->put(action([ApplicationDropInController::class, 'update'], [$this->environment, 'immich']), ['name' => $name, 'content' => "[Container]\n"])
        ->assertInvalid('name');

    $lpod->assertNothingRan();
})->with([
    'lpod\'s own' => 'immich.pod.d/lpod-ondemand.conf',
    'a Quadlet' => 'immich-server.container',
    'not a .conf' => 'immich-server.container.d/memory.local',
    'another folder' => 'immich-server.container.d/../memory.conf',
    'not a Quadlet\'s' => 'immich.env.d/memory.conf',
]);

it('says why the host did not save the drop-in, and does not restart', function () {
    $lpod = fakeLpod()->respond('immich files write *', new HostResult(1, '', 'immich-server.container.d/memory.conf is a symlink, which lpod leaves alone.'));

    $this->actingAs($this->owner)
        ->put(action([ApplicationDropInController::class, 'update'], [$this->environment, 'immich']), [
            'name' => 'immich-server.container.d/memory.conf',
            'content' => "[Container]\n",
            'restart' => true,
        ])
        ->assertInertiaFlash('type', 'error')
        ->assertInertiaFlash('title', 'immich-server.container.d/memory.conf not saved')
        ->assertInertiaFlash('description', 'lpod immich files write immich-server.container.d/memory.conf --stdin --json failed: immich-server.container.d/memory.conf is a symlink, which lpod leaves alone.');

    expect(collect($lpod->recorded())->pluck('arguments')->contains(['immich', 'restart', '--no-block', '--json']))->toBeFalse();
});

it('asks to update lpod when the host\'s lpod cannot write drop-ins', function () {
    fakeLpod()->respond('immich files write *', lpodFixture('app-files'));

    $this->actingAs($this->owner)
        ->put(action([ApplicationDropInController::class, 'update'], [$this->environment, 'immich']), [
            'name' => 'immich-server.container.d/memory.local.conf',
            'content' => "[Container]\n",
        ])
        ->assertInertiaFlash('type', 'error')
        ->assertInertiaFlash('description', 'The lpod on this host doesn\'t support "lpod immich files write" yet. Update lpod to a release that implements the whole of contract 1.');
});

it('says the drop-in was saved when only the restart fails', function () {
    fakeLpod()->respond('immich restart --no-block --json', new HostResult(1, '', 'Unit immich-pod.service not found.'));

    $this->actingAs($this->owner)
        ->put(action([ApplicationDropInController::class, 'update'], [$this->environment, 'immich']), [
            'name' => 'immich-server.container.d/memory.local.conf',
            'content' => "[Container]\n",
            'restart' => true,
        ])
        ->assertInertiaFlash('type', 'error')
        ->assertInertiaFlash('title', 'immich-server.container.d/memory.local.conf changed, but immich didn\'t restart');
});

it('forbids users who are not the owner to change drop-ins', function () {
    $lpod = fakeLpod();

    $this->actingAs(User::factory()->create())
        ->put(action([ApplicationDropInController::class, 'update'], [$this->environment, 'immich']), ['name' => 'immich-server.container.d/memory.local.conf', 'content' => "[Container]\n"])
        ->assertForbidden();

    $lpod->assertNothingRan();
});
