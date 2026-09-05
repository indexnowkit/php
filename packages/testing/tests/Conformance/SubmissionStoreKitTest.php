<?php

declare(strict_types=1);

namespace IndexNowKit\Testing\Tests\Conformance;

use DateTimeImmutable;
use DateTimeInterface;
use IndexNowKit\Result;
use IndexNowKit\ResultStatus;
use IndexNowKit\Submission\SubmissionRecord;
use IndexNowKit\Submission\SubmissionStoreInterface;
use IndexNowKit\Testing\Conformance\SubmissionStoreConformanceTestCase;

/**
 * The kit against a minimal in-memory store: the reference every real store (indexnowkit/history) is measured
 * against, and the proof the kit's expectations are satisfiable.
 */
final class SubmissionStoreKitTest extends SubmissionStoreConformanceTestCase
{
    protected function createStore(): SubmissionStoreInterface
    {
        return new class implements SubmissionStoreInterface {
            /** @var list<SubmissionRecord> */
            private array $records = [];

            public function record(Result $result, DateTimeImmutable $at): void
            {
                $this->records[] = SubmissionRecord::of($result, $at);
            }

            public function recent(int $limit = 100, ?string $host = null, ?ResultStatus $status = null): iterable
            {
                $records = array_reverse($this->records);
                $records = array_filter($records, static fn(SubmissionRecord $r): bool => ($host === null || $r->result->host === $host) && ($status === null || $r->result->status === $status));

                return \array_slice(array_values($records), 0, $limit);
            }

            public function lastFor(string $url): ?SubmissionRecord
            {
                foreach (array_reverse($this->records) as $record) {
                    if (\in_array($url, $record->urls, true)) {
                        return $record;
                    }
                }

                return null;
            }

            public function purge(DateTimeInterface $olderThan): int
            {
                $before = \count($this->records);
                $this->records = array_values(array_filter($this->records, static fn(SubmissionRecord $r): bool => $r->at >= $olderThan));

                return $before - \count($this->records);
            }
        };
    }

    protected function supportsPurge(): bool
    {
        return true;
    }
}
