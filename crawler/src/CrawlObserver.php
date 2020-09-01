<?php

declare(strict_types=1);

namespace Longman\Crawler;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;
use Psr\Log\LoggerInterface;
use Spatie\Crawler\CrawlObserver as BaseCrawlObserver;
use Spatie\PdfToText\Pdf;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use function array_unique;
use function container;
use function count;
use function explode;
use function file_exists;
use function gmdate;
use function localeconv;
use function md5;
use function memory_get_usage;
use function microtime;
use function preg_match_all;
use function preg_replace;
use function substr;
use function trim;
use function unlink;

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
            $database->updateCrawlerRecord($this->crawlId, Database::CRAWL_STATUS_ERRORED, 0, 'Response is empty');

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

        $crawlProject = $this->getCrawlProject();
        $words = $database->getWords($crawlProject['id']);

        $memory = $this->getMemoryUsage() - $this->startMemory;
        $this->output->writeln('<info>Total Words:</info> <comment>' . count($words) . '</comment>');
        $this->output->writeln('<info>Total Memory:</info> <comment>' . ($memory / 1024) . 'Kb</comment>');
    }

    public function crawlFailed(UriInterface $url, RequestException $requestException, ?UriInterface $foundOnUrl = null)
    {
        $this->output->writeln('<info>URL:</info> <comment>' . (string) $url . '</comment>');
        $this->output->writeln('<error>' . $requestException->getMessage() . '</error>');
        $this->getDatabase()->updateCrawlerRecord($this->crawlId, Database::CRAWL_STATUS_ERRORED, 0, $requestException->getMessage());
        /** @var \Psr\Log\LoggerInterface $logger */
        $logger = container()->get(LoggerInterface::class);
        $logger->error('Crawl request failed', [
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

        $database = $this->getDatabase();
        $database->updateCrawlerRecord($this->crawlId, Database::CRAWL_STATUS_PROCESSED, count($words), '');
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

        $database = $this->getDatabase();
        $crawlProject = $this->getCrawlProject();

        $database->saveWords($crawlProject['id'], $this->crawlId, $words);
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

    private function getMemoryUsage(): int
    {
        return memory_get_usage(true);
    }

    private function getMicroTime(): float
    {
        return microtime(true);
    }

    private function getMeasuredTimeAsString(): string
    {
        $localeInfo = localeconv();
        $point = $localeInfo['decimal_point'] ?? '.';

        $time = $this->getMicroTime() - $this->startTime;
        [$sec, $usec] = explode($point, (string) $time);

        return gmdate('H:i:s', (int) $sec) . '.' . substr($usec, 0, 4);
    }

    private function getDatabase(): Database
    {
        return container()->get(Database::class);
    }

    private function getCrawlProject(): array
    {
        return container()->get('crawl_project');
    }
}
