<?php

declare(strict_types=1);

namespace Lib\Profiles;

use Psr\Http\Message\UriInterface;
use Spatie\Crawler\CrawlProfile as BaseCrawlProfile;

use function substr;

class DomainCrawlProfile extends BaseCrawlProfile
{
    private string $domain;

    public function __construct(string $domain)
    {
        $this->domain = $domain;
    }

    public function shouldCrawl(UriInterface $url): bool
    {
        return substr($url->getHost(), -2) === $this->domain;
    }
}
