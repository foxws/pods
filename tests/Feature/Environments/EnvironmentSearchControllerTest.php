<?php

declare(strict_types=1);

use Domain\Environments\Models\Environment;
use Domain\Users\Models\User;
use Integrations\Ssh\HostResult;
use Modules\Web\Containers\Controllers\ContainerController;
use Modules\Web\Environments\Controllers\EnvironmentSearchController;
use Modules\Web\Images\Controllers\ImageController;

beforeEach(function () {
    $this->owner = User::factory()->create()->assignRole('super-admin');
    $this->environment = Environment::factory()->create();
});

it('lists what is on the host for the search palette', function () {
    fakeLpod();

    $response = $this->actingAs($this->owner)
        ->getJson(action(EnvironmentSearchController::class, $this->environment))
        ->assertSuccessful()
        ->assertJsonPath('error', null);

    $results = collect($response->json('data'));

    expect($results->pluck('group')->unique()->values()->all())
        ->toBe(['applications', 'containers', 'pods', 'images', 'volumes', 'networks', 'secrets'])
        ->and($results->firstWhere('label', 'immich-server'))
        ->toMatchArray([
            'group' => 'containers',
            'url' => action([ContainerController::class, 'show'], [$this->environment, 'immich-server']),
        ])
        ->and($results->firstWhere('label', 'docker.io/vaultwarden/server:1.34'))
        ->toMatchArray([
            'group' => 'images',
            'url' => action([ImageController::class, 'index'], $this->environment).'?search=docker.io%2Fvaultwarden%2Fserver%3A1.34',
        ]);
});

it('returns the error when the host cannot be read', function () {
    fakeLpod()->respond('apps --json', new HostResult(255, '', 'ssh: connect to host pods.test port 22: Connection refused'));

    $this->actingAs($this->owner)
        ->getJson(action(EnvironmentSearchController::class, $this->environment))
        ->assertSuccessful()
        ->assertJsonPath('data', null)
        ->assertJson(fn ($json) => $json->whereType('error', 'string')->etc());
});

it('forbids users who are not the owner', function () {
    $lpod = fakeLpod();

    $this->actingAs(User::factory()->create())
        ->getJson(action(EnvironmentSearchController::class, $this->environment))
        ->assertForbidden();

    $lpod->assertNothingRan();
});
