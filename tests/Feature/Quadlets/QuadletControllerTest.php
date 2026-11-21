<?php

declare(strict_types=1);

use Domain\Applications\Models\Installation;
use Domain\Environments\Models\Environment;
use Domain\Users\Models\User;
use Integrations\Ssh\HostResult;
use Modules\Web\Applications\Controllers\ApplicationController;
use Modules\Web\Quadlets\Controllers\QuadletCheckController;
use Modules\Web\Quadlets\Controllers\QuadletController;
use Modules\Web\Quadlets\Controllers\QuadletFetchController;

beforeEach(function () {
    $this->owner = User::factory()->create()->assignRole('super-admin');
    $this->environment = Environment::factory()->create();
    $this->files = [
        ['name' => 'uptime-kuma.container', 'content' => "[Container]\nImage=docker.io/louislam/uptime-kuma:2\n"],
        ['name' => 'uptime-kuma-data.volume', 'content' => "[Volume]\n"],
    ];
});

it('installs the files as an application and starts it', function () {
    $lpod = fakeLpod();

    $this->actingAs($this->owner)
        ->post(action([QuadletController::class, 'store'], $this->environment), [
            'application' => 'uptime-kuma',
            'files' => $this->files,
            'start' => true,
        ])
        ->assertRedirect(action([ApplicationController::class, 'show'], [$this->environment, 'uptime-kuma']))
        ->assertInertiaFlash('type', 'success');

    $lpod->assertRan(fn (array $arguments, ?string $input) => $arguments === ['quadlet', 'install', 'uptime-kuma', '--start', '--stdin', '--json']
        && json_decode($input, true) === ['files' => $this->files]);
});

it('keeps where the files came from, the pods.yml and a checksum of each file', function () {
    $this->travelTo('2026-11-17 10:00:00');
    fakeLpod();
    $manifest = "version: 1\nname: uptime-kuma\nlabel: Uptime Kuma\n";

    $this->actingAs($this->owner)->post(action([QuadletController::class, 'store'], $this->environment), [
        'application' => 'uptime-kuma',
        'files' => $this->files,
        'source' => [
            'repository' => 'https://github.com/foxws/quadlets',
            'reference' => 'main',
            'path' => '/uptime-kuma/',
            'commit' => 'a41f9c2',
            'manifest' => $manifest,
            'manifest_path' => 'uptime-kuma',
        ],
    ])->assertSessionHasNoErrors();

    expect($this->environment->installations()->sole())
        ->application->toBe('uptime-kuma')
        ->repository->toBe('https://github.com/foxws/quadlets')
        ->reference->toBe('main')
        ->path->toBe('uptime-kuma')
        ->commit->toBe('a41f9c2')
        ->manifest->toBe($manifest)
        ->manifest_path->toBe('uptime-kuma')
        ->checksums->toBe([
            'uptime-kuma-data.volume' => hash('sha256', "[Volume]\n"),
            'uptime-kuma.container' => hash('sha256', "[Container]\nImage=docker.io/louislam/uptime-kuma:2\n"),
        ])
        ->installed_at->toDateTimeString()->toBe('2026-11-17 10:00:00');
});

it('starts over when an application is installed again from pasted files', function () {
    $installation = Installation::factory()->for($this->environment)->fromGit()
        ->create(['application' => 'uptime-kuma', 'changes' => ['modified' => ['uptime-kuma.container'], 'added' => [], 'removed' => []]]);
    fakeLpod();

    $this->actingAs($this->owner)->post(action([QuadletController::class, 'store'], $this->environment), [
        'application' => 'uptime-kuma',
        'files' => $this->files,
        'replace' => true,
    ])->assertSessionHasNoErrors();

    expect($installation->refresh())
        ->repository->toBeNull()
        ->manifest->toBeNull()
        ->changes->toBeNull()
        ->checksums->toHaveKeys(['uptime-kuma.container', 'uptime-kuma-data.volume']);
});

