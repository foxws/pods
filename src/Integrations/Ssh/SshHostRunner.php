<?php

declare(strict_types=1);

namespace Integrations\Ssh;

use Domain\Environments\Models\Environment;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Integrations\Ssh\Contracts\RunsHostCommands;

class SshHostRunner implements RunsHostCommands
{
    public function __construct(
        protected SshIdentity $identity,
    ) {}

    public function run(Environment $environment, array $arguments, ?string $input = null, int $timeout = 30): HostResult
    {
        $result = Process::timeout($timeout)
            ->input($input)
            ->run($this->command($environment, $arguments));

        return new HostResult(
            exitCode: (int) $result->exitCode(),
            output: $result->output(),
            errorOutput: $result->errorOutput(),
        );
    }

    /**
     * Builds the ssh argument list. The remote command is a single string that the
     * host's shell parses, so each lpod argument is escaped on its own.
     *
     * @param  list<string>  $arguments
     * @return list<string>
     */
    public function command(Environment $environment, array $arguments): array
    {
        $remote = implode(' ', [
            'PATH="$HOME/.local/bin:$PATH"',
            escapeshellarg((string) config('services.pods.lpod_binary')),
            ...array_map(escapeshellarg(...), $arguments),
        ]);

        return [
            'ssh',
            '-T',
            '-i', $this->identity->privateKeyPath(),
            '-p', (string) $environment->port,
            '-o', 'BatchMode=yes',
            '-o', 'IdentitiesOnly=yes',
            '-o', 'StrictHostKeyChecking=yes',
            '-o', "UserKnownHostsFile={$this->knownHostsFile($environment)}",
            '-o', 'ConnectTimeout=10',
            '-o', 'LogLevel=ERROR',
            '-l', $environment->user,
            '--',
            $environment->host,
            $remote,
        ];
    }

    protected function knownHostsFile(Environment $environment): string
    {
        $path = $this->identity->knownHostsPath($environment->ulid);
        $contents = trim($environment->host_key).PHP_EOL;

        if (! File::exists($path) || File::get($path) !== $contents) {
            File::ensureDirectoryExists(dirname($path), 0700);
            File::put($path, $contents);
        }

        return $path;
    }
}
