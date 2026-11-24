<?php

declare(strict_types=1);

namespace Domain\Applications\Models;

use Database\Factories\PinnedApplicationFactory;
use Domain\Environments\Models\Environment;
use Domain\Users\Models\User;
use Foxws\Essentials\Casts\AsDateTime;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An application a user pinned, so its host's overview shows it first with its quick actions.
 * Applications live on the host, so a pin is keyed by environment and application name.
 *
 * @property-read User $user
 * @property-read Environment $environment
 */
class PinnedApplication extends Model
{
    /** @use HasFactory<PinnedApplicationFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'environment_id',
        'application',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => AsDateTime::class,
            'updated_at' => AsDateTime::class,
        ];
    }

    protected static function newFactory(): PinnedApplicationFactory
    {
        return PinnedApplicationFactory::new();
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
