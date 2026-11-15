<?php

declare(strict_types=1);

namespace Integrations\Lpod\Exceptions;

use Integrations\Ssh\HostResult;
use RuntimeException;

class LpodException extends RuntimeException
{
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
