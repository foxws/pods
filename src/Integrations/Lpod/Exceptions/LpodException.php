<?php

declare(strict_types=1);

namespace Integrations\Lpod\Exceptions;

use Domain\Environments\Models\Environment;
use Integrations\Ssh\HostResult;
use RuntimeException;

class LpodException extends RuntimeException
{
    /**
     * ssh exits with 255 when it cannot connect, sign in, or trust the host key.
     */
    public static function unreachable(Environment $environment, HostResult $result): self
    {
        $error = trim($result->errorOutput) ?: 'ssh exited with code 255';

        $hint = match (true) {
            str_contains($error, 'Permission denied') => " Add the public key of Pods to ~{$environment->user}/.ssh/authorized_keys on the host.",
            str_contains($error, 'Host key verification failed'), str_contains($error, 'HOST IDENTIFICATION HAS CHANGED') => ' The host key no longer matches the pinned one.',
            default => '',
        };

        return new self("Could not connect to {$environment->target()}: {$error}{$hint}");
    }

    public static function notInstalled(Environment $environment): self
    {
        $binary = (string) config('services.pods.lpod_binary');

        return new self("{$binary} was not found for {$environment->target()}. Install lpod 4 or newer for that user, or set PODS_LPOD_BINARY.");
    }

    public static function commandFailed(string $command, HostResult $result): self
    {
        $error = trim($result->errorOutput) ?: trim($result->output) ?: "exit code {$result->exitCode}";

        return new self("lpod {$command} failed: {$error}");
    }

    public static function invalidJson(string $command): self
    {
        return new self("lpod {$command} did not return valid JSON. Is lpod 4 or newer installed on the host?");
    }
}
