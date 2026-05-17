<?php

namespace Awirhosein\RateLimiter\Services;

use Illuminate\Http\Request;

class RateLimitService
{
    public function __construct(
        private RateLimitStore $store
    ) {
    }

    public function checkRequest(Request $request, string $period): RateLimitResult
    {
        $plan = $this->getPlan();
        $userId = $this->getUserId($request);
        $endpoint = $request->path();
        $limit = config("rate-limiter.$plan.per_$period");

        [$ttl, $expireAt] = $this->getPeriodConfig($period);

        $key = $this->buildKey($userId, $plan, $period, $endpoint);
        $current = $this->store->increment($key, 1, $ttl, $expireAt);

        return $this->buildResult($current, $limit, $key);
    }

    public function checkFile(Request $request): RateLimitResult
    {
        $plan = $this->getPlan();
        $userId = $this->getUserId($request);

        if (empty($request->allFiles()) || $plan != 'free') {
            return new RateLimitResult(allowed: true);
        }

        $limit = config('rate-limiter.free.file_size');
        $key = $this->buildKey($userId, $plan, 'file_size');
        $fileSize = $this->getTotalFileSize($request->allFiles());
        $expireAt = now()->addDay()->startOfDay()->timestamp;

        $currentBytes = $this->store->increment($key, $fileSize, 0, $expireAt);
        $currentMb = $currentBytes / (1024 * 1024);

        return $this->buildResult($currentMb, $limit, $key);
    }

    private function getPlan(): string
    {
        return auth()->user()?->plan ?? 'free';
    }

    private function getUserId(Request $request): string
    {
        return auth()->user()?->id ?? $request->ip();
    }

    private function getTotalFileSize(array $files): int
    {
        $size = 0;

        foreach ($files as $file) {
            $size += $file->getSize();
        }

        return $size;
    }

    private function getPeriodConfig(string $period): array
    {
        return match ($period) {
            'minute' => [60, null],
            'day' => [0, now()->addDay()->startOfDay()->timestamp],
            default => throw new \InvalidArgumentException("Unsupported period [$period]")
        };
    }

    private function buildKey(...$args): string
    {
        return implode(':', [
            'ratelimit',
            ...$args,
        ]);
    }

    private function buildResult(int $current, int $limit, string $key): RateLimitResult
    {
        return new RateLimitResult(
            allowed: $current <= $limit,
            key: $key,
            limit: $limit,
            remaining: max(0, $limit - $current),
            resetAt: time() + $this->store->ttl($key),
            retryAfter: $this->store->ttl($key)
        );
    }
}
