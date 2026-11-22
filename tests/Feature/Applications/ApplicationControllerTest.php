<?php

declare(strict_types=1);

use Domain\Applications\Models\Installation;
use Domain\Environments\Models\Environment;
use Domain\Users\Models\User;
use Foundation\Settings\CatalogSettings;
use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Process\Exceptions\ProcessTimedOutException;
use Integrations\Ssh\Contracts\RunsHostCommands;
use Integrations\Ssh\HostResult;
use Modules\Web\Applications\Controllers\ApplicationController;
use Symfony\Component\Process\Exception\ProcessTimedOutException as SymfonyTimeoutException;
use Symfony\Component\Process\Process;

beforeEach(function () {
    $this->owner = User::factory()->create()->assignRole('super-admin');
    $this->environment = Environment::factory()->create();
});

it('redirects guests to the login page', function () {
    $this->get(action([ApplicationController::class, 'index'], $this->environment))
        ->assertRedirect(route('login'));
});

it('forbids users who are not the owner', function () {
    $lpod = fakeLpod();

    $this->actingAs(User::factory()->create())
        ->get(action([ApplicationController::class, 'index'], $this->environment))
        ->assertForbidden();

    $lpod->assertNothingRan();
});

it('lists the applications and host stats of an environment', function () {
    fakeLpod();

    $this->actingAs($this->owner)
        ->get(action([ApplicationController::class, 'index'], $this->environment))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('Applications/ApplicationIndex')
            ->where('environment.id', $this->environment->ulid)
            ->missing('applications')
            ->loadDeferredProps(['default', 'images'], fn ($reload) => $reload
                ->has('applications.data', 6)
                ->where('images.data.3.update', '13')
                ->where('applications.data.0.name', 'immich')
                ->where('applications.data.0.description', 'Self-hosted photo library')
                ->where('applications.data.4.status', 'failed')
                ->where('applications.data.4.error', 'Secret ha-mqtt-password does not exist')
                ->where('applications.error', null)
                ->where('host.data.containers_running', 12)
                ->where('host.data.cpu_cores', 8)));
});

it('shows the error in place when lpod fails', function () {
    fakeLpod()->respond('apps --json', new HostResult(255, '', 'ssh: connect to host podman-01 port 22: Connection refused'));

    $this->actingAs($this->owner)
        ->get(action([ApplicationController::class, 'index'], $this->environment))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page->loadDeferredProps(fn ($reload) => $reload
            ->where('applications.data', null)
            ->where('applications.error', fn (string $error) => str_contains($error, 'Connection refused'))));
});

it('refuses an lpod that speaks another contract', function () {
    fakeLpod()->respond('apps --json', '{"contract": 2, "applications": []}');

    $this->actingAs($this->owner)
        ->get(action([ApplicationController::class, 'index'], $this->environment))
        ->assertInertia(fn ($page) => $page->loadDeferredProps(fn ($reload) => $reload
            ->where('applications.data', null)
            ->where('applications.error', fn (string $error) => str_contains($error, 'contract 2'))));
});

it('shows an application with its containers, files and journal', function () {
    $lpod = fakeLpod();

    $this->actingAs($this->owner)
        ->get(action([ApplicationController::class, 'show'], [$this->environment, 'immich']))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('Applications/ApplicationShow')
            ->where('name', 'immich')
            ->loadDeferredProps(['default', 'details'], fn ($reload) => $reload
                ->where('application.data.application.unit', 'immich-pod.service')
                ->has('application.data.items', 4)
                ->where('application.data.secrets', ['immich-db-password'])
                ->where('files.data.0.name', 'immich-server.container')
                ->has('logs.data', 5)
                ->has('secrets.data', 6)));

    $lpod->assertRan(['immich', 'status', '--json']);
});

