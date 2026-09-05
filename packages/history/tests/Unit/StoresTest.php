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
