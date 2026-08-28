<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddLegalDocumentConfig extends AbstractMigration
{
    private const GROUP_CODE = 'legal_config';

    private const CONFIG_KEYS = ['service_terms', 'privacy_policy'];

    public function up(): void
    {
        $group = $this->findGroup();
        if ($group === false) {
            $this->table('sa_system_config_group')->insert([
                'name' => '法律协议',
                'code' => self::GROUP_CODE,
                'remark' => '用户端展示的服务条款和隐私政策',
                'created_by' => 1,
                'updated_by' => 1,
                'create_time' => date('Y-m-d H:i:s'),
                'update_time' => date('Y-m-d H:i:s'),
            ])->saveData();
            $group = $this->findGroup();
        }

        if ($group === false) {
            throw new RuntimeException('Failed to create legal document config group.');
        }

        $groupId = (int) $group['id'];
        $defaults = [
            [
                'key' => 'service_terms',
                'name' => '服务条款',
                'sort' => 100,
                'remark' => '用户注册、登录或使用服务前展示的服务条款',
            ],
            [
                'key' => 'privacy_policy',
                'name' => '隐私政策',
                'sort' => 99,
                'remark' => '说明个人信息的收集、使用、存储与保护方式',
            ],
        ];

        foreach ($defaults as $config) {
            if ($this->findConfig($groupId, $config['key']) !== false) {
                continue;
            }

            $this->table('sa_system_config')->insert(array_merge($config, [
                'group_id' => $groupId,
                'value' => '',
                'input_type' => 'wangEditor',
                'config_select_data' => null,
                'created_by' => 1,
                'updated_by' => 1,
                'create_time' => date('Y-m-d H:i:s'),
                'update_time' => date('Y-m-d H:i:s'),
            ]))->saveData();
        }
    }

    public function down(): void
    {
        $group = $this->findGroup();
        if ($group === false) {
            return;
        }

        $groupId = (int) $group['id'];
        $this->getQueryBuilder('delete')
            ->delete('sa_system_config')
            ->where(['group_id' => $groupId])
            ->whereInList('key', self::CONFIG_KEYS)
            ->execute();

        $this->getQueryBuilder('delete')
            ->delete('sa_system_config_group')
            ->where(['id' => $groupId, 'code' => self::GROUP_CODE])
            ->execute();
    }

    /** @return array<string, mixed>|false */
    private function findGroup(): array|false
    {
        return $this->fetchRow(
            "SELECT id FROM sa_system_config_group WHERE code = '" . self::GROUP_CODE . "' LIMIT 1"
        );
    }

    /** @return array<string, mixed>|false */
    private function findConfig(int $groupId, string $key): array|false
    {
        foreach ($this->fetchAll(sprintf(
            'SELECT * FROM sa_system_config WHERE group_id = %d',
            $groupId,
        )) as $config) {
            if (($config['key'] ?? null) === $key) {
                return $config;
            }
        }

        return false;
    }
}
