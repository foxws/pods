<?php

declare(strict_types=1);

namespace Modules\Web\Environments\Concerns;

use Closure;
use Inertia\DeferProp;
use Inertia\Inertia;
use Inertia\OptionalProp;
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

    /**
     * A host read the page loads after it renders, in the request of its group, as fromHost() gives it.
     *
     * @param  Closure(): mixed  $callback
     */
    protected function deferFromHost(Closure $callback, string $group = 'default'): DeferProp
    {
        return Inertia::defer(fn (): array => $this->fromHost($callback), $group);
    }

    /**
     * A host read the page only gets when it asks for it, such as on a poll, as fromHost() gives it.
     *
     * @param  Closure(): mixed  $callback
     */
    protected function optionalFromHost(Closure $callback): OptionalProp
    {
        return Inertia::optional(fn (): array => $this->fromHost($callback));
    }
}
