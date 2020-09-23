<?php
declare(strict_types=1);

use Phinx\Db\Adapter\MysqlAdapter;
use Phinx\Migration\AbstractMigration;

final class CreateCrawlsTable extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('crawls', ['id' => false, 'primary_key' => 'id']);
        $table
            ->addColumn('id', 'string', ['limit' => 64])
            ->addColumn('project_id', 'integer', ['signed' => false])
            ->addColumn('url', 'string', ['limit' => 10000])
            ->addColumn('msg', 'text', ['limit' => MysqlAdapter::TEXT_REGULAR, 'null' => true])
            ->addColumn('words', 'integer', ['signed' => false, 'default' => 0])
            ->addColumn('status', 'tinyinteger', ['signed' => false, 'default' => 0])
            ->addColumn('created_at', 'timestamp', ['null' => true, 'precision' => 3])
            ->addColumn('updated_at', 'timestamp', ['null' => true, 'precision' => 3])
            ->addIndex('url', ['limit' => 1000])
            ->addForeignKey('project_id', 'projects', ['id'])
            ->create();
        $this->execute('ALTER TABLE `crawls` MODIFY COLUMN `created_at` TIMESTAMP(3)
            NULL DEFAULT CURRENT_TIMESTAMP(3)');
        $this->execute('ALTER TABLE `crawls` MODIFY COLUMN `updated_at` TIMESTAMP(3)
            NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP(3)');
    }
}
