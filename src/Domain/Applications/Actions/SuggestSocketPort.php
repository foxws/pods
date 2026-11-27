<?php

declare(strict_types=1);

namespace Domain\Applications\Actions;

use Domain\Applications\DataObjects\ApplicationData;

/**
 * A port for an application's on-demand socket that no other application on the host publishes. lpod moves
 * the application itself to the socket's port + 10000 on 127.0.0.1, so that port has to be free as well.
 * A sleeping application publishes that local port, so its socket's port is never offered either.
 */
class SuggestSocketPort
{
    public const int LocalPortOffset = 10000;

    public const int HighestPort = 65535 - self::LocalPortOffset;

    protected const int FirstPort = 8000;

    /**
     * The preferred port while it's free, or else the first free one from 8000.
     *
     * @param  list<ApplicationData>  $applications
     */
    public function __invoke(string $application, ?int $preferred, array $applications): ?int
    {
        $taken = $this->takenPorts($application, $applications);

        $isFree = fn (int $port): bool => ! isset($taken[$port]) && ! isset($taken[$port + self::LocalPortOffset]);

        if ($preferred !== null && $preferred >= 1 && $preferred <= self::HighestPort && $isFree($preferred)) {
            return $preferred;
        }

        for ($port = self::FirstPort; $port <= self::HighestPort; $port++) {
            if ($isFree($port)) {
                return $port;
            }
        }

        return null;
    }

    /**
     * The other application that publishes the port, or the local port lpod would give the application.
     *
     * @param  list<ApplicationData>  $applications
     * @return array{application: string, port: int}|null
     */
    public function conflict(string $application, int $port, array $applications): ?array
    {
        $taken = $this->takenPorts($application, $applications);

        foreach ([$port, $port + self::LocalPortOffset] as $candidate) {
            if (isset($taken[$candidate])) {
                return ['application' => $taken[$candidate], 'port' => $candidate];
            }
        }

        return null;
    }

    /**
     * The host ports of every other application, with the name of the application that publishes each.
     *
     * @param  list<ApplicationData>  $applications
     * @return array<int, string>
     */
    protected function takenPorts(string $application, array $applications): array
    {
        $taken = [];

        foreach ($applications as $other) {
            if ($other->name === $application) {
                continue;
            }

            foreach ($other->hostPorts() as $port) {
                $taken[$port] ??= $other->name;
            }
        }

        return $taken;
    }
}
