<?php

declare(strict_types=1);

use Domain\Applications\Enums\ApplicationOperation;
use Domain\Environments\Models\Environment;
use Domain\Users\Models\User;
use Integrations\Ssh\HostResult;
use Modules\Web\Applications\Controllers\ApplicationOperationController;

beforeEach(function () {
    $this->owner = User::factory()->create()->assignRole('super-admin');
    $this->environment = Environment::factory()->create();
});

it('queues the operation in systemd without waiting', function (ApplicationOperation $operation) {
    $lpod = fakeLpod();

    $this->actingAs($this->owner)
        ->post(action(ApplicationOperationController::class, [$this->environment, 'jellyfin', $operation]))
        ->assertRedirect()
        ->assertInertiaFlash('type', 'success');

    $lpod->assertRan(['jellyfin', $operation->value, '--no-block', '--json']);
})->with(array_filter(ApplicationOperation::cases(), fn (ApplicationOperation $operation) => $operation !== ApplicationOperation::ForceStop));

it('force stops an application with lpod down --force', function () {
    $lpod = fakeLpod();

    $this->actingAs($this->owner)
        ->post(action(ApplicationOperationController::class, [$this->environment, 'hello', ApplicationOperation::ForceStop]))
        ->assertRedirect()
        ->assertInertiaFlash('type', 'success');

    $lpod->assertRan(['hello', 'down', '--force', '--json']);
});

it('flashes the error when lpod fails', function () {
    fakeLpod()->respond('* up --no-block --json', new HostResult(1, '', 'Unit jellyfin.service not found.'));

    $this->actingAs($this->owner)
        ->post(action(ApplicationOperationController::class, [$this->environment, 'jellyfin', 'up']))
        ->assertRedirect()
        ->assertInertiaFlash('type', 'error')
        ->assertInertiaFlash('description', 'lpod jellyfin up --no-block --json failed: Unit jellyfin.service not found.');
});

it('rejects unknown operations', function () {
    $lpod = fakeLpod();

    $this->actingAs($this->owner)
        ->post("/actions/environments/{$this->environment->ulid}/applications/jellyfin/rm")
        ->assertNotFound();

    $lpod->assertNothingRan();
});

it('forbids users who are not the owner', function () {
    $lpod = fakeLpod();

    $this->actingAs(User::factory()->create())
        ->post(action(ApplicationOperationController::class, [$this->environment, 'jellyfin', 'down']))
        ->assertForbidden();

    $lpod->assertNothingRan();
});
