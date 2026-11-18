<?php

declare(strict_types=1);

namespace Domain\Applications\Exceptions;

use RuntimeException;

/**
 * A source Pods refuses to link an application to, before it records anything.
 */
class CannotLinkApplicationSourceException extends RuntimeException
{
    public static function noFiles(string $reference, string $path): self
    {
        $directory = $path !== '' ? "{$path}/" : 'the root of the repository';

        return new self("{$reference} has no Quadlet files in {$directory}.");
    }
}
