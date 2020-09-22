<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateWordsTable extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('words', ['id' => false, 'primary_key' => ['word', 'project_id']]);
        $table
            ->addColumn('word', 'string', ['limit' => 100])
            ->addColumn('project_id', 'integer', ['signed' => false])
            ->addColumn('crawl_id', 'string', ['limit' => 64])
            ->addColumn('occurrences', 'integer', ['signed' => false, 'default' => 1])
            ->addColumn('created_at', 'timestamp', ['null' => true, 'precision' => 3])
            ->addColumn('updated_at', 'timestamp', ['null' => true, 'precision' => 3])
            ->addForeignKey('project_id', 'projects', ['id'])
            ->addForeignKey('crawl_id', 'crawls', ['id'])
            ->create();
        $this->execute('ALTER TABLE `words` MODIFY COLUMN `created_at` TIMESTAMP(3)
            NULL DEFAULT CURRENT_TIMESTAMP(3)');
        $this->execute('ALTER TABLE `words` MODIFY COLUMN `updated_at` TIMESTAMP(3)
            NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP(3)');
    }
}
