<?php

declare(strict_types=1);

namespace Domain\Applications\DataObjects;

use Domain\Applications\Enums\UpstreamReach;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Where the Pods proxy can reach a container of an application, offered as a route's upstream.
 */
#[TypeScript]
class ReachableUpstreamData extends Data
{
    /**
     * @param  string  $container  the container, or the pod it is in
     * @param  string  $name  what the upstream starts with: the container or pod, or host.containers.internal
     * @param  int|null  $port  the port to route to, when Pods knows it
     * @param  string|null  $published  the port as Podman publishes it, such as 127.0.0.1:3000->3000/tcp
     */
    public function __construct(
        public string $container,
        public UpstreamReach $reach,
        public string $name,
        public ?int $port,
        public ?string $published,
    ) {}
}
