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
     * sshd hands the remote command to the user's login shell, which may be fish or
     * zsh rather than sh. Only this wrapper reaches that shell: it has no backslash or
     * single quote inside its quotes, so every common shell parses it the same way,
     * and the real command travels as base64 for sh to decode and run.
     */
    protected const string RemoteWrapper = 'sh -c \'eval "$(printf %s "$1" | base64 -d)"\' pods';

    /**
     * Builds the ssh argument list.
     *
     * @param  list<string>  $arguments
     * @return list<string>
     */
    public function command(Environment $environment, array $arguments): array
    {
        $remote = self::RemoteWrapper.' '.base64_encode($this->shellCommand($arguments));

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
            ...$this->connectionReuse(),
            '-l', $environment->user,
            '--',
            $environment->host,
            $remote,
        ];
    }

    /**
     * The POSIX sh command that runs lpod, with each argument escaped on its own.
     *
     * @param  list<string>  $arguments
     */
    public function shellCommand(array $arguments): string
    {
        return implode(' ', [
            'PATH="$HOME/.local/bin:$PATH"',
            'exec',
            escapeshellarg((string) config('services.pods.lpod_binary')),
            ...array_map(escapeshellarg(...), $arguments),
        ]);
    }

    /**
     * Every lpod call is its own ssh command, and a page load makes several at once. With a
     * control master, the first one keeps its connection open for a while and the next ones
     * run over it, so they skip the key exchange, the login and the user's login shell. ssh
     * falls back to a connection of its own when the master is busy starting or gone.
     *
     * @return list<string>
     */
    protected function connectionReuse(): array
    {
        $persist = (int) config('services.pods.ssh_control_persist');

        if ($persist <= 0) {
            return [];
        }

        $directory = (string) config('services.pods.ssh_control_directory');
        File::ensureDirectoryExists($directory, 0700);

        return [
            '-o', 'ControlMaster=auto',
            // %C hashes the host, port and user, which keeps the socket path short enough.
            '-o', "ControlPath={$directory}/%C",
            '-o', "ControlPersist={$persist}",
            // A master whose host went away stops within 45 seconds instead of hanging the next calls.
            '-o', 'ServerAliveInterval=15',
            '-o', 'ServerAliveCountMax=3',
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
