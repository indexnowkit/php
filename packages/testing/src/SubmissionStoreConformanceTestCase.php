<?php

declare(strict_types=1);

namespace IndexNowKit\Testing\Conformance;

use DateTimeImmutable;
use DateTimeZone;
use IndexNowKit\Reason;
use IndexNowKit\Result;
use IndexNowKit\ResultStatus;
use IndexNowKit\Submission\SubmissionRecord;
use IndexNowKit\Submission\SubmissionStoreInterface;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

/**
 * S01–S08: what every `Submission\SubmissionStoreInterface` implementation promises (core docs/submission-store.md):
 * one record per Result, newest first, filters by host and status, `lastFor()` the latest record naming the URL,
 * `recent()` honouring the limit, several URLs of one Result kept together, an empty store answering nothing.
 * Extend it, return a fresh store from {@see createStore()}; a store that supports `purge()` (the
 * `HistoryStoreInterface` of `indexnowkit/history`) says so in {@see supportsPurge()}.
 *
 * Optional: `Submission\NullSubmissionStore` keeps nothing and is not a participant.
 */
abstract class SubmissionStoreConformanceTestCase extends TestCase
{
    protected const HOST = 'www.example.com';
    protected const OTHER_HOST = 'example.de';

    /** A fresh, empty store for every test. */
    abstract protected function createStore(): SubmissionStoreInterface;

    /** Whether the store implements `purge(DateTimeInterface $olderThan): int` (indexnowkit/history's `HistoryStoreInterface`). */
    protected function supportsPurge(): bool
    {
        return false;
    }

    #[TestDox('S01 record() then recent(): the Result and its time come back unchanged')]
    public function testS01RecordAndRead(): void
    {
        $store = $this->createStore();
        $at = new DateTimeImmutable('2026-09-06 10:00:00', new DateTimeZone('UTC'));
        $result = Result::ok('api', self::HOST, ['https://www.example.com/a'], 200, 'https://api.indexnow.org/indexnow');

        $store->record($result, $at);
        $records = $this->list($store->recent());

        self::assertCount(1, $records);
        self::assertSame(['https://www.example.com/a'], $records[0]->urls);
        self::assertSame(ResultStatus::Ok, $records[0]->result->status);
        self::assertSame('api', $records[0]->result->engine);
        self::assertSame(self::HOST, $records[0]->result->host);
        self::assertSame(200, $records[0]->result->httpCode);
        self::assertSame($at->getTimestamp(), $records[0]->at->getTimestamp());
    }

    #[TestDox('S02 recent() lists the newest first')]
    public function testS02NewestFirst(): void
    {
        $store = $this->createStore();
        $this->record($store, '/one', '2026-09-06 10:00:00');
        $this->record($store, '/two', '2026-09-06 10:00:01');
        $this->record($store, '/three', '2026-09-06 10:00:02');

        self::assertSame(['https://www.example.com/three', 'https://www.example.com/two', 'https://www.example.com/one'], $this->urls($store->recent()));
    }

    #[TestDox('S03 recent(host: …) keeps the records of that host only')]
    public function testS03FilterByHost(): void
    {
        $store = $this->createStore();
        $this->record($store, '/a', '2026-09-06 10:00:00');
        $store->record(Result::ok('api', self::OTHER_HOST, ['https://example.de/b'], 200, 'https://api.indexnow.org/indexnow'), new DateTimeImmutable('2026-09-06 10:00:01'));

        self::assertSame(['https://example.de/b'], $this->urls($store->recent(host: self::OTHER_HOST)));
        self::assertSame(['https://www.example.com/a'], $this->urls($store->recent(host: self::HOST)));
        self::assertSame([], $this->urls($store->recent(host: 'nobody.example.org')));
    }

    #[TestDox('S04 recent(status: …) keeps the records of that status only; skipped results are records too')]
    public function testS04FilterByStatus(): void
    {
        $store = $this->createStore();
        $this->record($store, '/ok', '2026-09-06 10:00:00');
        $store->record(Result::failed('api', self::HOST, ['https://www.example.com/failed'], Reason::RateLimited, null, 429, true, 30, 'https://api.indexnow.org/indexnow'), new DateTimeImmutable('2026-09-06 10:00:01'));
        $store->record(Result::skipped(self::HOST, ['https://www.example.com/skipped'], Reason::Debounced), new DateTimeImmutable('2026-09-06 10:00:02'));

        self::assertSame(['https://www.example.com/ok'], $this->urls($store->recent(status: ResultStatus::Ok)));
        self::assertSame(['https://www.example.com/failed'], $this->urls($store->recent(status: ResultStatus::Failed)));
        self::assertSame(['https://www.example.com/skipped'], $this->urls($store->recent(status: ResultStatus::Skipped)));
        $failed = $this->list($store->recent(status: ResultStatus::Failed))[0]->result;
        self::assertSame(Reason::RateLimited, $failed->reason);
        self::assertSame(429, $failed->httpCode);
        self::assertTrue($failed->retryable);
        self::assertSame(Reason::Debounced, $this->list($store->recent(status: ResultStatus::Skipped))[0]->result->reason);
    }

