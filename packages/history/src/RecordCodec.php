<?php

declare(strict_types=1);

namespace IndexNowKit\History;

use DateTimeImmutable;
use DateTimeZone;
use IndexNowKit\Reason;
use IndexNowKit\Result;
use IndexNowKit\ResultStatus;
use IndexNowKit\Submission\SubmissionRecord;

/**
 * A `SubmissionRecord` as a flat array and back: the shape both stores keep (PSR-16 as one value, PDO as the
 * columns of a row). What is written is what the record carries and nothing else — the URLs after the normalizer,
 * the engine, host, status, reason, HTTP code and `Result::$error` (cut to {@see MAX_ERROR} characters; never a
 * response body, a header or the key).
 *
 * @internal shared by the stores of this package
 *
 * @phpstan-type Row array{urls: list<string>, engine: string, host: string, status: string, reason: ?string, http_status: ?int, error: ?string, retryable: bool, endpoint: string, at: string}
 */
final class RecordCodec
{
    public const MAX_ERROR = 1000;
    public const AT_FORMAT = 'Y-m-d H:i:s';

    private function __construct() {}

    /**
     * @return Row
     */
    public static function encode(Result $result, DateTimeImmutable $at): array
    {
        return [
            'urls' => $result->urls,
            'engine' => $result->engine,
            'host' => $result->host,
            'status' => $result->status->value,
            'reason' => $result->reason?->value,
            'http_status' => $result->httpCode,
            'error' => $result->error === null ? null : mb_substr($result->error, 0, self::MAX_ERROR),
            'retryable' => $result->retryable,
            'endpoint' => $result->endpoint,
            'at' => $at->setTimezone(new DateTimeZone('UTC'))->format(self::AT_FORMAT),
        ];
    }

    /**
     * @param Row $row
     */
    public static function decode(array $row): SubmissionRecord
    {
        $status = ResultStatus::from($row['status']);
        $result = new Result($row['engine'], $row['host'], $row['urls'], $status, $row['http_status'], $row['error'], $row['retryable'], null, $row['endpoint'], $row['reason'] === null ? null : Reason::tryFrom($row['reason']));

        return new SubmissionRecord($row['urls'], $result, self::at($row['at']));
    }

    public static function at(string $value): DateTimeImmutable
    {
        return new DateTimeImmutable($value, new DateTimeZone('UTC'));
    }
}
