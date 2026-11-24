<?php

declare(strict_types=1);

use Domain\Users\Models\User;
use Laravel\Sanctum\Sanctum;

it('lists the available endpoints', function () {
    $this->getJson(route('api.home'))
        ->assertSuccessful()
        ->assertJsonPath('version', 'v1')
        ->assertJsonPath('links.user', route('api.user'));
});

it('returns the authenticated user', function () {
    $user = User::factory()->create();

    Sanctum::actingAs($user);

    $this->getJson(route('api.user'))
        ->assertSuccessful()
        ->assertJsonPath('id', $user->getRouteKey())
        ->assertJsonPath('email', $user->email)
        ->assertJsonMissingPath('password');
});

it('rejects guests', function () {
    $this->getJson(route('api.user'))
        ->assertUnauthorized();
});

it('lets an admin search users', function () {
    $admin = User::factory()->create();
    $admin->assignRole('super-admin');

    User::factory()->create(['name' => 'Jane Doe']);
    User::factory()->create(['name' => 'John Smith']);

    Sanctum::actingAs($admin);

    $this->getJson(route('api.users.index', ['query' => 'Jane']))
        ->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Jane Doe');
});

it('forbids regular users from listing users, before validating the search', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->getJson(route('api.users.index', ['sort' => 'email']))
        ->assertForbidden();
});

it('sorts users by an allowed sort, descending with a leading minus', function () {
    $admin = User::factory()->create(['name' => 'Admin']);
    $admin->assignRole('super-admin');
    User::factory()->create(['name' => 'Zoe']);
    User::factory()->create(['name' => 'Bea']);

    Sanctum::actingAs($admin);

    $this->getJson(route('api.users.index', ['sort' => '-name']))
        ->assertSuccessful()
        ->assertJsonPath('data.*.name', ['Zoe', 'Bea', 'Admin']);
});

it('refuses a search the endpoint does not allow', function (array $parameters, string $field) {
    $admin = User::factory()->create();
    $admin->assignRole('super-admin');

    Sanctum::actingAs($admin);

    $this->getJson(route('api.users.index', $parameters))
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);
})->with([
    'unknown sort' => [['sort' => 'email'], 'sort'],
    'unknown sort after an allowed one' => [['sort' => 'name,-email'], 'sort'],
    'filter' => [['filter' => ['email' => 'jane@example.com']], 'filter'],
    'include' => [['include' => 'roles'], 'include'],
    'page too large' => [['page' => ['size' => 31]], 'page.size'],
    'unknown page key' => [['page' => ['offset' => 10]], 'page'],
    'query too long' => [['query' => str_repeat('a', 101)], 'query'],
]);
