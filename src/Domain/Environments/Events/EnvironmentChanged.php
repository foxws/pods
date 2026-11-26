<?php

declare(strict_types=1);

namespace Domain\Environments\Events;

use Domain\Environments\Models\Environment;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Support\Str;

/**
 * Pods changed something on a host. It names the reads the change affects by their `LpodClient`
 * cache keys (`apps`, `apps.hello`, `proxy.routes`), and broadcasts once on the channel of each,
 * so a page only reloads the props (live props) that show one of them. It carries no data,
 * and broadcasts at once, since production Pods runs no queue worker for it.
 */
class EnvironmentChanged implements ShouldBroadcastNow
{
    use InteractsWithSockets;

    /**
     * @param  list<string>  $keys
     */
    public function __construct(
        public Environment $environment,
        public array $keys,
    ) {}

    /**
     * The event a prop listens for to reload when one of the given reads changes.
     */
    public static function of(Environment $environment, string ...$keys): self
    {
        return new self($environment, array_values($keys));
    }

    /**
     * The channel of a read: `environments.ULID.apps` for a list, and the first part of the key
     * with a hash for a key that holds a name (`environments.ULID.apps-HASH` for `apps.hello`),
     * since names may contain dots, which channel patterns can't match, and Reverb refuses
     * channel names longer than 164 characters.
     */
    public static function channelName(Environment $environment, string $key): string
    {
        $topic = str_contains($key, '.')
            ? Str::before($key, '.').'-'.hash('xxh3', $key)
            : $key;

        return "environments.{$environment->getRouteKey()}.{$topic}";
    }

    /**
     * @return list<PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return array_map(
            fn (string $key): PrivateChannel => new PrivateChannel(self::channelName($this->environment, $key)),
            $this->keys,
        );
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
