<?php

declare(strict_types=1);

use Domain\Applications\Models\PinnedApplication;
use Domain\Environments\Models\Environment;
use Domain\Users\Models\User;
use Modules\Web\Applications\Controllers\ApplicationPinController;

beforeEach(function () {
    $this->owner = User::factory()->create()->assignRole('super-admin');
    $this->environment = Environment::factory()->create();
});

it('redirects guests to the login page', function () {
    $this->put(action([ApplicationPinController::class, 'update'], [$this->environment, 'immich']))
        ->assertRedirect(route('login'));

    $this->assertDatabaseEmpty('pinned_applications');
});

it('forbids users who are not the owner', function () {
    $this->actingAs(User::factory()->create())
        ->put(action([ApplicationPinController::class, 'update'], [$this->environment, 'immich']))
        ->assertForbidden();

    $this->assertDatabaseEmpty('pinned_applications');
});

it('pins an application once, without asking the host', function () {
    $lpod = fakeLpod();

    $this->actingAs($this->owner)
        ->put(action([ApplicationPinController::class, 'update'], [$this->environment, 'immich']))
        ->assertRedirect();

    $this->actingAs($this->owner)
        ->put(action([ApplicationPinController::class, 'update'], [$this->environment, 'immich']))
        ->assertRedirect();

    expect($this->owner->pinnedApplicationsOn($this->environment))->toBe(['immich']);
    $lpod->assertNothingRan();
});

it('unpins only the application of the current user on that host', function () {
    $otherUser = User::factory()->create();
    $otherEnvironment = Environment::factory()->create();
    $pin = PinnedApplication::factory()->for($this->owner)->for($this->environment)->create(['application' => 'immich']);
    $kept = [
        PinnedApplication::factory()->for($this->owner)->for($this->environment)->create(['application' => 'jellyfin']),
        PinnedApplication::factory()->for($this->owner)->for($otherEnvironment)->create(['application' => 'immich']),
        PinnedApplication::factory()->for($otherUser)->for($this->environment)->create(['application' => 'immich']),
    ];

    $this->actingAs($this->owner)
        ->delete(action([ApplicationPinController::class, 'destroy'], [$this->environment, 'immich']))
        ->assertRedirect();

    $this->assertModelMissing($pin);

    foreach ($kept as $other) {
        $this->assertModelExists($other);
    }
});
