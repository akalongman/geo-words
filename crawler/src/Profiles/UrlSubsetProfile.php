<?php

declare(strict_types=1);

namespace Longman\Crawler\Profiles;

use Psr\Http\Message\UriInterface;
use Spatie\Crawler\CrawlProfile as BaseCrawlProfile;

use function strpos;

class UrlSubsetProfile extends BaseCrawlProfile
{
    private string $subset;

    public function __construct(string $subset)
    {
        $this->subset = $subset;
    }

    public function shouldCrawl(UriInterface $url): bool
    {
        return strpos((string) $url, $this->subset) !== false;
    }
}
