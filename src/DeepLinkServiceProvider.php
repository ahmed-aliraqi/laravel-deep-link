<?php

namespace AhmedAliraqi\LaravelDeepLink;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class DeepLinkServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/deep-link.php', 'deep-link');

        $this->app->singleton(DeepLinkManager::class);
        $this->app->alias(DeepLinkManager::class, 'deep-link');
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'deep-link');
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'deep-link');

        $this->registerRoutes();
        $this->registerPublishing();
    }

    protected function registerRoutes(): void
    {
        if (! config('deep-link.verification.routes', true)) {
            return;
        }

        Route::middleware(config('deep-link.verification.middleware', []))
            ->group(__DIR__.'/../routes/deep-link.php');
    }

    protected function registerPublishing(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__.'/../config/deep-link.php' => config_path('deep-link.php'),
        ], 'deep-link-config');

        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/deep-link'),
        ], 'deep-link-views');

        $this->publishes([
            __DIR__.'/../lang' => $this->app->langPath('vendor/deep-link'),
        ], 'deep-link-lang');
    }
}
