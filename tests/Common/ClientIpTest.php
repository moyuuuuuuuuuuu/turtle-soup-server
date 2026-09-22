<?php

declare(strict_types=1);

namespace Tests\Common;

use App\Common\Support\ClientIp;
use PHPUnit\Framework\TestCase;

final class ClientIpTest extends TestCase
{
    public function testUntrustedClientCannotChooseItsAddress(): void
    {
        self::assertSame('198.51.100.4', ClientIp::resolve('198.51.100.4', '203.0.113.8', []));
    }

    public function testTrustedChainStopsAtFirstUntrustedHop(): void
    {
        self::assertSame('198.51.100.4', ClientIp::resolve('10.0.0.2', '203.0.113.8, 198.51.100.4, 10.0.0.3', ['10.0.0.0/24']));
        self::assertSame('198.51.100.4', ClientIp::resolve('10.0.0.2', 'garbage, 198.51.100.4', ['10.0.0.2']));
    }

    public function testInvalidHeaderDoesNotBecomeAnIdentity(): void
    {
        self::assertSame('10.0.0.2', ClientIp::resolve('10.0.0.2', 'garbage', ['10.0.0.2']));
        self::assertSame('198.51.100.4', ClientIp::resolve('::ffff:198.51.100.4'));
    }

    public function testIpv6CidrAndExactNetworkBoundaries(): void
    {
        self::assertSame('2001:db8:2::4', ClientIp::resolve('2001:db8:1::2', '2001:db8:2::4', ['2001:db8:1::/64']));
        self::assertSame('10.0.1.2', ClientIp::resolve('10.0.1.2', '198.51.100.4', ['10.0.0.0/24', 'invalid/8']));
    }
}
