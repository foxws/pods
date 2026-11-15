<?php

declare(strict_types=1);

use Domain\Environments\Models\Environment;
use Domain\Users\Models\User;
use Modules\Web\Journal\Controllers\JournalController;

beforeEach(function () {
    $this->owner = User::factory()->create()->assignRole('super-admin');
    $this->environment = Environment::factory()->create();
});

it('shows the journal of the last hour by default', function () {
    $lpod = fakeLpod();

    $this->actingAs($this->owner)
        ->get(action(JournalController::class, $this->environment))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('Journal/JournalIndex')
            ->where('since', '1h')
            ->loadDeferredProps(fn ($reload) => $reload->has('entries.data', 6)));

    $lpod->assertRan(['journal', '--json', '--lines', '500', '--since', '-1h']);
});

it('reads the chosen time window', function () {
    $lpod = fakeLpod();

    $this->actingAs($this->owner)
        ->get(action(JournalController::class, [$this->environment, 'since' => 'today']))
        ->assertInertia(fn ($page) => $page->where('since', 'today')->loadDeferredProps(fn ($reload) => $reload));

    $lpod->assertRan(['journal', '--json', '--lines', '500', '--since', 'today']);
});

it('falls back to the last hour for an unknown window', function () {
    $lpod = fakeLpod();

    $this->actingAs($this->owner)
        ->get(action(JournalController::class, [$this->environment, 'since' => '$(reboot)']))
        ->assertInertia(fn ($page) => $page->where('since', '1h')->loadDeferredProps(fn ($reload) => $reload));

    $lpod->assertRan(['journal', '--json', '--lines', '500', '--since', '-1h']);
});

it('forbids users who are not the owner', function () {
    $this->actingAs(User::factory()->create())
        ->get(action(JournalController::class, $this->environment))
        ->assertForbidden();
});
