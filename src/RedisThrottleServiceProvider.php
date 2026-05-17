<?php

namespace Awirhosein\RedisThrottle;

use Awirhosein\RedisThrottle\Middleware\UploadRateLimiter;
use Awirhosein\RedisThrottle\Middleware\RequestRateLimiter;
use Illuminate\Support\ServiceProvider;

class RedisThrottleServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/redis-throttle.php', 'redis-throttle'
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