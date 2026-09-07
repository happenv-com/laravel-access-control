<?php

namespace Happenv\LaravelAccessControl;

use Illuminate\Support\ServiceProvider;

class AccessControlServiceProvider extends ServiceProvider
{
    #[\Override]
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/access-control.php', 'access-control');

        $this->app->singleton(fn (): PermissionRegistry => new PermissionRegistry);
        $this->app->singleton(fn (): VoterRegistry => new VoterRegistry);
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes(
                [__DIR__ . '/../config/access-control.php' => config_path('access-control.php')],
                'access-control-config',
            );
        }

        resolve(GateConfigurator::class)->configure();
    }
}
