<?php

namespace Awirhosein\RedisThrottle\Services;

use Illuminate\Http\JsonResponse;

class RateLimitResult
{
    public function __construct(
        public bool $allowed,
        public ?string $key = null,
        public int $limit = 0,
        public int $remaining = 0,
        public int $resetAt = 0,
        public int $retryAfter = 0
    ) {
    }

    public function toErrorResponse(): JsonResponse
    {
        return response()
            ->json(['message' => 'Too Many Requests'], 429)
            ->withHeaders($this->getHeaders());
    }

    public function attachHeaders($response)
    {
        foreach ($this->getHeaders() as $key => $value) {
            $response->headers->set($key, $value);
        }

        return $response;
    }

    private function getHeaders(): array
    {
        $headers = [
            'X-RateLimit-Limit' => $this->limit,
            'X-RateLimit-Remaining' => $this->remaining,
            'X-RateLimit-Reset' => $this->resetAt,
        ];

        if (! $this->allowed) {
            $headers['Retry-After'] = $this->retryAfter;
        }

        return $headers;
    }
}
