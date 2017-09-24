<?php
declare(strict_types=1);

namespace Lib;

use Carbon\Carbon;
use PDO;
use Psr\Http\Message\StreamInterface;
use Spatie\Crawler\CrawlObserver as BaseCrawlObserver;
use Spatie\Crawler\Url;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;

class CrawlObserver implements BaseCrawlObserver
{
    private $crawl_id;

    private $output;

    private $input;

    private $file;

    private $start_time;
    private $start_memory;
    private $fs;

    /** @var \PDO */
    private $db;

    public function __construct(int $crawl_id, InputInterface $input, OutputInterface $output)
    {
        $this->crawl_id = $crawl_id;
        $this->output = $output;
        $this->input = $input;
        $this->fs = new Filesystem();

        $this->start_time = $this->getMicroTime();
        $this->start_memory = $this->getMemoryUsage();
    }

    public function setFile(string $file): void
    {
        $this->file = $file;
    }

    public function setDatabase(PDO $db): void
    {
        $this->db = $db;
    }

    public function willCrawl(Url $url)
    {

    }

    public function hasBeenCrawled(Url $url, $response, Url $foundOn = null)
    {
        $this->output->writeln('<info>URL:</info> <comment>' . (string) $url . '</comment>');
        if (! $response) {
            $this->output->writeln('<error>Response is empty</error>');

            return;
        }

        $this->process($response->getBody());
    }

    public function finishedCrawling()
    {
        $this->output->writeln('- - -');
        $this->output->writeln('<info>Crawling is finished</info>');
        $words = $this->getWords();

        $memory = $this->getMemoryUsage() - $this->start_memory;
        $this->output->writeln('<info>Total Words:</info> <comment>' . count($words) . '</comment>');
        $this->output->writeln('<info>Total Memory:</info> <comment>' . ($memory / 1024) . 'Kb</comment>');
    }

    private function process(StreamInterface $stream): void
    {
        $content = $stream->getContents();

        $words = $this->parseGeorgianWords($content);

        $this->saveWords($words);

        $this->output->writeln('<info>Words:</info> <comment>' . count($words) . '</comment>');

        $memory = $this->getMemoryUsage() - $this->start_memory;
        $this->output->writeln('<info>Memory:</info> <comment>' . ($memory / 1024) . 'Kb</comment>');
        $this->output->writeln('- - -');
    }

    private function saveWords(array $words): void
    {
        if (empty($words)) {
            return;
        }

        $date = Carbon::now();

        $values = [];
        $inserts = [];
        foreach ($words as $word) {
            $values[] = '(?, ?, ?, ?, ?)';
            $inserts[] = $word;
            $inserts[] = $this->crawl_id;
            $inserts[] = 1;
            $inserts[] = $date;
            $inserts[] = $date;
        }
        $values = implode(', ', $values);

        $st = $this->db->prepare('INSERT INTO `words`
                (`word`, `crawl_id`, `occurrences`, `created_at`, `updated_at`)
                VALUES
                ' . $values . '
                ON DUPLICATE KEY UPDATE `occurrences`=`occurrences`+1, `updated_at`="' . $date . '"
            ');

        $st->execute($inserts);
    }

    private function parseGeorgianWords(string $content): array
    {
        preg_match_all('#\b([აბგდევზთიკლმნოპჟრსტუფქღყშჩცძწჭხჯჰ-]{2,})\b#isu', $content, $matches);

        if (empty($matches[1])) {
            return [];
        }

        $unique_words = array_unique($matches[1]);

        $words = [];
        foreach ($unique_words as $word) {
            if (! $this->wordIsValid($word)) {
                continue;
            }
            $words[] = $word;
        }

        return $words;
    }

    private function wordIsValid(string $word): bool
    {
        if ($word === '--') {
            return false;
        }

        return true;
    }

    private function getMemoryUsage(): int
    {
        return memory_get_usage(true);
    }

    private function getMicroTime(): float
    {
        return microtime(true);
    }

    private function getWords(): array
    {
        $stmt = $this->db->prepare('SELECT * FROM `words` WHERE `crawl_id`=' . $this->crawl_id);
        $stmt->execute();
        $words = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $words;
    }
}
