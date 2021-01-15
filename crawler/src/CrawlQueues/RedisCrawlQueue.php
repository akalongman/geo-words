<?php

declare(strict_types=1);

namespace Longman\Crawler\CrawlQueues;

use Psr\Http\Message\UriInterface;
use Redis;
use Spatie\Crawler\CrawlQueues\CrawlQueue;
use Spatie\Crawler\CrawlUrl;
use Spatie\Crawler\Exceptions\InvalidUrl;
use Spatie\Crawler\Exceptions\UrlNotFoundByIndex;

use function is_null;
use function is_string;
use function serialize;
use function strlen;
use function substr;
use function uniqid;
use function unserialize;

class RedisCrawlQueue implements CrawlQueue
{
    // All known URLs, indexed by URL string.
    private const KEY_URLS = 'crawler:urls';
    // Pending URLs, indexed by URL string.
    private const KEY_PENDING_URLS = 'crawler:pending';

    private Redis $redis;
    private ?string $prefix;

    public function __construct(?Redis $redis = null, ?string $prefix = null)
    {
        $this->redis = $this->initializeRedisInstance($redis);

        $this->prefix = $prefix ?? uniqid() . ':';

        // make sure prefix has a colon at the end
        if (substr($this->prefix, -1) !== ':') {
            $this->prefix .= ':';
        }
    }

    public function add(CrawlUrl $url): CrawlQueue
    {
        $urlString = (string) $url->url;

        if (! $this->has($urlString)) {
            $url->setId($this->prefix . $urlString);

            $this->redis->hSet(self::KEY_URLS, $this->prefix . $urlString, serialize($url));
            $this->redis->hSet(self::KEY_PENDING_URLS, $this->prefix . $urlString, serialize($url));
        }

        return $this;
    }

    public function has($crawlUrl): bool
    {
        if ($crawlUrl instanceof CrawlUrl) {
            $url = $this->prefix . (string) $crawlUrl->url;
        } elseif ($crawlUrl instanceof UriInterface) {
            $url = $this->prefix . (string) $crawlUrl;
        } elseif (is_string($crawlUrl)) {
            $url = $crawlUrl;
        } else {
            throw InvalidUrl::unexpectedType($crawlUrl);
        }

        return (bool) $this->redis->hExists(self::KEY_URLS, $url);
    }

    public function hasPendingUrls(): bool
    {
        return (bool) $this->redis->hLen(self::KEY_PENDING_URLS);
    }

    public function getUrlById($id): CrawlUrl
    {
        if (! $this->has($id)) {
            throw new UrlNotFoundByIndex("Crawl url {$id} not found in hashes.");
        }

        return unserialize($this->redis->hGet(self::KEY_URLS, $id));
    }

    public function getFirstPendingUrl(): ?CrawlUrl
    {
        $keys = $this->redis->hKeys(self::KEY_PENDING_URLS);

        foreach ($keys as $key) {
            $crawlUrl = unserialize($this->redis->hGet(self::KEY_PENDING_URLS, $key));

            return $crawlUrl !== false ? $crawlUrl : null;
        }

        return null;
    }

    public function hasAlreadyBeenProcessed(CrawlUrl $url): bool
    {
        $url = (string) $url->url;

        if ($this->redis->hExists(self::KEY_PENDING_URLS, $this->prefix . $url)) {
            return false;
        }

        if ($this->redis->hExists(self::KEY_URLS, $this->prefix . $url)) {
            return true;
        }

        return false;
    }

    public function markAsProcessed(CrawlUrl $crawlUrl)
    {
        $this->redis->hDel(self::KEY_PENDING_URLS, $this->prefix . (string) $crawlUrl->url);
    }

    private function initializeRedisInstance(?Redis $redis = null): Redis
    {
        if (is_null($redis)) {
            $redis = new Redis();
        }

        if (! $redis->isConnected()) {
            $redis->connect('127.0.0.1');
        }

        return $redis;
    }

    public function __destruct()
    {
        $keys = $this->redis->hkeys(self::KEY_URLS);
        foreach ($keys as $key) {
            // if key is prefixed
            if (substr($key, 0, strlen($this->prefix)) === $this->prefix) {
                $this->redis->hDel(self::KEY_URLS, $key);
            }
        }
    }
}
