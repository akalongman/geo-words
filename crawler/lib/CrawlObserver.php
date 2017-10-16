<?php
declare(strict_types=1);

namespace Lib;

use GuzzleHttp\Client;
use Spatie\Crawler\CrawlObserver as BaseCrawlObserver;
use Spatie\Crawler\Url;
use Spatie\PdfToText\Pdf;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class CrawlObserver implements BaseCrawlObserver
{
    private $output;

    private $input;

    private $start_time;
    private $start_memory;

    private $crawl_id;
    private $crawl_project;

    public function __construct(InputInterface $input, OutputInterface $output, array $crawl_project)
    {
        $this->output = $output;
        $this->input = $input;
        $this->crawl_project = $crawl_project;

        $this->start_time = $this->getMicroTime();
        $this->start_memory = $this->getMemoryUsage();
    }

    public function willCrawl(Url $url)
    {
        $database = $this->getDatabase();
        $this->crawl_id = $database->createCrawlerRecord($this->crawl_project['id'], (string) $url);
    }

    public function hasBeenCrawled(Url $url, $response, Url $foundOn = null)
    {
        $database = $this->getDatabase();

        $this->output->writeln('<info>URL:</info> <comment>' . (string) $url . '</comment>');
        if (! $response) {
            $this->output->writeln('<error>Response is empty</error>');
            $database->updateCrawlerRecord($this->crawl_id, 2, 0, 'Response is empty');

            return;
        }

        $stream = $response->getBody();
        $content_type = $response->getHeader('Content-Type')[0] ?? 'text';
        switch ($content_type) {
            case 'application/pdf':
                $content = $this->getContentsFromPdf($url);
                break;

            default:
                $content = $stream->getContents();
                break;
        }


        $this->process($content);
    }

    public function finishedCrawling()
    {
        $this->output->writeln('<info>Crawling is finished</info>');

        $database = $this->getDatabase();

        $crawl_project = $this->getCrawlProject();
        $words = $database->getWords($crawl_project['id']);


        $memory = $this->getMemoryUsage() - $this->start_memory;
        $this->output->writeln('<info>Total Words:</info> <comment>' . count($words) . '</comment>');
        $this->output->writeln('<info>Total Memory:</info> <comment>' . ($memory / 1024) . 'Kb</comment>');

    }

    private function process(string $content): void
    {
        $words = $this->parseGeorgianWords($content);

        $this->saveWords($words);

        $this->output->writeln('<info>Words:</info> <comment>' . count($words) . '</comment>');

        $memory = $this->getMemoryUsage() - $this->start_memory;
        $this->output->writeln('<info>Memory:</info> <comment>' . ($memory / 1024) . 'Kb</comment>');
        $this->output->writeln('- - -');

        $database = $this->getDatabase();
        $database->updateCrawlerRecord($this->crawl_id, 1, count($words), '');
    }

    private function getContentsFromPdf(Url $url): string
    {
        $url_str = (string) $url;
        $name = md5($url_str) . '.pdf';
        $path = 'data/tmp/' . $name;

        $client = new Client();
        $client->request(
            'GET',
            (string) $url,
            ['sink' => $path]
        );

        $text = (new Pdf())
            ->setPdf($path)
            ->text();

        // @TODO: Delete $path

        return (string) $text;
    }

    private function saveWords(array $words): void
    {
        if (empty($words)) {
            return;
        }

        $database = $this->getDatabase();
        $crawl_project = $this->getCrawlProject();

        $database->saveWords($crawl_project['id'], $this->crawl_id, $words);
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

    private function getDatabase(): Database
    {
        return container()->get('database');
    }

    private function getCrawlProject(): array
    {
        return container()->get('crawl_project');
    }
}
