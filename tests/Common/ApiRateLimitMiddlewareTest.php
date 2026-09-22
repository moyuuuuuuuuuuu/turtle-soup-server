<?php

declare(strict_types=1);

namespace Tests\Common;

use App\Common\Exceptions\BusinessException;
use App\Common\Middleware\ApiRateLimitMiddleware;
use PHPUnit\Framework\TestCase;
use Webman\Http\Request;
use Webman\Http\Response;

final class ApiRateLimitMiddlewareTest extends TestCase
{
    public function testArbitraryCredentialsCannotResetAnonymousIssuanceBucket(): void
    {
        $keys = [];
        $middleware = new ApiRateLimitMiddleware(static function (string $key) use (&$keys): int {
            $keys[] = $key;
            return 1;
        }, [['methods' => ['POST'], 'paths' => ['/api/v1/anonymous/session'], 'limit' => 5, 'window' => 60]]);
        foreach (['first', 'second'] as $credential) {
            $request = new Request("POST /api/v1/anonymous/session HTTP/1.1\r\nHost: hgt.test\r\nAuthorization: Bearer {$credential}\r\nX-Anonymous-Token: {$credential}\r\nX-Forwarded-For: {$credential}\r\n\r\n");
            $middleware->process($request, static fn () => new Response(200));
        }
        self::assertSame($keys[0], $keys[1]);
    }

    public function testEveryMatchingWindowIsEnforced(): void
    {
        $windows = [];
        $middleware = new ApiRateLimitMiddleware(static function (string $key, int $window) use (&$windows): int {
            $windows[] = $window;
            return $window === 86400 ? 51 : 1;
        }, [
            ['methods' => ['POST'], 'paths' => ['/api/v1/anonymous/session'], 'limit' => 5, 'window' => 60],
            ['methods' => ['POST'], 'paths' => ['/api/v1/anonymous/session'], 'limit' => 50, 'window' => 86400],
        ]);
        try {
            $middleware->process(new Request("POST /api/v1/anonymous/session HTTP/1.1\r\n\r\n"), static fn () => new Response(200));
            self::fail('Expected daily limit');
        } catch (BusinessException) {
            self::assertSame([60, 86400], $windows);
        }
    }

    public function testRedisFailureDoesNotPassRequestToHandler(): void
    {
        $middleware = new ApiRateLimitMiddleware(static fn (): int => throw new \RuntimeException('offline'), [
            ['methods' => ['POST'], 'paths' => ['/api/v1/anonymous/session'], 'limit' => 5, 'window' => 60],
        ]);
        $this->expectException(BusinessException::class);
        $middleware->process(new Request("POST /api/v1/anonymous/session HTTP/1.1\r\n\r\n"), static fn () => self::fail('Must not issue a session'));
    }

    public function testRejectsRequestAboveConfiguredLimit(): void
    {
        $request = new Request("POST /api/v1/auth/login/password HTTP/1.1\r\nHost: hgt.test\r\n\r\n");
        $middleware = new ApiRateLimitMiddleware(static fn (): int => 11, [[
            'methods' => ['POST'],
            'paths' => ['/api/v1/auth/login/password'],
            'limit' => 10,
            'window' => 60,
        ]]);

        $this->expectException(BusinessException::class);
        $middleware->process($request, static fn () => new Response(200));
    }

    public function testDoesNotLimitUnmatchedReadEndpoint(): void
    {
        $request = new Request("GET /api/v1/questions HTTP/1.1\r\nHost: hgt.test\r\n\r\n");
        $middleware = new ApiRateLimitMiddleware(static fn (): int => 999);

        self::assertSame(200, $middleware->process($request, static fn () => new Response(200))->getStatusCode());
    }
}
