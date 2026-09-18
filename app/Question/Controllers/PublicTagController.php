<?php

declare(strict_types=1);

namespace App\Question\Controllers;

use App\Common\Controllers\BaseController;
use App\Question\Business\PublicTagBusiness;
use support\Request;
use support\Response;

final class PublicTagController extends BaseController
{
    public function index(Request $request): Response
    {
        return $this->success(
            (new PublicTagBusiness())->list(),
            (string) $request->header('X-Request-Id', ''),
        );
    }
}
