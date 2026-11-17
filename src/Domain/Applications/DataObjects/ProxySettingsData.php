<?php

declare(strict_types=1);

namespace Domain\Applications\DataObjects;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * How the Pods proxy of a host gets its certificates, which ports it publishes and which image
 * it runs. When lpod didn't write the proxy's Caddyfile ($managed is false), the settings are
 * read from it as far as they can be, so the form can start from a hand-written setup.
 */
#[TypeScript]
class ProxySettingsData extends Data
{
    /**
     * The mode lpod writes the Caddyfile for, which also decides how routes are written.
     */
    public const array Modes = ['letsencrypt', 'local', 'wildcard', 'http'];

    /**
     * The DNS providers ghcr.io/foxws/pods-proxy has a plugin for.
     */
    public const array DnsProviders = ['cloudflare'];

    /**
     * @param  string|null  $mode  letsencrypt, local, wildcard or http, or null without a Caddyfile
     * @param  string|null  $domain  the wildcard site's domain, in the wildcard mode
     * @param  string|null  $image  the image instead of the mode's own, or null for that one
     * @param  bool  $dns_token  whether the token for the DNS challenge is on the host
     * @param  int|null  $unprivileged_port_start  the lowest port rootless Podman may publish
     */
    public function __construct(
        public bool $installed,
        public bool $running,
        public bool $managed,
        public ?string $mode,
        public ?string $email,
        public ?string $domain,
        public ?string $dns_provider,
        public ?string $resolvers,
        public ?string $image,
        public ?int $http_port,
        public ?int $https_port,
        public bool $dns_token,
        public bool $rootless,
        public ?int $unprivileged_port_start,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromLpod(array $payload): self
    {
        $settings = $payload['settings'] ?? [];
        $port = fn (mixed $value): ?int => is_int($value) ? $value : null;

        return new self(
            installed: (bool) ($payload['proxy']['installed'] ?? false),
            running: (bool) ($payload['proxy']['running'] ?? false),
            managed: (bool) ($settings['managed'] ?? false),
            mode: in_array($settings['mode'] ?? null, self::Modes, true) ? $settings['mode'] : null,
            email: $settings['email'] ?? null,
            domain: $settings['domain'] ?? null,
            dns_provider: $settings['dns_provider'] ?? null,
            resolvers: $settings['resolvers'] ?? null,
            image: $settings['image'] ?? null,
            http_port: $port($settings['http_port'] ?? null),
            https_port: $port($settings['https_port'] ?? null),
            dns_token: (bool) ($settings['dns_token'] ?? false),
            rootless: ($payload['host']['scope'] ?? 'rootless') === 'rootless',
            unprivileged_port_start: $port($payload['host']['unprivileged_port_start'] ?? null),
        );
    }
}
