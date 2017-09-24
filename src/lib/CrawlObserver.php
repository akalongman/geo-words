<?php
declare(strict_types=1);

namespace Lib;

use Psr\Http\Message\StreamInterface;
use Spatie\Crawler\CrawlObserver as BaseCrawlObserver;
use Spatie\Crawler\Url;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;

class CrawlObserver implements BaseCrawlObserver
{
    private $output;

    private $input;

    private $file;

    private $words = [];

    private $start_time;
    private $start_memory;
    private $fs;

    public function __construct(InputInterface $input, OutputInterface $output)
    {
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

    public function willCrawl(Url $url)
    {
    }

    public function hasBeenCrawled(Url $url, $response, Url $foundOn = null)
    {
        $this->output->writeln('<info>URL:</info> <comment>' . (string) $url . '</comment>');

        $this->process($response->getBody());
    }

    public function finishedCrawling()
    {
        $this->output->writeln('<info>Crawling is finished</info>');
    }

    private function process(StreamInterface $stream): void
    {
        $content = $stream->getContents();

        $this->words = $this->readWordsFile();

        $words = $this->parseGeorgianWords($content);

        $this->saveWords($words);

        $this->output->writeln('<info>Words:</info> <comment>' . count($words) . '</comment>');

        $memory = $this->getMemoryUsage() - $this->start_memory;
        $this->output->writeln('<info>Memory:</info> <comment>' . ($memory / 1024) . 'Kb</comment>');
        $this->output->writeln('<info>- - -</info> <comment>');
    }

    private function readWordsFile(): array
    {
        if (! $this->fs->exists($this->file)) {
            return [];
        }
        $words = file($this->file);

        return $words;
    }

    private function saveWords(array $words): void
    {
        if (empty($words)) {
            return;
        }

        $content = "\n" . implode("\n", $words);
        $this->fs->appendToFile($this->file, $content);
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
        if (in_array($word, $this->words)) {
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