it('only updates the checksum of a file edited in Pods', function () {
    $installation = Installation::factory()->for($this->environment)->fromGit()->withFiles([
        'uptime-kuma.container' => "[Container]\nImage=docker.io/louislam/uptime-kuma:1\n",
        'uptime-kuma-data.volume' => "[Volume]\n",
    ])->create([
        'application' => 'uptime-kuma',
        'changes' => ['modified' => ['uptime-kuma.container', 'uptime-kuma-data.volume'], 'added' => [], 'removed' => []],
    ]);
    fakeLpod();

    $this->actingAs($this->owner)->post(action([QuadletController::class, 'store'], $this->environment), [
        'application' => 'uptime-kuma',
        'files' => [$this->files[0]],
        'start' => false,
        'replace' => true,
        'edit' => true,
    ])->assertSessionHasNoErrors();

    expect($installation->refresh())
        ->repository->toBe('https://github.com/foxws/quadlets')
        ->checksums->toBe([
            'uptime-kuma-data.volume' => hash('sha256', "[Volume]\n"),
            'uptime-kuma.container' => hash('sha256', $this->files[0]['content']),
        ])
        ->changes->toBe(['modified' => ['uptime-kuma-data.volume'], 'added' => [], 'removed' => []]);
});

it('restarts the application after an edit when asked, and says when only the restart failed', function (?HostResult $restart, string $type, string $title) {
    $lpod = fakeLpod();

    if ($restart !== null) {
        $lpod->respond('uptime-kuma restart --no-block --json', $restart);
    }

    $this->actingAs($this->owner)->post(action([QuadletController::class, 'store'], $this->environment), [
        'application' => 'uptime-kuma',
        'files' => [$this->files[0]],
        'start' => false,
        'replace' => true,
        'edit' => true,
        'restart' => true,
    ])
        ->assertInertiaFlash('type', $type)
        ->assertInertiaFlash('title', $title);

    $lpod->assertRan(['quadlet', 'install', 'uptime-kuma', '--replace', '--stdin', '--json']);
    $commands = collect($lpod->recorded())->pluck('arguments');
    expect($commands->search(['uptime-kuma', 'restart', '--no-block', '--json']))
        ->toBeGreaterThan($commands->search(['quadlet', 'install', 'uptime-kuma', '--replace', '--stdin', '--json']));
})->with([
    'restarted' => [null, 'success', 'uptime-kuma saved'],
    'the restart failed' => [new HostResult(1, '', 'Unit uptime-kuma.service not found.'), 'error', 'uptime-kuma saved, but it didn\'t restart'],
]);

it('only restarts for an edit', function () {
    $lpod = fakeLpod();

    $this->actingAs($this->owner)->post(action([QuadletController::class, 'store'], $this->environment), [
        'application' => 'uptime-kuma',
        'files' => $this->files,
        'start' => false,
        'restart' => true,
    ])->assertInertiaFlash('title', 'uptime-kuma installed');

    expect(collect($lpod->recorded())->pluck('arguments')->contains(['uptime-kuma', 'restart', '--no-block', '--json']))->toBeFalse();
});

it('does not start tracking an application by editing one of its files', function () {
    fakeLpod();

    $this->actingAs($this->owner)->post(action([QuadletController::class, 'store'], $this->environment), [
        'application' => 'uptime-kuma',
        'files' => [$this->files[0]],
        'replace' => true,
        'edit' => true,
    ])->assertSessionHasNoErrors();

    expect(Installation::query()->count())->toBe(0);
});

it('refuses a source whose pods.yml Pods cannot read', function () {
    $lpod = fakeLpod();

    $this->actingAs($this->owner)->post(action([QuadletController::class, 'store'], $this->environment), [
        'application' => 'uptime-kuma',
        'files' => $this->files,
        'source' => ['repository' => 'https://github.com/foxws/quadlets', 'reference' => 'main', 'manifest' => "version: 2\n"],
    ])->assertSessionHasErrors('source.manifest');

    $lpod->assertNothingRan();
});

