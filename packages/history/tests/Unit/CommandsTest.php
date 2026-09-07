<?php

declare(strict_types=1);

namespace IndexNowKit\History\Tests\Unit;

use DateTimeImmutable;
use IndexNowKit\Config;
use IndexNowKit\Console\ExitCode;
use IndexNowKit\History\Console\HistoryCommand;
use IndexNowKit\History\Console\HistoryRunner;
use IndexNowKit\History\Console\StatusCommand;
use IndexNowKit\History\Console\StatusRunner;
use IndexNowKit\History\HistoryConfig;
use IndexNowKit\History\Psr16SubmissionStore;
use IndexNowKit\History\Tests\Support\ArrayCache;
use IndexNowKit\Key\StaticKeyProvider;
use IndexNowKit\Reason;
use IndexNowKit\Result;
use IndexNowKit\Retry\ForbiddenCounter;
use IndexNowKit\Testing\FrozenClock;
use IndexNowKit\Url\UrlNormalizerFactory;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * The `indexnow:history` and `indexnow:status` commands over their runners: every option of the `Definitions` reaches
 * the runner in its type (`--purge` absent / bare / with days, `--limit` as given).
 */
final class CommandsTest extends TestCase
{
    private const KEY = 'abcdef1234567890abcdef1234567890';
    private const NOW = '2026-09-06 12:00:00';

    private function store(): Psr16SubmissionStore
    {
        $store = new Psr16SubmissionStore(new ArrayCache(), 'app_');
        $store->record(Result::ok('api', 'www.example.com', ['https://www.example.com/a', 'https://www.example.com/b'], 200, 'e'), new DateTimeImmutable('2026-09-06 09:00:00'));
        $store->record(Result::failed('api', 'example.de', ['https://example.de/c'], Reason::RateLimited, null, 429, true, 30, 'e'), new DateTimeImmutable('2026-09-06 11:00:00'));
        $store->record(Result::skipped('www.example.com', ['https://www.example.com/a'], Reason::Debounced), new DateTimeImmutable('2026-09-06 11:30:00'));
        $store->record(Result::ok('api', 'www.example.com', ['https://www.example.com/old'], 200, 'e'), new DateTimeImmutable('2026-08-01 09:00:00'));

        return $store;
    }

    private function config(): Config
    {
        return Config::fromArray(['key' => self::KEY, 'base_url' => 'https://www.example.com', 'hosts' => ['example.de' => self::KEY], 'dispatch' => 'sync', 'environment' => 'prod', 'debounce' => ['per_url' => 600, 'key_prefix' => 'app_']]);
    }

