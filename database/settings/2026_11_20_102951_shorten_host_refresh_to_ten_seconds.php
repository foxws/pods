<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    /**
     * Moves a host refresh still on the old default of 30 seconds to the new default of 10, and keeps one that was chosen.
     */
    public function up(): void
    {
        $this->migrator->update('general.host_refresh', fn (int $seconds): int => $seconds === 30 ? 10 : $seconds);
    }
};
