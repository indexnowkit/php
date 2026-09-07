<?php

declare(strict_types=1);

namespace IndexNowKit\History\Tests\Unit;

use DateTimeImmutable;
use IndexNowKit\Exception\ConfigurationException;
use IndexNowKit\History\Pdo\PdoSubmissionStore;
use IndexNowKit\History\Pdo\Schema;
use IndexNowKit\History\Psr16SubmissionStore;
use IndexNowKit\History\RecordCodec;
use IndexNowKit\History\Tests\Support\ArrayCache;
use IndexNowKit\Reason;
use IndexNowKit\Result;
use PDO;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

final class StoresTest extends TestCase
{
    private const ENDPOINT = 'https://api.indexnow.org/indexnow';

    #[TestDox('PSR-16: a ring buffer of `limit` records under <prefix>history.<n>; the index and the keys carry no PSR-6 reserved character; count(host), last()')]
    public function testRingBuffer(): void
    {
        $cache = new ArrayCache();
        $store = new Psr16SubmissionStore($cache, 'app_', 3);
        for ($i = 1; $i <= 5; ++$i) {
            $store->record(Result::ok('api', $i % 2 === 0 ? 'example.de' : 'www.example.com', ['https://www.example.com/' . $i], 200, self::ENDPOINT), new DateTimeImmutable('2026-09-06 10:00:0' . $i));
        }

        self::assertSame(3, $store->count());
        self::assertSame(['https://www.example.com/5', 'https://www.example.com/4', 'https://www.example.com/3'], array_merge(...array_map(static fn($r): array => $r->urls, [...$store->recent()])));
        self::assertNull($store->lastFor('https://www.example.com/1'), 'overwritten');
        self::assertSame(1, $store->count('example.de'));
        self::assertSame(['https://www.example.com/5'], $store->last()?->urls);
        foreach (array_keys($cache->values) as $key) {
            self::assertMatchesRegularExpression('/^app_history\.(index|\d+)$/', $key);
            self::assertDoesNotMatchRegularExpression('/[{}()\/\\\\@:]/', $key);
        }
        self::assertCount(4, $cache->values, 'the index and three slots');
        self::assertSame(1, $store->purge(new DateTimeImmutable('2026-09-06 10:00:04')));
        self::assertSame(2, $store->count());
        self::assertSame(0, (new Psr16SubmissionStore(new ArrayCache()))->count(), 'an empty cache is an empty store');
    }

    #[TestDox('PDO: a record is one transaction of multi-row INSERTs — its own, or the one the application has open')]
    public function testPdoTransaction(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $store = new PdoSubmissionStore($pdo);
        $store->createTable();
        $urls = [];
        for ($i = 0; $i < PdoSubmissionStore::INSERT_ROWS + 3; ++$i) {
            $urls[] = 'https://www.example.com/p' . $i;
        }
        $store->record(Result::ok('bing', 'www.example.com', $urls, 200, self::ENDPOINT), new DateTimeImmutable('2026-09-06 10:00:00'));
        self::assertSame(PdoSubmissionStore::INSERT_ROWS + 3, (int) $pdo->query('SELECT COUNT(*) FROM indexnow_submissions')->fetchColumn(), 'two INSERT statements, every row');
        self::assertFalse($pdo->inTransaction(), 'its own transaction is committed');

        $pdo->beginTransaction();
        $store->record(Result::ok('bing', 'www.example.com', ['https://www.example.com/tx'], 200, self::ENDPOINT), new DateTimeImmutable('2026-09-06 10:00:01'));
        self::assertTrue($pdo->inTransaction(), 'the application transaction is left open');
        $pdo->rollBack();
        self::assertSame(1, $store->count(), 'the record went with the application rollback: that is what sharing the connection means');
        self::assertSame(0, (new PdoSubmissionStore($pdo))->purge(new DateTimeImmutable('2026-01-01')));
    }

