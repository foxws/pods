<?php

declare(strict_types=1);

namespace Domain\Quadlets\Exceptions;

use RuntimeException;

class InvalidManifestException extends RuntimeException
{
    public static function because(string $reason): self
    {
        return new self("pods.yml is ignored: {$reason}");
    }

    public static function unsupportedVersion(mixed $version, int $supported): self
    {
        $given = is_scalar($version) ? (string) $version : 'unknown';

        return new self("pods.yml is ignored: it's version {$given}, but Pods reads version {$supported}. Update Pods.");
    }
}
