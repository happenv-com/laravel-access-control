<?php

namespace Happenv\LaravelAccessControl;

use Happenv\LaravelAccessControl\Commands\PermissionGraphCommand;
use Happenv\LaravelAccessControl\Diagram\Renderer\DiagramRendererRegistry;
use Happenv\LaravelAccessControl\Diagram\Renderer\DotRenderer;
use Happenv\LaravelAccessControl\Diagram\Renderer\JsonRenderer;
use Happenv\LaravelAccessControl\Diagram\Renderer\MermaidRenderer;
use Happenv\LaravelAccessControl\Diagram\Renderer\TreeRenderer;
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
        $this->app->singleton(fn (): PermissionConditions => new PermissionConditions);

        // One graph per process: rules are facts about the code, compiled once and kept.
        $this->app->singleton(fn (): PermissionGraph => new PermissionGraph($this->app->make(PermissionRegistry::class)));
        $this->app->singleton(fn (): PermissionResolver => new PermissionResolver($this->app->make(PermissionGraph::class)));

        // Tagged, so an application can add a format or replace one: a later renderer of the same
        // format wins. Bound, not shared, so a tag added after the first use still counts.
        $this->app->tag([
            TreeRenderer::class,
            MermaidRenderer::class,
            DotRenderer::class,
            JsonRenderer::class,
        ], DiagramRendererRegistry::TAG);
        $this->app->bind(fn (): DiagramRendererRegistry => new DiagramRendererRegistry($this->app->tagged(DiagramRendererRegistry::TAG)));
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes(
                [__DIR__ . '/../config/access-control.php' => config_path('access-control.php')],
                'access-control-config',
            );

            $this->commands([PermissionGraphCommand::class]);
        }

        resolve(GateConfigurator::class)->configure();

        // Resolved HERE, not at the first check: Octane serves every request from a clone of the
        // booted application, and a singleton first resolved during a request leaves with its
        // clone — the graph would be compiled again for each request. Compilation stays lazy.
        resolve(PermissionResolver::class);
        resolve(PermissionConditions::class);
    }
}
