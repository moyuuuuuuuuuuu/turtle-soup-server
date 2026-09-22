<?php

declare(strict_types=1);

namespace App\Admin\Services;

use App\Admin\Contracts\RevokedSessionStore;
use App\Common\Support\AdminJwtConfiguration;
use plugin\saiadmin\exception\ApiException;
use Tinywan\Jwt\JwtToken;

final class AdminSessionService
{
    public function __construct(private readonly RevokedSessionStore $revoked = new RedisRevokedSessionStore())
    {
    }

    /** @param array<string, mixed> $identity
     *  @return array<string, mixed>
     */
    public function issue(array $identity): array
    {
        $this->validateConfiguration();
        $identity['sid'] = bin2hex(random_bytes(32));
        return JwtToken::generateToken($identity);
    }

    /** @return array<string, mixed> */
    public function authenticate(?string $token = null): array
    {
        $this->validateConfiguration();
        $payload = JwtToken::verify(1, $token);
        $identity = (array) ($payload['extend'] ?? []);
        $sid = $identity['sid'] ?? null;
        if (($identity['plat'] ?? '') !== 'saiadmin'
            || !is_int($identity['id'] ?? null) || $identity['id'] < 1
            || !is_string($sid) || !preg_match('/\A[0-9a-f]{64}\z/', $sid)
            || $this->revoked->contains($sid)) {
            throw new ApiException('您的登录凭证错误或者已过期，请重新登录', 401);
        }
        return $payload;
    }

    public function logout(?string $token = null): void
    {
        $payload = $this->authenticate($token);
        // Covers both tokens in this login session, including clock skew.
        $ttl = max(
            (int) $payload['exp'] - time(),
            (int) config('plugin.tinywan.jwt.app.jwt.refresh_exp', 604800),
        ) + (int) config('plugin.tinywan.jwt.app.jwt.leeway', 60);
        $this->revoked->revoke($payload['extend']['sid'], max(1, $ttl));
    }

    private function validateConfiguration(): void
    {
        AdminJwtConfiguration::validate(
            (string) config('plugin.tinywan.jwt.app.jwt.access_secret_key', ''),
            (string) config('plugin.tinywan.jwt.app.jwt.refresh_secret_key', ''),
        );
    }
}
