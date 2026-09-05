<?php

declare(strict_types=1);

namespace IndexNowKit\History\Console;

use DateTimeImmutable;
use DateTimeZone;
use Exception;
use IndexNowKit\Clock\SystemClock;
use IndexNowKit\Console\ExitCode;
use IndexNowKit\History\HistoryConfig;
use IndexNowKit\History\HistoryStoreInterface;
use IndexNowKit\ResultStatus;
use IndexNowKit\Submission\SubmissionRecord;
use IndexNowKit\Submission\SubmissionStoreInterface;
use IndexNowKit\Url\UrlNormalizerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;

/**
 * Body of `indexnow:history`: the records of any `SubmissionStoreInterface` as a table or JSON, filtered by host,
 * status, URL and time; `--purge` on a {@see HistoryStoreInterface}.
 */
final class HistoryRunner
{
    private const JSON_FLAGS = JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;

    private readonly ClockInterface $clock;

    /**
     * @param UrlNormalizerInterface|null $normalizer normalizes `--url` the way the submission did (null = as typed)
     */
    public function __construct(
        private readonly SubmissionStoreInterface $store,
        private readonly HistoryConfig $config,
        private readonly ?UrlNormalizerInterface $normalizer = null,
        ?ClockInterface $clock = null,
    ) {
        $this->clock = $clock ?? new SystemClock();
    }

    /**
     * @return int exit code ({@see ExitCode})
     */
    public function run(SymfonyStyle $io, HistoryOptions $options): int
    {
        if ($options->purge !== null && $options->purge !== false) {
            return $this->purge($io, $options->purge);
        }
        $status = null;
        if ($options->status !== null && $options->status !== '') {
            $status = ResultStatus::tryFrom(strtolower($options->status));
            if ($status === null) {
                $io->error(\sprintf('--status must be one of %s, got "%s".', implode(', ', array_map(static fn(ResultStatus $s): string => $s->value, ResultStatus::cases())), $options->status));

                return ExitCode::INVALID;
            }
        }
        try {
            $since = self::since($options->since, $this->clock->now());
        } catch (Exception $e) {
            $io->error(\sprintf('--since: %s', $e->getMessage()));

            return ExitCode::INVALID;
        }
        $limit = is_numeric($options->limit) ? max(1, (int) $options->limit) : 50;
        $records = $this->records($options, $status, $since, $limit);

        if ($options->json) {
            $io->writeln((string) json_encode(['records' => array_map(self::row(...), $records)], self::JSON_FLAGS));

            return ExitCode::SUCCESS;
        }
        if ($records === []) {
            $io->text('No records' . ($this->store instanceof HistoryStoreInterface && $this->store->count() === 0 ? ' yet: nothing was submitted since the history was switched on.' : ' match.'));

            return ExitCode::SUCCESS;
        }
        $rows = [];
        foreach ($records as $record) {
            foreach ($record->urls as $i => $url) {
                $rows[] = $i === 0
                    ? [$record->at->format('Y-m-d H:i:s'), $record->result->status->value, $record->result->reason !== null ? $record->result->reason->value : '', $record->result->engine, $record->result->httpCode !== null ? (string) $record->result->httpCode : '', $url]
                    : ['', '', '', '', '', $url];
            }
        }
        $io->table(['at (UTC)', 'status', 'reason', 'engine', 'http', 'url'], $rows);
        $io->text(\sprintf('%d record(s)%s. Machine-readable: --json.', \count($records), \count($records) >= $limit ? \sprintf(', the newest %d (--limit)', $limit) : ''));

        return ExitCode::SUCCESS;
    }

    /**
     * @return list<SubmissionRecord>
     */
    private function records(HistoryOptions $options, ?ResultStatus $status, ?DateTimeImmutable $since, int $limit): array
    {
        $host = $options->host !== null && $options->host !== '' ? strtolower($options->host) : null;
        $url = $options->url !== null && $options->url !== '' ? $options->url : null;
        if ($url !== null && $this->normalizer !== null) {
            try {
                $url = $this->normalizer->normalize($url);
            } catch (Throwable) {
                // as typed
            }
        }
        $records = [];
        // With --url or --since more records may be needed than the limit: read in pages of the store.
        $fetch = $url !== null || $since !== null ? max($limit * 10, 200) : $limit;
        foreach ($this->store->recent($fetch, $host, $status) as $record) {
            if ($since !== null && $record->at < $since) {
                continue;
            }
            if ($url !== null && !\in_array($url, $record->urls, true)) {
                continue;
            }
            $records[] = $record;
            if (\count($records) >= $limit) {
                break;
            }
        }

        return $records;
    }

    private function purge(SymfonyStyle $io, bool|string $purge): int
    {
        if (!$this->store instanceof HistoryStoreInterface) {
            $io->error(\sprintf('The store (%s) does not support purge: implement %s or run the retention in the store itself.', $this->store::class, HistoryStoreInterface::class));

            return ExitCode::FAILURE;
        }
        $days = \is_string($purge) && $purge !== '' ? $purge : $this->config->retentionDays;
        if (!is_numeric($days) || (int) $days < 1) {
            $io->error(\sprintf('--purge takes a number of days, got "%s".', (string) $days));

            return ExitCode::INVALID;
        }
        $olderThan = $this->clock->now()->setTimezone(new DateTimeZone('UTC'))->modify(\sprintf('-%d days', (int) $days));
        $removed = $this->store->purge($olderThan);
        $io->writeln(\sprintf('purged %d records older than %s', $removed, $olderThan->format(DATE_ATOM)));

        return ExitCode::SUCCESS;
    }

    /**
     * `2h`, `3d`, `45m`, `1w` mean that long ago; anything else is handed to DateTimeImmutable as it is.
     *
     * @throws Exception on an unparseable value
     */
    public static function since(?string $option, DateTimeImmutable $now): ?DateTimeImmutable
    {
        if ($option === null || $option === '') {
            return null;
        }
        if (preg_match('/^(\d+)\s*([mhdw])$/i', $option, $m) === 1) {
            $unit = ['m' => 'minutes', 'h' => 'hours', 'd' => 'days', 'w' => 'weeks'][strtolower($m[2])];

            return $now->modify(\sprintf('-%d %s', (int) $m[1], $unit));
        }

        return new DateTimeImmutable($option);
    }

    /**
     * @return array<string, mixed>
     */
    public static function row(SubmissionRecord $record): array
    {
        return [
            'at' => $record->at->format(DATE_ATOM),
            'status' => $record->result->status->value,
            'reason' => $record->result->reason?->value,
            'engine' => $record->result->engine,
            'http_status' => $record->result->httpCode,
            'error' => $record->result->error,
            'urls' => $record->urls,
        ];
    }
}
