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

    public function __construct()
    {
        $this->db = $this->createDatabaseConnection();
    }

    public function createCrawlerRecord(string $url): int
    {
        $st = $this->db->prepare('INSERT INTO `crawls`
                (`url`, `created_at`)
                VALUES
                (:url, :created_at);
            ');

        $st->bindValue(':url', $url);
        $st->bindValue(':created_at', Carbon::now());
        $st->execute();

        return (int) $this->db->lastInsertId();
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

        return $crawl;
    }

    public function getWords(int $crawl_id, int $occurrence): array
    {
        $sql = 'SELECT * FROM `words` WHERE `crawl_id`=' . $crawl_id;
        if ($occurrence) {
            $sql .= ' AND `occurrences` >= ' . $occurrence;
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $words = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $words;
    }

    public function saveWords(int $crawl_id, array $words): void
    {
        if (empty($words)) {
            return;
        }

        $date = Carbon::now();

        $values = [];
        $inserts = [];
        foreach ($words as $word) {
            $values[] = '(?, ?, ?, ?, ?)';
            $inserts[] = trim($word);
            $inserts[] = $crawl_id;
            $inserts[] = 1;
            $inserts[] = $date;
            $inserts[] = $date;
        }
        $values = implode(', ', $values);

        $st = $this->db->prepare('INSERT INTO `words`
                (`word`, `crawl_id`, `occurrences`, `created_at`, `updated_at`)
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