it('does not track an application that could not be installed', function () {
    fakeLpod()->respond('quadlet install *', new HostResult(1, '', 'uptime-kuma.container already exists'));

    $this->actingAs($this->owner)->post(action([QuadletController::class, 'store'], $this->environment), [
        'application' => 'uptime-kuma',
        'files' => $this->files,
    ])->assertInertiaFlash('type', 'error');

    expect(Installation::query()->count())->toBe(0);
});

it('creates the secrets the files need before installing, with the values on stdin only', function () {
    $lpod = fakeLpod();

    $this->actingAs($this->owner)->post(action([QuadletController::class, 'store'], $this->environment), [
        'application' => 'nextcloud',
        'files' => $this->files,
        'secrets' => [['name' => 'nextcloud-db-password', 'driver' => 'file', 'value' => 'correct horse battery staple']],
    ]);

    $calls = collect($lpod->recorded())->pluck('arguments')->map(fn (array $arguments) => $arguments[0].' '.$arguments[1]);

    expect($calls->all())->toBe(['secret create', 'quadlet install']);
    $lpod->assertRan(fn (array $arguments, ?string $input) => $arguments[0] === 'secret' && $input === 'correct horse battery staple');

    foreach ($lpod->recorded() as $call) {
        expect(implode(' ', $call['arguments']))->not->toContain('correct horse');
    }
});

it('fills secrets in from a key of an entered .env file', function () {
    $lpod = fakeLpod();
    $env = "APP_NAME=hello\nDB_PASSWORD=\"correct horse # battery\"\nDB_USERNAME=\${APP_NAME}\n";

    $this->actingAs($this->owner)->post(action([QuadletController::class, 'store'], $this->environment), [
        'application' => 'hello',
        'files' => $this->files,
        'secrets' => [['name' => 'hello-env', 'driver' => 'file', 'value' => $env]],
        'derived_secrets' => [
            ['name' => 'hello-pgsql-password', 'from' => 'hello-env', 'key' => 'DB_PASSWORD'],
            ['name' => 'hello-pgsql-user', 'from' => 'hello-env', 'key' => 'DB_USERNAME'],
        ],
    ])->assertSessionHasNoErrors();

    $created = collect($lpod->recorded())
        ->filter(fn (array $call) => $call['arguments'][0] === 'secret')
        ->mapWithKeys(fn (array $call) => [$call['arguments'][2] => $call['input']]);

    expect($created->all())->toBe([
        'hello-env' => $env,
        'hello-pgsql-password' => 'correct horse # battery',
        'hello-pgsql-user' => 'hello',
    ]);

    foreach ($lpod->recorded() as $call) {
        expect(implode(' ', $call['arguments']))->not->toContain('correct horse');
    }
});

it('refuses to fill a secret in when the .env file lacks its key', function (array $derived, string $field) {
    $lpod = fakeLpod();

    $this->actingAs($this->owner)
        ->post(action([QuadletController::class, 'store'], $this->environment), [
            'application' => 'hello',
            'files' => $this->files,
            'secrets' => [
                ['name' => 'hello-env', 'driver' => 'file', 'value' => "DB_PASSWORD=secret-value\nDB_HOST=\n"],
                ['name' => 'hello-typesense-api-key', 'driver' => 'file', 'value' => 'typed-by-hand'],
            ],
            'derived_secrets' => [$derived],
        ])
        ->assertSessionHasErrors($field);

    expect(session('errors')->first($field))->not->toContain('secret-value');
    $lpod->assertNothingRan();
})->with([
    'missing key' => [['name' => 'hello-pgsql-user', 'from' => 'hello-env', 'key' => 'DB_USERNAME'], 'derived_secrets.0.key'],
    'empty key' => [['name' => 'hello-pgsql-host', 'from' => 'hello-env', 'key' => 'DB_HOST'], 'derived_secrets.0.key'],
    'source not entered' => [['name' => 'hello-pgsql-user', 'from' => 'other-env', 'key' => 'DB_USERNAME'], 'derived_secrets.0.from'],
    'value entered too' => [['name' => 'hello-typesense-api-key', 'from' => 'hello-env', 'key' => 'DB_PASSWORD'], 'derived_secrets.0.name'],
]);

