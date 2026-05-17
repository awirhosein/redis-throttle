<?php

namespace Awirhosein\RedisThrottle\Middleware;

use Awirhosein\RedisThrottle\Services\RateLimitService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequestRateLimiter
{
    public function __construct(
        private RateLimitService $rateLimitService
    ) {
    }

    /**
     * Handle an incoming request.
     *
     * @param Closure(Request): (Response) $next
     */
    public function handle(Request $request, Closure $next, string $period): Response
    {
        $result = $this->rateLimitService->checkRequest($request, $period);

        if (! $result->allowed) {
            return $result->toErrorResponse();
        }

        return $result->attachHeaders($next($request));
    }
}
