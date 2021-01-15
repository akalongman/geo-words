<?php

declare(strict_types=1);

namespace Longman\Crawler\Profiles;

use Illuminate\Support\Str;
use Psr\Http\Message\UriInterface;
use Spatie\Crawler\CrawlProfiles\CrawlProfile as BaseCrawlProfile;

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
        return substr($url->getHost(), -Str::length($this->domain)) === $this->domain;
    }
}
