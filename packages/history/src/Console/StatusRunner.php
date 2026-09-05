<?php

declare(strict_types=1);

namespace IndexNowKit\History\Console;

use Closure;
use IndexNowKit\Clock\SystemClock;
use IndexNowKit\Config;
use IndexNowKit\Console\ExitCode;
use IndexNowKit\History\Check\HistoryCheck;
use IndexNowKit\History\HistoryStoreInterface;
use IndexNowKit\Key\KeyProviderInterface;
use IndexNowKit\ResultStatus;
use IndexNowKit\Retry\ForbiddenCounter;
use IndexNowKit\Submission\SubmissionStoreInterface;
use IndexNowKit\Version;
use Psr\Clock\ClockInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;

/**
 * Body of `indexnow:status`: what the application is doing with IndexNow right now, read-only — switches,
 * environment, dispatch (plus what the adapter knows about its queue), the debounce store, the 403 counter of every
 * configured host with its escalation flag, the last successful submission, the history size, the core version.
 * `--json` follows docs/status.schema.json.
 */
final class StatusRunner
{
    private const JSON_FLAGS = JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;

    private readonly ClockInterface $clock;

    /**
     * @param string                                  $debounceStore a description of the debounce store (`cache.app (RedisAdapter)`, `memory`)
     * @param (Closure(): array<string, scalar|null>)|null $adapterFacts what the adapter adds to the dispatch section (`queue`, `connection`, `transport`)
     * @param SubmissionStoreInterface|null           $history       the history store; null when `history.store` is null
     */
    public function __construct(
        private readonly Config $config,
        private readonly KeyProviderInterface $keys,
        private readonly ForbiddenCounter $forbidden,
        private readonly string $debounceStore,
        private readonly ?SubmissionStoreInterface $history = null,
        private readonly ?Closure $adapterFacts = null,
        ?ClockInterface $clock = null,
    ) {
        $this->clock = $clock ?? new SystemClock();
    }

    /**
     * @return int exit code ({@see ExitCode})
     */
    public function run(SymfonyStyle $io, bool $json = false): int
    {
        $status = $this->status();
        if ($json) {
            $io->writeln((string) json_encode($status, self::JSON_FLAGS));

            return ExitCode::SUCCESS;
        }
        $io->title('IndexNow status');
        $lines = [
            \sprintf('enabled: %s, dry_run: %s, environment: %s', $status['enabled'] ? 'yes' : 'NO', $status['dry_run'] ? 'yes' : 'no', $status['environment'] ?? '-'),
            \sprintf('dispatch: %s%s', $status['dispatch']['mode'], $status['dispatch']['adapter'] === [] ? '' : ' (' . implode(', ', array_map(static fn(string $k, mixed $v): string => $k . ': ' . (\is_scalar($v) ? (string) $v : '-'), array_keys($status['dispatch']['adapter']), $status['dispatch']['adapter'])) . ')'),
            \sprintf('debounce: %s, store %s', $status['debounce']['per_url'] > 0 ? $status['debounce']['per_url'] . 's' : 'off', $status['debounce']['store']),
            \sprintf('engines: %s', implode(', ', $status['engines'])),
        ];
        foreach ($status['hosts'] as $host) {
            $lines[] = \sprintf('%s: %d consecutive 403%s (escalates at %d)', $host['host'], $host['forbidden'], $host['escalated'] ? ', ESCALATED — the key file is not being verified' : '', $status['forbidden_escalation']);
        }
        $last = $status['history']['last_success'];
        $lines[] = match (true) {
            $status['history']['store'] === null => 'history: not configured (history.store)',
            $status['history']['error'] !== null => 'history: ' . $status['history']['error'],
            $last === null => 'history: ' . ($status['history']['records'] !== null ? HistoryCheck::number($status['history']['records']) . ' records, ' : '') . 'no successful submission recorded',
            default => \sprintf('history: %s; last successful submission %s (%d URL%s, %s)', $status['history']['records'] !== null ? HistoryCheck::number($status['history']['records']) . ' records' : 'records: ?', HistoryCheck::ago((int) strtotime($last['at']), $this->clock->now()->getTimestamp()), $last['urls'], $last['urls'] === 1 ? '' : 's', $last['engine']),
        };
        $lines[] = 'core: ' . $status['core'];
        $io->listing($lines);

        return ExitCode::SUCCESS;
    }

    /**
     * @return array{enabled: bool, dry_run: bool, environment: ?string, dispatch: array{mode: string, adapter: array<string, scalar|null>}, debounce: array{per_url: int, store: string}, engines: list<string>, forbidden_escalation: int, hosts: list<array{host: string, forbidden: int, escalated: bool}>, history: array{store: ?string, records: ?int, last_success: ?array{at: string, urls: int, engine: string}, error: ?string}, core: string}
     */
    public function status(): array
    {
        $hosts = [];
        foreach ($this->keys->managedHosts() as $host) {
            $hosts[] = ['host' => $host, 'forbidden' => $this->forbidden->count($host), 'escalated' => $this->forbidden->isEscalated($host)];
        }
        $adapter = $this->adapterFacts === null ? [] : ($this->adapterFacts)();

        return [
            'enabled' => $this->config->enabled,
            'dry_run' => $this->config->dryRun,
            'environment' => $this->config->environment,
            'dispatch' => ['mode' => $this->config->dispatch, 'adapter' => $adapter],
            'debounce' => ['per_url' => $this->config->debouncePerUrl, 'store' => $this->debounceStore],
            'engines' => $this->config->engines,
            'forbidden_escalation' => $this->config->forbiddenEscalation,
            'hosts' => $hosts,
            'history' => $this->history(),
            'core' => Version::get(),
        ];
    }

    /**
     * @return array{store: ?string, records: ?int, last_success: ?array{at: string, urls: int, engine: string}, error: ?string}
     */
    private function history(): array
    {
        if ($this->history === null) {
            return ['store' => null, 'records' => null, 'last_success' => null, 'error' => null];
        }
        $store = $this->history instanceof HistoryStoreInterface ? 'history' : 'custom';
        try {
            $records = $this->history instanceof HistoryStoreInterface ? $this->history->count() : null;
            $last = null;
            foreach ($this->history->recent(1, null, ResultStatus::Ok) as $record) {
                $last = ['at' => $record->at->format(DATE_ATOM), 'urls' => \count($record->urls), 'engine' => $record->result->engine];
            }
        } catch (Throwable $e) {
            return ['store' => $store, 'records' => null, 'last_success' => null, 'error' => $e->getMessage()];
        }

        return ['store' => $store, 'records' => $records, 'last_success' => $last, 'error' => null];
    }
}
