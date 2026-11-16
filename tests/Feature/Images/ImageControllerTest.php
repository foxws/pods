<?php

declare(strict_types=1);

use Domain\Environments\Models\Environment;
use Domain\Users\Models\User;
use Integrations\Ssh\HostResult;
use Modules\Web\Images\Controllers\ImageController;
use Modules\Web\Images\Controllers\ImagePruneController;
use Modules\Web\Images\Controllers\ImageUpdateController;

beforeEach(function () {
    $this->owner = User::factory()->create()->assignRole('super-admin');
    $this->environment = Environment::factory()->create();
});

it('lists the images with their updates', function () {
    fakeLpod();

    $this->actingAs($this->owner)
        ->get(action([ImageController::class, 'index'], $this->environment))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('Images/ImageIndex')
            ->loadDeferredProps(fn ($reload) => $reload
                ->has('images.data', 6)
                ->where('images.data.3.update', '13')
                ->where('images.data.5.dangling', true)
                ->where('images.data.5.repository', null)));
});

it('pulls an image', function () {
    $lpod = fakeLpod();

    $this->actingAs($this->owner)
        ->post(action([ImageController::class, 'store'], $this->environment), ['reference' => 'codeberg.org/forgejo/forgejo:13.1'])
        ->assertRedirect()
        ->assertInertiaFlash('type', 'success');

    $lpod->assertRan(['image', 'pull', 'codeberg.org/forgejo/forgejo:13.1', '--json']);
});

it('rejects image references that are not plain', function (string $reference) {
    $lpod = fakeLpod();

    $this->actingAs($this->owner)
        ->post(action([ImageController::class, 'store'], $this->environment), ['reference' => $reference])
        ->assertSessionHasErrors('reference');

    $lpod->assertNothingRan();
})->with([
    'option-like' => '--all-tags',
    'shell' => 'alpine;reboot',
    'space' => 'alpine latest',
]);

it('flashes the error when a pull fails', function () {
    fakeLpod()->respond('image pull * --json', new HostResult(125, '', 'manifest unknown: jellyfin:10.12'));

    $this->actingAs($this->owner)
        ->post(action([ImageController::class, 'store'], $this->environment), ['reference' => 'jellyfin:10.12'])
        ->assertRedirect()
        ->assertInertiaFlash('type', 'error');
});

it('removes an image by its id', function () {
    $lpod = fakeLpod();

    $this->actingAs($this->owner)
        ->delete(action([ImageController::class, 'destroy'], [$this->environment, 'f4d09c1b8a27']))
        ->assertRedirect()
        ->assertInertiaFlash('type', 'success');

    $lpod->assertRan(['image', 'rm', 'f4d09c1b8a27', '--json']);
});

it('only removes images by id', function () {
    $lpod = fakeLpod();

    $this->actingAs($this->owner)
        ->delete("/actions/environments/{$this->environment->ulid}/images/--force")
        ->assertNotFound();

    $lpod->assertNothingRan();
});

it('prunes unused images and checks for updates', function () {
    $lpod = fakeLpod();

    $this->actingAs($this->owner)->post(action(ImagePruneController::class, $this->environment))->assertInertiaFlash('type', 'success');
    $this->actingAs($this->owner)->post(action(ImageUpdateController::class, $this->environment))->assertInertiaFlash('type', 'success');

    $lpod->assertRan(['images', 'prune', '--json']);
    $lpod->assertRan(['auto-update', '--dry-run', '--json']);
});

it('forbids users who are not the owner', function () {
    $lpod = fakeLpod();

    $this->actingAs(User::factory()->create())
        ->post(action(ImagePruneController::class, $this->environment))
        ->assertForbidden();

    $lpod->assertNothingRan();
});