it('refuses a source that is not a valid .env file without echoing it', function () {
    $lpod = fakeLpod();

    $this->actingAs($this->owner)
        ->post(action([QuadletController::class, 'store'], $this->environment), [
            'application' => 'hello',
            'files' => $this->files,
            'secrets' => [['name' => 'hello-env', 'driver' => 'file', 'value' => "DB_PASSWORD=has secret-value spaces\n"]],
            'derived_secrets' => [['name' => 'hello-pgsql-password', 'from' => 'hello-env', 'key' => 'DB_PASSWORD']],
        ])
        ->assertSessionHasErrors('derived_secrets.0.from');

    expect(session('errors')->first('derived_secrets.0.from'))->not->toContain('secret-value');
    $lpod->assertNothingRan();
});

it('does not keep secret values as old input after a failed validation', function () {
    fakeLpod();

    $this->actingAs($this->owner)
        ->post(action([QuadletController::class, 'store'], $this->environment), [
            'application' => '--bad',
            'files' => $this->files,
            'secrets' => [['name' => 'db', 'driver' => 'file', 'value' => 'super-secret-value']],
        ])
        ->assertSessionHasErrors('application');

    expect(session()->getOldInput('secrets'))->toBeNull();
});

it('stays on the page when the install fails', function () {
    fakeLpod()->respond('quadlet install *', new HostResult(1, '', 'Error: unsupported key Imagee'));

    $this->actingAs($this->owner)
        ->from('/install')
        ->post(action([QuadletController::class, 'store'], $this->environment), [
            'application' => 'uptime-kuma',
            'files' => $this->files,
        ])
        ->assertRedirect('/install')
        ->assertInertiaFlash('type', 'error');
});

it('rejects file names that are not plain quadlet files', function (string $name) {
    $lpod = fakeLpod();

    $this->actingAs($this->owner)
        ->post(action([QuadletController::class, 'store'], $this->environment), [
            'application' => 'uptime-kuma',
            'files' => [['name' => $name, 'content' => "[Container]\n"]],
        ])
        ->assertSessionHasErrors('files.0.name');

    $lpod->assertNothingRan();
})->with([
    'path traversal' => '../../.bashrc',
    'nested path' => 'systemd/evil.container',
    'unknown extension' => 'install.sh',
    'option-like' => '--replace.container',
]);

it('checks files with a dry run and gives the result as json', function () {
    $lpod = fakeLpod();

    $this->actingAs($this->owner)
        ->postJson(action(QuadletCheckController::class, $this->environment), ['files' => $this->files])
        ->assertOk()
        ->assertExactJson(['data' => ['valid' => true, 'errors' => [], 'units' => ['uptime-kuma.service']], 'error' => null]);

    $lpod->assertRan(fn (array $arguments, ?string $input) => $arguments === ['quadlet', 'check', '--stdin', '--json']
        && json_decode($input, true) === ['files' => $this->files]);
});

it('gives the errors of an invalid file', function () {
    fakeLpod()->respond('quadlet check --stdin --json', lpodFixture('quadlet-check-invalid'));

    $this->actingAs($this->owner)
        ->postJson(action(QuadletCheckController::class, $this->environment), ['files' => $this->files])
        ->assertOk()
        ->assertJsonPath('data.valid', false)
        ->assertJsonPath('data.errors.0', "uptime-kuma.container: unsupported key 'Imagee' in group 'Container'");
});

it('fetches the files of a git repository', function () {
    $lpod = fakeLpod();

    $this->actingAs($this->owner)
        ->postJson(action(QuadletFetchController::class, $this->environment), [
            'repository' => 'https://codeberg.org/foxws/quadlets.git',
            'reference' => 'main',
            'path' => 'nextcloud/',
        ])
        ->assertOk()
        ->assertJsonPath('data.commit', 'a41f9c2')
        ->assertJsonCount(5, 'data.files');

    $lpod->assertRan(['quadlet', 'fetch', 'https://codeberg.org/foxws/quadlets.git', '--ref', 'main', '--path', 'nextcloud', '--json']);
});

