<?php

declare(strict_types=1);

namespace IndexNowKit\History\Tests\Unit;

use IndexNowKit\Exception\ConfigurationException;
use IndexNowKit\History\HistoryConfig;
use IndexNowKit\Testing\ArrayLogger;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

final class HistoryConfigTest extends TestCase
{
    #[TestDox('defaults: no store, 500 records, the default table, 90 days; toArray() mirrors fromArray()')]
    public function testDefaultsAndCoercion(): void
    {
        $config = HistoryConfig::fromArray([]);
        self::assertNull($config->store);
        self::assertSame(500, $config->limit);
        self::assertNull($config->keyPrefix);
        self::assertSame('indexnow_submissions', $config->pdoTable);
        self::assertSame(90, $config->retentionDays);
        self::assertNull(HistoryConfig::disabled()->store);
        self::assertSame(['store' => null, 'limit' => 500, 'key_prefix' => null, 'pdo' => ['dsn' => null, 'service' => null, 'table' => 'indexnow_submissions'], 'retention_days' => 90], $config->toArray());

        $config = HistoryConfig::fromArray(['store' => 'PDO', 'limit' => '10', 'key_prefix' => 'app_', 'pdo' => ['dsn' => 'sqlite::memory:', 'service' => 'db', 'table' => 'seo_log'], 'retention_days' => '7']);
        self::assertSame('pdo', $config->store);
        self::assertSame(10, $config->limit);
        self::assertSame('app_', $config->keyPrefix);
        self::assertSame('sqlite::memory:', $config->pdoDsn);
        self::assertSame('db', $config->pdoService);
        self::assertSame('seo_log', $config->pdoTable);
        self::assertSame(7, $config->retentionDays);
        self::assertCount(7, HistoryConfig::OPTIONS);
    }

    /**
     * @return iterable<string, array{0: array<string, mixed>, 1: string}>
     */
    public static function invalid(): iterable
    {
        yield 'store' => [['store' => 'redis'], '"history.store" must be one of psr16, pdo or null, got "redis".'];
        yield 'limit' => [['limit' => 0], '"history.limit" must be >= 1, got 0.'];
        yield 'limit text' => [['limit' => 'many'], '"history.limit" must be an integer, got "many".'];
        yield 'key_prefix' => [['key_prefix' => 'app:'], '"history.key_prefix" must not contain the PSR-6 reserved characters {}()/\\@:, got "app:".'];
        yield 'table' => [['pdo' => ['table' => '1bad']], '"history.pdo.table" must match [A-Za-z_][A-Za-z0-9_]*, got "1bad".'];
        yield 'retention' => [['retention_days' => 0], '"history.retention_days" must be >= 1, got 0.'];
    }

    /**
     * @param array<string, mixed> $block
     */
    #[DataProvider('invalid')]
    public function testInvalid(array $block, string $message): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage($message);
        HistoryConfig::fromArray($block);
    }

    public function testLoadOrDisabled(): void
    {
        $logger = new ArrayLogger();
        self::assertNull(HistoryConfig::loadOrDisabled(['store' => 'nope'], $logger, 'php yii indexnow/check')->store);
        self::assertStringContainsString('(run "php yii indexnow/check")', $logger->messages('critical')[0]);
        self::assertSame('psr16', HistoryConfig::loadOrDisabled(['store' => 'psr16'], $logger, 'x')->store);
    }
}
