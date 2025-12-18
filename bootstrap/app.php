<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use App\Http\Middleware\CheckApiKey;
use App\Http\Middleware\VerifyFrontendOrigin;
use App\Http\Middleware\VerifyAccessKey;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use App\Providers\ApiRateLimiterServiceProvider;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // $middleware->append(CheckApiKey::class);
        $middleware->alias([
            'verify.access.key' => VerifyAccessKey::class,
            'verify.origin' => VerifyFrontendOrigin::class,
        ]);
    })
    ->withProviders([
        ApiRateLimiterServiceProvider::class
    ])
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();