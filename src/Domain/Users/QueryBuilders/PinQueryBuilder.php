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
    /**
     * @return $this
     */
    public function ofType(PinType $type): static
    {
        return $this->where('type', $type);
    }

    /**
     * @return $this
     */
    public function on(Environment $environment): static
    {
        return $this->whereBelongsTo($environment);
    }
}
