<?php

declare(strict_types=1);

namespace Domain\Users\Models;

use Database\Factories\PinFactory;
use Domain\Environments\Models\Environment;
use Domain\Users\Enums\PinType;
use Domain\Users\QueryBuilders\PinQueryBuilder;
use Foxws\Essentials\Casts\AsDateTime;
use Illuminate\Database\Eloquent\Attributes\UseEloquentBuilder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Something a user pinned, such as an application to its host's overview. Host resources live on
 * the host, so a pin names its type and the resource's name, and the environment it's on.
 *
 * @property PinType $type
 * @property-read User $user
 * @property-read Environment|null $environment
 */
#[UseEloquentBuilder(PinQueryBuilder::class)]
class Pin extends Model
{
    /** @use HasFactory<PinFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'environment_id',
        'type',
        'name',
    ];

    protected function casts(): array
    {
        return [
            'type' => PinType::class,
            'created_at' => AsDateTime::class,
            'updated_at' => AsDateTime::class,
        ];
    }

    protected static function newFactory(): PinFactory
    {
        return PinFactory::new();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Environment, $this>
     */
    public function environment(): BelongsTo
    {
        return $this->belongsTo(Environment::class);
    }
}
