<?php

namespace App\Providers;

use Elastic\Elasticsearch\ClientBuilder;
use Illuminate\Support\ServiceProvider;
use Laravel\Telescope\TelescopeServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        if (!$this->app->isProduction()) {
            $this->app->register(TelescopeServiceProvider::class);
        }

        $this->app->singleton('Elasticsearch', function () {
            return ClientBuilder::create()
                ->setHosts(['localhost:9200']) // Or your actual host/IP
                ->build();
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
