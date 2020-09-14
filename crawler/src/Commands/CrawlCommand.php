<?php

declare(strict_types=1);

namespace Longman\Crawler\Commands;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Handler\CurlMultiHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\RequestOptions;
use Longman\Crawler\CrawlQueues\DatabaseCrawlQueue;
use Longman\Crawler\CrawlQueues\RedisCrawlQueue;
use Longman\Crawler\Database;
use Longman\Crawler\Entities\Project;
use Longman\Crawler\Observers\WordsObserver;
use Longman\Crawler\Profiles\DomainCrawlProfile;
use Longman\Crawler\Profiles\UrlSubsetProfile;
use Psr\Log\LoggerInterface;
use Spatie\Crawler\CrawlAllUrls;
use Spatie\Crawler\Crawler;
use Spatie\Crawler\CrawlInternalUrls;
use Spatie\Crawler\CrawlQueue\ArrayCrawlQueue;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use function container;
use function intval;
use function sprintf;

use const PHP_EOL;

class CrawlCommand extends Command
{
    private const PROFILE_INTERNAL = 'internal';
    private const PROFILE_ALL = 'all';
    private const PROFILE_DOMAIN = 'domain';
    private const PROFILE_SUBSET = 'subset';
    private const QUEUE_ARRAY = 'array';
    private const QUEUE_REDIS = 'redis';
    private const QUEUE_DATABASE = 'database';
    private const CONCURRENCY_DEFAULT = 10;

    private InputInterface $input;
    private OutputInterface $output;

