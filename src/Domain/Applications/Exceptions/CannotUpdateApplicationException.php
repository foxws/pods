<?php

declare(strict_types=1);

namespace Domain\Applications\Exceptions;

use RuntimeException;

/**
 * An update Pods refuses before it stops anything, so the application keeps running as it is.
 */
class CannotUpdateApplicationException extends RuntimeException
{
    public static function notFromGit(string $application): self
    {
        return new self("{$application} wasn't installed from a Git repository, so Pods doesn't know where to update it from.");
    }

    public static function noFiles(string $tag, string $path): self
    {
        $directory = $path !== '' ? "{$path}/" : 'the root of the repository';

        return new self("{$tag} has no Quadlet files in {$directory}.");
    }

    /**
     * @param  list<string>  $errors
     */
    public static function invalidFiles(string $tag, array $errors): self
    {
        return new self("The files of {$tag} don't pass podman quadlet --dryrun: ".implode(' ', $errors));
    }

    /**
     * @param  list<string>  $secrets
     */
    public static function missingSecrets(string $tag, array $secrets): self
    {
        return new self("{$tag} uses secrets this host doesn't have yet: ".implode(', ', $secrets).'. Create them on the Secrets page first.');
    }
}
