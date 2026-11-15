<?php

declare(strict_types=1);

namespace Integrations\Ssh;

use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use Integrations\Ssh\Exceptions\SshException;

class HostKeyScanner
{
    /**
     * Fetches the host's ed25519 key, as a known_hosts line.
     */
    public function scan(string $host, int $port): string
    {
        $result = Process::timeout(15)->run([
            'ssh-keyscan', '-T', '10', '-t', 'ed25519', '-p', (string) $port, $host,
        ]);

        $line = Str::of($result->output())
            ->explode("\n")
            ->map(fn (string $line): string => trim($line))
            ->first(fn (string $line): bool => $line !== '' && ! str_starts_with($line, '#'));

        if ($result->failed() || $line === null) {
            throw SshException::hostKeyScanFailed($host, $port);
        }

        return $line;
    }

    /**
     * Gives the SHA256 fingerprint of a known_hosts line, as ssh-keygen prints it.
     */
    public function fingerprint(string $hostKey): ?string
    {
        $parts = preg_split('/\s+/', trim($hostKey)) ?: [];

        if (count($parts) < 3) {
            return null;
        }

        $key = base64_decode($parts[2], true);

        if ($key === false) {
            return null;
        }

        return 'SHA256:'.rtrim(base64_encode(hash('sha256', $key, true)), '=');
    }
}