it('shows where an installed application came from and what changed outside Pods since', function () {
    Installation::factory()->for($this->environment)->fromGit()
        ->withFiles(['immich.pod' => "[Pod]\nPodName=immich\n", 'immich-server.container' => lpodFixtureFiles()['immich-server.container']])
        ->create(['application' => 'immich']);
    fakeLpod();

    $this->actingAs($this->owner)
        ->get(action([ApplicationController::class, 'show'], [$this->environment, 'immich']))
        ->assertInertia(fn ($page) => $page->loadDeferredProps(['default', 'details'], fn ($reload) => $reload
            ->where('installation.data.repository', 'https://github.com/foxws/quadlets')
            ->where('installation.data.commit', 'a41f9c2')
            ->where('installation.data.manifest.label', 'Immich')
            ->where('installation.data.manifest.icon', 'i-lucide-image')
            ->where('installation.data.changed', true)
            ->where('installation.data.modified', ['immich.pod'])
            ->where('installation.data.added', [])
            ->where('installation.data.removed', [])));
});

it('shows no changes outside Pods when the files on the host are the installed ones', function () {
    Installation::factory()->for($this->environment)->fromGit()->withFiles(lpodFixtureFiles())
        ->create(['application' => 'immich', 'checked_at' => now()->subDay()]);
    fakeLpod();

    $this->actingAs($this->owner)
        ->get(action([ApplicationController::class, 'show'], [$this->environment, 'immich']))
        ->assertInertia(fn ($page) => $page->loadDeferredProps(['default', 'details'], fn ($reload) => $reload
            ->where('installation.data.changed', false)
            ->where('installation.data.modified', [])));
});

it('shows no installation for an application installed outside Pods', function () {
    fakeLpod();

    $this->actingAs($this->owner)
        ->get(action([ApplicationController::class, 'show'], [$this->environment, 'immich']))
        ->assertInertia(fn ($page) => $page->loadDeferredProps(['default', 'details'], fn ($reload) => $reload
            ->where('installation.data', null)
            ->where('installation.error', null)));
});

it('does not route application names that could be read as options', function () {
    $lpod = fakeLpod();

    $this->actingAs($this->owner)
        ->get("/environments/{$this->environment->ulid}/applications/-rf")
        ->assertNotFound();

    $lpod->assertNothingRan();
});

it('shows the install page with the secrets that already exist', function () {
    fakeLpod();

    $this->actingAs($this->owner)
        ->get(action([ApplicationController::class, 'create'], $this->environment))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('Applications/ApplicationCreate')
            ->loadDeferredProps(fn ($reload) => $reload->has('secrets.data', 6)));
});

it('offers the applications of the catalog repositories on the install page', function () {
    $lpod = fakeLpod();

    $this->actingAs($this->owner)
        ->get(action([ApplicationController::class, 'create'], $this->environment))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->missing('catalog')
            ->loadDeferredProps('catalog', fn ($reload) => $reload
                ->has('catalog.data.applications', 2)
                ->where('catalog.data.applications.0.name', 'jellyfin')
                ->where('catalog.data.applications.0.repository', 'https://github.com/foxws/quadlets')
                ->where('catalog.data.applications.0.path', 'jellyfin')
                ->where('catalog.data.applications.0.manifest.author', 'francoism90')
                ->where('catalog.data.applications.0.manifest.icon', 'i-lucide-clapperboard')
                ->where('catalog.data.applications.0.manifest.sources', [])
                ->where('catalog.data.applications.1.manifest.label', 'MariaDB')
                ->where('catalog.data.applications.1.manifest.sources.0.path', 'mariadb/production')
                ->where('catalog.data.errors', ["scratch: pods.yml is ignored: it's version 2, but Pods reads version 1. Update Pods."])
                ->where('catalog.error', null)));

    $lpod->assertRan(['quadlet', 'catalog', 'https://github.com/foxws/quadlets', '--ref', 'main', '--json']);
});

