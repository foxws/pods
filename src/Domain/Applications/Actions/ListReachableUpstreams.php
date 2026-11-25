<?php

declare(strict_types=1);

namespace Domain\Applications\Actions;

use Domain\Applications\DataObjects\ReachableUpstreamData;
use Domain\Applications\Enums\UpstreamReach;
use Domain\Containers\DataObjects\HostContainerData;
use Domain\Networks\DataObjects\NetworkData;
use Illuminate\Support\Collection;

class ListReachableUpstreams
{
    /**
     * A port as Podman publishes it: 0.0.0.0:8080->80/tcp, 127.0.0.1:3000->3000/tcp or 8000-8001->8000-8001/tcp.
     */
    public const string PublishedPattern = '/^(?:(?<address>\[[^\]]*\]|[^:\[\]]+):)?(?<host>\d+)(?:-\d+)?->(?<container>\d+)(?:-\d+)?\/(?<protocol>[a-z]+)$/';

    /**
     * Where the Pods proxy can reach the containers of an application, from what Podman knows.
     * A container on a network the proxy can join is reached by name, on its published container
     * ports. A port published on an address other than loopback is reached through the host. The
     * containers of a pod share its network and ports, so they're listed once, by the pod's name.
     *
     * @param  list<HostContainerData>  $containers
     * @param  list<NetworkData>  $networks
     * @return list<ReachableUpstreamData>
     */
    public function __invoke(string $application, array $containers, array $networks): array
    {
        $isOnJoinableNetwork = collect($networks)->contains(fn (NetworkData $network): bool => $this->isJoinable($network, $application));

        return array_values(collect($containers)
            ->filter(fn (HostContainerData $container): bool => $container->application === $application)
            ->groupBy(fn (HostContainerData $container): string => $container->pod ?? $container->name)
            ->flatMap(fn (Collection $group, string $name): array => $this->reachableOf($name, $this->published($group), $isOnJoinableNetwork))
            ->all());
    }

    /**
     * Only a bridge network with DNS lets the proxy resolve container names, and the default
     * network has no DNS.
     */
    protected function isJoinable(NetworkData $network, string $application): bool
    {
        return $network->driver === 'bridge'
            && $network->dns
            && ! $network->default
            && in_array($application, $network->applications, true);
    }

    /**
     * @param  list<array{published: string, address: string|null, host: int, container: int}>  $ports
     * @return list<ReachableUpstreamData>
     */
    protected function reachableOf(string $name, array $ports, bool $isOnJoinableNetwork): array
    {
        $reachable = [];

        if ($isOnJoinableNetwork) {
            $byContainerPort = collect($ports)->unique('container');

            $reachable = $byContainerPort->isEmpty()
                ? [new ReachableUpstreamData($name, UpstreamReach::Network, $name, null, null)]
                : $byContainerPort
                    ->map(fn (array $port): ReachableUpstreamData => new ReachableUpstreamData($name, UpstreamReach::Network, $name, $port['container'], $port['published']))
                    ->values()
                    ->all();
        }

        foreach ($ports as $port) {
            if (! $this->isLoopback($port['address'])) {
                $reachable[] = new ReachableUpstreamData($name, UpstreamReach::Host, 'host.containers.internal', $port['host'], $port['published']);
            } elseif (! $isOnJoinableNetwork) {
                $reachable[] = new ReachableUpstreamData($name, UpstreamReach::Loopback, 'host.containers.internal', $port['host'], $port['published']);
            }
        }

        if ($reachable === []) {
            $reachable[] = new ReachableUpstreamData($name, UpstreamReach::Unknown, $name, null, null);
        }

        return array_values($reachable);
    }

    /**
     * The TCP ports the containers publish, once each.
     *
     * @param  Collection<int, HostContainerData>  $containers
     * @return list<array{published: string, address: string|null, host: int, container: int}>
     */
    protected function published(Collection $containers): array
    {
        return array_values($containers
            ->flatMap(fn (HostContainerData $container): array => $container->ports)
            ->unique()
            ->map(fn (string $published): ?array => preg_match(self::PublishedPattern, $published, $matches) === 1 && $matches['protocol'] === 'tcp'
                ? [
                    'published' => $published,
                    'address' => $matches['address'] !== '' ? $matches['address'] : null,
                    'host' => (int) $matches['host'],
                    'container' => (int) $matches['container'],
                ]
                : null)
            ->filter()
            ->all());
    }

    protected function isLoopback(?string $address): bool
    {
        return $address !== null && (str_starts_with($address, '127.') || $address === '[::1]' || $address === 'localhost');
    }
}
