<?php

declare(strict_types=1);

namespace Longman\Crawler\Commands;

use InvalidArgumentException;
use Longman\Crawler\Database;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use function count;
use function file;
use function file_exists;
use function getcwd;
use function realpath;

class ImportCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('import')
            ->setDescription('Import words in to database')
            ->setHelp('This command allows to import words in to database')
            ->addArgument('file', InputArgument::REQUIRED, 'File path for importing')
            ->addOption('crawl_id', 'cid', InputOption::VALUE_OPTIONAL, 'The crawl process ID for adding');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $file = $input->getArgument('file');
        $filePath = realpath(getcwd() . '/' . $file);

        if (! file_exists($filePath)) {
            throw new InvalidArgumentException('File ' . $file . ' does not found');
        }

        /** @var \Longman\Crawler\Database $database */
        $database = container()->get(Database::class);

        $crawlId = (int) $input->getOption('crawl_id');
        if (! $crawlId) {
            $crawlId = $database->createCrawlerRecord($filePath);
        }
        $crawl = $database->getCrawlerRecord($crawlId);

        $output->writeln('<info>Start importing of:</info> <comment>' . $file . '</comment>');

        $words = file($filePath);
        $output->writeln('<info>Words found:</info> <comment>' . count($words) . '</comment>');

        $database->saveWords($crawl['project_id'], $crawlId, $words);

        $output->writeln('<info>Words imported successfully</info>');

        return 0;
    }
}
