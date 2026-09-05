<?php

declare(strict_types=1);

namespace IndexNowKit\History\Console;

/**
 * Raw command-line input of `history`, before validation.
 */
final class HistoryOptions
{
    /**
     * @param string|null      $since an ISO date or a relative interval (`2h`, `3d`, `1w`)
     * @param int|string       $limit records at most (the value of `--limit` as typed)
     * @param bool|string|null $purge null = no purge; true/"" = older than `history.retention_days`; a number = that many days
     */
    public function __construct(
        public readonly ?string $host = null,
        public readonly ?string $status = null,
        public readonly ?string $url = null,
        public readonly ?string $since = null,
        public readonly int|string $limit = 50,
        public readonly bool $json = false,
        public readonly bool|string|null $purge = null,
    ) {}
}
