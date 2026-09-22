<?php

declare(strict_types=1);

namespace App\Game\Services;

use App\Auth\Entities\PlayerContext;
use App\Common\Enums\ErrorCode;
use Psr\Log\LoggerInterface;
use support\Log;
use support\Redis;
use Throwable;

final class GameUsageGuard
{
    // Admission and reservation are one Redis operation across HTTP and WebSocket workers.
    public const ACQUIRE = <<<'LUA'
local quotas = cjson.decode(ARGV[1])
local locks = cjson.decode(ARGV[2])
local cost = tonumber(ARGV[3])
local lease = ARGV[4]
local ttl = tonumber(ARGV[5])
local now = tonumber(redis.call('TIME')[1])
local quotaKeys = {}
for i, quota in ipairs(quotas) do
    quotaKeys[i] = KEYS[i] .. ':' .. math.floor(now / quota.window)
    if tonumber(redis.call('GET', quotaKeys[i]) or '0') + cost > quota.limit then
        return 0
    end
end
for i, limit in ipairs(locks) do
    local key = KEYS[#quotas + i]
    redis.call('ZREMRANGEBYSCORE', key, '-inf', now)
    if redis.call('ZCARD', key) >= limit then
        return 2
    end
end
for i, quota in ipairs(quotas) do
    redis.call('INCRBY', quotaKeys[i], cost)
    redis.call('EXPIRE', quotaKeys[i], quota.window + 1)
end
for i, limit in ipairs(locks) do
    local key = KEYS[#quotas + i]
    redis.call('ZADD', key, now + ttl, lease)
    redis.call('EXPIRE', key, ttl + 1)
end
return 1
LUA;

    public const RELEASE = <<<'LUA'
for i, key in ipairs(KEYS) do
    redis.call('ZREM', key, ARGV[1])
end
return 1
LUA;

    /**
     * @param null|callable(string, list<string>, list<string>): int $evaluate
     * @param null|array<string, int> $limits
     */
    public function __construct(private readonly mixed $evaluate = null, private readonly ?array $limits = null, private readonly ?LoggerInterface $logger = null)
    {
    }

    /**
     * @template T
     * @param callable(): T $operation
     * @return T
     */
    public function run(PlayerContext $context, string $gameId, callable $operation): mixed
    {
        $id = $context->isUser() ? $context->userId : $context->anonymousSessionId;
        if ($id === null || $id < 1) {
            ErrorCode::AUTH_TOKEN_INVALID->throw();
        }
        $kind = $context->isUser() ? 'user' : 'anonymous';
        $identity = $kind . ':' . $id;
        // Missing source information uses a shared restrictive bucket, never a caller-selected identity.
        $ip = $context->sourceIp !== '' ? $context->sourceIp : 'unknown';
        $limits = $this->limits ?? (array) config('abuse', []);
        $quotas = [
            ['window' => 60, 'limit' => (int) ($limits[$kind . '_minute'] ?? ($context->isUser() ? 20 : 10))],
            ['window' => 86400, 'limit' => (int) ($limits[$kind . '_day'] ?? ($context->isUser() ? 300 : 120))],
            ['window' => 60, 'limit' => (int) ($limits['ip_minute'] ?? 60)],
            ['window' => 86400, 'limit' => (int) ($limits['ip_day'] ?? 1000)],
            ['window' => 86400, 'limit' => (int) ($limits['global_day'] ?? 10000)],
        ];
        $key = static fn (string $value): string => 'hgt:{game-usage}:' . hash('sha256', $value);
        $quotaKeys = [$key($identity . ':minute'), $key($identity . ':day'), $key('ip:' . $ip . ':minute'), $key('ip:' . $ip . ':day'), $key('global:day')];
        $lockKeys = [$key('active:' . $identity), $key('active:game:' . $gameId), $key('active:ip:' . $ip), $key('active:global')];
        $locks = [1, 1, max(0, (int) ($limits['ip_concurrency'] ?? 4)), max(0, (int) ($limits['global_concurrency'] ?? 20))];
        // Reserve the maximum upstream attempts, including configured retries. Never refund uncertain failures.
        $attempts = max(0, (int) config('ai.game_judge.retries', 1)) + 1;
        $timeout = max(1, (int) config('ai.game_judge.timeout', 120));
        $delay = max(0, (int) config('ai.game_judge.retry_delay_ms', 250));
        $ttl = $attempts * $timeout + (int) ceil($delay * $attempts * ($attempts - 1) / 2000) + 60;
        $lease = bin2hex(random_bytes(16));
        try {
            $result = $this->eval(self::ACQUIRE, [...$quotaKeys, ...$lockKeys], [
                json_encode($quotas, JSON_THROW_ON_ERROR), json_encode($locks, JSON_THROW_ON_ERROR),
                (string) $attempts, $lease, (string) $ttl,
            ]);
        } catch (Throwable) {
            ErrorCode::SYSTEM_BUSY->throw();
        }
        if ($result === 0) {
            ErrorCode::REQUEST_FREQUENCY->throw();
        }
        if ($result !== 1) {
            ErrorCode::SYSTEM_BUSY->throw();
        }
        try {
            return $operation();
        } finally {
            try {
                $this->eval(self::RELEASE, $lockKeys, [$lease]);
            } catch (Throwable) {
                // Lease expiration recovers crashed workers; a failed release must not replay a paid call.
                try {
                    ($this->logger ?? Log::channel('default'))->warning('Game usage lease release failed');
                } catch (Throwable) {
                }
            }
        }
    }

    /**
     * @param list<string> $keys
     * @param list<string> $arguments
     */
    private function eval(string $script, array $keys, array $arguments): int
    {
        return $this->evaluate !== null
            ? (int) ($this->evaluate)($script, $keys, $arguments)
            : (int) Redis::eval($script, count($keys), ...[...$keys, ...$arguments]);
    }
}
