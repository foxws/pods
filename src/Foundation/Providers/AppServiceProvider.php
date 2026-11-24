<?php

declare(strict_types=1);

namespace Foundation\Providers;

use Illuminate\Support\ServiceProvider;
use Integrations\Lpod\LpodClient;
use Integrations\Ssh\Contracts\RunsHostCommands;
use Integrations\Ssh\SshHostRunner;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->registerHostGateway();
        $this->registerTelescope();
    }

    public function boot(): void
    {
        //
    }

    protected function registerHostGateway(): void
    {
        $this->app->scoped(RunsHostCommands::class, SshHostRunner::class);
        $this->app->scoped(LpodClient::class);
    }

    protected function registerTelescope(): void
    {
        if ((bool) config('telescope.enabled') && class_exists(\Laravel\Telescope\TelescopeServiceProvider::class)) {
            $this->app->register(\Laravel\Telescope\TelescopeServiceProvider::class);
            $this->app->register(TelescopeServiceProvider::class);
        }
    }
}
