<?php

namespace Awirhosein\RateLimiter\Services;

use Illuminate\Support\Facades\Redis;

class RateLimitStore
{
    public function increment(string $key, int $value = 1, int $ttl = 60, ?int $expireAt = null): int
    {
        $current = Redis::incrBy($key, $value);

        if ($current === $value) {
            if ($expireAt) {
                Redis::expireAt($key, $expireAt);
            } else {
                Redis::expire($key, $ttl);
            }
        }

        return $current;
    }

    public function ttl(string $key): int
    {
        return max(0, Redis::ttl($key));
    }
}