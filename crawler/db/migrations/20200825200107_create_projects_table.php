<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateProjectsTable extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('projects', ['signed' => false]);
        $table
            ->addColumn('name', 'string', ['limit' => 1000])
            ->addColumn('url', 'string', ['limit' => 1000])
            ->addColumn('created_at', 'timestamp', ['null' => true, 'precision' => 3])
            ->create();
        $this->execute('ALTER TABLE `projects` MODIFY COLUMN `created_at` TIMESTAMP(3)
            NULL DEFAULT CURRENT_TIMESTAMP(3)');
    }
}
