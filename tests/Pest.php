<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Integrations\Ssh\Contracts\RunsHostCommands;
use Integrations\Ssh\Testing\FakeHostRunner;
use Tests\CreatesApplication;

expect()
    ->extend('toBeSameModel', fn (Model $model) => $this->is($model)->toBeTrue());

uses(TestCase::class, CreatesApplication::class, RefreshDatabase::class)
    ->beforeEach(function () {
        // Make sure we do not run on production
        throw_if(app()->environment() === 'production');

        // Fake instances
        Bus::fake();
        Mail::fake();
        Notification::fake();
        Queue::fake();
        Storage::fake();

        // Keep SSH keys and known_hosts files out of the real storage directory
        config(['services.pods.ssh_directory' => sys_get_temp_dir().'/pods-ssh-'.Str::random(16)]);

        // Setup database
        $this->seed();
    })
    ->afterEach(function () {
        File::deleteDirectory((string) config('services.pods.ssh_directory'));
    })
    ->in(__DIR__);

/**
 * Binds a fake host that answers lpod commands with the contract fixtures.
 */
function fakeLpod(): FakeHostRunner
{
    $fixture = lpodFixture(...);

    $runner = new FakeHostRunner([
        'version --json' => $fixture('version'),
        'host --json' => $fixture('host'),
        'apps --json' => $fixture('apps'),
        'secrets --json' => $fixture('secrets'),
        '* status --json' => $fixture('app-status'),
        '* files --json' => $fixture('app-files'),
        '* logs --json *' => $fixture('app-logs'),
        '* up --no-block --json' => $fixture('ok'),
        '* down --no-block --json' => $fixture('ok'),
        '* restart --no-block --json' => $fixture('ok'),
        '* redeploy --no-block --json' => $fixture('ok'),
        'secret create *' => $fixture('ok'),
        '* secret add *' => $fixture('ok'),
        'secret rm *' => $fixture('ok'),
        '* remove*--json' => $fixture('ok'),
        'containers --json' => $fixture('containers'),
        'container * inspect --json' => $fixture('container-inspect'),
        'container * logs --json *' => $fixture('app-logs'),
        'container * start --json' => $fixture('ok'),
        'container * stop --json' => $fixture('ok'),
        'container * restart --json' => $fixture('ok'),
        'container * stats --json' => $fixture('container-stats'),
        'containers prune --json' => $fixture('container-prune'),
        'pods --json' => $fixture('pods'),
        'events --json *' => $fixture('events'),
        'images --json' => $fixture('images'),
        'image pull * --json' => $fixture('ok'),
        'image rm * --json' => $fixture('ok'),
        'images prune --json' => $fixture('image-prune'),
        'auto-update --dry-run --json' => $fixture('ok'),
        'volumes --json' => $fixture('volumes'),
        'volume rm * --json' => $fixture('ok'),
        'volumes prune --json' => $fixture('volume-prune'),
        'networks --json' => $fixture('networks'),
        'network rm * --json' => $fixture('ok'),
        'networks prune --json' => $fixture('network-prune'),
        'journal --json *' => $fixture('journal'),
        'quadlet check --stdin --json' => $fixture('quadlet-check'),
        'quadlet install *' => $fixture('ok'),
        'quadlet fetch *' => $fixture('quadlet-fetch'),
        'settings --json' => $fixture('host-settings'),
        'settings set *' => $fixture('ok'),
    ]);

    app()->instance(RunsHostCommands::class, $runner);

    return $runner;
}

/**
 * Gives the JSON of an lpod contract fixture.
 */
function lpodFixture(string $name): string
{
    return file_get_contents(__DIR__."/Fixtures/lpod/contract-1/{$name}.json");
}
