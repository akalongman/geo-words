<?php
declare(strict_types=1);

namespace Lib\Profiles;

use Spatie\Crawler\CrawlProfile as BaseCrawlProfile;
use Spatie\Crawler\Url;

class DomainCrawlProfile implements BaseCrawlProfile
{
    private $domain;

    public function __construct(string $domain)
    {
        $this->domain = $domain;
    }

    public function shouldCrawl(Url $url): bool
    {
        return substr($url->host, -2) === $this->domain;
    }
}