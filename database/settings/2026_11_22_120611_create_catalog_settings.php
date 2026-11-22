<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    /**
     * Starts the catalog with the repository that config/catalog.php used to list.
     */
    public function up(): void
    {
        $this->migrator->add('catalog.repositories', [
            ['repository' => 'https://github.com/foxws/quadlets', 'reference' => 'main'],
        ]);
    }
};
