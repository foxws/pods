<?php

declare(strict_types=1);

use Domain\Environments\Models\Environment;
use Domain\Users\Models\User;

it('sends guests to the login page without javascript errors', function () {
    visit('/')
        ->assertPathIs('/login')
        ->assertNoJavaScriptErrors();
});

it('logs the owner in and opens the applications of the local environment', function () {
    fakeLpod();
    $owner = User::factory()->create(['email' => 'jane@example.com'])->assignRole('super-admin');
    $environment = Environment::factory()->local()->create();

    visit('/login')
        ->fill('email', 'jane@example.com')
        ->fill('password', 'password')
        ->press('Continue')
        ->assertPathIs("/environments/{$environment->ulid}/applications")
        ->assertSee('immich')
        ->assertSee('home-assistant.service failed to start')
        ->assertNoJavaScriptErrors();

    $this->assertAuthenticatedAs($owner);
});