it('returns no manifest for a repository without pods.yml', function () {
    fakeLpod();

    $this->actingAs($this->owner)
        ->postJson(action(QuadletFetchController::class, $this->environment), [
            'repository' => 'https://codeberg.org/foxws/quadlets.git',
            'reference' => 'main',
        ])
        ->assertOk()
        ->assertJsonPath('data.manifest', null)
        ->assertJsonPath('data.manifest_error', null);
});

it('reads the sources and secrets of the repository pods.yml', function () {
    fakeLpod()->respond('quadlet fetch *', new HostResult(0, lpodFixture('quadlet-fetch-manifest'), ''));

    $this->actingAs($this->owner)
        ->postJson(action(QuadletFetchController::class, $this->environment), [
            'repository' => 'https://codeberg.org/foxws/quadlets.git',
            'reference' => 'main',
        ])
        ->assertOk()
        ->assertJsonPath('data.manifest.name', 'nextcloud')
        ->assertJsonPath('data.manifest.sources.0', ['name' => 'stable', 'path' => 'nextcloud', 'description' => 'Nextcloud with PostgreSQL'])
        ->assertJsonPath('data.manifest.secrets.1', [
            'name' => 'nextcloud-db-password',
            'description' => 'Password of the database user',
            'format' => 'text',
            'generate' => true,
            'from' => null,
            'key' => null,
        ])
        ->assertJsonPath('data.manifest_error', null);
});

it('fills secrets in from a dotenv secret of the manifest', function () {
    $manifest = "secrets:\n  app-env:\n    format: dotenv\n  app-db-password:\n    from: app-env\n    key: DB_PASSWORD\n";
    fakeLpod()->respond('quadlet fetch *', new HostResult(0, json_encode(['contract' => 1, 'commit' => 'a41f9c2', 'manifest' => $manifest, 'files' => []]), ''));

    $this->actingAs($this->owner)
        ->postJson(action(QuadletFetchController::class, $this->environment), [
            'repository' => 'https://codeberg.org/foxws/quadlets.git',
            'reference' => 'main',
        ])
        ->assertOk()
        ->assertJsonPath('data.manifest.secrets.0.format', 'dotenv')
        ->assertJsonPath('data.manifest.secrets.1.from', 'app-env')
        ->assertJsonPath('data.manifest.secrets.1.key', 'DB_PASSWORD');
});

it('still returns the files when pods.yml is invalid', function (string $manifest, string $error) {
    fakeLpod()->respond('quadlet fetch *', new HostResult(0, json_encode([
        ...json_decode(lpodFixture('quadlet-fetch'), true),
        'manifest' => $manifest,
    ]), ''));

    $this->actingAs($this->owner)
        ->postJson(action(QuadletFetchController::class, $this->environment), [
            'repository' => 'https://codeberg.org/foxws/quadlets.git',
            'reference' => 'main',
        ])
        ->assertOk()
        ->assertJsonCount(5, 'data.files')
        ->assertJsonPath('data.manifest', null)
        ->assertJsonPath('data.manifest_error', fn (string $message) => str_contains($message, $error));
})->with([
    'not yaml' => ["name: [unclosed\n", 'pods.yml is ignored'],
    'newer version' => ["version: 2\n", "it's version 2, but Pods reads version 1"],
    'path traversal' => ["sources:\n  evil:\n    path: ../../etc\n", 'sources.evil.path'],
    'invalid secret name' => ["secrets:\n  '--rm':\n    description: x\n", 'isn\'t a valid secret name'],
    'from a plain secret' => ["secrets:\n  app-env: {}\n  db:\n    from: app-env\n    key: DB_PASSWORD\n", "which isn't a secret with format: dotenv"],
]);

