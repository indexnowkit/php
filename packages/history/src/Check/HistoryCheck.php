<?php

declare(strict_types=1);

namespace IndexNowKit\History\Check;

use IndexNowKit\Check\CheckInterface;
use IndexNowKit\Check\CheckReport;
use IndexNowKit\Clock\SystemClock;
use IndexNowKit\History\HistoryConfig;
use IndexNowKit\History\HistoryStoreInterface;
use IndexNowKit\Submission\SubmissionStoreInterface;
use Psr\Clock\ClockInterface;
use Throwable;

/**
 * The `history` lines of `check`: `history.store` (the configured store, or none, or an exception with the migration
 * hint) and `history.records` (`history: 1 240 records, last 3 min ago`), for any `SubmissionStoreInterface`.
 */
final class HistoryCheck implements CheckInterface
{
    public const CODE_STORE = 'history.store';
    public const CODE_RECORDS = 'history.records';

    private readonly ClockInterface $clock;

    /**
     * @param SubmissionStoreInterface|null $store null = `history.store` is null (nothing is recorded)
     */
    public function __construct(private readonly HistoryConfig $config, private readonly ?SubmissionStoreInterface $store, ?ClockInterface $clock = null)
    {
        $this->clock = $clock ?? new SystemClock();
    }

    public function check(CheckReport $report): void
    {
        if ($this->store === null) {
            $report->ok('history: installed, no store configured (history.store)', self::CODE_STORE);

            return;
        }
        if (!$this->store instanceof HistoryStoreInterface) {
            $report->ok(\sprintf('history: custom store (%s)', $this->store::class), self::CODE_STORE);

            return;
        }
        try {
            $count = $this->store->count();
            $last = $this->store->last();
        } catch (Throwable $e) {
            $report->error(\sprintf('history: the %s store failed: %s. Run the migration of docs/migrations.md (table %s) or fix history.pdo.*.', (string) $this->config->store, $e->getMessage(), $this->config->pdoTable), self::CODE_STORE);

            return;
        }
        $report->ok(\sprintf('history: %s store%s', (string) $this->config->store, $this->config->store === HistoryConfig::STORE_PDO ? ' (' . $this->config->pdoTable . ')' : \sprintf(' (%d records kept)', $this->config->limit)), self::CODE_STORE);
        if ($count === 0 || $last === null) {
            $report->ok('history: no records yet', self::CODE_RECORDS);

            return;
        }
        $report->ok(\sprintf('history: %s records, last %s', self::number($count), self::ago($last->at->getTimestamp(), $this->clock->now()->getTimestamp())), self::CODE_RECORDS);
    }

    public static function number(int $n): string
    {
        return number_format($n, 0, '.', ' ');
    }

    public static function ago(int $then, int $now): string
    {
        $seconds = max(0, $now - $then);

        return match (true) {
            $seconds < 60 => $seconds . ' s ago',
            $seconds < 3600 => intdiv($seconds, 60) . ' min ago',
            $seconds < 86400 => intdiv($seconds, 3600) . ' h ago',
            default => intdiv($seconds, 86400) . ' d ago',
        };
    }
}