    private function history(?Psr16SubmissionStore $store = null): CommandTester
    {
        return new CommandTester(new HistoryCommand(new HistoryRunner($store ?? $this->store(), HistoryConfig::fromArray(['store' => 'psr16', 'retention_days' => 7]), UrlNormalizerFactory::fromConfig($this->config()), new FrozenClock(self::NOW))));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function records(CommandTester $tester): array
    {
        $decoded = json_decode($tester->getDisplay(), true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded);
        self::assertIsArray($decoded['records']);

        return array_values($decoded['records']);
    }

    #[TestDox('indexnow:history with the options of Definitions::history(); indexnow:status with --json')]
    public function testDefinitions(): void
    {
        $history = new HistoryCommand(new HistoryRunner($this->store(), HistoryConfig::fromArray([])));
        self::assertSame('indexnow:history', $history->getName());
        self::assertSame([], $history->getDefinition()->getArguments());
        self::assertSame(['host', 'status', 'url', 'since', 'limit', 'json', 'purge'], array_keys($history->getDefinition()->getOptions()));
        self::assertTrue($history->getDefinition()->getOption('purge')->isValueOptional());
        self::assertSame('50', $history->getDefinition()->getOption('limit')->getDefault());

        $status = new StatusCommand(new StatusRunner($this->config(), StaticKeyProvider::fromConfig($this->config()), new ForbiddenCounter(null, 'app_', 5), 'memory'));
        self::assertSame('indexnow:status', $status->getName());
        self::assertSame(['json'], array_keys($status->getDefinition()->getOptions()));
        self::assertStringContainsString('IndexNow status', $status->getDescription());
    }

    #[TestDox('history: the table of every record; --host, --status, --url, --since and --limit reach the runner; --json prints the records')]
    public function testHistoryFilters(): void
    {
        $tester = $this->history();

        self::assertSame(ExitCode::SUCCESS, $tester->execute([]));
        self::assertStringContainsString('https://www.example.com/a', $tester->getDisplay());
        self::assertStringContainsString('4 record(s)', $tester->getDisplay());

        self::assertSame(ExitCode::SUCCESS, $tester->execute(['--host' => 'example.de', '--json' => true]));
        self::assertSame(['https://example.de/c'], self::records($tester)[0]['urls']);
        self::assertCount(1, self::records($tester));

        self::assertSame(ExitCode::SUCCESS, $tester->execute(['--status' => 'ok', '--json' => true]));
        self::assertSame(['ok', 'ok'], array_column(self::records($tester), 'status'));

        self::assertSame(ExitCode::SUCCESS, $tester->execute(['--url' => 'https://www.example.com/a?utm_source=x', '--json' => true]));
        self::assertSame(['skipped', 'ok'], array_column(self::records($tester), 'status'), '--url is normalized like a submission');

        self::assertSame(ExitCode::SUCCESS, $tester->execute(['--since' => '2h', '--json' => true]));
        self::assertCount(2, self::records($tester));

        self::assertSame(ExitCode::SUCCESS, $tester->execute(['--limit' => '1', '--json' => true]));
        self::assertCount(1, self::records($tester), '--limit reaches the runner as given');
        self::assertSame(ExitCode::SUCCESS, $tester->execute(['--limit' => 'many', '--json' => true]));
        self::assertCount(4, self::records($tester), 'a value that is not a number is the default');

        self::assertSame(ExitCode::INVALID, $tester->execute(['--status' => 'maybe']));
        self::assertStringContainsString('--status must be one of', $tester->getDisplay());
        self::assertSame(ExitCode::INVALID, $tester->execute(['--since' => 'yesterday-ish']));
    }

    #[TestDox('--purge absent leaves the store alone, --purge without a value uses retention_days, --purge=<days> the given days')]
    public function testPurge(): void
    {
        $store = $this->store();
        $tester = $this->history($store);

        self::assertSame(ExitCode::SUCCESS, $tester->execute(['--json' => true]));
        self::assertCount(4, self::records($tester), 'no purge without the option');

        self::assertSame(ExitCode::SUCCESS, $tester->execute(['--purge' => null]));
        self::assertSame("purged 1 records older than 2026-08-30T12:00:00+00:00\n", $tester->getDisplay(), 'retention_days of the config');
        self::assertSame(3, $store->count());

        self::assertSame(ExitCode::SUCCESS, $tester->execute(['--purge' => '1']));
        self::assertSame("purged 0 records older than 2026-09-05T12:00:00+00:00\n", $tester->getDisplay(), 'the given days: everything left is newer');
        self::assertSame(3, $store->count());

        self::assertSame(ExitCode::INVALID, $tester->execute(['--purge' => 'soon']));
    }

    #[TestDox('status prints the lines of the runner; --json is the document of docs/status.schema.json')]
    public function testStatus(): void
    {
        $config = $this->config();
        $cache = new ArrayCache();
        $counter = new ForbiddenCounter($cache, 'app_', 5);
        $counter->hit('example.de');
        $tester = new CommandTester(new StatusCommand(new StatusRunner($config, StaticKeyProvider::fromConfig($config), $counter, 'cache.app (ArrayAdapter)', $this->store(), static fn(): array => ['queue' => 'redis'], new FrozenClock(self::NOW))));

        self::assertSame(ExitCode::SUCCESS, $tester->execute([]));
        $display = $tester->getDisplay();
        self::assertStringContainsString('enabled: yes, dry_run: no, environment: prod', $display);
        self::assertStringContainsString('dispatch: sync (queue: redis)', $display);
        self::assertStringContainsString('debounce: 600s, store cache.app (ArrayAdapter)', $display);
        self::assertStringContainsString('example.de: 1 consecutive 403 (escalates at 5)', $display);
        self::assertStringContainsString('history: 4 records; last successful submission', $display);

        self::assertSame(ExitCode::SUCCESS, $tester->execute(['--json' => true]));
        $decoded = json_decode($tester->getDisplay(), true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded);
        self::assertSame(['mode' => 'sync', 'adapter' => ['queue' => 'redis']], $decoded['dispatch']);
        self::assertSame(['per_url' => 600, 'store' => 'cache.app (ArrayAdapter)'], $decoded['debounce']);
        self::assertSame(4, $decoded['history']['records']);
    }
}
