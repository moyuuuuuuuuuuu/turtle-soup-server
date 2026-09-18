<?php

declare(strict_types=1);

namespace App\FriendLink\Controllers;

use App\FriendLink\Business\FriendLinkBusiness;
use plugin\saiadmin\basic\BaseController;
use plugin\saiadmin\service\Permission;
use support\Request;
use support\Response;

final class FriendLinkAdminController extends BaseController
{
    #[Permission('友链列表', 'friend_link:index')]
    public function index(Request $request): Response
    {
        return $this->success((new FriendLinkBusiness())->page(
            $request->only(['keyword', 'status']),
            max(1, (int) $request->get('page', 1)),
            min(100, max(1, (int) $request->get('pageSize', 20))),
        ));
    }

    #[Permission('新增友链', 'friend_link:create')]
    public function save(Request $request): Response
    {
        return $this->success((new FriendLinkBusiness())->save($request->post()));
    }

    #[Permission('编辑友链', 'friend_link:update')]
    public function update(Request $request): Response
    {
        return $this->success((new FriendLinkBusiness())->update((int) $request->post('id'), $request->post()));
    }

    #[Permission('删除友链', 'friend_link:delete')]
    public function destroy(Request $request): Response
    {
        (new FriendLinkBusiness())->destroy((array) $request->post('ids', []));

        return $this->success('删除成功');
    }
}
