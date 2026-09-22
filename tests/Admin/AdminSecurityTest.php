<?php

declare(strict_types=1);

namespace Tests\Admin;

use App\Admin\Contracts\RevokedSessionStore;
use App\Admin\Middleware\AdminTerminalMiddleware;
use App\Admin\Services\AdminSessionService;
use App\Common\Support\AdminJwtConfiguration;
use App\Common\Support\AdminLogRedactor;
use PHPUnit\Framework\TestCase;
use plugin\saiadmin\app\event\SystemUser;
use plugin\saiadmin\app\exception\Handler;
use plugin\saiadmin\exception\ApiException;
use ReflectionClass;
use RuntimeException;
use Tinywan\Jwt\JwtToken;
use Webman\Config;
use Webman\Http\Request;
use Webman\Http\Response;

final class AdminSecurityTest extends TestCase
{
    /** @var array<string, mixed> */
    private array $originalConfig = [];
    private AdminSessionService $sessions;
    private RevokedSessionStore $store;

    protected function setUp(): void
    {
        $reflection = new ReflectionClass(Config::class);
        foreach (['config', 'flatCache', 'loaded'] as $property) {
            $this->originalConfig[$property] = $reflection->getStaticPropertyValue($property);
        }
        $settings = require dirname(__DIR__, 2) . '/config/plugin/tinywan/jwt/app.php';
        $settings['jwt']['access_secret_key'] = bin2hex(random_bytes(32));
        $settings['jwt']['refresh_secret_key'] = bin2hex(random_bytes(32));
        $reflection->setStaticPropertyValue('config', ['plugin' => ['tinywan' => ['jwt' => ['app' => $settings]]]]);
        $reflection->setStaticPropertyValue('flatCache', []);
        $reflection->setStaticPropertyValue('loaded', true);
        $this->store = new class () implements RevokedSessionStore {
            /** @var array<string, int> */
            public array $revoked = [];
            public function contains(string $sessionId): bool
            {
                return isset($this->revoked[$sessionId]);
            }
            public function revoke(string $sessionId, int $ttl): void
            {
                if ($ttl < 604800) {
                    throw new RuntimeException('Revocation must cover the refresh lifetime');
                }
                $this->revoked[$sessionId] = $ttl;
            }
        };
        $this->sessions = new AdminSessionService($this->store);
    }

    protected function tearDown(): void
    {
        $reflection = new ReflectionClass(Config::class);
        foreach ($this->originalConfig as $property => $value) {
            $reflection->setStaticPropertyValue($property, $value);
        }
    }

    /** @return array<string, mixed> */
    private function login(int $id = 1): array
    {
        return $this->sessions->issue(['id' => $id, 'username' => 'test-admin', 'plat' => 'saiadmin']);
    }

    public function testLogoutRejectsCopiedTokenWithoutRevokingAnotherLogin(): void
    {
        $first = $this->login();
        $second = $this->login();
        self::assertSame(1, $this->sessions->authenticate($first['access_token'])['extend']['id']);
        $this->sessions->logout($first['access_token']);
        self::assertSame(1, $this->sessions->authenticate($second['access_token'])['extend']['id']);
        $this->expectException(ApiException::class);
        $this->sessions->authenticate($first['access_token']);
    }

    public function testTokensWithoutSessionIdAndOtherPlatformsAreRejected(): void
    {
        $token = JwtToken::generateToken(['id' => 1, 'plat' => 'saiadmin']);
        $this->expectException(ApiException::class);
        $this->sessions->authenticate($token['access_token']);
    }

    public function testOtherPlatformIsRejected(): void
    {
        $token = $this->sessions->issue(['id' => 1, 'plat' => 'player']);
        $this->expectException(ApiException::class);
        $this->sessions->authenticate($token['access_token']);
    }

    public function testRevocationFailureIsNotReportedAsSuccessfulLogout(): void
    {
        $token = $this->login();
        $service = new AdminSessionService(new class () implements RevokedSessionStore {
            public function contains(string $sessionId): bool
            {
                return false;
            }
            public function revoke(string $sessionId, int $ttl): void
            {
                throw new RuntimeException('Redis unavailable');
            }
        });
        $this->expectException(RuntimeException::class);
        $service->logout($token['access_token']);
    }

