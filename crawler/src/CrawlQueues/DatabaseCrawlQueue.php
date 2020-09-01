<?php

declare(strict_types=1);

namespace Longman\Crawler\CrawlQueues;

use Longman\Crawler\Database;
use Spatie\Crawler\CrawlQueue\CrawlQueue;
use Spatie\Crawler\CrawlUrl;
use Spatie\Crawler\Exception\UrlNotFoundByIndex;

use function serialize;
use function unserialize;

class DatabaseCrawlQueue implements CrawlQueue
{
    // Crawls table
    private const TABLE = 'crawls';

    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    public function add(CrawlUrl $url): CrawlQueue
    {
        if ($this->has($url)) {
            return $this;
        }

        $urlString = (string) $url->url;

        if (! $this->has($urlString)) {
            $url->setId($this->prefix . $urlString);

            $this->db->hSet(self::KEY_URLS, $this->prefix . $urlString, serialize($url));
            $this->db->hSet(self::KEY_PENDING_URLS, $this->prefix . $urlString, serialize($url));
        }

        return $this;
    }

    public function has($crawlUrl): bool
    {
        if (! $crawlUrl instanceof CrawlUrl) {
            $crawlUrl = CrawlUrl::create($crawlUrl);
        }
        $urlString = (string) $url->url;

        $stmt = $this->db->getPdo()->prepare('SELECT `url` FROM `crawls` WHERE `url`="' . $urlString . '"');
        $stmt->execute();
        $result = $stmt->fetchColumn();

        return (bool) $result;
    }

    public function hasPendingUrls(): bool
    {
        die('hasPendingUrls');

        return (bool) $this->db->hLen(self::KEY_PENDING_URLS);
    }

    public function getUrlById($id): CrawlUrl
    {
        die('getUrlById');

        if (! $this->has($id)) {
            throw new UrlNotFoundByIndex("Crawl url {$id} not found in hashes.");
        }

        return unserialize($this->db->hGet(self::KEY_URLS, $id));
    }

    public function getFirstPendingUrl(): ?CrawlUrl
    {
        die('getFirstPendingUrl');

        $keys = $this->db->hKeys(self::KEY_PENDING_URLS);

        foreach ($keys as $key) {
            $crawlUrl = unserialize($this->db->hGet(self::KEY_PENDING_URLS, $key));

            return $crawlUrl !== false ? $crawlUrl : null;
        }

        return null;
    }

    public function hasAlreadyBeenProcessed(CrawlUrl $url): bool
    {
        die('hasAlreadyBeenProcessed');

        $url = (string) $url->url;

        if ($this->db->hExists(self::KEY_PENDING_URLS, $this->prefix . $url)) {
            return false;
        }

        if ($this->db->hExists(self::KEY_URLS, $this->prefix . $url)) {
            return true;
        }

        return false;
    }

    public function markAsProcessed(CrawlUrl $crawlUrl)
    {
        die('markAsProcessed');

        $this->db->hDel(self::KEY_PENDING_URLS, $this->prefix . (string) $crawlUrl->url);
    }

    public function __destruct()
    {
        /*$keys = $this->db->hkeys(self::KEY_URLS);
        foreach ($keys as $key) {
            // if key is prefixed
            if (substr($key, 0, strlen($this->prefix)) === $this->prefix) {
                $this->db->hDel(self::KEY_URLS, $key);
            }
        }*/
    }
}
