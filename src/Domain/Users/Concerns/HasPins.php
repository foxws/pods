<?php

declare(strict_types=1);

namespace Domain\Users\Concerns;

use Domain\Users\Models\Pin;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * For the models a pin belongs to: the user who pinned it, and the environment it's on.
 */
trait HasPins
{
    /**
     * @return HasMany<Pin, $this>
     */
    public function pins(): HasMany
    {
        return $this->hasMany(Pin::class)->chaperone();
    }
}
