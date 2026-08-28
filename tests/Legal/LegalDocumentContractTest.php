<?php

declare(strict_types=1);

namespace Tests\Legal;

use App\Legal\Formats\LegalDocumentFormat;
use PHPUnit\Framework\TestCase;

final class LegalDocumentContractTest extends TestCase
{
    public function testPublicContractContainsOnlySupportedDocuments(): void
    {
        self::assertSame([
            'service_terms' => '<p>服务条款</p>',
            'privacy_policy' => '<p>隐私政策</p>',
        ], LegalDocumentFormat::publicDocuments([
            'service_terms' => '<p>服务条款</p>',
            'privacy_policy' => '<p>隐私政策</p>',
        ]));
    }

    public function testPublicRouteAndManagedConfigSourceRemainConnected(): void
    {
        $routes = file_get_contents(dirname(__DIR__, 2) . '/config/route.php');
        $repository = file_get_contents(dirname(__DIR__, 2) . '/app/Legal/Repositories/LegalDocumentRepository.php');

        self::assertIsString($routes);
        self::assertIsString($repository);
        self::assertStringContainsString("'/api/v1/legal/documents'", $routes);
        self::assertStringContainsString("getConfig('legal_config')", $repository);
    }
}
