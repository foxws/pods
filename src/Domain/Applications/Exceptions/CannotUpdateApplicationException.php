<?php

declare(strict_types=1);

namespace Domain\Applications\Exceptions;

use Domain\Environments\DataObjects\LpodVersionData;
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
     * An older lpod can find problems that aren't there (before 4.0.0-rc.3 it counted the generator's
     * own log lines as errors), so the message says when the host's lpod is behind.
     *
     * @param  list<string>  $errors
     */
    public static function invalidFiles(string $tag, array $errors, ?LpodVersionData $lpod = null): self
    {
        $message = "The files of {$tag} don't pass podman quadlet --dryrun: ".implode(' ', $errors);

        if ($lpod !== null && $lpod->installed !== null && $lpod->update_available) {
            $message .= " This host's lpod ({$lpod->installed}) isn't the {$lpod->required} Pods needs, which can make the check fail: update lpod in the host's settings, then try again.";
        }

        return new self($message);
    }

    /**
     * @param  list<string>  $secrets
     */
    public static function missingSecrets(string $tag, array $secrets): self
    {
        return new self("{$tag} uses secrets this host doesn't have yet: ".implode(', ', $secrets).'. Create them on the Secrets page first.');
    }
}
