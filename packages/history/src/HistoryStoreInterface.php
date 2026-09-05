<?php

declare(strict_types=1);

namespace IndexNowKit\History;

use DateTimeInterface;
use IndexNowKit\Submission\SubmissionRecord;
use IndexNowKit\Submission\SubmissionStoreInterface;

/**
 * What the stores of this package add to the core's `SubmissionStoreInterface`: counting, the last record and
 * retention. The `history` command and the profiler work with any `SubmissionStoreInterface` (`recent()` only);
 * `count()`, `last()` and `purge()` are used when the store is one of these — a custom store of the core loses
 * nothing (`check` prints `history: custom store (<class>)`, `--purge` refuses).
 */
interface HistoryStoreInterface extends SubmissionStoreInterface
{
    /** Records kept, for one host or all. */
    public function count(?string $host = null): int;

    /** The newest record of any status, null when empty. */
    public function last(): ?SubmissionRecord;

    /** Removes the records older than $olderThan; the number removed. */
    public function purge(DateTimeInterface $olderThan): int;
}
