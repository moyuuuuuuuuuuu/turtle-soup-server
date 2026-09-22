<?php

declare(strict_types=1);

namespace Tests\Game;

use App\Auth\Entities\PlayerContext;
use App\Common\Exceptions\BusinessException;
use App\Game\Services\GameUsageGuard;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use RuntimeException;

final class GameUsageGuardTest extends TestCase
{
    public function testDeniedAdmissionNeverInvokesPaidOperation(): void
    {
        foreach ([0, 2, -1] as $result) {
            $calls = 0;
            $guard = new GameUsageGuard(static fn (): int => $result);
            try {
                $guard->run(new PlayerContext(userId: 1), 'game', static function () use (&$calls): void {
                    ++$calls;
                });
                self::fail('Expected rejection');
            } catch (BusinessException) {
                self::assertSame(0, $calls);
            }
        }
    }

    public function testUnavailableRedisDoesNotInvokePaidOperation(): void
    {
        $guard = new GameUsageGuard(static fn (): int => throw new RuntimeException('offline'));
        $this->expectException(BusinessException::class);
        $guard->run(new PlayerContext(userId: 1), 'game', static fn () => self::fail('Paid operation must not run'));
    }

    public function testRefreshSessionAndTransportDoNotChangeIdentityAndGlobalKeys(): void
    {
        $admissions = [];
        $guard = new GameUsageGuard(static function (string $script, array $keys, array $args) use (&$admissions): int {
            if ($script === GameUsageGuard::ACQUIRE) {
                $admissions[] = [$keys, $args];
            }
            return 1;
        });
        $guard->run(new PlayerContext(userId: 7, refreshSessionId: 1, sourceIp: '198.51.100.4'), 'game-a', static fn () => true);
        $guard->run(new PlayerContext(userId: 7, refreshSessionId: 2, sourceIp: '198.51.100.4'), 'game-b', static fn () => true);
        self::assertSame(array_slice($admissions[0][0], 0, 6), array_slice($admissions[1][0], 0, 6));
        self::assertNotSame($admissions[0][0][6], $admissions[1][0][6]);
        $guard->run(new PlayerContext(anonymousSessionId: 8, sourceIp: '198.51.100.4'), 'game-c', static fn () => true);
        self::assertSame(array_slice($admissions[0][0], 2, 3), array_slice($admissions[2][0], 2, 3));
        self::assertNotSame($admissions[0][0][0], $admissions[2][0][0]);
    }

    public function testReleasesOnlyItsLeaseOnSuccessAndFailureWithoutRefundingBudget(): void
    {
        foreach ([false, true] as $fail) {
            $calls = [];
            $guard = new GameUsageGuard(static function (string $script, array $keys, array $args) use (&$calls): int {
                $calls[] = [$script, $keys, $args];
                return 1;
            });
            try {
                $result = $guard->run(new PlayerContext(userId: 3), 'game', static fn () => $fail ? throw new RuntimeException('upstream failed') : 42);
                self::assertSame(42, $result);
            } catch (RuntimeException $exception) {
                self::assertSame('upstream failed', $exception->getMessage());
            }
            self::assertCount(2, $calls);
            self::assertSame(GameUsageGuard::RELEASE, $calls[1][0]);
            self::assertSame(array_slice($calls[0][1], 5), $calls[1][1]);
            self::assertSame([$calls[0][2][3]], $calls[1][2]);
            self::assertGreaterThanOrEqual(1, (int) $calls[0][2][2]);
            self::assertGreaterThan(120, (int) $calls[0][2][4]);
        }
    }

    public function testReleaseFailureDoesNotReplaceSuccessfulResult(): void
    {
        $guard = new GameUsageGuard(static fn (string $script): int => $script === GameUsageGuard::RELEASE ? throw new RuntimeException('offline') : 1, logger: new NullLogger());
        self::assertSame(42, $guard->run(new PlayerContext(userId: 3), 'game', static fn () => 42));
    }

    public function testInvalidPrincipalNeverReservesGlobalQuota(): void
    {
        $guard = new GameUsageGuard(static fn (): int => self::fail('Must not reserve'));
        $this->expectException(BusinessException::class);
        $guard->run(new PlayerContext(), 'game', static fn () => self::fail('Must not run'));
    }
}
