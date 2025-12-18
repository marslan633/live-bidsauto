<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Cache\RateLimiting\Limit;

class ApiRateLimiterServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // This runs after the application is booted, so Facades work
        RateLimiter::for('api', function ($request) {
            $apiKey = $request->header('X-Access-Key') ?: $request->ip();
            return Limit::perMinute(60)->by($apiKey);
        });
    }
}