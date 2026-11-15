<?php

declare(strict_types=1);

namespace Modules\Web\Environments\Concerns;

use Closure;
use Integrations\Lpod\Exceptions\LpodException;

trait ReadsFromHost
{
    /**
     * Wraps a host read so the page can show the error in place, instead of failing.
     *
     * @template TData
     *
     * @param  Closure(): TData  $callback
     * @return array{data: TData|null, error: string|null}
     */
    protected function fromHost(Closure $callback): array
    {
        try {
            return ['data' => $callback(), 'error' => null];
        } catch (LpodException $exception) {
            report($exception);

            return ['data' => null, 'error' => $exception->getMessage()];
        }
    }
}
