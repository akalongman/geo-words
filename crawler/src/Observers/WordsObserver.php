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
use Spatie\Crawler\CrawlObserver as BaseCrawlObserver;
use Spatie\PdfToText\Pdf;
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
    private ?int $crawlId;

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

        $this->startTime = $this->getMicroTime();
        $this->startMemory = $this->getMemoryUsage();
    }

    public function willCrawl(UriInterface $url)
    {
        $this->crawlId = $this->database->createCrawlerRecord($this->crawlProject->getId(), (string) $url);
    }

    public function crawled(UriInterface $url, ResponseInterface $response, ?UriInterface $foundOn = null)
    {
        $this->output->writeln('<info>URL:</info> <comment>' . (string) $url . '</comment>');
        if (! $response) {
            $this->output->writeln('<error>Response is empty</error>');
            $this->database->updateCrawlerRecord($this->crawlId, Database::CRAWL_STATUS_ERRORED, 0, 'Response is empty');

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

        $words = $this->database->getWords($this->crawlProject->getId());

        $memory = $this->getMemoryUsage() - $this->startMemory;
        $this->output->writeln('<info>Total Words:</info> <comment>' . count($words) . '</comment>');
        $this->output->writeln('<info>Total Memory:</info> <comment>' . ($memory / 1024) . 'Kb</comment>');
    }

    public function crawlFailed(UriInterface $url, RequestException $requestException, ?UriInterface $foundOnUrl = null)
    {
        $this->output->writeln('<info>URL:</info> <comment>' . (string) $url . '</comment>');
        $this->output->writeln('<error>' . $requestException->getMessage() . '</error>');
        $this->database->updateCrawlerRecord($this->crawlId, Database::CRAWL_STATUS_ERRORED, 0, $requestException->getMessage());
        $this->logger->error('Crawl request failed', [
            'url'       => (string) $url,
            'exception' => $requestException,
        ]);
    }

    private function process(string $content): void
    {
        $words = $this->parseGeorgianWords($content);

        $this->saveWords($words);

        $this->output->writeln('<info>Words:</info> <comment>' . count($words) . '</comment>');

        $memory = $this->getMemoryUsage() - $this->startMemory;
        $this->output->writeln('<info>Memory:</info> <comment>' . ($memory / 1024) . 'Kb</comment>');
        $time = $this->getMeasuredTimeAsString();
        $this->output->writeln('<info>Time:</info> <comment>' . $time . '</comment>');
        $this->output->writeln('- - -');

        $this->database->updateCrawlerRecord($this->crawlId, Database::CRAWL_STATUS_PROCESSED, count($words), '');
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

    private function saveWords(array $words): void
    {
        if (empty($words)) {
            return;
        }

        $this->database->saveWords($this->crawlProject->getId(), $this->crawlId, $words);
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
