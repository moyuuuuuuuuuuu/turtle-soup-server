<?php

declare(strict_types=1);

namespace App\Admin\Contracts;

interface RevokedSessionStore
{
    public function contains(string $sessionId): bool;

    public function revoke(string $sessionId, int $ttl): void;
}
