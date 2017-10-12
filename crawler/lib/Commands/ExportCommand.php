<?php
declare(strict_types=1);

namespace Lib\Commands;

use InvalidArgumentException;
use Lib\Database;
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

    private const SORT_OCCURRENCES_ASC = 'occurrences_asc';
    private const SORT_OCCURRENCES_DESC = 'occurrences_desc';
    private const SORT_WORD_ASC = 'word_asc';
    private const SORT_WORD_DESC = 'word_desc';


    protected function configure()
    {
        $this
            ->setName('export')
            ->setDescription('Export words in the file')
            ->setHelp('This command allows to export words in the file')
            ->addArgument('path', InputArgument::REQUIRED, 'Path for export file')
            ->addOption('crawl-id', 'cid', InputOption::VALUE_REQUIRED, 'The crawl process ID for adding')
            ->addOption('min-occurrence', 'o', InputOption::VALUE_REQUIRED, 'The minimum amount for occurrence for export')
            ->addOption('sort', 's', InputOption::VALUE_REQUIRED, 'Sorting mechanism for exporting')
            ->addOption('with-occurrences', 'w', InputOption::VALUE_NONE, 'Export words with occurrence in the same line')
            ->addOption('format', 'f', InputOption::VALUE_REQUIRED, 'File format for exporting (sql, txt, dic)');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $path = $input->getArgument('path');

        $folder_path = realpath(getcwd() . '/' . $path);

        if (! is_dir($folder_path)) {
            throw new InvalidArgumentException('Folder ' . $folder_path . ' does not found');
        }
        $min_occurrence = (int) $input->getOption('min-occurrence');

        /** @var \Lib\Database $database */
        $database = container()->get('database');

        $crawl_id = (int) $input->getOption('crawl-id');
        $database->getCrawlerRecord($crawl_id);

        $output->writeln('<info>Start exporting of crawl ID:</info> <comment>' . $crawl_id . '</comment>');
        if ($min_occurrence) {
            $output->writeln('<info>Minimum occurrences:</info> <comment>' . $min_occurrence . '</comment>');
        }

        $sort = $this->getTranslatedSort($input);

        $words = $database->getWords($crawl_id, $min_occurrence, $sort);
        $output->writeln('<info>Words found in the database:</info> <comment>' . count($words) . '</comment>');

        $with_occurrences = (bool) $input->getOption('with-occurrences');
        $format = $input->getOption('format');
        switch ($format) {
            default;
            case self::FORMAT_TXT;
                $this->exportToText($path, $words, 'txt', $with_occurrences);

                break;

            case self::FORMAT_DIC;
                $this->exportToText($path, $words, 'dic', $with_occurrences);

                break;

            case self::FORMAT_SQL;
                $this->exportToSql($path, $words, $with_occurrences);

                break;
        }

        $output->writeln('<info>Words exported successfully</info>');

        return 0;
    }

    private function exportToText(string $path, array $words, string $ext, bool $with_occurrences)
    {
        $file = realpath($path) . '/ka_GE.' . $ext;

        $list = [];
        foreach ($words as $row) {
            $line = $row['word'];
            if ($with_occurrences) {
                $line = $line . ' ' . $row['occurrences'];
            }
            $list[] = $line;
        }

        file_put_contents($file, implode("\n", $list));
    }

    private function exportToSql(string $path, array $words, bool $with_occurrences)
    {
        $file = realpath($path) . '/ka_GE.sql';

        $list = [];
        foreach ($words as $row) {
            $line = "'" . $row['word'] . "'";
            if ($with_occurrences) {
                $line = $line . ", " . $row['occurrences'];
            }

            $list[] = $line;
        }
        $rows = '(' . implode("),\n(", $list) . ');';

        $sql = $with_occurrences
            ? 'INSERT INTO `words` (`word`, `occurrences`) VALUES ' . $rows
            : 'INSERT INTO `words` (`word`) VALUES ' . $rows;

        file_put_contents($file, $sql);
    }

    private function getTranslatedSort(InputInterface $input): string
    {
        $sort = $input->getOption('sort');
        if (! $sort) {
            return Database::SORT_WORD_ASC;
        }

        switch ($sort) {
            case self::SORT_OCCURRENCES_ASC:
                $sort = Database::SORT_OCCURRENCES_ASC;
                break;

            case self::SORT_OCCURRENCES_DESC:
                $sort = Database::SORT_OCCURRENCES_DESC;
                break;

            default:
            case self::SORT_WORD_ASC:
                $sort = Database::SORT_WORD_ASC;
                break;

            case self::SORT_WORD_DESC:
                $sort = Database::SORT_WORD_DESC;
                break;
        }

        return $sort;
    }

}
