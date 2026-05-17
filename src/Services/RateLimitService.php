<?php

namespace Awirhosein\RateLimiter\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;

class RateLimitService
{
    public function checkRequest(Request $request, string $period): RateLimitResult
    {
        $plan = $this->getPlan();
        $userId = $this->getUserId($request);
        $endpoint = $request->path();
        $limit = config("rate-limiter.$plan.per_$period");

        [$ttl, $expireAt] = $this->getPeriodConfig($period);

        $key = "ratelimit:{$userId}:{$plan}:{$period}:{$endpoint}";
        $current = $this->increment($key, 1, $ttl, $expireAt);

        return $this->buildResult($current, $limit, $key);
    }

    public function checkFile(Request $request): RateLimitResult
    {
        // TODO: dynamic file name

        $plan = $this->getPlan();
        $userId = $this->getUserId($request);

        if (! $request->hasFile('image') || $plan != 'free') {
            return new RateLimitResult(allowed: true);
        }

        $limit = config('rate-limiter.free.file_size');
        $key = "ratelimit:{$userId}:{$plan}:file_size";
        $fileSize = $request->file('image')->getSize();
        $expireAt = now()->addDay()->startOfDay()->timestamp;

        $currentBytes = $this->increment($key, $fileSize, 0, $expireAt);
        $currentMb = $currentBytes / (1024 * 1024);

        return $this->buildResult($currentMb, $limit, $key);
    }

    private function getPlan(): string
    {
        return auth()->user()->plan ?? 'free';
    }

    private function getUserId(Request $request)
    {
        return auth()->user()->id ?? $request->ip();
    }

    private function getPeriodConfig(string $period): array
    {
        return match ($period) {
            'hour' => [3600, null],
            'day' => [0, now()->addDay()->startOfDay()->timestamp],
            'month' => [0, now()->addMonth()->startOfDay()->timestamp],
            default => [60, null],
        };
    }

    private function increment(string $key, int $value = 1, int $ttl = 60, ?int $expireAt = null): int
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

    private function buildResult(int $current, int $limit, string $key): RateLimitResult
    {
        return new RateLimitResult(
            allowed: $current <= $limit,
            key: $key,
            limit: $limit,
            remaining: max(0, $limit - $current),
            resetAt: time() + Redis::ttl($key),
            retryAfter: Redis::ttl($key)
        );
    }
}
