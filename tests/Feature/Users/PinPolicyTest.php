<?php

declare(strict_types=1);

use Domain\Users\Models\Pin;
use Domain\Users\Models\User;
use Illuminate\Support\Facades\Gate;

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->pin = Pin::factory()->for($this->owner)->application('immich')->create();
});

it('lets every user list and create their own pins', function (string $ability) {
    expect(Gate::forUser(User::factory()->create())->allows($ability, Pin::class))->toBeTrue();
})->with(['viewAny', 'create']);

it('lets only the owner view, update and delete a pin', function (string $ability) {
    expect(Gate::forUser($this->owner)->allows($ability, $this->pin))->toBeTrue();
})->with(['view', 'update', 'delete']);

it('hides a pin from everyone but its owner, admins included', function (string $ability, string $role) {
    $other = User::factory()->create();

    if ($role !== 'none') {
        $other->assignRole($role);
    }

    expect(Gate::forUser($other)->inspect($ability, $this->pin))
        ->allowed()->toBeFalse()
        ->status()->toBe(404);
})->with(['view', 'update', 'delete'])->with(['none', 'admin', 'super-admin']);

it('never restores or force deletes a pin, which is not soft deleted', function (string $ability) {
    expect(Gate::forUser($this->owner)->allows($ability, $this->pin))->toBeFalse();
})->with(['restore', 'forceDelete']);
