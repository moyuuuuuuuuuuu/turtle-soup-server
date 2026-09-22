<?php

declare(strict_types=1);

namespace App\Admin\Services;

use App\Admin\Contracts\RevokedSessionStore;
use RuntimeException;
use support\Redis;

final class RedisRevokedSessionStore implements RevokedSessionStore
{
    public function contains(string $sessionId): bool
    {
        return (bool) Redis::exists($this->key($sessionId));
    }

    public function revoke(string $sessionId, int $ttl): void
    {
        if (!Redis::setEx($this->key($sessionId), $ttl, '1')) {
            throw new RuntimeException('Administrator session could not be revoked');
        }
    }

    private function key(string $sessionId): string
    {
        // Outside SaiAdmin's clearable application cache; never store raw JWTs.
        return 'admin:revoked-session:' . hash('sha256', $sessionId);
    }
}
