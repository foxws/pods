<?php

declare(strict_types=1);

namespace Domain\Environments\Events;

use Domain\Environments\Models\Environment;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

/**
 * Pods changed something on a host. It carries no data: the pages of the host that listen for it
 * (live props) reload what they show. It broadcasts at once, since production Pods runs no queue worker.
 */
class EnvironmentChanged implements ShouldBroadcastNow
{
    use InteractsWithSockets;

    public function __construct(public Environment $environment) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel("environments.{$this->environment->getRouteKey()}");
    }

    public function broadcastAs(): string
    {
        return 'environment.changed';
    }

    /**
     * @return array<string, never>
     */
    public function broadcastWith(): array
    {
        return [];
    }
}
