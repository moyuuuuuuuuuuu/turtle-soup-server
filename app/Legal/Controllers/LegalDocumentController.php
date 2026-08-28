<?php

declare(strict_types=1);

namespace App\Legal\Controllers;

use App\Common\Controllers\BaseController;
use App\Legal\Business\LegalDocumentBusiness;
use App\Legal\Formats\LegalDocumentFormat;
use support\Request;
use support\Response;

final class LegalDocumentController extends BaseController
{
    public function index(Request $request): Response
    {
        $documents = (new LegalDocumentBusiness())->documents();

        return $this->success(
            LegalDocumentFormat::publicDocuments($documents),
            (string) $request->header('X-Request-Id', '')
        );
    }
}
