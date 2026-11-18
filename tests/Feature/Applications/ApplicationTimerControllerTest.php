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
        'application' => 'nextcloud',
        'manifest' => "version: 1\ntimers:\n  cron:\n    description: Runs the background jobs\n    container: nextcloud-cron\n    on_calendar: hourly\n",
    ]);

    $this->actingAs($this->owner)
        ->get(action([ApplicationController::class, 'show'], [$this->environment, 'nextcloud']))
        ->assertInertia(fn ($page) => $page->loadDeferredProps('details', fn ($reload) => $reload
            ->where('timers.data.0.unit', 'nextcloud-cron.timer')
            ->where('timers.data.0.on_calendar', ['*:0/5'])
            ->where('timers.data.0.next_run', '2026-11-18T16:05:00+00:00')
            ->where('timers.data.0.last_result', 'success')
            ->where('installation.data.manifest.timers.0.description', 'Runs the background jobs')));
});

it('does not ask the host for the timers of an application whose pods.yml names none', function () {
    $lpod = fakeLpod();
    Installation::factory()->for($this->environment)->create(['application' => 'nextcloud', 'manifest' => "version: 1\n"]);

    $this->actingAs($this->owner)
        ->get(action([ApplicationController::class, 'show'], [$this->environment, 'nextcloud']))
        ->assertInertia(fn ($page) => $page->loadDeferredProps('details', fn ($reload) => $reload->where('timers.data', [])));

    expect(collect($lpod->recorded())->pluck('arguments')->contains(fn (array $arguments): bool => ($arguments[1] ?? null) === 'timers'))->toBeFalse();
});

it('runs the container of a timer now', function () {
    $lpod = fakeLpod();

    $this->actingAs($this->owner)
        ->post(action([ApplicationTimerController::class, 'store'], [$this->environment, 'nextcloud', 'cron']))
        ->assertRedirect()
        ->assertInertiaFlash('type', 'success')
        ->assertInertiaFlash('title', 'cron of nextcloud started');

    $lpod->assertRan(['nextcloud', 'timers', 'run', 'cron', '--json']);
});

it('shows the error when lpod cannot run the timer', function () {
    fakeLpod()->respond('* timers run * --json', new HostResult(1, '', 'nextcloud has no timer named cron.'));

    $this->actingAs($this->owner)
        ->post(action([ApplicationTimerController::class, 'store'], [$this->environment, 'nextcloud', 'cron']))
        ->assertInertiaFlash('type', 'error')
        ->assertInertiaFlash('description', 'lpod nextcloud timers run cron --json failed: nextcloud has no timer named cron.');
});

it('does not pass a timer name lpod would refuse', function () {
    $lpod = fakeLpod();

    $this->actingAs($this->owner)
        ->post(action([ApplicationTimerController::class, 'store'], [$this->environment, 'nextcloud', '--json']))
        ->assertNotFound();

    $lpod->assertNothingRan();
});

it('forbids users who are not the owner to run a timer', function () {
    $lpod = fakeLpod();

    $this->actingAs(User::factory()->create())
        ->post(action([ApplicationTimerController::class, 'store'], [$this->environment, 'nextcloud', 'cron']))
        ->assertForbidden();

    $lpod->assertNothingRan();
});
