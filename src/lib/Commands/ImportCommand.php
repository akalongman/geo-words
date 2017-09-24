<?php
declare(strict_types=1);

namespace Lib\Commands;

use InvalidArgumentException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ImportCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('import')
            ->setDescription('Import words in to database')
            ->setHelp('This command allows to import words in to database')
            ->addArgument('file', InputArgument::REQUIRED, 'File path for importing')
            ->addOption('crawl_id', 'cid', InputOption::VALUE_OPTIONAL, 'The crawl process ID for adding');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $file = $input->getArgument('file');
        $file_path = realpath(getcwd() . '/' . $file);

        if (! file_exists($file_path)) {
            throw new InvalidArgumentException('File ' . $file . ' does not found');
        }

        /** @var \Lib\Database $database */
        $database = container()->get('database');

        $crawl_id = (int) $input->getOption('crawl_id');
        if (! $crawl_id) {
            $crawl_id = $database->createCrawlerRecord($file_path);
        }
        $database->getCrawlerRecord($crawl_id);

        $output->writeln('<info>Start importing of:</info> <comment>' . $file . '</comment>');

        $words = file($file_path);
        $output->writeln('<info>Words found:</info> <comment>' . count($words) . '</comment>');

        $database->saveWords($crawl_id, $words);

        $output->writeln('<info>Words imported successfully</info>');

        return 0;
    }
}
