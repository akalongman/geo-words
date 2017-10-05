<?php
declare(strict_types=1);

namespace Lib\Profiles;

use Spatie\Crawler\CrawlProfile as BaseCrawlProfile;
use Spatie\Crawler\Url;

class UrlSubsetProfile implements BaseCrawlProfile
{
    private $subset;

    public function __construct(string $subset)
    {
        $this->subset = $subset;
    }

    public function shouldCrawl(Url $url): bool
    {
        return strpos((string) $url, $this->subset) !== false;
    }
}