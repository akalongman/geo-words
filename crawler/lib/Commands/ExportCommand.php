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
    private const FORMAT_TXT = 'txt';
    private const FORMAT_DIC = 'dic';

    protected function configure()
    {
        $this
            ->setName('export')
            ->setDescription('Import words in to database')
            ->setHelp('This command allows to import words in to database')
            ->addArgument('path', InputArgument::REQUIRED, 'Path for exporting a file')
            ->addOption('crawl_id', 'cid', InputOption::VALUE_REQUIRED, 'The crawl process ID for adding')
            ->addOption('min_occurrence', 'o', InputOption::VALUE_REQUIRED, 'The minimum amount for occurrence for export')
            ->addOption('format', 'f', InputOption::VALUE_OPTIONAL, 'File format for exporting (sql, txt, dic)');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $path = $input->getArgument('path');

        $folder_path = realpath(getcwd() . '/' . $path);

        if (! is_dir($folder_path)) {
            throw new InvalidArgumentException('Folder ' . $folder_path . ' does not found');
        }
        $min_occurrence = (int) $input->getOption('min_occurrence');

        /** @var \Lib\Database $database */
        $database = container()->get('database');

        $crawl_id = (int) $input->getOption('crawl_id');
        $database->getCrawlerRecord($crawl_id);

        $output->writeln('<info>Start exporting of crawl ID:</info> <comment>' . $crawl_id . '</comment>');
        if ($min_occurrence) {
            $output->writeln('<info>Minimum occurrences:</info> <comment>' . $min_occurrence . '</comment>');
        }

        $words = $database->getWords($crawl_id, $min_occurrence);
        $output->writeln('<info>Words found in the database:</info> <comment>' . count($words) . '</comment>');


        $format = (int) $input->getOption('format');
        switch ($format) {
            default;
            case self::FORMAT_TXT;
                $this->exportToText($path, $words, 'txt');

                break;

            case self::FORMAT_DIC;
                $this->exportToText($path, $words, 'dic');

                break;

            case self::FORMAT_SQL;
                $this->exportToSql($path, $words);

                break;
        }

        $output->writeln('<info>Words imported successfully</info>');

        return 0;
    }

    private function exportToText(string $path, array $words, string $ext)
    {
        $file = realpath($path) . '/ka_GE.txt';

        $list = array_column($words, 'word');
        sort($list);

        file_put_contents($file, implode("\n", $list));
    }

    private function exportToSql(string $path, array $words)
    {

    }

}
