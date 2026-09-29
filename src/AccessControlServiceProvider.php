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
        $this->app->singleton(fn (): PermissionRestrictions => new PermissionRestrictions);

        // One graph per process: rules are facts about the code, compiled once and kept.
        $this->app->singleton(fn (): PermissionGraph => new PermissionGraph($this->app->make(PermissionRegistry::class)));
        $this->app->singleton(fn (): PermissionResolver => new PermissionResolver($this->app->make(PermissionGraph::class)));
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

        // Resolved HERE, not at the first check: Octane serves every request from a clone of the
        // booted application, and a singleton first resolved during a request leaves with its
        // clone — the graph would be compiled again for each request. Compilation stays lazy.
        resolve(PermissionResolver::class);
    }
}