it('rejects repositories and references that are not plain', function (array $input, string $field) {
    $lpod = fakeLpod();

    $this->actingAs($this->owner)
        ->postJson(action(QuadletFetchController::class, $this->environment), [
            'repository' => 'https://codeberg.org/foxws/quadlets.git',
            'reference' => 'main',
            ...$input,
        ])
        ->assertJsonValidationErrors($field);

    $lpod->assertNothingRan();
})->with([
    'local path' => [['repository' => '/etc'], 'repository'],
    'ext transport' => [['repository' => 'ext::sh -c reboot'], 'repository'],
    'option-like reference' => [['reference' => '--upload-pack=reboot'], 'reference'],
    'path traversal' => [['path' => '../../'], 'path'],
]);

it('forbids users who are not the owner', function () {
    $lpod = fakeLpod();

    $this->actingAs(User::factory()->create())
        ->post(action([QuadletController::class, 'store'], $this->environment), ['application' => 'uptime-kuma', 'files' => $this->files])
        ->assertForbidden();

    $this->actingAs(User::factory()->create())
        ->postJson(action(QuadletCheckController::class, $this->environment), ['files' => $this->files])
        ->assertForbidden();

    $lpod->assertNothingRan();
});

it('resolves the sources of an application pods.yml relative to its directory', function () {
    $payload = json_decode(lpodFixture('quadlet-catalog'), true);

    fakeLpod()->respond('quadlet fetch *', new HostResult(0, json_encode([
        'contract' => 1,
        'commit' => '5e0b7d1',
        'manifest' => $payload['applications'][1]['manifest'],
        'manifest_path' => 'mariadb',
        'files' => [['name' => 'mariadb.container', 'content' => "[Container]\nImage=docker.io/library/mariadb:11.8\n"]],
    ]), ''));

    $this->actingAs($this->owner)
        ->postJson(action(QuadletFetchController::class, $this->environment), [
            'repository' => 'https://github.com/foxws/quadlets',
            'reference' => 'main',
            'path' => 'mariadb/production',
        ])
        ->assertOk()
        ->assertJsonPath('data.manifest.directory', 'mariadb')
        ->assertJsonPath('data.manifest.label', 'MariaDB')
        ->assertJsonPath('data.manifest.sources.0.path', 'mariadb/production')
        ->assertJsonPath('data.manifest.sources.1.path', 'mariadb/development');
});

it('sets up the timers its pods.yml names after the install', function () {
    $lpod = fakeLpod();
    $manifest = "version: 1\nname: nextcloud\ntimers:\n  cron:\n    container: nextcloud-cron\n    on_calendar: hourly\n    persistent: true\n    randomized_delay_sec: 5min\n    timeout_start_sec: infinity\n";

    $this->actingAs($this->owner)->post(action([QuadletController::class, 'store'], $this->environment), [
        'application' => 'nextcloud',
        'files' => $this->files,
        'source' => ['repository' => 'https://github.com/foxws/quadlets', 'reference' => 'main', 'path' => 'nextcloud', 'commit' => 'a41f9c2', 'manifest' => $manifest, 'manifest_path' => ''],
    ])->assertInertiaFlash('type', 'success');

    $lpod->assertRan(fn (array $arguments, ?string $input) => $arguments === ['nextcloud', 'timers', 'set', '--stdin', '--json']
        && json_decode($input, true) === ['timers' => [[
            'name' => 'cron',
            'container' => 'nextcloud-cron',
            'on_calendar' => ['hourly'],
            'persistent' => true,
            'randomized_delay_sec' => '5min',
            'timeout_start_sec' => 'infinity',
        ]]]);
    expect(collect($lpod->recorded())->pluck('arguments')->search(['quadlet', 'install', 'nextcloud', '--start', '--stdin', '--json']))
        ->toBeLessThan(collect($lpod->recorded())->pluck('arguments')->search(['nextcloud', 'timers', 'set', '--stdin', '--json']));
});

