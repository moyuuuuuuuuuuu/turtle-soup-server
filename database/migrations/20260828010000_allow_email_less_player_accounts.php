<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AllowEmailLessPlayerAccounts extends AbstractMigration
{
    public function change(): void
    {
        $this->table('turtle_users')
            ->changeColumn('email', 'string', ['limit' => 254, 'null' => true])
            ->changeColumn('email_normalized', 'string', ['limit' => 254, 'null' => true])
            ->changeColumn('email_verified_at', 'datetime', ['null' => true])
            ->update();
    }
}
