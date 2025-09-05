<?php

namespace SlowestWind\TabSessionGuard\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;
use SlowestWind\TabSessionGuard\Services\TabGuardService;
use SlowestWind\TabSessionGuard\Http\Middleware\TabSessionGuardMiddleware;

class TabSessionGuardServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../Config/tab-session-guard.php',
            'tab-session-guard'
        );

        $this->app->singleton('tab-guard', function ($app) {
            return new TabGuardService($app['config']['tab-session-guard']);
        });

        $this->app->singleton(TabGuardService::class, function ($app) {
            return $app['tab-guard'];
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        $this->bootConfig();
        $this->bootViews();
        $this->bootAssets();
        $this->bootMiddleware();
        $this->bootRoutes();
        $this->bootCommands();
    }

    /**
     * Boot configuration.
     */
    protected function bootConfig(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../Config/tab-session-guard.php' => config_path('tab-session-guard.php'),
            ], 'tab-guard-config');
        }
    }

    /**
     * Boot views.
     */
    protected function bootViews(): void
    {
        $this->loadViewsFrom(__DIR__ . '/../../resources/views', 'tab-guard');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../../resources/views' => resource_path('views/vendor/tab-guard'),
            ], 'tab-guard-views');
        }
    }

    /**
     * Boot assets.
     */
    protected function bootAssets(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../../resources/js' => public_path('vendor/tab-guard'),
            ], 'tab-guard-assets');
        }
    }

    /**
     * Boot middleware.
     */
    protected function bootMiddleware(): void
    {
        $router = $this->app['router'];
        
        $router->aliasMiddleware('tab.guard', TabSessionGuardMiddleware::class);
        
        // Auto-apply middleware to web routes if enabled
        if (config('tab-session-guard.enabled', true)) {
            $router->pushMiddlewareToGroup('web', TabSessionGuardMiddleware::class);
        }
    }

    /**
     * Boot routes.
     */
    protected function bootRoutes(): void
    {
        Route::group([
            'prefix' => 'tab-guard',
            'middleware' => ['web'],
            'namespace' => 'SlowestWind\TabSessionGuard\Http\Controllers',
        ], function () {
            $this->loadRoutesFrom(__DIR__ . '/../Http/routes.php');
        });
    }

    /**
     * Boot commands.
     */
    protected function bootCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                \SlowestWind\TabSessionGuard\Console\CleanupTabsCommand::class,
            ]);
        }
    }

    /**
     * Get the services provided by the provider.
     */
    public function provides(): array
    {
        return ['tab-guard', TabGuardService::class];
    }
}
