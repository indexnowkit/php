<?php

declare(strict_types=1);

namespace IndexNowKit\History\Pdo;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use IndexNowKit\History\HistoryConfig;
use IndexNowKit\History\HistoryStoreInterface;
use IndexNowKit\History\RecordCodec;
use IndexNowKit\Result;
use IndexNowKit\ResultStatus;
use IndexNowKit\Submission\SubmissionRecord;
use PDO;

/**
 * The history in a database table ({@see Schema}), one row per URL of a Result, the rows of one Result sharing a
 * `batch` id; `recent()` groups them back. Every value is a placeholder; the table name is validated against
 * `[A-Za-z_][A-Za-z0-9_]*` (it is the one identifier that reaches the SQL from the configuration). Times are UTC.
 *
 * @phpstan-import-type Row from RecordCodec
 */
final class PdoSubmissionStore implements HistoryStoreInterface
{
    /**
     * @throws \IndexNowKit\Exception\ConfigurationException on an invalid table name
     */
    public function __construct(private readonly PDO $pdo, private readonly string $table = HistoryConfig::DEFAULT_TABLE)
    {
        Schema::assertTable($table);
    }

    /** Creates the table when it does not exist (tests, `sqlite::memory:`); applications run the migration of docs/migrations.md. */
    public function createTable(): void
    {
        $driver = self::str($this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME));
        foreach (Schema::sql($driver, $this->table) as $statement) {
            $this->pdo->exec($driver === 'sqlite' ? str_replace('CREATE TABLE', 'CREATE TABLE IF NOT EXISTS', str_replace('CREATE INDEX', 'CREATE INDEX IF NOT EXISTS', $statement)) : $statement);
        }
    }

    public function record(Result $result, DateTimeImmutable $at): void
    {
        $row = RecordCodec::encode($result, $at);
        $batch = self::batchId($at);
        $insert = $this->pdo->prepare(\sprintf('INSERT INTO %s (batch, url, host, engine, status, reason, http_status, error, retryable, endpoint, at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', $this->table));
        foreach ($row['urls'] as $url) {
            $insert->execute([$batch, $url, $row['host'], $row['engine'], $row['status'], $row['reason'], $row['http_status'], $row['error'], $row['retryable'] ? 1 : 0, $row['endpoint'], $row['at']]);
        }
    }

    public function recent(int $limit = 100, ?string $host = null, ?ResultStatus $status = null): iterable
    {
        $where = [];
        $params = [];
        if ($host !== null) {
            $where[] = 'host = ?';
            $params[] = $host;
        }
        if ($status !== null) {
            $where[] = 'status = ?';
            $params[] = $status->value;
        }
        $statement = $this->pdo->prepare(\sprintf('SELECT batch, MAX(id) AS last_id FROM %s%s GROUP BY batch ORDER BY last_id DESC LIMIT %d', $this->table, $where === [] ? '' : ' WHERE ' . implode(' AND ', $where), max(1, $limit)));
        $statement->execute($params);
        /** @var list<array{batch: string}> $batches */
        $batches = $statement->fetchAll(PDO::FETCH_ASSOC);

        return $this->load(array_map(static fn(array $b): string => $b['batch'], $batches));
    }

    public function lastFor(string $url): ?SubmissionRecord
    {
        $statement = $this->pdo->prepare(\sprintf('SELECT batch FROM %s WHERE url = ? ORDER BY id DESC LIMIT 1', $this->table));
        $statement->execute([$url]);
        $batch = $statement->fetchColumn();

        return \is_string($batch) ? ($this->load([$batch])[0] ?? null) : null;
    }

    public function count(?string $host = null): int
    {
        $statement = $this->pdo->prepare(\sprintf('SELECT COUNT(DISTINCT batch) FROM %s%s', $this->table, $host === null ? '' : ' WHERE host = ?'));
        $statement->execute($host === null ? [] : [$host]);

        return (int) $statement->fetchColumn();
    }

    public function last(): ?SubmissionRecord
    {
        foreach ($this->recent(1) as $record) {
            return $record;
        }

        return null;
    }

    /** One `DELETE … WHERE at < ?`: on a big table that is a long lock — run it from `history --purge` in a quiet hour. */
    public function purge(DateTimeInterface $olderThan): int
    {
        $statement = $this->pdo->prepare(\sprintf('SELECT COUNT(DISTINCT batch) FROM %s WHERE at < ?', $this->table));
        $at = DateTimeImmutable::createFromInterface($olderThan)->setTimezone(new DateTimeZone('UTC'))->format(RecordCodec::AT_FORMAT);
        $statement->execute([$at]);
        $records = (int) $statement->fetchColumn();
        $this->pdo->prepare(\sprintf('DELETE FROM %s WHERE at < ?', $this->table))->execute([$at]);

        return $records;
    }

    /**
     * The records of these batches, in the given order.
     *
     * @param list<string> $batches
     *
     * @return list<SubmissionRecord>
     */
    private function load(array $batches): array
    {
        if ($batches === []) {
            return [];
        }
        $statement = $this->pdo->prepare(\sprintf('SELECT * FROM %s WHERE batch IN (%s) ORDER BY id ASC', $this->table, implode(', ', array_fill(0, \count($batches), '?'))));
        $statement->execute($batches);
        /** @var array<string, Row> $rows */
        $rows = [];
        /** @var array<string, mixed> $row */
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $batch = self::str($row['batch']);
            if (!isset($rows[$batch])) {
                $rows[$batch] = [
                    'urls' => [],
                    'engine' => self::str($row['engine']),
                    'host' => self::str($row['host']),
                    'status' => self::str($row['status']),
                    'reason' => $row['reason'] === null ? null : self::str($row['reason']),
                    'http_status' => $row['http_status'] === null ? null : (int) self::str($row['http_status']),
                    'error' => $row['error'] === null ? null : self::str($row['error']),
                    'retryable' => (bool) $row['retryable'],
                    'endpoint' => self::str($row['endpoint']),
                    'at' => self::str($row['at']),
                ];
            }
            $rows[$batch]['urls'][] = self::str($row['url']);
        }
        $records = [];
        foreach ($batches as $batch) {
            if (isset($rows[$batch])) {
                $records[] = RecordCodec::decode($rows[$batch]);
            }
        }

        return $records;
    }

    private static function str(mixed $value): string
    {
        return \is_scalar($value) ? (string) $value : '';
    }

    /** 26 characters, sortable by time: the millisecond timestamp in base 36, then random. */
    private static function batchId(DateTimeImmutable $at): string
    {
        return str_pad(base_convert((string) ((int) $at->format('Uv')), 10, 36), 10, '0', STR_PAD_LEFT) . bin2hex(random_bytes(8));
    }
}
