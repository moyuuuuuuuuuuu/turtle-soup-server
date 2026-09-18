<?php

declare(strict_types=1);

namespace App\FriendLink\Controllers;

use App\Common\Controllers\BaseController;
use App\FriendLink\Business\FriendLinkBusiness;
use support\Request;
use support\Response;

final class PublicFriendLinkController extends BaseController
{
    public function index(Request $request): Response
    {
        return $this->success(
            (new FriendLinkBusiness())->publicList(),
            (string) $request->header('X-Request-Id', '')
        );
    }
}
