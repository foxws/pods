<?php

declare(strict_types=1);

use Domain\Environments\Models\Environment;
use Domain\Users\Models\User;
use Modules\Web\Applications\Controllers\ApplicationController;
use Modules\Web\Environments\Controllers\EnvironmentController;
use Modules\Web\Home\Controllers\HomeController;

it('sends guests to the login page', function () {
    $this->get(action(HomeController::class))
        ->assertRedirect(route('login'));
});

it('opens the local environment for the owner', function () {
    $owner = User::factory()->create()->assignRole('super-admin');
    Environment::factory()->create(['name' => 'alpha']);
    $local = Environment::factory()->local()->create();

    $this->actingAs($owner)
        ->get(action(HomeController::class))
        ->assertRedirect(action([ApplicationController::class, 'index'], $local));
});

it('sends the owner to the environment list when there are no environments', function () {
    $owner = User::factory()->create()->assignRole('super-admin');

    $this->actingAs($owner)
        ->get(action(HomeController::class))
        ->assertRedirect(action([EnvironmentController::class, 'index']));
});
