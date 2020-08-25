<?php

declare(strict_types=1);

namespace Lib;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;
use Spatie\Crawler\CrawlObserver as BaseCrawlObserver;
use Spatie\PdfToText\Pdf;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class CrawlObserver extends BaseCrawlObserver
{
    private InputInterface $input;
    private OutputInterface $output;
    private float $startTime;
    private int $startMemory;
    private ?int $crawlId;
    private array $crawlProject;

    public function __construct(InputInterface $input, OutputInterface $output, array $crawlProject)
    {
        $this->output = $output;
        $this->input = $input;
        $this->crawlProject = $crawlProject;

        $this->startTime = $this->getMicroTime();
        $this->startMemory = $this->getMemoryUsage();
    }

    public function willCrawl(UriInterface $url)
    {
        $database = $this->getDatabase();
        $this->crawlId = $database->createCrawlerRecord($this->crawlProject['id'], (string) $url);
    }

    public function crawled(UriInterface $url, ResponseInterface $response, ?UriInterface $foundOn = null)
    {
        $database = $this->getDatabase();

        $this->output->writeln('<info>URL:</info> <comment>' . (string) $url . '</comment>');
        if (! $response) {
            $this->output->writeln('<error>Response is empty</error>');
            $database->updateCrawlerRecord($this->crawlId, 2, 0, 'Response is empty');

            return;
        }

        $stream = $response->getBody();
        $contentType = $response->getHeader('Content-Type')[0] ?? 'text';
        switch ($contentType) {
            case 'application/pdf':
                $content = $this->getContentsFromPdf($url);
                break;

            default:
                $content = (string) $stream;
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

        $memory = $this->getMemoryUsage() - $this->startMemory;
        $this->output->writeln('<info>Total Words:</info> <comment>' . count($words) . '</comment>');
        $this->output->writeln('<info>Total Memory:</info> <comment>' . ($memory / 1024) . 'Kb</comment>');

    }

    public function crawlFailed(UriInterface $url, RequestException $requestException, ?UriInterface $foundOnUrl = null)
    {
        throw $requestException;
    }

    private function process(string $content): void
    {
        $words = $this->parseGeorgianWords($content);

        $this->saveWords($words);

        $this->output->writeln('<info>Words:</info> <comment>' . count($words) . '</comment>');

        $memory = $this->getMemoryUsage() - $this->startMemory;
        $this->output->writeln('<info>Memory:</info> <comment>' . ($memory / 1024) . 'Kb</comment>');
        $this->output->writeln('- - -');

        $database = $this->getDatabase();
        $database->updateCrawlerRecord($this->crawlId, 1, count($words), '');
    }

    private function getContentsFromPdf(UriInterface $url): string
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

        $database->saveWords($crawl_project['id'], $this->crawlId, $words);
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
            $word = preg_replace('#-{2,}#u', '-', $word);
            $word = trim($word, " \t\n\r\0\x0B-");

            if (! $this->wordIsValid($word)) {
                continue;
            }
            $words[] = $word;
        }

        return $words;
    }

    private function wordIsValid(string $word): bool
    {
        if (empty($word) || $word === '-') {
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
