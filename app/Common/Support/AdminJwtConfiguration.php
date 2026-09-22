<?php

declare(strict_types=1);

namespace App\Common\Support;

use RuntimeException;

final class AdminJwtConfiguration
{
    // Fingerprints only: the signing keys previously committed must never be reused.
    private const COMPROMISED = [
        '77cc52d60ea19d2151a9508a17edf91e645182489e2a6ddbffe7e202ac3f2e4b',
        '47ca1a5583bc1f011feebc7a9ecf991485991920a94fb13d3f827c2923be783b',
    ];

    public static function validate(string $accessSecret, string $refreshSecret): void
    {
        foreach (['ADMIN_JWT_ACCESS_SECRET' => $accessSecret, 'ADMIN_JWT_REFRESH_SECRET' => $refreshSecret] as $name => $secret) {
            if (in_array(hash('sha256', $secret), self::COMPROMISED, true)) {
                throw new RuntimeException($name . ' must be rotated; the previous key was exposed');
            }
            if (!preg_match('/\A[0-9a-f]{64}\z/i', $secret)) {
                throw new RuntimeException($name . ' must contain 32 random bytes encoded as 64 hexadecimal characters');
            }
        }
        if (hash_equals($accessSecret, $refreshSecret)) {
            throw new RuntimeException('Administrator access and refresh signing secrets must be different');
        }
    }
}
