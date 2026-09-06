<?php

declare(strict_types=1);

namespace IndexNowKit\History;

use DateTimeImmutable;
use DateTimeInterface;
use IndexNowKit\Result;
use IndexNowKit\ResultStatus;
use IndexNowKit\Submission\SubmissionRecord;
use Psr\SimpleCache\CacheInterface;

/**
 * A ring buffer of the last `$limit` records in a PSR-16 cache: the index (the slots in insertion order) under
 * `<prefix>history.index`, one record per slot under `<prefix>history.<n>`; a full buffer overwrites the oldest.
 * Keys carry none of the PSR-6 reserved characters (`{}()/\@:`).
 *
 * For one process, development and small sites. The slot of a record comes from a counter under `<prefix>history.seq`,
 * taken with the cache's atomic `increment()` when it has one (Laravel's repository, a Redis client) — concurrent
 * workers then get distinct slots; on a plain PSR-16 cache the counter is read-modify-write and workers recording at
 * the same moment can lose all but one of their records and stall the ring. The index update after the record is
 * best effort too. An eviction of the cache empties the history. Production keeps its history in
 * {@see Pdo\PdoSubmissionStore}; `indexnow:check` says so when this store sees several workers.
 *
 * @phpstan-import-type Row from RecordCodec
 */
final class Psr16SubmissionStore implements HistoryStoreInterface
{
    /**
     * @param string $keyPrefix the adapter's `debounce.key_prefix`
     * @param int    $limit     records kept ({@see HistoryConfig::$limit})
     */
    public function __construct(
        private readonly CacheInterface $cache,
        private readonly string $keyPrefix = 'indexnowkit_',
        private readonly int $limit = HistoryConfig::DEFAULT_LIMIT,
    ) {}

    public function record(Result $result, DateTimeImmutable $at): void
    {
        $index = $this->index();
        $seq = $this->nextSeq($index['seq']);
        $slot = $seq % $this->limit;
        $slots = array_values(array_filter($index['slots'], static fn(int $s): bool => $s !== $slot));
        $slots[] = $slot;
        $this->cache->set($this->key((string) $slot), RecordCodec::encode($result, $at));
        $this->cache->set($this->key('index'), ['seq' => $seq + 1, 'slots' => $slots, 'limit' => $this->limit]);
    }

    /**
     * The sequence number of the record being written: `increment()` on `<prefix>history.seq` when the cache has it
     * (atomic across workers), else the index's counter (read-modify-write).
     */
    private function nextSeq(int $fromIndex): int
    {
        if (method_exists($this->cache, 'increment')) {
            /** @var mixed $next */
            $next = $this->cache->increment($this->key('seq'));
            if (\is_int($next) && $next > 0) {
                return $next - 1;
            }
        }

        return $fromIndex;
    }

    public function recent(int $limit = 100, ?string $host = null, ?ResultStatus $status = null): iterable
    {
        $out = [];
        foreach ($this->records() as $record) {
            if (($host !== null && $record->result->host !== $host) || ($status !== null && $record->result->status !== $status)) {
                continue;
            }
            $out[] = $record;
            if (\count($out) >= $limit) {
                break;
            }
        }

        return $out;
    }

    public function lastFor(string $url): ?SubmissionRecord
    {
        foreach ($this->records() as $record) {
            if (\in_array($url, $record->urls, true)) {
                return $record;
            }
        }

        return null;
    }

    public function count(?string $host = null): int
    {
        if ($host === null) {
            return \count($this->index()['slots']);
        }
        $count = 0;
        foreach ($this->records() as $record) {
            $count += $record->result->host === $host ? 1 : 0;
        }

        return $count;
    }

    public function last(): ?SubmissionRecord
    {
        foreach ($this->records() as $record) {
            return $record;
        }

        return null;
    }

    public function purge(DateTimeInterface $olderThan): int
    {
        $index = $this->index();
        $kept = [];
        $removed = [];
        foreach ($index['slots'] as $slot) {
            $row = $this->cache->get($this->key((string) $slot));
            if (self::isRow($row) && RecordCodec::at($row['at']) >= $olderThan) {
                $kept[] = $slot;
            } else {
                $removed[] = $this->key((string) $slot);
            }
        }
        if ($removed !== []) {
            $this->cache->deleteMultiple($removed);
            $this->cache->set($this->key('index'), ['seq' => $index['seq'], 'slots' => $kept]);
        }

        return \count($removed);
    }

    /** `<prefix>history.<name>`. */
    public function key(string $name): string
    {
        return $this->keyPrefix . 'history.' . $name;
    }

    /**
     * @return array{seq: int, slots: list<int>}
     */
    private function index(): array
    {
        $index = $this->cache->get($this->key('index'));
        if (!\is_array($index) || !\is_int($index['seq'] ?? null) || !\is_array($index['slots'] ?? null)) {
            return ['seq' => 0, 'slots' => []];
        }
        if (($index['limit'] ?? $this->limit) !== $this->limit) {
            // `history.limit` changed: the slots were numbered modulo the old limit, so the buffer starts over (the old records stay until overwritten or purged).
            return ['seq' => 0, 'slots' => []];
        }

        return ['seq' => $index['seq'], 'slots' => array_values(array_filter($index['slots'], 'is_int'))];
    }

    /**
     * @return iterable<SubmissionRecord> newest first
     */
    private function records(): iterable
    {
        $slots = array_reverse($this->index()['slots']);
        if ($slots === []) {
            return;
        }
        $keys = array_map(fn(int $s): string => $this->key((string) $s), $slots);
        $rows = [];
        foreach ($this->cache->getMultiple($keys) as $key => $row) {
            $rows[(string) $key] = $row; // PSR-16 does not promise the order of getMultiple(): index by key, read in slot order
        }
        foreach ($keys as $key) {
            $row = $rows[$key] ?? null;
            if (self::isRow($row)) {
                yield RecordCodec::decode($row);
            }
        }
    }

    /**
     * @phpstan-assert-if-true Row $row
     */
    private static function isRow(mixed $row): bool
    {
        return \is_array($row) && \is_array($row['urls'] ?? null) && \is_string($row['status'] ?? null) && \is_string($row['at'] ?? null);
    }
}