    #[TestDox('S05 lastFor(url) is the latest record naming the URL, whatever its status')]
    public function testS05LastFor(): void
    {
        $store = $this->createStore();
        $this->record($store, '/page', '2026-09-06 10:00:00');
        $store->record(Result::skipped(self::HOST, ['https://www.example.com/page'], Reason::Debounced), new DateTimeImmutable('2026-09-06 10:05:00'));
        $this->record($store, '/other', '2026-09-06 10:10:00');

        $last = $store->lastFor('https://www.example.com/page');
        self::assertNotNull($last);
        self::assertSame(ResultStatus::Skipped, $last->result->status);
        self::assertSame((new DateTimeImmutable('2026-09-06 10:05:00'))->getTimestamp(), $last->at->getTimestamp());
        self::assertNull($store->lastFor('https://www.example.com/never'));
    }

    #[TestDox('S06 recent(limit) returns at most that many records, the newest')]
    public function testS06Limit(): void
    {
        $store = $this->createStore();
        for ($i = 1; $i <= 5; ++$i) {
            $this->record($store, '/p' . $i, '2026-09-06 10:00:0' . $i);
        }

        self::assertSame(['https://www.example.com/p5', 'https://www.example.com/p4'], $this->urls($store->recent(2)));
        self::assertCount(5, $this->list($store->recent(100)));
    }

    #[TestDox('S07 several URLs of one Result stay one record, and each URL finds it through lastFor()')]
    public function testS07SeveralUrlsOneRecord(): void
    {
        $store = $this->createStore();
        $urls = ['https://www.example.com/x', 'https://www.example.com/y', 'https://www.example.com/z'];
        $store->record(Result::ok('api', self::HOST, $urls, 200, 'https://api.indexnow.org/indexnow'), new DateTimeImmutable('2026-09-06 10:00:00'));

        $records = $this->list($store->recent());
        self::assertCount(1, $records);
        self::assertSame($urls, $records[0]->urls);
        self::assertSame($urls, $store->lastFor('https://www.example.com/y')?->urls);
    }

    #[TestDox('S08 an empty store: recent() yields nothing, lastFor() is null; purge() (when supported) removes what is older')]
    public function testS08EmptyAndPurge(): void
    {
        $store = $this->createStore();
        self::assertSame([], $this->list($store->recent()));
        self::assertNull($store->lastFor('https://www.example.com/a'));
        if (!$this->supportsPurge()) {
            return;
        }
        self::assertTrue(method_exists($store, 'purge'), 'supportsPurge() but no purge() method');
        $this->record($store, '/old', '2026-09-01 10:00:00');
        $this->record($store, '/new', '2026-09-06 10:00:00');
        $purged = $store->purge(new DateTimeImmutable('2026-09-03 00:00:00'));
        self::assertSame(1, $purged, 'purge() returns the number of removed records');
        self::assertSame(['https://www.example.com/new'], $this->urls($store->recent()));
    }

    private function record(SubmissionStoreInterface $store, string $path, string $at): void
    {
        $store->record(Result::ok('api', self::HOST, ['https://www.example.com' . $path], 200, 'https://api.indexnow.org/indexnow'), new DateTimeImmutable($at, new DateTimeZone('UTC')));
    }

    /**
     * @param iterable<SubmissionRecord> $records
     *
     * @return list<SubmissionRecord>
     */
    private function list(iterable $records): array
    {
        $list = [];
        foreach ($records as $record) {
            self::assertInstanceOf(SubmissionRecord::class, $record);
            $list[] = $record;
        }

        return $list;
    }

    /**
     * @param iterable<SubmissionRecord> $records
     *
     * @return list<string>
     */
    private function urls(iterable $records): array
    {
        $urls = [];
        foreach ($this->list($records) as $record) {
            $urls = [...$urls, ...$record->urls];
        }

        return $urls;
    }
}
