<?php

declare(strict_types=1);

namespace IndexNowKit\History\Tests\Unit;

use DateTimeImmutable;
use IndexNowKit\Config;
use IndexNowKit\Console\ExitCode;
use IndexNowKit\History\Console\Definitions;
use IndexNowKit\History\Console\HistoryOptions;
use IndexNowKit\History\Console\HistoryRunner;
use IndexNowKit\History\Console\StatusRunner;
use IndexNowKit\History\HistoryConfig;
use IndexNowKit\History\Psr16SubmissionStore;
use IndexNowKit\History\Tests\Support\ArrayCache;
use IndexNowKit\Key\StaticKeyProvider;
use IndexNowKit\Reason;
use IndexNowKit\Result;
use IndexNowKit\ResultStatus;
use IndexNowKit\Retry\ForbiddenCounter;
use IndexNowKit\Submission\NullSubmissionStore;
use IndexNowKit\Submission\SubmissionRecord;
use IndexNowKit\Submission\SubmissionStoreInterface;
use IndexNowKit\Testing\FrozenClock;
use IndexNowKit\Url\UrlNormalizerFactory;
use JsonSchema\Validator;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionNamedType;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Style\SymfonyStyle;

final class RunnersTest extends TestCase
{
    private const KEY = 'abcdef1234567890abcdef1234567890';
    private const NOW = '2026-09-06 12:00:00';

    private BufferedOutput $output;

    protected function setUp(): void
    {
        $this->output = new BufferedOutput();
    }

    private function io(): SymfonyStyle
    {
        return new SymfonyStyle(new ArrayInput([]), $this->output);
    }

    private function store(): Psr16SubmissionStore
    {
        $store = new Psr16SubmissionStore(new ArrayCache(), 'app_');
        $store->record(Result::ok('api', 'www.example.com', ['https://www.example.com/a', 'https://www.example.com/b'], 200, 'e'), new DateTimeImmutable('2026-09-06 09:00:00'));
        $store->record(Result::failed('api', 'example.de', ['https://example.de/c'], Reason::RateLimited, null, 429, true, 30, 'e'), new DateTimeImmutable('2026-09-06 11:00:00'));
        $store->record(Result::skipped('www.example.com', ['https://www.example.com/a'], Reason::Debounced), new DateTimeImmutable('2026-09-06 11:30:00'));

        return $store;
    }

    private function runner(SubmissionStoreInterface $store): HistoryRunner
    {
        $config = Config::fromArray(['key' => self::KEY, 'base_url' => 'https://www.example.com']);

        return new HistoryRunner($store, HistoryConfig::fromArray(['store' => 'psr16', 'retention_days' => 7]), UrlNormalizerFactory::fromConfig($config), new FrozenClock(self::NOW));
    }

    #[TestDox('history() covers every constructor parameter of HistoryOptions, in order; status() has --json')]
    public function testDefinitions(): void
    {
        $parameters = array_map(static fn($p): string => $p->getName(), (new ReflectionClass(HistoryOptions::class))->getConstructor()?->getParameters() ?? []);
        self::assertSame($parameters, array_map(static fn($o): string => $o->property(), Definitions::history()->options));
        self::assertSame(['json'], array_map(static fn($o): string => $o->property(), Definitions::status()->options));
        self::assertInstanceOf(ReflectionNamedType::class, (new ReflectionClass(HistoryOptions::class))->getProperty('json')->getType());
    }

