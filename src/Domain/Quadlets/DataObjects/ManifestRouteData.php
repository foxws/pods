<?php

declare(strict_types=1);

namespace Domain\Quadlets\DataObjects;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * An upstream of the application that the Pods proxy can give a hostname, such as its web app
 * or its WebSockets server. The hostname is asked for when installing, never in pods.yml.
 * A route with via "main" goes where the main route goes, for an application that routes its
 * own hostnames, so it wakes a sleeping application too.
 */
#[TypeScript]
class ManifestRouteData extends Data
{
    public function __construct(
        public string $role,
        public string $upstream,
        public ?string $description,
        public bool $main,
        public ?string $via,
    ) {}
}