it('shows why the catalog could not be read', function () {
    fakeLpod()->respond('quadlet catalog *', new HostResult(1, '', 'fatal: unable to access the repository'));

    $this->actingAs($this->owner)
        ->get(action([ApplicationController::class, 'create'], $this->environment))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->loadDeferredProps('catalog', fn ($reload) => $reload
                ->where('catalog.data', null)
                ->whereType('catalog.error', 'string')));
});

it('reads every repository in the settings, and names the one behind an unreadable pods.yml', function () {
    saveCatalog('https://example.com/first', 'https://example.com/second');
    $lpod = fakeLpod();

    $this->actingAs($this->owner)
        ->get(action([ApplicationController::class, 'create'], $this->environment))
        ->assertInertia(fn ($page) => $page
            ->loadDeferredProps('catalog', fn ($reload) => $reload
                ->has('catalog.data.applications', 4)
                ->where('catalog.data.applications.0.repository', 'https://example.com/first')
                ->where('catalog.data.applications.2.repository', 'https://example.com/second')
                ->where('catalog.data.failures', [])
                ->where('catalog.data.errors.1', "https://example.com/second, scratch: pods.yml is ignored: it's version 2, but Pods reads version 1. Update Pods.")));

    $lpod->assertRan(['quadlet', 'catalog', 'https://example.com/first', '--ref', 'main', '--json']);
    $lpod->assertRan(['quadlet', 'catalog', 'https://example.com/second', '--ref', 'main', '--json']);
});

it('keeps the other repositories when one cannot be read', function () {
    saveCatalog('https://example.com/broken', 'https://example.com/working');
    fakeLpod()->respond('quadlet catalog https://example.com/broken *', new HostResult(1, '', 'fatal: repository not found'));

    $this->actingAs($this->owner)
        ->get(action([ApplicationController::class, 'create'], $this->environment))
        ->assertInertia(fn ($page) => $page
            ->loadDeferredProps('catalog', fn ($reload) => $reload
                ->has('catalog.data.applications', 2)
                ->where('catalog.data.applications.0.repository', 'https://example.com/working')
                ->where('catalog.data.failures', ['lpod quadlet catalog https://example.com/broken --ref main --json failed: fatal: repository not found'])
                ->where('catalog.error', null)));
});

it('shows one error when the host is down for every repository', function () {
    saveCatalog('https://example.com/first', 'https://example.com/second');
    fakeLpod()->respond('quadlet catalog *', new HostResult(255, '', 'Connection refused'));

    $this->actingAs($this->owner)
        ->get(action([ApplicationController::class, 'create'], $this->environment))
        ->assertInertia(fn ($page) => $page
            ->loadDeferredProps('catalog', fn ($reload) => $reload
                ->where('catalog.data', null)
                ->where('catalog.error', "Could not connect to {$this->environment->target()}: Connection refused")));
});

it('gives a hanging repository its share of the time, and reads the next one', function () {
    $this->freezeSecond();
    saveCatalog('https://example.com/hanging', 'https://example.com/working');
    $timeouts = [];

    $this->mock(RunsHostCommands::class)
        ->shouldReceive('run')
        ->andReturnUsing(function ($environment, array $arguments, ?string $input, int $timeout) use (&$timeouts): HostResult {
            $timeouts[$arguments[2]] = $timeout;

            if ($arguments[2] === 'https://example.com/hanging') {
                $this->travel($timeout)->seconds();

                throw new ProcessTimedOutException(
                    new SymfonyTimeoutException((new Process(['ssh']))->setTimeout($timeout), SymfonyTimeoutException::TYPE_GENERAL),
                    Mockery::mock(ProcessResult::class),
                );
            }

            return new HostResult(0, lpodFixture('quadlet-catalog'), '');
        });

    $this->actingAs($this->owner)
        ->get(action([ApplicationController::class, 'create'], $this->environment))
        ->assertInertia(fn ($page) => $page
            ->loadDeferredProps('catalog', fn ($reload) => $reload
                ->has('catalog.data.applications', 2)
                ->where('catalog.data.failures', ["https://example.com/hanging didn't answer within 38 seconds. Reload the page to try again."])));

    expect($timeouts)->toBe(['https://example.com/hanging' => 38, 'https://example.com/working' => 37]);
});

