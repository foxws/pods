<?php

declare(strict_types=1);

namespace Domain\Applications\Enums;

use Foxws\Essentials\Concerns\HasLabels;
use Foxws\Essentials\Contracts\Enumerable;
use Spatie\LaravelOptions\Options;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * How the Pods proxy can reach an upstream: the kinds a route can use, and why a container
 * can't be reached yet.
 */
#[TypeScript]
enum UpstreamReach: string implements Enumerable
{
    use HasLabels;

    /**
     * By name, on a network the proxy joins.
     */
    case Network = 'network';

    /**
     * Through the host, as host.containers.internal, on a port published on an address other than loopback.
     */
    case Host = 'host';

    /**
     * Another machine on the network, by its address.
     */
    case Machine = 'machine';

    /**
     * Not yet: the port is only published on the host's loopback, which the proxy can't reach.
     */
    case Loopback = 'loopback';

    /**
     * Not that Pods can tell: no network and no published port, such as a container on the host network.
     */
    case Unknown = 'unknown';

    /**
     * Every case with its description, an example upstream and whether the proxy can use it.
     */
    public static function options(): Options
    {
        return Options::forEnum(self::class)->append(fn (self $reach): array => [
            'description' => $reach->description(),
            'example' => $reach->example(),
            'usable' => $reach->isUsable(),
        ]);
    }

    public function label(): string
    {
        return match ($this) {
            self::Network => __('On its network'),
            self::Host => __('Through the host'),
            self::Machine => __('Another machine'),
            self::Loopback => __('Only on loopback'),
            self::Unknown => __('Unknown'),
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Network => __("The proxy joins the application's network and reaches the container by name. The usual choice."),
            self::Host => __("A port on this host, for an application with no network of its own. It has to be published on an address other than 127.0.0.1. The host's own IP doesn't reach it from the proxy."),
            self::Machine => __("An address on your network. IPv6 addresses aren't supported."),
            self::Loopback => __('Published only on the host\'s 127.0.0.1, which the proxy can\'t reach. Publish it on another address in a drop-in first, then route to host.containers.internal.'),
            self::Unknown => __('No network or published port that Pods can see, such as on the host network. Check its notes for the port, and that it listens on an address other than 127.0.0.1.'),
        };
    }

    public function example(): ?string
    {
        return match ($this) {
            self::Network => 'systemd-hello:8000',
            self::Host => 'host.containers.internal:3000',
            self::Machine => '192.168.1.20:8080',
            self::Loopback, self::Unknown => null,
        };
    }

    public function isUsable(): bool
    {
        return in_array($this, [self::Network, self::Host, self::Machine], true);
    }
}
