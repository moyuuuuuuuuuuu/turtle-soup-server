<?php

declare(strict_types=1);

namespace Tests\FriendLink;

use App\FriendLink\Formats\FriendLinkFormat;
use App\FriendLink\Models\FriendLink;
use Illuminate\Database\Eloquent\Collection;
use PHPUnit\Framework\TestCase;

final class FriendLinkContractTest extends TestCase
{
    public function testPublicFormatExposesOnlySafeFields(): void
    {
        $link = new FriendLink([
            'id' => 1,
            'public_id' => '00000000000000000000000001',
            'name' => '示例站点',
            'url' => 'https://example.com',
            'logo_url' => 'https://example.com/logo.png',
            'description' => '伙伴站点',
            'reciprocal_url' => 'https://example.com/backlink',
            'contact_email' => 'owner@example.com',
            'status' => true,
            'sort' => 10,
        ]);

        $items = FriendLinkFormat::publicList(new Collection([$link]));

        self::assertCount(1, $items);
        self::assertSame([
            'id' => '00000000000000000000000001',
            'name' => '示例站点',
            'url' => 'https://example.com',
            'logo_url' => 'https://example.com/logo.png',
            'description' => '伙伴站点',
        ], $items[0]);
        self::assertArrayNotHasKey('sort', $items[0]);
        self::assertArrayNotHasKey('status', $items[0]);
        self::assertArrayNotHasKey('reciprocal_url', $items[0]);
        self::assertArrayNotHasKey('contact_email', $items[0]);
    }

    public function testAdminOnlyManualCreateContract(): void
    {
        $publicRoutes = file_get_contents(dirname(__DIR__, 2) . '/config/route.php');
        $adminRoutes = file_get_contents(dirname(__DIR__, 2) . '/plugin/saiadmin/config/route.php');
        $business = file_get_contents(dirname(__DIR__, 2) . '/app/FriendLink/Business/FriendLinkBusiness.php');
        $repository = file_get_contents(dirname(__DIR__, 2) . '/app/FriendLink/Repositories/FriendLinkRepository.php');
        $errorCodes = file_get_contents(dirname(__DIR__, 2) . '/app/Common/Enums/ErrorCode.php');
        $migration = file_get_contents(dirname(__DIR__, 2) . '/database/migrations/20260828030000_create_friend_links.php');

        self::assertIsString($publicRoutes);
        self::assertIsString($adminRoutes);
        self::assertIsString($business);
        self::assertIsString($repository);
        self::assertIsString($errorCodes);
        self::assertIsString($migration);
        self::assertStringContainsString("'/api/v1/friend-links'", $publicRoutes);
        self::assertStringContainsString('FriendLinkAdminController', $adminRoutes);
        self::assertStringContainsString("'/friend-link/index'", $adminRoutes);
        self::assertStringContainsString('friend-link/save', $adminRoutes);
        self::assertStringContainsString('FRIEND_LINK_NOT_FOUND', $errorCodes);
        self::assertStringContainsString('turtle_friend_links', $migration);
        self::assertStringContainsString("where('status', true)", $repository);
        self::assertStringContainsString('PublicId::make()', $business);
        self::assertStringContainsString("addColumn('reciprocal_url'", $migration);
        self::assertStringContainsString("addColumn('contact_email'", $migration);
        self::assertStringContainsString('reciprocal_url', $business);
        self::assertStringContainsString('contact_email', $business);
        self::assertStringNotContainsString('friend-links/apply', $publicRoutes);
        self::assertStringNotContainsString('friend-links/save', $publicRoutes);
    }
}
