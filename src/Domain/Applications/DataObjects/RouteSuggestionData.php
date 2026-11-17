<?php

declare(strict_types=1);

namespace Domain\Applications\DataObjects;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * A route to offer on the application page: one the application has, or one its pods.yml
 * names, with a hostname suggested from the application's name. A route from pods.yml keeps
 * its upstream; another one can be edited.
 */
#[TypeScript]
class RouteSuggestionData extends Data
{
    public function __construct(
        public string $role,
        public ?string $description,
        public string $upstream,
        public bool $main,
        public ?string $via,
        public ?string $host,
        public string $suggested_host,
        public ?string $target,
        public bool $from_manifest,
    ) {}
}
