<?php

declare(strict_types=1);

namespace Integrations\Ssh;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Integrations\Ssh\Exceptions\SshException;

/**
 * The ed25519 key pair this Pods instance signs in to every host with.
 */
class SshIdentity
{
    public function privateKeyPath(): string
    {
        return $this->directory().'/id_ed25519';
    }

    public function publicKeyPath(): string
    {
        return "{$this->privateKeyPath()}.pub";
    }

    public function exists(): bool
    {
        return File::exists($this->privateKeyPath()) && File::exists($this->publicKeyPath());
    }

    public function publicKey(): ?string
    {
        if (! $this->exists()) {
            return null;
        }

        return trim(File::get($this->publicKeyPath()));
    }

    public function generate(string $comment): void
    {
        File::ensureDirectoryExists($this->directory(), 0700);

        $result = Process::run([
            'ssh-keygen', '-q', '-t', 'ed25519', '-N', '', '-C', $comment, '-f', $this->privateKeyPath(),
        ]);

        if ($result->failed()) {
            throw SshException::keyGenerationFailed($result->errorOutput());
        }
    }

    public function knownHostsPath(string $name): string
    {
        return "{$this->directory()}/known_hosts/{$name}";
    }

    public function directory(): string
    {
        return storage_path('app/private/ssh');
    }
}
