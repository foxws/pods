<?php

declare(strict_types=1);

use Domain\Users\Models\User;
use Foundation\Settings\CatalogSettings;
use Modules\Web\Settings\Controllers\CatalogSettingsController;

beforeEach(function () {
    $this->owner = User::factory()->create()->assignRole('super-admin');
    $this->default = [['repository' => 'https://github.com/foxws/quadlets', 'reference' => 'main']];
});

it('starts with the foxws/quadlets repository', function () {
    $this->actingAs($this->owner)
        ->get(action([CatalogSettingsController::class, 'show']))
        ->assertOk()
        ->assertExactJson(['repositories' => $this->default]);
});

it('saves repositories in their order, without other fields', function () {
    $repositories = [
        ['repository' => 'git@github.com:example/private.git', 'reference' => 'v1.0.0'],
        ['repository' => 'ssh://git@git.example.com:2222/apps.git', 'reference' => 'release/next'],
        ...$this->default,
    ];

    $this->actingAs($this->owner)
        ->putJson(action([CatalogSettingsController::class, 'update']), [
            'repositories' => array_map(fn (array $source): array => [...$source, 'token' => 'not-kept'], $repositories),
        ])
        ->assertRedirect()
        ->assertInertiaFlash('type', 'success');

    expect(app(CatalogSettings::class)->refresh()->repositories)->toEqual($repositories);
});

it('saves an empty catalog', function () {
    $this->actingAs($this->owner)
        ->putJson(action([CatalogSettingsController::class, 'update']), ['repositories' => []])
        ->assertRedirect();

    expect(app(CatalogSettings::class)->refresh()->repositories)->toBe([]);
});

it('refuses a repository a host should not clone', function (array $source, string $field) {
    $this->actingAs($this->owner)
        ->put(action([CatalogSettingsController::class, 'update']), [
            'repositories' => [['repository' => 'https://example.com/apps', 'reference' => 'main', ...$source]],
        ])
        ->assertSessionHasErrors("repositories.0.{$field}");

    expect(app(CatalogSettings::class)->refresh()->repositories)->toEqual($this->default);
})->with([
    'no URL' => [['repository' => ''], 'repository'],
    'a local path' => [['repository' => '/srv/apps'], 'repository'],
    'a file URL' => [['repository' => 'file:///srv/apps'], 'repository'],
    'another transport' => [['repository' => 'ext::sh -c id'], 'repository'],
    'plain HTTP' => [['repository' => 'http://example.com/apps'], 'repository'],
    'an option as the host' => [['repository' => 'ssh://-oProxyCommand=id/apps'], 'repository'],
    'a token' => [['repository' => 'https://token@example.com/apps'], 'repository'],
    'a user and password' => [['repository' => 'https://user:password@example.com/apps'], 'repository'],
    'no branch or tag' => [['reference' => ''], 'reference'],
    'an option as the branch' => [['reference' => '--upload-pack=id'], 'reference'],
    'a branch with ..' => [['reference' => 'main..next'], 'reference'],
]);

it('refuses the same repository twice', function () {
    $this->actingAs($this->owner)
        ->put(action([CatalogSettingsController::class, 'update']), ['repositories' => [
            ['repository' => 'https://example.com/apps', 'reference' => 'main'],
            ['repository' => 'https://example.com/apps', 'reference' => 'next'],
        ]])
        ->assertSessionHasErrors('repositories.1.repository');
});

it('refuses a list it cannot use', function (array $payload) {
    $this->actingAs($this->owner)
        ->put(action([CatalogSettingsController::class, 'update']), $payload)
        ->assertSessionHasErrors('repositories');

    expect(app(CatalogSettings::class)->refresh()->repositories)->toEqual($this->default);
})->with([
    'no list' => [[]],
    'not a list' => [['repositories' => 'https://example.com/apps']],
    'more than the limit' => fn (): array => ['repositories' => array_map(
        fn (int $number): array => ['repository' => "https://example.com/apps-{$number}", 'reference' => 'main'],
        range(0, CatalogSettings::MaxRepositories),
    )],
]);

it('only lets an admin read or change the catalog', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(action([CatalogSettingsController::class, 'show']))->assertForbidden();
    $this->actingAs($user)->put(action([CatalogSettingsController::class, 'update']), ['repositories' => []])->assertForbidden();

    expect(app(CatalogSettings::class)->refresh()->repositories)->toEqual($this->default);
});

it('sends guests to the login page', function () {
    $this->put(action([CatalogSettingsController::class, 'update']), ['repositories' => []])->assertRedirect(route('login'));
});
