<?php

declare(strict_types=1);

namespace Domain\Environments\Exceptions;

use RuntimeException;

/**
 * lpod's self-update ended without error, but the host still reports another version.
 */
class CannotUpdateLpodException extends RuntimeException
{
    public static function stillAt(?string $installed, string $version): self
    {
        return new self('The host still reports lpod '.($installed ?? 'unknown')." instead of {$version}. Run \"lpod self-update {$version}\" on the host as this user to see why.");
    }
}
