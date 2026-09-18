<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateFriendLinks extends AbstractMigration
{
    public function up(): void
    {
        $this->table('turtle_friend_links', ['id' => false, 'primary_key' => ['id']])
            ->addColumn('id', 'biginteger', ['identity' => true, 'signed' => false])
            ->addColumn('public_id', 'string', ['limit' => 26])
            ->addColumn('name', 'string', ['limit' => 80])
            ->addColumn('url', 'string', ['limit' => 500])
            ->addColumn('logo_url', 'string', ['limit' => 500, 'null' => true])
            ->addColumn('description', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('reciprocal_url', 'string', ['limit' => 500, 'null' => true])
            ->addColumn('contact_email', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('status', 'boolean', ['default' => true])
            ->addColumn('sort', 'integer', ['default' => 0])
            ->addColumn('created_by', 'biginteger', ['signed' => false, 'null' => true])
            ->addColumn('updated_by', 'biginteger', ['signed' => false, 'null' => true])
            ->addColumn('create_time', 'datetime')
            ->addColumn('update_time', 'datetime')
            ->addIndex(['public_id'], ['unique' => true])
            ->addIndex(['status', 'sort'])
            ->create();

        $this->insertMenus();
    }

    public function down(): void
    {
        $this->getQueryBuilder('delete')->delete('sa_system_role_menu')->whereInList('menu_id', range(900220, 900224))->execute();
        $this->getQueryBuilder('delete')->delete('sa_system_menu')->whereInList('id', range(900220, 900224))->execute();
        $this->table('turtle_friend_links')->drop()->save();
    }

    private function insertMenus(): void
    {
        $now = date('Y-m-d H:i:s');
        $base = ['code' => '', 'icon' => '', 'status' => 1, 'is_iframe' => 2, 'is_keep_alive' => 1, 'is_hidden' => 2, 'is_fixed_tab' => 2, 'is_full_page' => 2, 'create_time' => $now, 'update_time' => $now];
        $rows = [
            ['id' => 900220, 'parent_id' => 900200, 'name' => '友链管理', 'code' => 'FriendLinkIndex', 'slug' => 'friend_link:index', 'type' => 2, 'path' => 'friend-links', 'component' => 'friend-link/index', 'method' => 'GET', 'sort' => 3],
            ['id' => 900221, 'parent_id' => 900220, 'name' => '友链列表', 'slug' => 'friend_link:index', 'type' => 3, 'path' => '/core/friend-link/index', 'component' => '', 'method' => 'GET', 'sort' => 1],
            ['id' => 900222, 'parent_id' => 900220, 'name' => '新增友链', 'slug' => 'friend_link:create', 'type' => 3, 'path' => '/core/friend-link/save', 'component' => '', 'method' => 'POST', 'sort' => 2],
            ['id' => 900223, 'parent_id' => 900220, 'name' => '编辑友链', 'slug' => 'friend_link:update', 'type' => 3, 'path' => '/core/friend-link/update', 'component' => '', 'method' => 'PUT', 'sort' => 3],
            ['id' => 900224, 'parent_id' => 900220, 'name' => '删除友链', 'slug' => 'friend_link:delete', 'type' => 3, 'path' => '/core/friend-link/destroy', 'component' => '', 'method' => 'DELETE', 'sort' => 4],
        ];
        $this->table('sa_system_menu')->insert(array_map(static fn (array $row): array => array_merge($base, $row), $rows))->saveData();
    }
}
