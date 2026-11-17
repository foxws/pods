<?php

declare(strict_types=1);

namespace Domain\Quadlets\DataObjects;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * A value Pods asks for before running a setup step. With an option (such as --email), it
 * goes in the arguments as --email=VALUE. Without one, the command reads it on stdin, so a password
 * never shows in the arguments.
 */
#[TypeScript]
class ManifestSetupInputData extends Data
{
    public function __construct(
        public string $name,
        public string $label,
        public ?string $description,
        public string $type,
        public ?string $option,
    ) {}
}
