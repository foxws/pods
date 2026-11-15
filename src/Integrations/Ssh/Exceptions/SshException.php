<?php

declare(strict_types=1);

namespace Integrations\Ssh\Exceptions;

use RuntimeException;

class SshException extends RuntimeException
{
    public static function keyGenerationFailed(string $error): self
    {
        return new self("The SSH key could not be generated: {$error}");
    }

    public static function hostKeyScanFailed(string $host, int $port): self
    {
        return new self("No ed25519 host key was found for {$host}:{$port}. Is sshd running?");
    }
}
