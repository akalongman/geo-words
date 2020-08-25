<?php
declare(strict_types=1);

namespace Lib;

use Carbon\Carbon;
use Dotenv\Dotenv;
use InvalidArgumentException;
use PDO;

class Database
{
    /** @var \PDO */
    private $db;

    public const SORT_OCCURRENCES_ASC = '`occurrences` ASC, `word` ASC';
    public const SORT_OCCURRENCES_DESC = '`occurrences` DESC, `word` ASC';
    public const SORT_WORD_ASC = '`word` ASC';
    public const SORT_WORD_DESC = '`word` DESC';

    public function __construct()
    {
        $this->db = $this->createDatabaseConnection();
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

    public function getCrawlProject(int $project_id): array
    {
        $stmt = $this->db->prepare('SELECT * FROM `projects` WHERE `id`=' . $project_id . ' LIMIT 1');
        $stmt->execute();
        $project = $stmt->fetch(PDO::FETCH_ASSOC);
        if (empty($project)) {
            throw new InvalidArgumentException('Crawl project with id ' . $project_id . ' does not found');
        }
        $project['id'] = (int) $project['id'];

        return $project;
    }

    public function createCrawlerRecord(int $project_id, string $url): int
    {
        $st = $this->db->prepare('INSERT INTO `crawls`
                (`project_id`, `url`, `status`, `created_at`, `updated_at`)
                VALUES
                (:project_id, :url, :status, :created_at, :updated_at);
            ');

        $st->bindValue(':project_id', $project_id);
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

    public function getCrawlerRecord(int $crawl_id): array
    {
        $stmt = $this->db->prepare('SELECT * FROM `crawls` WHERE `id`=' . $crawl_id . ' LIMIT 1');
        $stmt->execute();
        $crawl = $stmt->fetch(PDO::FETCH_ASSOC);
        if (empty($crawl)) {
            throw new InvalidArgumentException('Crawl process with id ' . $crawl_id . ' does not found');
        }
        $crawl['id'] = (int) $crawl['id'];
        $crawl['project_id'] = (int) $crawl['project_id'];

        return $crawl;
    }

    public function getWords(int $crawl_id, int $occurrence = 0, string $sort = null): array
    {
        $sql = 'SELECT * FROM `words` WHERE `crawl_id`=' . $crawl_id;
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

    public function saveWords(int $project_id, int $crawl_id, array $words): void
    {
        if (empty($words)) {
            return;
        }

        $collection = collect($words);

        $chunks = $collection->chunk(env('DB_INSERT_CHUNK_SIZE', 500));
        /** @var \Illuminate\Support\Collection $chunk */
        foreach ($chunks as $chunk) {
            $this->insertWords($project_id, $crawl_id, $chunk->toArray());
        }
    }

    private function insertWords(int $project_id, int $crawl_id, array $words): void
    {
        $date = Carbon::now();

        $values = [];
        $inserts = [];
        foreach ($words as $word) {
            $values[] = '(?, ?, ?, ?, ?, ?)';
            $inserts[] = trim($word);
            $inserts[] = $project_id;
            $inserts[] = $crawl_id;
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

    private function createDatabaseConnection(): PDO
    {
        $this->loadConfig();

        $dsn = 'mysql:host=' . env('DB_HOST') . ';dbname=' . env('DB_NAME');
        $options = [PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES ' . env('DB_ENCODING', 'utf8mb4')];

        $pdo = new PDO($dsn, env('DB_USER'), env('DB_PASSWORD'), $options);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        return $pdo;
    }

    private function loadConfig(): void
    {
        $dot_env = new Dotenv(getcwd(), '.env');
        $dot_env->load();
    }
}