    #[TestDox('history: the table newest first, one URL per line; --host, --status, --url (normalized), --since relative and ISO, --limit; --json')]
    public function testHistory(): void
    {
        $runner = $this->runner($this->store());

        self::assertSame(ExitCode::SUCCESS, $runner->run($this->io(), new HistoryOptions()));
        $display = $this->output->fetch();
        $lines = array_values(array_filter(array_map('trim', explode("\n", $display)), static fn(string $l): bool => str_contains($l, 'https://')));
        self::assertCount(4, $lines, 'one URL per line');
        self::assertStringContainsString('2026-09-06 11:30:00', $lines[0]);
        self::assertStringContainsString('debounced', $lines[0]);
        self::assertStringContainsString('2026-09-06 11:00:00', $lines[1]);
        self::assertStringContainsString('429', $lines[1]);
        self::assertStringContainsString('https://www.example.com/a', $lines[2]);
        self::assertStringNotContainsString('2026', $lines[3], 'the second URL of a record is a continuation line');
        self::assertStringContainsString('https://www.example.com/b', $lines[3]);
        self::assertStringContainsString('3 record(s)', $display);

        $runner->run($this->io(), new HistoryOptions(host: 'example.de', json: true));
        $decoded = json_decode($this->output->fetch(), true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded);
        self::assertSame([['at' => '2026-09-06T11:00:00+00:00', 'status' => 'failed', 'reason' => 'rate_limited', 'engine' => 'api', 'http_status' => 429, 'error' => 'Rate limited (429).', 'urls' => ['https://example.de/c']]], $decoded['records']);

        $runner->run($this->io(), new HistoryOptions(status: 'ok', json: true));
        self::assertCount(1, json_decode($this->output->fetch(), true, flags: JSON_THROW_ON_ERROR)['records'] ?? []);
        $runner->run($this->io(), new HistoryOptions(url: 'https://www.example.com/a?utm_source=x', json: true));
        self::assertSame(['skipped', 'ok'], array_column(json_decode($this->output->fetch(), true, flags: JSON_THROW_ON_ERROR)['records'] ?? [], 'status'), '--url is normalized like a submission and matches every record naming it');
        $runner->run($this->io(), new HistoryOptions(since: '2h', json: true));
        self::assertCount(2, json_decode($this->output->fetch(), true, flags: JSON_THROW_ON_ERROR)['records'] ?? [], 'not older than two hours before ' . self::NOW);
        $runner->run($this->io(), new HistoryOptions(since: '2026-09-06T11:15:00+00:00', json: true));
        self::assertCount(1, json_decode($this->output->fetch(), true, flags: JSON_THROW_ON_ERROR)['records'] ?? []);
        $runner->run($this->io(), new HistoryOptions(limit: '1', json: true));
        self::assertCount(1, json_decode($this->output->fetch(), true, flags: JSON_THROW_ON_ERROR)['records'] ?? []);

        self::assertSame(ExitCode::INVALID, $runner->run($this->io(), new HistoryOptions(status: 'maybe')));
        self::assertStringContainsString('--status must be one of ok, pending, failed, skipped', $this->output->fetch());
        self::assertSame(ExitCode::INVALID, $runner->run($this->io(), new HistoryOptions(since: 'yesterday-ish')));
        self::assertStringContainsString('--since:', $this->output->fetch());
    }

    #[TestDox('--purge removes what is older than retention_days (or the given days) and prints one line; a store without purge() is refused')]
    public function testPurge(): void
    {
        $store = $this->store();
        $store->record(Result::ok('api', 'www.example.com', ['https://www.example.com/old'], 200, 'e'), new DateTimeImmutable('2026-08-01 09:00:00'));
        $runner = $this->runner($store);

        self::assertSame(ExitCode::SUCCESS, $runner->run($this->io(), new HistoryOptions(purge: true)));
        self::assertSame("purged 1 records older than 2026-08-30T12:00:00+00:00\n", $this->output->fetch());
        self::assertSame(ExitCode::SUCCESS, $runner->run($this->io(), new HistoryOptions(purge: '1')));
        self::assertSame("purged 0 records older than 2026-09-05T12:00:00+00:00\n", $this->output->fetch(), 'a number of days');
        self::assertSame(ExitCode::INVALID, $runner->run($this->io(), new HistoryOptions(purge: 'soon')));
        self::assertSame(ExitCode::INVALID, $runner->run($this->io(), new HistoryOptions(purge: '0')));

        $plain = new class implements SubmissionStoreInterface {
            public function record(Result $result, DateTimeImmutable $at): void {}

            public function recent(int $limit = 100, ?string $host = null, ?ResultStatus $status = null): iterable
            {
                return [];
            }

            public function lastFor(string $url): ?SubmissionRecord
            {
                return null;
            }
        };
        self::assertSame(ExitCode::FAILURE, $this->runner($plain)->run($this->io(), new HistoryOptions(purge: true)));
        self::assertStringContainsString('does not support purge', $this->output->fetch());
        self::assertSame(ExitCode::SUCCESS, $this->runner($plain)->run($this->io(), new HistoryOptions()));
        self::assertStringContainsString('No records match.', $this->output->fetch());
        self::assertSame(ExitCode::SUCCESS, $this->runner(new Psr16SubmissionStore(new ArrayCache()))->run($this->io(), new HistoryOptions()));
        self::assertStringContainsString('No records yet', $this->output->fetch());
    }

