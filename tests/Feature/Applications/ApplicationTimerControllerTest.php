<?php

declare(strict_types=1);

use Domain\Applications\Models\Installation;
use Domain\Environments\Models\Environment;
use Domain\Users\Models\User;
use Integrations\Ssh\HostResult;
use Modules\Web\Applications\Controllers\ApplicationController;
use Modules\Web\Applications\Controllers\ApplicationTimerController;

beforeEach(function () {
    $this->owner = User::factory()->create()->assignRole('super-admin');
    $this->environment = Environment::factory()->create();
});

it('shows the timers of an application whose pods.yml names them', function () {
    fakeLpod();
    Installation::factory()->for($this->environment)->create([
        'application' => 'spotweb',
        'manifest' => "version: 1\ntimers:\n  retrieve:\n    description: Fetches new spots\n    container: spotweb-retrieve\n    on_calendar: hourly\n",
    ]);

    $this->actingAs($this->owner)
        ->get(action([ApplicationController::class, 'show'], [$this->environment, 'spotweb']))
        ->assertInertia(fn ($page) => $page->loadDeferredProps('details', fn ($reload) => $reload
            ->where('timers.data.0.unit', 'spotweb-retrieve.timer')
            ->where('timers.data.0.on_calendar', ['hourly'])
            ->where('timers.data.0.next_run', '2026-11-18T16:00:00+00:00')
            ->where('timers.data.0.last_result', 'success')
            ->where('installation.data.manifest.timers.0.description', 'Fetches new spots')));
});

it('does not ask the host for the timers of an application whose pods.yml names none', function () {
    $lpod = fakeLpod();
    Installation::factory()->for($this->environment)->create(['application' => 'spotweb', 'manifest' => "version: 1\n"]);

    $this->actingAs($this->owner)
        ->get(action([ApplicationController::class, 'show'], [$this->environment, 'spotweb']))
        ->assertInertia(fn ($page) => $page->loadDeferredProps('details', fn ($reload) => $reload->where('timers.data', [])));

    expect(collect($lpod->recorded())->pluck('arguments')->contains(fn (array $arguments): bool => ($arguments[1] ?? null) === 'timers'))->toBeFalse();
});

it('runs the container of a timer now', function () {
    $lpod = fakeLpod();

    $this->actingAs($this->owner)
        ->post(action([ApplicationTimerController::class, 'store'], [$this->environment, 'spotweb', 'retrieve']))
        ->assertRedirect()
        ->assertInertiaFlash('type', 'success')
        ->assertInertiaFlash('title', 'retrieve of spotweb started');

    $lpod->assertRan(['spotweb', 'timers', 'run', 'retrieve', '--json']);
});

it('shows the error when lpod cannot run the timer', function () {
    fakeLpod()->respond('* timers run * --json', new HostResult(1, '', 'spotweb has no timer named retrieve.'));

    $this->actingAs($this->owner)
        ->post(action([ApplicationTimerController::class, 'store'], [$this->environment, 'spotweb', 'retrieve']))
        ->assertInertiaFlash('type', 'error')
        ->assertInertiaFlash('description', 'lpod spotweb timers run retrieve --json failed: spotweb has no timer named retrieve.');
});

it('does not pass a timer name lpod would refuse', function () {
    $lpod = fakeLpod();

    $this->actingAs($this->owner)
        ->post(action([ApplicationTimerController::class, 'store'], [$this->environment, 'spotweb', '--json']))
        ->assertNotFound();

    $lpod->assertNothingRan();
});

it('forbids users who are not the owner to run a timer', function () {
    $lpod = fakeLpod();

    $this->actingAs(User::factory()->create())
        ->post(action([ApplicationTimerController::class, 'store'], [$this->environment, 'spotweb', 'retrieve']))
        ->assertForbidden();

    $lpod->assertNothingRan();
});
