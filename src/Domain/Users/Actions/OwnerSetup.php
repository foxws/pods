<?php

declare(strict_types=1);

namespace Domain\Users\Actions;

use Domain\Users\Models\User;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use SensitiveParameter;

/**
 * A new Pods instance has no owner, and its setup page creates one. So that whoever reaches
 * the page first can't claim it, the page asks for a one-time code that only someone with
 * access to the host can read: "php artisan pods:setup" prints it, and so does Pods' log.
 */
class OwnerSetup
{
    /**
     * Letters and digits that can't be mistaken for one another.
     */
    protected const string Alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    protected const string Path = 'setup-code';

    public function isNeeded(): bool
    {
        return User::query()->whereHas('roles', fn ($query) => $query->where('name', 'super-admin'))->doesntExist();
    }

    /**
     * The code, created on first use and kept until the owner exists, so restarting Pods
     * doesn't change it. Formatted as four groups of four, such as K7QM-2XPA-9DTR-W4HN.
     */
    public function code(): string
    {
        if ($this->disk()->exists(self::Path)) {
            return trim((string) $this->disk()->get(self::Path));
        }

        $characters = '';

        for ($i = 0; $i < 16; $i++) {
            $characters .= self::Alphabet[random_int(0, strlen(self::Alphabet) - 1)];
        }

        $code = implode('-', str_split($characters, 4));

        $this->disk()->put(self::Path, $code, 'private');

        return $code;
    }

    /**
     * Compares case-insensitively and ignores spaces, as people type codes from a terminal.
     */
    public function matches(#[SensitiveParameter] string $code): bool
    {
        if (! $this->disk()->exists(self::Path)) {
            return false;
        }

        $normalize = fn (string $value): string => strtoupper((string) preg_replace('/\s+/', '', $value));

        return hash_equals($normalize($this->code()), $normalize($code));
    }

    public function forget(): void
    {
        $this->disk()->delete(self::Path);
    }

    /**
     * The local disk is storage/app/private, on Pods' data volume.
     */
    protected function disk(): Filesystem
    {
        return Storage::disk('local');
    }
}
