<?php

declare(strict_types=1);

namespace IndexNowKit\History\Tests\Unit;

use DateTimeImmutable;
use IndexNowKit\Adapter\ServicesBuilder;
use IndexNowKit\Config;
use IndexNowKit\History\Adapter\HistoryServices;
use IndexNowKit\History\Check\HistoryCheck;
use IndexNowKit\History\Console\HistoryRunner;
use IndexNowKit\History\Console\StatusRunner;
use IndexNowKit\History\HistoryConfig;
use IndexNowKit\History\Pdo\PdoSubmissionStore;
use IndexNowKit\History\Pdo\Schema;
use IndexNowKit\History\Psr16SubmissionStore;
use IndexNowKit\History\Tests\Support\ArrayCache;
use IndexNowKit\Retry\ForbiddenCounter;
use IndexNowKit\Submission\NullSubmissionStore;
use IndexNowKit\Testing\ArrayLogger;
use IndexNowKit\Testing\FakeTransport;
use PDO;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

/** `History\Adapter\HistoryServices`: what every framework adapter wires, in one place. */
final class AdapterServicesTest extends TestCase
{
    private const KEY = 'abcdef1234567890abcdef1234567890';

    #[TestDox('package(), options(), config(), the two stores, the cache id behind debounce.store and the 403 counter')]
    public function testPiecesOneByOne(): void
    {
        self::assertSame('indexnowkit/history', HistoryServices::package()->package);
        self::assertSame(HistoryConfig::class, HistoryServices::package()->marker);
        self::assertFalse(HistoryServices::package(false)->installed());
        self::assertSame(HistoryConfig::OPTIONS, HistoryServices::options());

        $logger = new ArrayLogger();
        $history = HistoryServices::config(['store' => 'pdo', 'pdo' => ['dsn' => 'sqlite::memory:']], $logger, 'php x check');
        self::assertSame(HistoryConfig::STORE_PDO, $history->store);
        self::assertNull(HistoryServices::config(['store' => 'pdo', 'pdo' => ['dsn' => 'sqlite::memory:', 'service' => 'db']], $logger, 'php x check')->store, 'a DSN together with a connection switches the history off');
        self::assertStringContainsString('php x check', implode("\n", $logger->messages('critical')));

        $pdo = HistoryServices::pdoFromDsn('sqlite::memory:');
        self::assertSame(PDO::ERRMODE_EXCEPTION, $pdo->getAttribute(PDO::ATTR_ERRMODE));
        $silent = new PDO('sqlite::memory:');
        $store = HistoryServices::pdoStore($silent, $history);
        self::assertInstanceOf(PdoSubmissionStore::class, $store);
        self::assertSame(PDO::ERRMODE_EXCEPTION, $silent->getAttribute(PDO::ATTR_ERRMODE), 'an application connection is switched to exceptions');

        $config = Config::fromArray(['key' => self::KEY, 'base_url' => 'https://www.example.com', 'debounce' => ['store' => 'redis', 'key_prefix' => 'pfx']]);
        $psr16 = HistoryServices::psr16Store(new ArrayCache(), HistoryConfig::fromArray(['store' => 'psr16', 'limit' => 5]), $config);
        self::assertInstanceOf(Psr16SubmissionStore::class, $psr16);
        self::assertSame('redis', HistoryServices::debounceCacheId($config));
        self::assertNull(HistoryServices::debounceCacheId(Config::fromArray(['key' => self::KEY, 'base_url' => 'https://www.example.com', 'debounce' => ['store' => 'memory']])));
        self::assertNull(HistoryServices::debounceCacheId(Config::fromArray(['key' => self::KEY, 'base_url' => 'https://www.example.com'])));
        self::assertInstanceOf(ForbiddenCounter::class, HistoryServices::forbiddenCounter($config, null, $logger));
    }

    #[TestDox('over a runtime graph: the check, the runners and the store description')]
    public function testGraphPieces(): void
    {
        $logger = new ArrayLogger();
        $config = Config::fromArray(['key' => self::KEY, 'base_url' => 'https://www.example.com']);
        $history = HistoryConfig::fromArray(['store' => 'pdo', 'pdo' => ['dsn' => 'sqlite::memory:']]);
        $pdo = HistoryServices::pdoFromDsn('sqlite::memory:');
        foreach (Schema::sql('sqlite') as $sql) {
            $pdo->exec($sql);
        }
        $store = HistoryServices::pdoStore($pdo, $history);
        $services = (new ServicesBuilder($config, $logger))->transport(new FakeTransport())->submissionStore($store)->build();

        self::assertInstanceOf(HistoryCheck::class, HistoryServices::checksFor($history, $services)[0]);
        self::assertInstanceOf(HistoryCheck::class, HistoryServices::check($history, null), 'the null store when the graph has none');
        self::assertInstanceOf(HistoryRunner::class, HistoryServices::historyRunnerFor($history, $services));
        self::assertInstanceOf(StatusRunner::class, HistoryServices::statusRunnerFor($services, 'memory', null));
        self::assertInstanceOf(StatusRunner::class, HistoryServices::statusRunner($config, $services->keys(), $services->forbiddenCounter(), 'redis (redis)', null, static fn(): array => ['queue' => 'default']));

        self::assertSame('off (history.store)', HistoryServices::describe(HistoryConfig::fromArray([]), $store));
        self::assertSame('pdo (indexnow_submissions), 0 records', HistoryServices::describe($history, $store));
        self::assertSame('pdo (indexnow_submissions)', HistoryServices::describe($history, new NullSubmissionStore()));
        self::assertSame('pdo (indexnow_submissions)', HistoryServices::describe($history, null));
        self::assertSame('psr16 (10 records kept)', HistoryServices::describe(HistoryConfig::fromArray(['store' => 'psr16', 'limit' => 10]), null));
        $custom = new class implements \IndexNowKit\Submission\SubmissionStoreInterface {
            public function record(\IndexNowKit\Result $result, DateTimeImmutable $at): void {}

            public function recent(int $limit = 100, ?string $host = null, ?\IndexNowKit\ResultStatus $status = null): iterable
            {
                return [];
            }

            public function lastFor(string $url): ?\IndexNowKit\Submission\SubmissionRecord
            {
                return null;
            }
        };
        self::assertStringStartsWith('custom (', HistoryServices::describe($history, $custom));
    }
}
