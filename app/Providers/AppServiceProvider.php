<?php

namespace App\Providers;

use Elastic\Elasticsearch\ClientBuilder;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {


        $this->app->singleton('Elasticsearch', function () {
            return ClientBuilder::create()
                ->setHosts(['168.231.107.98:9200']) // Or your actual host/IP
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
