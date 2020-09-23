<?php

declare(strict_types=1);

namespace Longman\Crawler\Observers;

use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Str;
use Longman\Crawler\Database;
use Longman\Crawler\Entities\Project;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;
use Psr\Log\LoggerInterface;
use Spatie\Crawler\CrawlObserver as BaseCrawlObserver;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use function curl_close;
use function curl_exec;
use function curl_getinfo;
use function curl_init;
use function curl_setopt;
use function implode;
use function preg_match_all;
use function preg_replace;

use const CURLINFO_HTTP_CODE;
use const CURLOPT_FAILONERROR;
use const CURLOPT_NOBODY;
use const CURLOPT_RETURNTRANSFER;
use const CURLOPT_URL;

class ErrorsObserver extends BaseCrawlObserver
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
                $content = '';
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
        $this->output->writeln('<info>Total Memory:</info> <comment>' . $this->getMeasuredMemoryAsString() . '</comment>');
    }

    public function crawlFailed(UriInterface $url, RequestException $requestException, ?UriInterface $foundOnUrl = null): void
    {
        $this->database->updateCrawlerRecord(
            $this->database->getCrawlId($this->crawlProject, $url),
            Database::CRAWL_STATUS_ERRORED,
            null,
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
        $imgUrls = $this->getImgUrls($url, $content);
        $errors = [];
        foreach ($imgUrls as $imgUrl) {
            $status = $this->checkRemoteFile($imgUrl);
            if (! $status) {
                $errors[] = $imgUrl;
            }
        }

        $this->output->writeln('<info>Memory:</info> <comment>' . $this->getMeasuredMemoryAsString() . '</comment>');
        $this->output->writeln('<info>Time:</info> <comment>' . $this->getMeasuredTimeAsString() . '</comment>');
        $this->output->writeln('- - -');
        if (! empty($errors)) {
            $this->database->updateCrawlerRecord(
                $this->database->getCrawlId($this->crawlProject, $url),
                Database::CRAWL_STATUS_ERRORED,
                $response->getStatusCode(),
                'Found missing images: ' . implode(', ', $errors)
            );
        } else {
            $this->database->updateCrawlerRecord(
                $this->database->getCrawlId($this->crawlProject, $url),
                Database::CRAWL_STATUS_PROCESSED,
                $response->getStatusCode(),
                null
            );
        }
    }

    private function getImgUrls(UriInterface $url, string $content): array
    {
        preg_match_all('#<img.*?src="(.*?)".*?>#isu', $content, $matches);

        $imgUrls = $matches[1] ?? [];

        $urls = [];
        foreach ($imgUrls as $imgUrl) {
            if (Str::startsWith($imgUrl, 'data:')) {
                continue;
            }

            if (Str::startsWith($imgUrl, 'http')) {
                $urls[] = $imgUrl;
            } else {
                $guessedUrl = $url->getScheme() . '://' . preg_replace('#\/+#', '/', $url->getHost() . '/' . $url->getPath() . '/' . $imgUrl);
                $urls[] = $guessedUrl;
            }
        }

        return $urls;
    }

    private function checkRemoteFile(string $url): bool
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        // don't download content
        curl_setopt($ch, CURLOPT_NOBODY, 1);
        curl_setopt($ch, CURLOPT_FAILONERROR, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            return false;
        }

        if ($result !== false) {
            return true;
        } else {
            return false;
        }
    }
}