    #[TestDox('PSR-16: the slot comes from an atomic increment() when the cache has one; a changed history.limit starts the ring over; getMultiple() order is not trusted')]
    public function testRingBufferConcurrencyAndLimit(): void
    {
        $cache = new class extends ArrayCache {
            public int $increments = 0;

            public function increment(string $key, int $by = 1): int
            {
                ++$this->increments;
                $next = (int) ($this->values[$key] ?? 0) + $by;
                $this->values[$key] = $next;

                return $next;
            }

            /**
             * @param iterable<string> $keys
             *
             * @return array<string, mixed>
             */
            public function getMultiple($keys, $default = null): iterable
            {
                $out = [];
                foreach (array_reverse([...$keys]) as $key) { // the reverse of the asked order: PSR-16 promises none
                    $out[$key] = $this->values[$key] ?? $default;
                }

                return $out;
            }
        };
        $store = new Psr16SubmissionStore($cache, 'p_', 3);
        foreach (['a', 'b', 'c', 'd'] as $i => $path) {
            $store->record(Result::ok('bing', 'www.example.com', ['https://www.example.com/' . $path], 200, self::ENDPOINT), new DateTimeImmutable('2026-09-06 10:00:0' . $i));
        }
        self::assertSame(4, $cache->increments, 'one atomic increment per record');
        self::assertSame(4, $cache->values['p_history.seq']);
        self::assertSame(['d', 'c', 'b'], array_map(static fn($r): string => substr($r->urls[0], -1), [...$store->recent()]), 'newest first whatever order getMultiple() returned');
        self::assertSame(3, $store->count());

        $smaller = new Psr16SubmissionStore($cache, 'p_', 2);
        self::assertSame(0, $smaller->count(), 'another history.limit: the slots were numbered modulo the old one, the ring starts over');
        $smaller->record(Result::ok('bing', 'www.example.com', ['https://www.example.com/e'], 200, self::ENDPOINT), new DateTimeImmutable('2026-09-06 10:00:09'));
        self::assertSame(1, $smaller->count());
    }

    #[TestDox('PDO: one row per URL, the batch groups them; error cut to 1000 characters; an invalid table name is refused; Schema::sql() for the three drivers')]
    public function testPdo(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $store = new PdoSubmissionStore($pdo, 'my_history');
        $store->createTable();
        $store->createTable(); // idempotent on sqlite
        $store->record(Result::failed('api', 'www.example.com', ['https://www.example.com/a', 'https://www.example.com/b'], Reason::ServerError, str_repeat('x', 1500), 503, true, 30, self::ENDPOINT), new DateTimeImmutable('2026-09-06 10:00:00'));

        self::assertSame(2, (int) $pdo->query('SELECT COUNT(*) FROM my_history')->fetchColumn(), 'one row per URL');
        self::assertSame(1, $store->count());
        $record = $store->last();
        self::assertNotNull($record);
        self::assertSame(['https://www.example.com/a', 'https://www.example.com/b'], $record->urls);
        self::assertSame(1000, \strlen((string) $record->result->error));
        self::assertSame(Reason::ServerError, $record->result->reason);
        self::assertSame(503, $record->result->httpCode);
        self::assertTrue($record->result->retryable);
        self::assertSame('2026-09-06 10:00:00', $record->at->format(RecordCodec::AT_FORMAT));

        foreach (Schema::DRIVERS as $driver) {
            $sql = Schema::sql($driver, 'indexnow_submissions');
            self::assertCount(4, $sql);
            self::assertStringStartsWith('CREATE TABLE indexnow_submissions (', $sql[0]);
        }
        try {
            Schema::sql('oracle');
            self::fail('expected a ConfigurationException');
        } catch (ConfigurationException $e) {
            self::assertStringContainsString('knows no schema for the PDO driver "oracle"', $e->getMessage());
        }
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('"history.pdo.table" must match [A-Za-z_][A-Za-z0-9_]*, got "drop table; --".');
        new PdoSubmissionStore($pdo, 'drop table; --');
    }
}
