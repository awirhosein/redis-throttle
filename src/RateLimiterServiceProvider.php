<?php

namespace Awirhosein\RateLimiter;

use Awirhosein\RateLimiter\Middleware\UploadRateLimiter;
use Awirhosein\RateLimiter\Middleware\RequestRateLimiter;
use Illuminate\Support\ServiceProvider;

class RateLimiterServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/rate-limiter.php', 'rate-limiter'
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $router = $this->app['router'];

        $router->aliasMiddleware('redis-throttle', RequestRateLimiter::class);
        $router->aliasMiddleware('redis-throttle.file', UploadRateLimiter::class);
    }
}