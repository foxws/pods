<?php

declare(strict_types=1);

namespace Modules\Web\Environments\Concerns;

use Closure;
use Domain\Environments\Events\EnvironmentChanged;
use Domain\Environments\Models\Environment;
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
     * Given `live`, the page reloads it whenever Pods changes one of the reads it names on that host.
     *
     * @param  Closure(): mixed  $callback
     */
    protected function deferFromHost(Closure $callback, string $group = 'default', ?EnvironmentChanged $live = null): DeferProp
    {
        return $this->liveOn(Inertia::defer(fn (): array => $this->fromHost($callback), $group), $live);
    }

    /**
     * A host read the page only gets when it asks for it, such as on a poll, as fromHost() gives it.
     *
     * @param  Closure(): mixed  $callback
     */
    protected function optionalFromHost(Closure $callback, ?EnvironmentChanged $live = null): OptionalProp
    {
        return $this->liveOn(Inertia::optional(fn (): array => $this->fromHost($callback)), $live);
    }

    /**
     * The changes a live prop reloads on: the `LpodClient` cache keys of the reads it shows,
     * such as `apps` for the list of applications or `apps.hello` for one's status.
     */
    protected function changesTo(Environment $environment, string ...$keys): EnvironmentChanged
    {
        return EnvironmentChanged::of($environment, ...$keys);
    }

    /**
     * Pods only knows about the changes it makes itself, so pages still poll for the rest,
     * such as a unit that crashed or finished starting.
     *
     * @template TProp of DeferProp|OptionalProp
     *
     * @param  TProp  $prop
     * @return TProp
     */
    protected function liveOn(DeferProp|OptionalProp $prop, ?EnvironmentChanged $changes): DeferProp|OptionalProp
    {
        if ($changes === null) {
            return $prop;
        }

        return $prop->live(on: $changes);
    }
}
