<?php

declare(strict_types=1);

namespace Longman\Crawler\Commands;

use GuzzleHttp\RequestOptions;
use Longman\Crawler\CrawlObserver;
use Longman\Crawler\CrawlQueues\RedisCrawlQueue;
use Longman\Crawler\Profiles\DomainCrawlProfile;
use Longman\Crawler\Profiles\UrlSubsetProfile;
use Spatie\Crawler\CrawlAllUrls;
use Spatie\Crawler\Crawler;
use Spatie\Crawler\CrawlInternalUrls;
use Spatie\Crawler\CrawlQueue\ArrayCrawlQueue;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use function intval;

use const PHP_EOL;

class CrawlCommand extends Command
{
    private const PROFILE_INTERNAL = 'internal';
    private const PROFILE_ALL = 'all';
    private const PROFILE_DOMAIN = 'domain';
    private const PROFILE_SUBSET = 'subset';
    private const QUEUE_ARRAY = 'array';
    private const QUEUE_REDIS = 'redis';

    private const CONCURRENCY_DEFAULT = 10;

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
        $url = $input->getArgument('url');
        $concurrency = $input->getOption('concurrency');
        $concurrency = $concurrency ? intval($concurrency) : self::CONCURRENCY_DEFAULT;
        $profile = $input->getOption('profile');
        $queue = $input->getOption('queue');
        $projectId = (int) $input->getOption('project-id');

        $output->writeln('<info>Start crawling of:</info> <comment>' . $url . '</comment>');
        $output->writeln('<info>Concurrency:</info> <comment>' . $concurrency . '</comment>');
        $output->writeln('<info>Profile:</info> <comment>' . ($profile ?? self::PROFILE_INTERNAL) . '</comment>');

        $crawler = Crawler::create([
            RequestOptions::COOKIES         => true,
            RequestOptions::CONNECT_TIMEOUT => 10,
            RequestOptions::TIMEOUT         => 10,
            RequestOptions::ALLOW_REDIRECTS => false,
        ]);

        $crawler->ignoreRobots();
        $crawler->acceptNofollowLinks();
        $crawler->doNotExecuteJavaScript();
        $crawler->setParseableMimeTypes(['text/html', 'text/plain', 'text/json', 'application/pdf']);

        switch ($profile) {
            case self::PROFILE_ALL:
                $crawler->setCrawlProfile(new CrawlAllUrls());
                break;

            case self::PROFILE_SUBSET:
                $subset = $input->getOption('subset');
                if (empty($subset)) {
                    $output->writeln('<error>URL subset is not specified</error>');

                    return 1;
                }
                $output->writeln('<info>URL Subset:</info> <comment>' . $subset . '</comment>');
                $crawler->setCrawlProfile(new UrlSubsetProfile($subset));
                break;

            case self::PROFILE_DOMAIN:
                $domain = $input->getOption('domain');
                if (empty($domain)) {
                    $output->writeln('<error>Domain is not specified</error>');

                    return 1;
                }
                $output->writeln('<info>Domain:</info> <comment>' . $domain . '</comment>');
                $crawler->setCrawlProfile(new DomainCrawlProfile($domain));
                break;

            case self::PROFILE_INTERNAL:
            default:
                $crawler->setCrawlProfile(new CrawlInternalUrls($url));
                break;
        }

        $container = container();
        /** @var \src\Database $database */
        $database = $container->get('database');

        if (! $projectId) {
            $projectId = $database->createCrawlProject($url);
        }

        $crawlProject = $database->getCrawlProject($projectId);
        $container->bind('crawl_project', static function () use ($crawlProject) {
            return $crawlProject;
        });

        $output->writeln('<info>Crawl Project ID:</info> <comment>' . $projectId . '</comment>');

        $observer = new CrawlObserver($input, $output, $crawlProject);

        $crawler->setConcurrency($concurrency);
        $crawler->setCrawlObserver($observer);

        switch ($queue) {
            case self::QUEUE_REDIS:
                $crawler->setCrawlQueue(new RedisCrawlQueue());
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
}
