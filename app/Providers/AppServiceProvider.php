<?php

namespace App\Providers;

use App\Services\TenantManager;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Register TenantManager as singleton
        $this->app->singleton(TenantManager::class, function ($app) {
            return new TenantManager;
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
