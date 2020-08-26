<?php

declare(strict_types=1);

namespace Longman\Crawler;

use Carbon\Carbon;
use InvalidArgumentException;
use PDO;

use function implode;
use function mt_rand;
use function trim;

class Database
{
    public const SORT_OCCURRENCES_ASC = '`occurrences` ASC, `word` ASC';
    public const SORT_OCCURRENCES_DESC = '`occurrences` DESC, `word` ASC';
    public const SORT_WORD_ASC = '`word` ASC';
    public const SORT_WORD_DESC = '`word` DESC';

    private PDO $db;

    public function __construct(array $config)
    {
        $this->db = $this->createDatabaseConnection($config);
    }

    public function createCrawlProject(string $name = 'crawl'): int
    {
        $st = $this->db->prepare('INSERT INTO `projects`
                (`name`, `created_at`)
                VALUES
                (:name, :created_at);
            ');

        $st->bindValue(':name', $name . ' ' . mt_rand(1, 1000));
        $st->bindValue(':created_at', Carbon::now());
        $st->execute();

        return (int) $this->db->lastInsertId();
    }

    public function getCrawlProject(int $projectId): array
    {
        $stmt = $this->db->prepare('SELECT * FROM `projects` WHERE `id`=' . $projectId . ' LIMIT 1');
        $stmt->execute();
        $project = $stmt->fetch(PDO::FETCH_ASSOC);
        if (empty($project)) {
            throw new InvalidArgumentException('Crawl project with id ' . $projectId . ' does not found');
        }
        $project['id'] = (int) $project['id'];

        return $project;
    }

    public function createCrawlerRecord(int $projectId, string $url): int
    {
        $st = $this->db->prepare('INSERT INTO `crawls`
                (`project_id`, `url`, `status`, `created_at`, `updated_at`)
                VALUES
                (:project_id, :url, :status, :created_at, :updated_at);
            ');

        $st->bindValue(':project_id', $projectId);
        $st->bindValue(':url', $url);
        $st->bindValue(':status', 0);
        $st->bindValue(':created_at', Carbon::now());
        $st->bindValue(':updated_at', Carbon::now());
        $st->execute();

        return (int) $this->db->lastInsertId();
    }

    public function updateCrawlerRecord(int $id, int $status, int $words, string $msg)
    {
        $st = $this->db->prepare('UPDATE `crawls` SET
                `status`=:status, `words`=:words, `msg`=:msg, `updated_at`=:updated_at
                WHERE `id`=' . $id . '
                ;
            ');

        $st->bindValue(':status', $status);
        $st->bindValue(':words', $words);
        $st->bindValue(':msg', $msg);
        $st->bindValue(':updated_at', Carbon::now());
        $st->execute();
    }

    public function getCrawlerRecord(int $crawlId): array
    {
        $stmt = $this->db->prepare('SELECT * FROM `crawls` WHERE `id`=' . $crawlId . ' LIMIT 1');
        $stmt->execute();
        $crawl = $stmt->fetch(PDO::FETCH_ASSOC);
        if (empty($crawl)) {
            throw new InvalidArgumentException('Crawl process with id ' . $crawlId . ' does not found');
        }
        $crawl['id'] = (int) $crawl['id'];
        $crawl['project_id'] = (int) $crawl['project_id'];

        return $crawl;
    }

    public function getWords(int $crawlId, int $occurrence = 0, ?string $sort = null): array
    {
        $sql = 'SELECT * FROM `words` WHERE `crawl_id`=' . $crawlId;
        if ($occurrence) {
            $sql .= ' AND `occurrences` >= ' . $occurrence;
        }
        if ($sort) {
            $sql .= ' ORDER BY ' . $sort;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $words = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $words;
    }

    public function saveWords(int $projectId, int $crawlId, array $words): void
    {
        if (empty($words)) {
            return;
        }

        $collection = collect($words);

        $chunks = $collection->chunk(env('DB_INSERT_CHUNK_SIZE', 500));
        /** @var \Illuminate\Support\Collection $chunk */
        foreach ($chunks as $chunk) {
            $this->insertWords($projectId, $crawlId, $chunk->toArray());
        }
    }

    private function insertWords(int $projectId, int $crawlId, array $words): void
    {
        $date = Carbon::now();

        $values = [];
        $inserts = [];
        foreach ($words as $word) {
            $values[] = '(?, ?, ?, ?, ?, ?)';
            $inserts[] = trim($word);
            $inserts[] = $projectId;
            $inserts[] = $crawlId;
            $inserts[] = 1;
            $inserts[] = $date;
            $inserts[] = $date;
        }
        $values = implode(', ', $values);

        $st = $this->db->prepare('INSERT INTO `words`
                (`word`, `project_id`, `crawl_id`, `occurrences`, `created_at`, `updated_at`)
                VALUES
                ' . $values . '
                ON DUPLICATE KEY UPDATE `occurrences`=`occurrences`+1, `updated_at`="' . $date . '"
            ');

        $st->execute($inserts);
    }

    private function createDatabaseConnection(array $config): PDO
    {
        $dsn = 'mysql:host=' . $config['host'] . ';dbname=' . $config['dbname'];
        $options = [PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES ' . $config['encoding']];

        $pdo = new PDO($dsn, $config['user'], $config['password'], $options);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        return $pdo;
    }
}