    #[TestDox('status: the switches, dispatch with the adapter facts, debounce, the 403 counters per host, the last successful submission; --json validates against docs/status.schema.json')]
    public function testStatus(): void
    {
        $config = Config::fromArray(['key' => self::KEY, 'base_url' => 'https://www.example.com', 'hosts' => ['example.de' => self::KEY], 'dispatch' => 'sync', 'environment' => 'prod', 'debounce' => ['per_url' => 600, 'key_prefix' => 'app_']]);
        $cache = new ArrayCache();
        $counter = new ForbiddenCounter($cache, 'app_', 5);
        $counter->hit('example.de');
        $counter->hit('example.de');
        $store = $this->store();
        $runner = new StatusRunner($config, StaticKeyProvider::fromConfig($config), $counter, 'cache.app (ArrayAdapter)', $store, static fn(): array => ['queue' => 'redis', 'connection' => 'default'], new FrozenClock(self::NOW));

        self::assertSame(ExitCode::SUCCESS, $runner->run($this->io()));
        $display = $this->output->fetch();
        self::assertStringContainsString('enabled: yes, dry_run: no, environment: prod', $display);
        self::assertStringContainsString('dispatch: sync (queue: redis, connection: default)', $display);
        self::assertStringContainsString('debounce: 600s, store cache.app (ArrayAdapter)', $display);
        self::assertStringContainsString('example.de: 2 consecutive 403 (escalates at 5)', $display);
        self::assertStringContainsString('www.example.com: 0 consecutive 403', $display);
        self::assertStringContainsString('history: 3 records; last successful submission 3 h ago (2 URLs, api)', $display);

        self::assertSame(ExitCode::SUCCESS, $runner->run($this->io(), true));
        $json = $this->output->fetch();
        $decoded = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded);
        self::assertSame([['host' => 'example.de', 'forbidden' => 2, 'escalated' => false], ['host' => 'www.example.com', 'forbidden' => 0, 'escalated' => false]], $decoded['hosts']);
        self::assertSame(['store' => 'history', 'records' => 3, 'last_success' => ['at' => '2026-09-06T09:00:00+00:00', 'urls' => 2, 'engine' => 'api'], 'error' => null], $decoded['history']);
        $validator = new Validator();
        $document = json_decode($json);
        $validator->validate($document, json_decode((string) file_get_contents(__DIR__ . '/../../docs/status.schema.json')));
        self::assertTrue($validator->isValid(), json_encode($validator->getErrors(), JSON_PRETTY_PRINT) ?: '');

        $none = new StatusRunner($config, StaticKeyProvider::fromConfig($config), new ForbiddenCounter(null, 'app_', 5), 'memory');
        $none->run($this->io(), true);
        $decoded = json_decode($this->output->fetch(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame(['store' => null, 'records' => null, 'last_success' => null, 'error' => null], $decoded['history'] ?? null);
        $none->run($this->io());
        self::assertStringContainsString('history: not configured (history.store)', $this->output->fetch());

        $null = new StatusRunner($config, StaticKeyProvider::fromConfig($config), $counter, 'memory', new NullSubmissionStore());
        $null->run($this->io(), true);
        $decoded = json_decode($this->output->fetch(), true, flags: JSON_THROW_ON_ERROR);
        self::assertArrayHasKey('store', $decoded['history']);
        self::assertNull($decoded['history']['store'], 'the core null store is "no store", not a custom one');

        $custom = new StatusRunner($config, StaticKeyProvider::fromConfig($config), $counter, 'memory', new class implements SubmissionStoreInterface {
            public function record(Result $result, DateTimeImmutable $at): void {}

            public function recent(int $limit = 100, ?string $host = null, ?ResultStatus $status = null): iterable
            {
                return [];
            }

            public function lastFor(string $url): ?SubmissionRecord
            {
                return null;
            }
        });
        $custom->run($this->io(), true);
        $decoded = json_decode($this->output->fetch(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame('custom', $decoded['history']['store'] ?? null);
        self::assertArrayHasKey('records', $decoded['history']);
        self::assertNull($decoded['history']['records']);
    }
}