it('leaves timers alone when neither pods.yml names any', function () {
    $lpod = fakeLpod();

    $this->actingAs($this->owner)->post(action([QuadletController::class, 'store'], $this->environment), [
        'application' => 'uptime-kuma',
        'files' => $this->files,
    ])->assertInertiaFlash('type', 'success');

    expect(collect($lpod->recorded())->pluck('arguments')->contains(fn (array $arguments): bool => ($arguments[1] ?? null) === 'timers'))->toBeFalse();
});

it('removes the timers of the previous pods.yml when the new one names none', function () {
    $lpod = fakeLpod();
    Installation::factory()->for($this->environment)->create([
        'application' => 'uptime-kuma',
        'manifest' => "version: 1\ntimers:\n  backup:\n    container: uptime-kuma\n    on_calendar: daily\n",
    ]);

    $this->actingAs($this->owner)->post(action([QuadletController::class, 'store'], $this->environment), [
        'application' => 'uptime-kuma',
        'files' => $this->files,
        'replace' => true,
    ])->assertInertiaFlash('type', 'success');

    $lpod->assertRan(fn (array $arguments, ?string $input) => $arguments === ['uptime-kuma', 'timers', 'set', '--stdin', '--json']
        && json_decode($input, true) === ['timers' => []]);
});

it('says when the timers could not be set up, but keeps the install', function () {
    fakeLpod()->respond('* timers set --stdin --json', new HostResult(1, '', 'nextcloud-cron isn\'t a container Quadlet of nextcloud.'));

    $this->actingAs($this->owner)->post(action([QuadletController::class, 'store'], $this->environment), [
        'application' => 'nextcloud',
        'files' => $this->files,
        'source' => ['repository' => 'https://github.com/foxws/quadlets', 'reference' => 'main', 'path' => '', 'commit' => 'a41f9c2', 'manifest' => "timers:\n  cron:\n    container: nextcloud-cron\n    on_calendar: hourly\n", 'manifest_path' => ''],
    ])
        ->assertRedirect(action([ApplicationController::class, 'show'], [$this->environment, 'nextcloud']))
        ->assertInertiaFlash('type', 'error')
        ->assertInertiaFlash('title', 'nextcloud installed, but its timers weren\'t set up');

    expect($this->environment->installations()->sole()->application)->toBe('nextcloud');
});

it('refuses a pods.yml with timers lpod could not set up', function (string $manifest, string $error) {
    fakeLpod()->respond('quadlet fetch *', new HostResult(0, json_encode([...json_decode(lpodFixture('quadlet-fetch'), true), 'manifest' => $manifest]), ''));

    $this->actingAs($this->owner)
        ->postJson(action(QuadletFetchController::class, $this->environment), [
            'repository' => 'https://github.com/foxws/quadlets',
            'reference' => 'main',
        ])
        ->assertOk()
        ->assertJsonPath('data.manifest', null)
        ->assertJsonPath('data.manifest_error', fn (string $message) => str_contains($message, $error));
})->with([
    'invalid name' => ["timers:\n  Cron:\n    container: nextcloud-cron\n    on_calendar: hourly\n", 'isn\'t a valid timer name'],
    'the idle check' => ["timers:\n  idle:\n    container: nextcloud-cron\n    on_calendar: hourly\n", 'isn\'t a valid timer name'],
    'no schedule' => ["timers:\n  cron:\n    container: nextcloud-cron\n", 'timers.cron.on_calendar'],
    'empty schedule list' => ["timers:\n  cron:\n    container: nextcloud-cron\n    on_calendar: []\n", 'timers.cron.on_calendar'],
    'two lines' => ["timers:\n  cron:\n    container: nextcloud-cron\n    on_calendar: \"hourly\\nOnBootSec=1\"\n", 'isn\'t a calendar event'],
    'one container twice' => ["timers:\n  a:\n    container: nextcloud-cron\n    on_calendar: hourly\n  b:\n    container: nextcloud-cron\n    on_calendar: daily\n", 'timers.a.container'],
    'invalid timeout' => ["timers:\n  cron:\n    container: nextcloud-cron\n    on_calendar: hourly\n    timeout_start_sec: forever\n", 'timers.cron.timeout_start_sec'],
]);
