<?php

declare(strict_types=1);

namespace App\Common\Services;

use App\Common\Enums\ErrorCode;
use support\Redis;
use Throwable;

final class RequestLimiter
{
    /** @param null|callable(string, int): int $increment */
    public function __construct(private readonly mixed $increment = null)
    {
    }

    public function consume(string $bucket, string $identity, int $limit, int $window): void
    {
        $window = max(1, $window);
        $key = 'hgt:request-rate:' . hash('sha256', $bucket . '|' . $identity . '|' . $window . '|' . intdiv(time(), $window));
        try {
            $count = $this->increment !== null ? ($this->increment)($key, $window) : (int) Redis::eval(
                "local count = redis.call('INCR', KEYS[1]); if count == 1 then redis.call('EXPIRE', KEYS[1], ARGV[1]) end; return count",
                1,
                $key,
                $window,
            );
        } catch (Throwable) {
            ErrorCode::SYSTEM_BUSY->throw();
        }
        if ($count > max(0, $limit)) {
            ErrorCode::REQUEST_FREQUENCY->throw();
        }
    }
}
