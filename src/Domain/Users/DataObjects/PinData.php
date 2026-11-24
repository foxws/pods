<?php

declare(strict_types=1);

namespace Domain\Users\DataObjects;

use Domain\Users\Enums\PinType;
use Domain\Users\Models\Pin;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Something the current user pinned.
 */
#[TypeScript]
class PinData extends Data
{
    public function __construct(
        public int $id,
        public PinType $type,
        public string $name,
    ) {}

    public static function fromModel(Pin $pin): self
    {
        return new self(
            id: $pin->getKey(),
            type: $pin->type,
            name: $pin->name,
        );
    }
}
