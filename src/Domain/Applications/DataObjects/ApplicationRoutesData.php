<?php

declare(strict_types=1);

namespace Domain\Applications\DataObjects;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * An application's routes through the Pods proxy, and whether the proxy is there to serve them.
 */
#[TypeScript]
class ApplicationRoutesData extends Data
{
    /**
     * @param  string  $layout  "sites" or "handlers", see ProxyRoutesData
     * @param  list<RouteSuggestionData>  $routes
     */
    public function __construct(
        public bool $proxy_installed,
        public bool $proxy_running,
        public string $layout,
        public ?string $domain,
        public array $routes,
    ) {}
}
