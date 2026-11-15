<?php

declare(strict_types=1);

namespace Integrations\Lpod;

use Domain\Applications\DataObjects\ApplicationData;
use Domain\Applications\DataObjects\ApplicationDetailData;
use Domain\Applications\DataObjects\HostStatsData;
use Domain\Applications\DataObjects\JournalEntryData;
use Domain\Applications\DataObjects\QuadletFileData;
use Domain\Environments\Models\Environment;
use Domain\Secrets\DataObjects\SecretData;
use Illuminate\Support\Facades\Cache;
use Integrations\Lpod\Exceptions\LpodException;
use Integrations\Lpod\Exceptions\UnsupportedContractException;
use Integrations\Ssh\Contracts\RunsHostCommands;

/**
 * Talks to lpod on an environment's host, through its JSON contract.
 */
class LpodClient
{
    public const int Contract = 1;

    public function __construct(
        protected RunsHostCommands $runner,
    ) {}

    /**
     * @return array{contract: int, lpod: string, podman: string, systemd: string, scope: string, user: string, hostname: string}
     */
    public function version(Environment $environment): array
    {
        /** @var array{contract: int, lpod: string, podman: string, systemd: string, scope: string, user: string, hostname: string} */
        return $this->json($environment, ['version', '--json']);
    }

    public function host(Environment $environment): HostStatsData
    {
        return HostStatsData::fromLpod($this->cached($environment, 'host', ['host', '--json']));
    }

    /**
     * @return list<ApplicationData>
     */
    public function applications(Environment $environment): array
    {
        $payload = $this->cached($environment, 'apps', ['apps', '--json']);

        return array_map(ApplicationData::fromLpod(...), array_values($payload['applications'] ?? []));
    }

    public function application(Environment $environment, string $application): ApplicationDetailData
    {
        $payload = $this->cached($environment, "apps.{$application}", [$application, 'status', '--json']);

        return ApplicationDetailData::fromLpod($payload['application']);
    }

    /**
     * @return list<QuadletFileData>
     */
    public function files(Environment $environment, string $application): array
    {
        $payload = $this->json($environment, [$application, 'files', '--json']);

        return array_map(QuadletFileData::fromLpod(...), array_values($payload['files'] ?? []));
    }

    /**
     * @return list<JournalEntryData>
     */
    public function logs(Environment $environment, string $application, int $lines = 100): array
    {
        $payload = $this->json($environment, [$application, 'logs', '--json', '--lines', (string) $lines]);

        return array_map(JournalEntryData::fromLpod(...), array_values($payload['entries'] ?? []));
    }

    /**
     * Gives the existing secrets, followed by the ones a Quadlet references but that don't exist.
     *
     * @return list<SecretData>
     */
    public function secrets(Environment $environment): array
    {
        $payload = $this->cached($environment, 'secrets', ['secrets', '--json']);

        return [
            ...array_map(fn (array $secret): SecretData => SecretData::fromLpod($secret), array_values($payload['secrets'] ?? [])),
            ...array_map(fn (array $secret): SecretData => SecretData::fromLpod($secret, missing: true), array_values($payload['missing'] ?? [])),
        ];
    }

    public function createSecret(Environment $environment, string $name, string $driver, string $value, bool $replace = false): void
    {
        $this->json($environment, [
            'secret', 'create', $name, '--driver', $driver, ...($replace ? ['--replace'] : []), '--stdin', '--json',
        ], input: $value);

        $this->forget($environment, 'secrets');
    }

    public function addSecretToApplication(Environment $environment, string $application, string $secret, string $type, string $target): void
    {
        $this->json($environment, [$application, 'secret', 'add', $secret, '--type', $type, '--target', $target, '--json']);

        $this->forget($environment, 'secrets', "apps.{$application}");
    }

    public function up(Environment $environment, string $application): void
    {
        $this->lifecycle($environment, $application, 'up');
    }

    public function down(Environment $environment, string $application): void
    {
        $this->lifecycle($environment, $application, 'down');
    }

    public function restart(Environment $environment, string $application): void
    {
        $this->lifecycle($environment, $application, 'restart');
    }

    /**
     * Pulls the newest images of the application, then restarts it.
     */
    public function redeploy(Environment $environment, string $application): void
    {
        $this->lifecycle($environment, $application, 'redeploy');
    }

    protected function lifecycle(Environment $environment, string $application, string $operation): void
    {
        $this->json($environment, [$application, $operation, '--no-block', '--json']);

        $this->forget($environment, 'apps', "apps.{$application}", 'host');
    }

    /**
     * @param  list<string>  $arguments
     * @return array<string, mixed>
     */
    protected function cached(Environment $environment, string $key, array $arguments): array
    {
        $ttl = (int) config('services.pods.cache_ttl');

        return Cache::flexible($this->cacheKey($environment, $key), [$ttl, $ttl * 6], fn (): array => $this->json($environment, $arguments));
    }

    protected function forget(Environment $environment, string ...$keys): void
    {
        foreach ($keys as $key) {
            Cache::forget($this->cacheKey($environment, $key));
        }
    }

    protected function cacheKey(Environment $environment, string $key): string
    {
        return "pods.lpod.{$environment->ulid}.{$key}";
    }

    /**
     * @param  list<string>  $arguments
     * @return array<string, mixed>
     */
    protected function json(Environment $environment, array $arguments, ?string $input = null): array
    {
        $command = implode(' ', $arguments);

        $result = $this->runner->run($environment, $arguments, $input);

        if ($result->exitCode === 255) {
            throw LpodException::unreachable($environment, $result);
        }

        if ($result->exitCode === 127 && preg_match('/\bssh: (command )?not found/', $result->errorOutput) === 1) {
            throw LpodException::sshClientMissing();
        }

        if ($result->exitCode === 127) {
            throw LpodException::notInstalled($environment);
        }

        if (! $result->successful()) {
            throw LpodException::commandFailed($command, $result);
        }

        $payload = json_decode($result->output, true);

        if (! is_array($payload)) {
            throw LpodException::invalidJson($command);
        }

        $contract = isset($payload['contract']) ? (int) $payload['contract'] : null;

        if ($contract !== self::Contract) {
            throw UnsupportedContractException::make($contract, self::Contract);
        }

        return $payload;
    }
}
