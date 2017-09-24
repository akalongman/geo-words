<?php
declare(strict_types=1);

namespace Lib;

use Psr\Http\Message\StreamInterface;
use Spatie\Crawler\CrawlObserver as BaseCrawlObserver;
use Spatie\Crawler\Url;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class CrawlObserver implements BaseCrawlObserver
{
    private $output;

    private $input;

    private $start_time;
    private $start_memory;

    public function __construct(InputInterface $input, OutputInterface $output)
    {
        $this->output = $output;
        $this->input = $input;

        $this->start_time = $this->getMicroTime();
        $this->start_memory = $this->getMemoryUsage();
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
        /** @var \Lib\Database $database */
        $database = container()->get('database');

        /** @var array $crawl_record */
        $crawl_record = container()->get('crawl_record');
        $words = $database->getWords($crawl_record['id']);

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

        /** @var \Lib\Database $database */
        $database = container()->get('database');

        /** @var array $crawl_record */
        $crawl_record = container()->get('crawl_record');

        $database->saveWords($crawl_record['id'], $words);
    }

    private function parseGeorgianWords(string $content): array
    {
        preg_match_all('#\b([აბგდევზთიკლმნოპჟრსტუფქღყშჩცძწჭხჯჰ-]{2,})\b#u', $content, $matches);

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
}
