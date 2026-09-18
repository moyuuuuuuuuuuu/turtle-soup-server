<?php

declare(strict_types=1);

namespace Tests\Question;

use App\Question\Formats\TagFormat;
use App\Question\Models\Tag;
use Illuminate\Database\Eloquent\Collection;
use PHPUnit\Framework\TestCase;

final class PublicTagContractTest extends TestCase
{
    public function testPublicFormatExposesOnlyIdAndName(): void
    {
        $tag = new Tag();
        $tag->setAttribute('id', 13);
        $tag->setAttribute('name', '超自然');
        $tag->setAttribute('slug', 'supernatural');

        $items = TagFormat::publicList(new Collection([$tag]));

        self::assertSame([['id' => 13, 'name' => '超自然']], $items);
        self::assertArrayNotHasKey('slug', $items[0]);
    }

    public function testPublicTagsRouteIsRegistered(): void
    {
        $routes = file_get_contents(dirname(__DIR__, 2) . '/config/route.php');
        $business = file_get_contents(dirname(__DIR__, 2) . '/app/Question/Business/PublicTagBusiness.php');
        $controller = file_get_contents(dirname(__DIR__, 2) . '/app/Question/Controllers/PublicTagController.php');

        self::assertIsString($routes);
        self::assertIsString($business);
        self::assertIsString($controller);
        self::assertStringContainsString("'/api/v1/tags'", $routes);
        self::assertStringContainsString('PublicTagController', $routes);
        self::assertStringContainsString("where('status', 'published')", $business);
        self::assertStringContainsString("whereIn('risk_level', ['safe', 'caution'])", $business);
        self::assertStringContainsString('PublicTagBusiness', $controller);
    }
}
