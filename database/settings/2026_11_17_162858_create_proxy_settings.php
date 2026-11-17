<?php

use Spatie\LaravelSettings\Migrations\SettingsBlueprint;
use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->inGroup('proxy', function (SettingsBlueprint $blueprint): void {
            $blueprint->add('mode', 'letsencrypt');
            $blueprint->add('email', null);
            $blueprint->add('domain', null);
            $blueprint->add('dns_provider', 'cloudflare');
            $blueprint->add('resolvers', null);
            $blueprint->add('http_port', 80);
            $blueprint->add('https_port', 443);
            $blueprint->add('image', null);
        });
    }
};
