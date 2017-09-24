<?php
declare(strict_types=1);

namespace Lib\Commands;

use InvalidArgumentException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ExportCommand extends Command
{
    private const FORMAT_SQL = 'sql';

    protected function configure()
    {
        $this
            ->setName('export')
            ->setDescription('Import words in to database')
            ->setHelp('This command allows to import words in to database')
            ->addArgument('path', InputArgument::REQUIRED, 'Path for exporting a file')
            ->addOption('crawl_id', 'cid', InputOption::VALUE_REQUIRED, 'The crawl process ID for adding')
            ->addOption('format', 'f', InputOption::VALUE_OPTIONAL, 'File format for exporting (sql, txt, dic)');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $path = $input->getArgument('path');

        $folder_path = realpath(getcwd() . '/' . $path);

        if (! is_dir($folder_path)) {
            throw new InvalidArgumentException('Folder ' . $folder_path . ' does not found');
        }

        /** @var \Lib\Database $database */
        $database = container()->get('database');

        $crawl_id = (int) $input->getOption('crawl_id');
        $database->getCrawlerRecord($crawl_id);

        $output->writeln('<info>Start exporting of crawl ID:</info> <comment>' . $crawl_id . '</comment>');


        $words = $database->getWords($crawl_id);
        $output->writeln('<info>Words found in the database:</info> <comment>' . count($words) . '</comment>');


        die;



        $format = (int) $input->getOption('format');
        switch($format) {
            case self::FORMAT_SQL;


                break;
        }


        $words = file($file_path);
        $output->writeln('<info>Words found:</info> <comment>' . count($words) . '</comment>');

        $database->saveWords($crawl_id, $words);

        $output->writeln('<info>Words imported successfully</info>');

        return 0;
    }
}
