<?php

declare(strict_types=1);

namespace Integrations\Lpod\Exceptions;

class UnsupportedContractException extends LpodException
{
    public static function make(?int $contract, int $supported): self
    {
        $given = $contract === null ? 'none' : (string) $contract;

        return new self("This host's lpod speaks contract {$given}, but Pods needs contract {$supported}. Update lpod or Pods.");
    }
}
