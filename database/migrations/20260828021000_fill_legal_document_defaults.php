<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class FillLegalDocumentDefaults extends AbstractMigration
{
    /** @var array<string, string> */
    private array $documents;

    public function init(): void
    {
        /** @var array<string, string> $documents */
        $documents = require dirname(__DIR__) . '/data/legal_documents.php';
        $this->documents = $documents;
    }

    public function up(): void
    {
        $group = $this->fetchRow("SELECT id FROM sa_system_config_group WHERE code = 'legal_config' LIMIT 1");
        if ($group === false) {
            throw new RuntimeException('Legal document config group does not exist.');
        }

        foreach ($this->configs((int) $group['id']) as $config) {
            $key = (string) ($config['key'] ?? '');
            $value = (string) ($config['value'] ?? '');
            if ($value !== '' || !isset($this->documents[$key])) {
                continue;
            }

            $this->getQueryBuilder('update')
                ->update('sa_system_config')
                ->set(['value' => $this->documents[$key], 'update_time' => date('Y-m-d H:i:s')])
                ->where(['id' => (int) $config['id']])
                ->execute();
        }
    }

    public function down(): void
    {
        $group = $this->fetchRow("SELECT id FROM sa_system_config_group WHERE code = 'legal_config' LIMIT 1");
        if ($group === false) {
            return;
        }

        foreach ($this->configs((int) $group['id']) as $config) {
            $key = (string) ($config['key'] ?? '');
            if (!isset($this->documents[$key]) || ($config['value'] ?? null) !== $this->documents[$key]) {
                continue;
            }

            $this->getQueryBuilder('update')
                ->update('sa_system_config')
                ->set(['value' => '', 'update_time' => date('Y-m-d H:i:s')])
                ->where(['id' => (int) $config['id']])
                ->execute();
        }
    }

    /** @return array<int, array<string, mixed>> */
    private function configs(int $groupId): array
    {
        return $this->fetchAll(sprintf(
            'SELECT * FROM sa_system_config WHERE group_id = %d',
            $groupId,
        ));
    }
}
