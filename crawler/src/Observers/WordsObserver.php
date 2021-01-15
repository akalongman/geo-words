<?php

declare(strict_types=1);

namespace Longman\Crawler\Observers;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Longman\Crawler\Database;
use Longman\Crawler\Entities\Project;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;
use Psr\Log\LoggerInterface;
use Spatie\Crawler\CrawlObservers\CrawlObserver as BaseCrawlObserver;
use Spatie\PdfToText\Pdf;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use function array_unique;
use function count;
use function file_exists;
use function md5;
use function preg_match_all;
use function preg_replace;
use function trim;
use function unlink;

class WordsObserver extends BaseCrawlObserver
{
    use MeasuresTimeAndMemory;

    private InputInterface $input;
    private OutputInterface $output;
    private Database $database;
    private LoggerInterface $logger;
    private Project $crawlProject;

    public function __construct(
        InputInterface $input,
        OutputInterface $output,
        Database $database,
        LoggerInterface $logger,
        Project $crawlProject
    ) {
        $this->output = $output;
        $this->input = $input;
        $this->database = $database;
        $this->logger = $logger;
        $this->crawlProject = $crawlProject;
    }

    public function willCrawl(UriInterface $url): void
    {
        $this->startTime = $this->getMicroTime();
        $this->startMemory = $this->getMemoryUsage();
        $this->database->createCrawlerRecord($this->database->getCrawlId($this->crawlProject, $url), $this->crawlProject->getId(), (string) $url);
    }

    public function crawled(UriInterface $url, ResponseInterface $response, ?UriInterface $foundOn = null): void
    {
        if ($this->output->isVerbose()) {
            $this->output->writeln('<info>Crawl ID:</info> <comment>' . $this->database->getCrawlId($this->crawlProject, $url) . '</comment>');
        }
        $this->output->writeln('<info>URL:</info> <comment>' . (string) $url . '</comment>');
        if (! $response) {
            $this->output->writeln('<error>Response is empty</error>');
            $this->database->updateCrawlerRecord(
                $this->database->getCrawlId($this->crawlProject, $url),
                Database::CRAWL_STATUS_ERRORED,
                0,
                'Response is empty'
            );

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

        $this->process($url, $response, $content);
    }

    public function finishedCrawling(): void
    {
        $this->output->writeln('<info>Crawling is finished</info>');

        $words = $this->database->getWords($this->crawlProject->getId());

        $this->output->writeln('<info>Total Words:</info> <comment>' . count($words) . '</comment>');
        $this->output->writeln('<info>Total Memory:</info> <comment>' . $this->getMeasuredMemoryAsString() . '</comment>');
    }

    public function crawlFailed(UriInterface $url, RequestException $requestException, ?UriInterface $foundOnUrl = null): void
    {
        $this->database->updateCrawlerRecord(
            $this->database->getCrawlId($this->crawlProject, $url),
            Database::CRAWL_STATUS_ERRORED,
            $requestException->hasResponse() ? $requestException->getResponse()->getStatusCode() : null,
            $requestException->getMessage()
        );
        $this->logger->error('Crawl request failed', [
            'url'       => (string) $url,
            'exception' => $requestException,
        ]);

        if ($this->output->isVerbose()) {
            $this->output->writeln('<info>Crawl ID:</info> <comment>' . $this->database->getCrawlId($this->crawlProject, $url) . '</comment>');
        }
        $this->output->writeln('<info>URL:</info> <comment>' . (string) $url . '</comment>');
        $this->output->writeln('<info>Error:</info> <error>' . OutputFormatter::escape($requestException->getMessage()) . '</error>');
        $this->output->writeln('<info>Memory:</info> <comment>' . $this->getMeasuredMemoryAsString() . '</comment>');
        $this->output->writeln('<info>Time:</info> <comment>' . $this->getMeasuredTimeAsString() . '</comment>');
        $this->output->writeln('- - -');
    }

    private function process(UriInterface $url, ResponseInterface $response, string $content): void
    {
        $words = $this->parseGeorgianWords($content);

        $this->saveWords($url, $words);

        $this->output->writeln('<info>Words:</info> <comment>' . count($words) . '</comment>');
        $this->output->writeln('<info>Memory:</info> <comment>' . $this->getMeasuredMemoryAsString() . '</comment>');
        $this->output->writeln('<info>Time:</info> <comment>' . $this->getMeasuredTimeAsString() . '</comment>');
        $this->output->writeln('- - -');

        $this->database->updateCrawlerRecord(
            $this->database->getCrawlId($this->crawlProject, $url),
            Database::CRAWL_STATUS_PROCESSED,
            $response->getStatusCode(),
            null
        );
    }

    private function getContentsFromPdf(UriInterface $url): string
    {
        $urlStr = (string) $url;
        $name = md5($urlStr) . '.pdf';
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

        // Delete file
        if (file_exists($path)) {
            unlink($path);
        }

        return (string) $text;
    }

    private function saveWords(UriInterface $url, array $words): void
    {
        if (empty($words)) {
            return;
        }

        $this->database->saveWords($this->crawlProject->getId(), $this->database->getCrawlId($this->crawlProject, $url), $words);
    }

    private function parseGeorgianWords(string $content): array
    {
        preg_match_all('#\b([აბგდევზთიკლმნოპჟრსტუფქღყშჩცძწჭხჯჰ-]{2,})\b#u', $content, $matches);

        if (empty($matches[1])) {
            return [];
        }

        $uniqueWords = array_unique($matches[1]);

        $words = [];
        foreach ($uniqueWords as $word) {
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
}