it('leaves out an application whose pods.yml cannot be sent to the page', function (string $manifest) {
    fakeLpod()->respond('quadlet catalog *', json_encode(['contract' => 1, 'applications' => [
        ['path' => 'broken', 'manifest' => $manifest],
        ['path' => 'hello', 'manifest' => 'label: Hello'],
    ]]));

    $this->actingAs($this->owner)
        ->get(action([ApplicationController::class, 'create'], $this->environment))
        ->assertInertia(fn ($page) => $page
            ->loadDeferredProps('catalog', fn ($reload) => $reload
                ->has('catalog.data.applications', 1)
                ->where('catalog.data.applications.0.name', 'hello')
                ->has('catalog.data.errors', 1)));
})->with([
    'binary text' => 'label: !!binary //4=',
    'a binary version' => 'version: !!binary //4=',
]);

it('asks the host for nothing when the catalog has no repositories', function () {
    saveCatalog();
    $lpod = fakeLpod();

    $this->actingAs($this->owner)
        ->get(action([ApplicationController::class, 'create'], $this->environment))
        ->assertInertia(fn ($page) => $page
            ->loadDeferredProps('catalog', fn ($reload) => $reload
                ->where('catalog.data.applications', [])
                ->where('catalog.error', null)));

    $lpod->assertNothingRan();
});

it('removes an application and the data it owns once confirmed', function () {
    $lpod = fakeLpod();

    $this->actingAs($this->owner)
        ->delete(action([ApplicationController::class, 'destroy'], [$this->environment, 'immich']), [
            'confirmation' => 'immich',
            'volumes' => true,
            'secrets' => true,
        ])
        ->assertRedirect(action([ApplicationController::class, 'index'], $this->environment))
        ->assertInertiaFlash('type', 'success');

    $lpod->assertRan(['immich', 'remove', '--volumes', '--secrets', '--json']);
});

it('forgets the installation of a removed application', function () {
    $installation = Installation::factory()->for($this->environment)->create(['application' => 'immich']);
    fakeLpod();

    $this->actingAs($this->owner)
        ->delete(action([ApplicationController::class, 'destroy'], [$this->environment, 'immich']), ['confirmation' => 'immich'])
        ->assertRedirect(action([ApplicationController::class, 'index'], $this->environment));

    $this->assertModelMissing($installation);
});

it('keeps the installation when the application could not be removed', function () {
    $installation = Installation::factory()->for($this->environment)->create(['application' => 'immich']);
    fakeLpod()->respond('* remove*--json', new HostResult(1, '', 'Unit immich-pod.service is busy'));

    $this->actingAs($this->owner)
        ->delete(action([ApplicationController::class, 'destroy'], [$this->environment, 'immich']), ['confirmation' => 'immich'])
        ->assertInertiaFlash('type', 'error');

    $this->assertModelExists($installation);
});

it('does not remove an application without typing its name', function () {
    $lpod = fakeLpod();

    $this->actingAs($this->owner)
        ->delete(action([ApplicationController::class, 'destroy'], [$this->environment, 'immich']), ['confirmation' => 'immic'])
        ->assertSessionHasErrors(['confirmation' => 'Type the name of the application to confirm.']);

    $lpod->assertNothingRan();
});

it('forbids users who are not the owner to remove an application', function () {
    $lpod = fakeLpod();

    $this->actingAs(User::factory()->create())
        ->delete(action([ApplicationController::class, 'destroy'], [$this->environment, 'immich']), ['confirmation' => 'immich'])
        ->assertForbidden();

    $lpod->assertNothingRan();
});

function saveCatalog(string ...$repositories): void
{
    $settings = app(CatalogSettings::class);
    $settings->repositories = array_map(
        fn (string $repository): array => ['repository' => $repository, 'reference' => 'main'],
        $repositories,
    );
    $settings->save();
}
