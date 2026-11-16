<?php

declare(strict_types=1);

use Domain\Environments\Models\Environment;
use Domain\Users\Models\User;
use Integrations\Ssh\HostResult;
use Modules\Web\Pods\Controllers\PodController;

beforeEach(function () {
    $this->owner = User::factory()->create()->assignRole('super-admin');
    $this->environment = Environment::factory()->create();
});

it('lists the pods with their applications, containers and ports', function () {
    $lpod = fakeLpod();

    $this->actingAs($this->owner)
        ->get(action([PodController::class, 'index'], $this->environment))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('Pods/PodIndex')
            ->missing('pods')
            ->loadDeferredProps(fn ($reload) => $reload
                ->has('pods.data', 3)
                ->where('pods.data.0.application', 'immich')
                ->where('pods.data.0.ports', ['0.0.0.0:2283->2283/tcp'])
                ->where('pods.data.1.status', 'degraded')
                ->where('pods.data.1.running', 2)
                ->where('pods.data.2.quadlet', null)));

    $lpod->assertRan(['pods', '--json']);
});

it('asks to update lpod when it has no pods command yet', function () {
    fakeLpod()->respond('pods --json', new HostResult(125, '', 'Error: unknown flag: --json'));

    $this->actingAs($this->owner)
        ->get(action([PodController::class, 'index'], $this->environment))
        ->assertInertia(fn ($page) => $page->loadDeferredProps(fn ($reload) => $reload
            ->where('pods.data', null)
            ->where('pods.error', fn (string $error) => str_contains($error, 'Update lpod'))));
});

it('forbids users who are not the owner', function () {
    $lpod = fakeLpod();

    $this->actingAs(User::factory()->create())
        ->get(action([PodController::class, 'index'], $this->environment))
        ->assertForbidden();

    $lpod->assertNothingRan();
});