    public function testTerminalRequiresPostHeaderAndUnrevokedSuperAdminSession(): void
    {
        $middleware = new AdminTerminalMiddleware($this->sessions);
        $handler = static fn () => new Response(204);
        $admin = $this->login();
        $ordinary = $this->login(2);
        $token = $admin['access_token'];
        $get = new Request("GET /app/saipackage/index/terminal?token=$token HTTP/1.1\r\nHost: test\r\n\r\n");
        self::assertSame(405, $middleware->process($get, $handler)->getStatusCode());
        $noHeader = new Request("POST /app/saipackage/index/terminal?token=$token HTTP/1.1\r\nHost: test\r\n\r\n");
        self::assertSame(401, $middleware->process($noHeader, $handler)->getStatusCode());
        $request = static fn (string $jwt) => new Request("POST /app/saipackage/index/terminal HTTP/1.1\r\nHost: test\r\nAuthorization: Bearer $jwt\r\n\r\n");
        self::assertSame(403, $middleware->process($request($ordinary['access_token']), $handler)->getStatusCode());
        self::assertSame(204, $middleware->process($request($token), $handler)->getStatusCode());
        $this->sessions->logout($token);
        self::assertSame(401, $middleware->process($request($token), $handler)->getStatusCode());
    }

    public function testActualOperationLogFilterRemovesConfirmationPasswordsAndNestedCredentials(): void
    {
        $event = new class () extends SystemUser {
            /** @param array<string, mixed> $values */
            public function filter(array $values): string
            {
                return $this->filterParams($values);
            }
        };
        $values = ['password' => 'test-only-password', 'password_confirm' => 'test-only-password',
            'nested' => [['access_token' => 'test-token', 'oldPassword' => 'old-secret', 'answer' => 'hidden-answer']],
            'name' => 'safe-label'];
        $json = $event->filter($values);
        foreach (['test-only-password', 'test-token', 'old-secret', 'hidden-answer'] as $secret) {
            self::assertStringNotContainsString($secret, $json);
        }
        self::assertSame('safe-label', json_decode($json, true)['name']);
        self::assertSame('[REDACTED]', AdminLogRedactor::redact(['Authorization' => 'Bearer value'])['Authorization']);
    }

    public function testMissingSigningSecretFailsClosed(): void
    {
        $this->expectException(RuntimeException::class);
        AdminJwtConfiguration::validate('', bin2hex(random_bytes(32)));
    }

    public function testExistingLogRowsAreRedactedWithoutDatabaseMutation(): void
    {
        $log = new \plugin\saiadmin\app\model\system\SystemOperLog();
        $log->setRawAttributes([
            'request_data' => '{"password_confirm":"legacy-secret","nested":{"token":"legacy-token"},"id":9}',
            'router' => '/core/user/save?token=legacy-token',
        ]);
        self::assertStringNotContainsString('legacy-secret', $log->request_data);
        self::assertStringNotContainsString('legacy-token', $log->request_data);
        self::assertSame(9, json_decode($log->request_data, true)['id']);
        self::assertSame('/core/user/save', $log->router);
        self::assertStringContainsString('legacy-secret', $log->getAttributes()['request_data']);
    }

    public function testReusedSigningSecretsAreRejected(): void
    {
        $key = bin2hex(random_bytes(32));
        $this->expectException(RuntimeException::class);
        AdminJwtConfiguration::validate($key, $key);
    }

    public function testIndependentSigningSecretsAreAccepted(): void
    {
        AdminJwtConfiguration::validate(bin2hex(random_bytes(32)), bin2hex(random_bytes(32)));
        $this->addToAssertionCount(1);
    }

    public function testRotationScriptPreservesOtherSettingsAndDoesNotPrintKeys(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'admin-key-test-');
        self::assertIsString($path);
        try {
            file_put_contents($path, "UNCHANGED=test-value\nADMIN_JWT_ACCESS_SECRET=old-test-key\n");
            $process = proc_open(
                [PHP_BINARY, dirname(__DIR__, 2) . '/bin/rotate-admin-jwt-keys.php', $path],
                [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
                $pipes,
            );
            self::assertIsResource($process);
            fclose($pipes[0]);
            $output = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            self::assertSame(0, proc_close($process), $output);
            $values = parse_ini_file($path, false, INI_SCANNER_RAW);
            self::assertSame('test-value', $values['UNCHANGED']);
            AdminJwtConfiguration::validate($values['ADMIN_JWT_ACCESS_SECRET'], $values['ADMIN_JWT_REFRESH_SECRET']);
            self::assertStringNotContainsString($values['ADMIN_JWT_ACCESS_SECRET'], $output);
            self::assertStringNotContainsString($values['ADMIN_JWT_REFRESH_SECRET'], $output);
        } finally {
            unlink($path);
        }
    }

    public function testDebugExceptionResponseDoesNotExposePasswordsOrQueryTokens(): void
    {
        $request = new Request("POST /core/user/save?token=query-test-secret HTTP/1.1\r\nHost: test\r\nContent-Type: application/json\r\n\r\n{\"password_confirm\":\"body-test-secret\"}");
        $handler = (new ReflectionClass(Handler::class))->newInstanceWithoutConstructor();
        $response = $handler->render($request, new RuntimeException('body-test-secret'));
        self::assertStringNotContainsString('body-test-secret', $response->rawBody());
        self::assertStringNotContainsString('query-test-secret', $response->rawBody());
    }
}
