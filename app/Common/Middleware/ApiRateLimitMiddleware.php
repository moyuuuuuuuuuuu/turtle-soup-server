<?php

declare(strict_types=1);

namespace App\Common\Middleware;

use App\Common\Services\RequestLimiter;
use App\Common\Support\ClientIp;
use Webman\Http\Request;
use Webman\Http\Response;
use Webman\MiddlewareInterface;

final class ApiRateLimitMiddleware implements MiddlewareInterface
{
    /**
     * @param null|callable(string, int): int $increment
     * @param null|list<array{methods: list<string>, paths: list<string>, limit: int, window: int}> $rules
     */
    public function __construct(private readonly mixed $increment = null, private readonly ?array $rules = null)
    {
    }

    public function process(Request $request, callable $handler): Response
    {
        if (!(bool) config('api_rate_limit.enabled', true)) {
            return $handler($request);
        }
        $limiter = new RequestLimiter($this->increment);
        $ip = ClientIp::resolve($request->getRemoteIp(), (string) $request->header('x-forwarded-for', ''));
        foreach ($this->rules ?? (array) config('api_rate_limit.rules', []) as $rule) {
            if (in_array(strtoupper($request->method()), $rule['methods'], true)
                && in_array($request->path(), $rule['paths'], true)) {
                // Apply every matching window; unverified credentials never select the bucket.
                $limiter->consume($request->path(), $ip, (int) $rule['limit'], (int) $rule['window']);
            }
        }

        return $handler($request);
    }
}
