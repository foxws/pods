<?php

declare(strict_types=1);

use Domain\Environments\Models\Environment;
use Domain\Users\Enums\PinType;
use Domain\Users\Models\Pin;
use Domain\Users\Models\User;
use Modules\Web\Users\Controllers\PinController;

beforeEach(function () {
    $this->owner = User::factory()->create()->assignRole('super-admin');
    $this->environment = Environment::factory()->create();
});

it('redirects guests to the login page', function () {
    $this->post(action([PinController::class, 'store'], $this->environment), ['type' => 'application', 'name' => 'immich'])
        ->assertRedirect(route('login'));

    $this->assertDatabaseEmpty('pins');
});

it('forbids users who are not the owner', function () {
    $this->actingAs(User::factory()->create())
        ->post(action([PinController::class, 'store'], $this->environment), ['type' => 'application', 'name' => 'immich'])
        ->assertForbidden();

    $this->assertDatabaseEmpty('pins');
});

it('pins an application once, without asking the host', function () {
    $lpod = fakeLpod();

    foreach ([1, 2] as $attempt) {
        $this->actingAs($this->owner)
            ->post(action([PinController::class, 'store'], $this->environment), ['type' => 'application', 'name' => 'immich'])
            ->assertRedirect();
    }

    expect($this->owner->pins()->ofType(PinType::Application)->on($this->environment)->pluck('name')->all())->toBe(['immich']);
    $this->assertDatabaseCount('pins', 1);
    $lpod->assertNothingRan();
});

it('refuses what it cannot pin', function (array $payload, string $field) {
    $this->actingAs($this->owner)
        ->post(action([PinController::class, 'store'], $this->environment), $payload)
        ->assertSessionHasErrors($field);

    $this->assertDatabaseEmpty('pins');
})->with([
    'an unknown type' => [['type' => 'planet', 'name' => 'immich'], 'type'],
    'an application name read as an option' => [['type' => 'application', 'name' => '-rf'], 'name'],
    'an application name with a path' => [['type' => 'application', 'name' => '../immich'], 'name'],
    'an application name too long' => [['type' => 'application', 'name' => str_repeat('a', 129)], 'name'],
]);

it('unpins a pin of the current user', function () {
    $pin = Pin::factory()->for($this->owner)->for($this->environment)->application('immich')->create();
    $other = Pin::factory()->for($this->owner)->for($this->environment)->application('jellyfin')->create();

    $this->actingAs($this->owner)
        ->delete(action([PinController::class, 'destroy'], [$this->environment, $pin]))
        ->assertRedirect();

    $this->assertModelMissing($pin);
    $this->assertModelExists($other);
});

it('does not find the pin of another user', function () {
    $otherOwner = User::factory()->create()->assignRole('super-admin');
    $pin = Pin::factory()->for($otherOwner)->for($this->environment)->application('immich')->create();

    $this->actingAs($this->owner)
        ->delete(action([PinController::class, 'destroy'], [$this->environment, $pin]))
        ->assertNotFound();

    $this->assertModelExists($pin);
});

it('does not find a pin through another environment', function () {
    $pin = Pin::factory()->for($this->owner)->for(Environment::factory())->application('immich')->create();

    $this->actingAs($this->owner)
        ->delete(action([PinController::class, 'destroy'], [$this->environment, $pin]))
        ->assertNotFound();

    $this->assertModelExists($pin);
});
