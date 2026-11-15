<?php

declare(strict_types=1);

namespace Modules\Web\Environments\Concerns;

use Closure;
use Integrations\Lpod\Exceptions\LpodException;

trait WritesToHost
{
    /**
     * Runs a host write and flashes a toast with the outcome.
     *
     * @param  Closure(): mixed  $callback
     */
    protected function toHost(Closure $callback, string $success, string $description, string $failure): bool
    {
        try {
            $callback();
        } catch (LpodException $exception) {
            toast(title: $failure, description: $exception->getMessage(), type: 'error');

            return false;
        }

        toast(title: $success, description: $description);

        return true;
    }
}