    protected function configure(): void
    {
        $this
            ->setName('crawl')
            ->setDescription('Start crawling')
            ->setHelp('This command allows to crawl given url')
            ->addArgument('url', InputArgument::REQUIRED, 'The website url for crawling. If url contains &, entire url should be wrapped by quotes (")')
            ->addOption('concurrency', 'c', InputOption::VALUE_OPTIONAL, 'The concurrency (default: ' . self::CONCURRENCY_DEFAULT . ')')
            ->addOption('project-id', 'P', InputOption::VALUE_OPTIONAL, 'The project ID for continue')
            ->addOption('profile', 'p', InputOption::VALUE_OPTIONAL, 'The crawling profile. Values are: '
                . PHP_EOL . self::PROFILE_INTERNAL . ' (default) - this profile will only crawl the internal urls on the pages of a host.'
                . PHP_EOL . self::PROFILE_ALL . ' - this profile will crawl all urls on all pages including urls to an external site.'
                . PHP_EOL . self::PROFILE_DOMAIN . ' - this profile will crawl all urls with given domain. --domain option should be passed')
            ->addOption('observer', 'o', InputOption::VALUE_OPTIONAL, 'The observer')
            ->addOption('domain', 'd', InputOption::VALUE_OPTIONAL, 'Domain for matching parse urls, e.g. "ge"')
            ->addOption('subset', 's', InputOption::VALUE_OPTIONAL, 'URL subset for matching parse urls')
            ->addOption(
                'queue',
                'Q',
                InputOption::VALUE_OPTIONAL,
                'Queue implementation: ' . self::QUEUE_ARRAY . ', ' . self::QUEUE_REDIS . '. (default: ' . self::QUEUE_ARRAY . ')'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->input = $input;
        $this->output = $output;
        $url = $input->getArgument('url');
        $concurrency = $input->getOption('concurrency');
        $concurrency = $concurrency ? intval($concurrency) : self::CONCURRENCY_DEFAULT;
        $profile = $input->getOption('profile');
        $queue = $input->getOption('queue');
        $observer = $input->getOption('observer');
        $projectId = (int) $input->getOption('project-id');

        $output->writeln('<info>Start crawling of:</info> <comment>' . $url . '</comment>');
        $output->writeln('<info>Concurrency:</info> <comment>' . $concurrency . '</comment>');
        $output->writeln('<info>Profile:</info> <comment>' . ($profile ?? self::PROFILE_INTERNAL) . '</comment>');

        $httpClient = $this->createHttpClient();

        $crawler = new Crawler($httpClient);
        $crawler->ignoreRobots();
        $crawler->acceptNofollowLinks();
        $crawler->doNotExecuteJavaScript();
        $crawler->setParseableMimeTypes(['text/html', 'text/plain', 'text/json', 'application/pdf']);

        $this->setProfile($crawler, $url, $profile);

        $container = container();
        /** @var \Longman\Crawler\Database $database */
        $database = $container->get(Database::class);
        /** @var \Psr\Log\LoggerInterface $logger */
        $logger = $container->get(LoggerInterface::class);

        if (! $projectId) {
            $crawlProject = $database->createCrawlProject($url);
        } else {
            $crawlProject = $database->getCrawlProject($projectId);
        }

        $container->bind(Project::class, static function () use ($crawlProject): Project {
            return $crawlProject;
        });

        $output->writeln('<info>Crawl Project ID:</info> <comment>' . $crawlProject->getId() . '</comment>');

        $observer = new WordsObserver($input, $output, $database, $logger, $crawlProject);

        $crawler->setConcurrency($concurrency);
        $crawler->setCrawlObserver($observer);

        switch ($queue) {
            case self::QUEUE_REDIS:
                $crawler->setCrawlQueue(new RedisCrawlQueue());
                break;

            case self::QUEUE_DATABASE:
                $crawler->setCrawlQueue(new DatabaseCrawlQueue($database));
                break;

            case self::QUEUE_ARRAY:
            default:
                $crawler->setCrawlQueue(new ArrayCrawlQueue());
                break;
        }

        $output->writeln('- - -');

        $crawler->startCrawling($url);

        return 0;
    }

    private function setProfile(Crawler $crawler, string $url, ?string $profile): void
    {
        switch ($profile) {
            case self::PROFILE_ALL:
                $crawler->setCrawlProfile(new CrawlAllUrls());
                break;

            case self::PROFILE_SUBSET:
                $subset = $this->input->getOption('subset');
                if (empty($subset)) {
                    $this->output->writeln('<error>URL subset is not specified</error>');

                    exit;
                }
                $this->output->writeln('<info>URL Subset:</info> <comment>' . $subset . '</comment>');
                $crawler->setCrawlProfile(new UrlSubsetProfile($subset));
                break;

            case self::PROFILE_DOMAIN:
                $domain = $this->input->getOption('domain');
                if (empty($domain)) {
                    $this->output->writeln('<error>Domain is not specified</error>');

                    exit;
                }
                $this->output->writeln('<info>Domain:</info> <comment>' . $domain . '</comment>');
                $crawler->setCrawlProfile(new DomainCrawlProfile($domain));
                break;

            case self::PROFILE_INTERNAL:
            default:
                $crawler->setCrawlProfile(new CrawlInternalUrls($url));
                break;
        }
    }

    private function createHttpClient(): Client
    {
        $stack = HandlerStack::create(new CurlMultiHandler());

        // Add retry policy
        $stack->push(Middleware::retry(static function (
            int $retries,
            Request $request,
            ?Response $response = null,
            ?RequestException $exception = null
        ): bool {
            if ($retries >= 5) {
                return false;
            }

            $shouldRetry = false;
            // Retry connection exceptions.
            if ($exception instanceof ConnectException) {
                $shouldRetry = true;
            }
            // Retry on server errors.
            if ($response && $response->getStatusCode() >= 500) {
                $shouldRetry = true;
            }

            // Log if we are retrying
            if ($shouldRetry) {
                container()->get(LoggerInterface::class)->notice(
                    sprintf(
                        'Retrying %s %s %s/5, %s',
                        $request->getMethod(),
                        $request->getUri(),
                        $retries + 1,
                        $response ? 'status code: ' . $response->getStatusCode() :
                            $exception->getMessage()
                    )
                );
            }

            return $shouldRetry;
        }, static function (int $numberOfRetries): int {
            return 10000 * $numberOfRetries;
        }));

        $httpClient = new Client([
            'handler'                       => $stack,
            'verify'                        => false,
            RequestOptions::COOKIES         => true,
            RequestOptions::CONNECT_TIMEOUT => 10,
            RequestOptions::TIMEOUT         => 10,
            RequestOptions::ALLOW_REDIRECTS => false,
            RequestOptions::HEADERS         => [
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/84.0.4147.105 Safari/537.36',
            ],
        ]);

        return $httpClient;
    }
}
