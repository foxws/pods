<?php

declare(strict_types=1);

namespace Domain\Users\QueryBuilders;

use Domain\Environments\Models\Environment;
use Domain\Users\Enums\PinType;
use Domain\Users\Models\Pin;
use Illuminate\Database\Eloquent\Builder;

/**
 * @template TModel of Pin
 *
 * @extends Builder<TModel>
 */
class PinQueryBuilder extends Builder
{
    public function ofType(PinType $type): self
    {
        return $this->where('type', $type);
    }

    public function on(Environment $environment): self
    {
        return $this->whereBelongsTo($environment);
    }
}
