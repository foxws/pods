<?php

declare(strict_types=1);

namespace Foundation\Settings;

use Spatie\LaravelSettings\Settings;

/**
 * What a host's Proxy section starts from before its proxy is installed, and the domain hostnames
 * are suggested under. Once a proxy is installed, its host keeps its own settings (lpod writes
 * them into its Caddyfile), so changing these doesn't change any host. The token for the DNS
 * challenge is never kept here: it's asked for per host and only stored there.
 */
class ProxySettings extends Settings
{
    public string $mode = 'letsencrypt';

    public ?string $email = null;

    /**
     * The domain hostnames go under, such as example.com, and the wildcard site's in that mode.
     */
    public ?string $domain = null;

    public string $dns_provider = 'cloudflare';

    public ?string $resolvers = null;

    public int $http_port = 80;

    public int $https_port = 443;

    public ?string $image = null;

    public static function group(): string
    {
        return 'proxy';
    }
}
