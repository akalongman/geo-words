<?php
declare(strict_types=1);

namespace Lib\Commands;

use GuzzleHttp\RequestOptions;
use Lib\CrawlObserver;
use Lib\Profiles\DomainCrawlProfile;
use Lib\Profiles\UrlSubsetProfile;
use Spatie\Crawler\CrawlAllUrls;
use Spatie\Crawler\Crawler;
use Spatie\Crawler\CrawlInternalUrls;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class CrawlCommand extends Command
{
    private const PROFILE_INTERNAL = 'internal';
    private const PROFILE_ALL = 'all';
    private const PROFILE_DOMAIN = 'domain';
    private const PROFILE_SUBSET = 'subset';

    private const CONCURRENCY_DEFAULT = 10;

    protected function configure(): void
    {
        $this
            ->setName('crawl')
            ->setDescription('Start crawling')
            ->setHelp('This command allows to crawl given url')
            ->addArgument('url', InputArgument::REQUIRED, 'The website url for crawling. If url contains &, entire url should be wrapped by quotes (")')
            ->addOption('concurrency', 'c', InputOption::VALUE_OPTIONAL, 'The concurrency. Default is ' . self::CONCURRENCY_DEFAULT)
            ->addOption('project-id', 'pid', InputOption::VALUE_OPTIONAL, 'The project ID for continue')
            ->addOption('profile', 'p', InputOption::VALUE_OPTIONAL, 'The crawling profile. Values are: '
                . PHP_EOL . self::PROFILE_INTERNAL . ' (default) - this profile will only crawl the internal urls on the pages of a host.'
                . PHP_EOL . self::PROFILE_ALL . ' - this profile will crawl all urls on all pages including urls to an external site.'
                . PHP_EOL . self::PROFILE_DOMAIN . ' - this profile will crawl all urls with given domain. --domain option should be passed')
            ->addOption('domain', 'd', InputOption::VALUE_OPTIONAL, 'Domain for matching parse urls, e.g. "ge"')
            ->addOption('subset', 's', InputOption::VALUE_OPTIONAL, 'URL subset for matching parse urls');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $url = $input->getArgument('url');
        $concurrency = $input->getOption('concurrency');
        $concurrency = $concurrency ? intval($concurrency) : 1;
        $profile = $input->getOption('profile');
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

        switch ($profile) {
            default:
            case self::PROFILE_INTERNAL:
                $crawler->setCrawlProfile(new CrawlInternalUrls($url));
                break;

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
        }

        $container = container();
        /** @var \Lib\Database $database */
        $database = $container->get('database');

        if (! $projectId) {
            $projectId = $database->createCrawlProject($url);
        }

        $crawlProject = $database->getCrawlProject($projectId);
        $container->bind('crawl_project', function () use ($crawlProject) {
            return $crawlProject;
        });

        $output->writeln('<info>Crawl Project ID:</info> <comment>' . $projectId . '</comment>');

        $observer = new CrawlObserver($input, $output, $crawlProject);

        $crawler->setConcurrency($concurrency);
        $crawler->setCrawlObserver($observer);

        $output->writeln('- - -');

        $crawler->startCrawling($url);

        return 0;
    }
}
