<?php

declare(strict_types=1);

use Domain\Environments\Models\Environment;
use Domain\Users\Actions\OwnerSetup;
use Domain\Users\Models\User;

it('sends guests to the login page without javascript errors', function () {
    User::factory()->create()->assignRole('super-admin');

    visit('/')
        ->assertPathIs('/login')
        ->assertNoJavaScriptErrors();
});

it('logs the owner in and opens the overview of the local environment', function () {
    fakeLpod();
    $owner = User::factory()->create(['email' => 'jane@example.com'])->assignRole('super-admin');
    $environment = Environment::factory()->local()->create();

    visit('/login')
        ->fill('email', 'jane@example.com')
        ->fill('password', 'password')
        ->press('Continue')
        ->assertPathIs("/environments/{$environment->ulid}")
        ->assertSee('Needs attention')
        ->assertSee('immich')
        ->assertSee('home-assistant failed')
        ->assertNoJavaScriptErrors();

    $this->assertAuthenticatedAs($owner);
});

it('sends guests of a new instance to the setup page without javascript errors', function () {
    visit('/')
        ->assertPathIs('/setup')
        ->assertSee('Set up Pods')
        ->assertNoJavaScriptErrors();
});

it('creates the owner on the setup page', function () {
    $code = app(OwnerSetup::class)->code();

    visit('/setup')
        ->fill('code', $code)
        ->fill('name', 'Jane Owner')
        ->fill('email', 'jane@example.com')
        ->fill('password', 'Sup3r-Secret-Passw0rd!')
        ->fill('password_confirmation', 'Sup3r-Secret-Passw0rd!')
        ->press('Create owner')
        ->assertPathIsNot('/setup')
        ->assertNoJavaScriptErrors();

    expect(User::query()->where('email', 'jane@example.com')->firstOrFail()->hasRole('super-admin'))->toBeTrue();
});
