<?php

declare(strict_types=1);

use Domain\Environments\Models\Environment;
use Domain\Secrets\Actions\CreateSecret;
use Domain\Users\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Integrations\Ssh\Contracts\RunsHostCommands;
use Integrations\Ssh\HostResult;

// An instance that has its owner, so guests aren't sent to the setup page.
beforeEach(fn () => User::factory()->create()->assignRole('super-admin'));

it('keeps a field that can hold a secret out of the session when validation fails', function (string $field) {
    Route::middleware('web')->post('/_secrets', fn (Request $request) => $request->validate(['name' => 'required']));

    $this->post('/_secrets', [$field => 'hunter2', 'label' => 'kept'])->assertSessionHasErrors('name');

    expect(session()->getOldInput())->toBe(['label' => 'kept']);
})->with([
    'value', 'secrets', 'inputs', 'dns_token', 'code', 'recovery_code', 'token',
    'files', 'content', 'source', 'repository', 'repositories',
]);

it('keeps a secret out of the stack trace when the host fails', function () {
    $this->app->instance(RunsHostCommands::class, new class implements RunsHostCommands
    {
        public function run(Environment $environment, array $arguments, #[SensitiveParameter] ?string $input = null, int $timeout = 30): HostResult
        {
            throw new RuntimeException('The host timed out.');
        }

        public function download(Environment $environment, array $arguments, string $path, int $timeout = 30): HostResult
        {
            throw new RuntimeException('The host timed out.');
        }
    });

    // As on a host without Pods' php.ini
    $ignoresArguments = ini_set('zend.exception_ignore_args', '0');
    $trace = '';

    try {
        app(CreateSecret::class)(Environment::factory()->create(), 'db-password', 'file', 'hunter2');
    } catch (RuntimeException $exception) {
        $trace = $exception->getTraceAsString();
    } finally {
        ini_set('zend.exception_ignore_args', (string) $ignoresArguments);
    }

    expect($trace)->toContain('db-password')->not->toContain('hunter2');
});
