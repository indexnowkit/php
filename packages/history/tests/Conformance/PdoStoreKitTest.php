<?php

declare(strict_types=1);

namespace IndexNowKit\History\Tests\Conformance;

use Closure;
use IndexNowKit\History\Pdo\PdoSubmissionStore;
use IndexNowKit\Submission\SubmissionStoreInterface;
use IndexNowKit\Testing\Conformance\SubmissionStoreConformanceTestCase;
use PDO;

/**
 * S01–S08 against `PdoSubmissionStore`: on `sqlite::memory:` by default; CI also runs it against MySQL and PostgreSQL with
 * `INDEXNOWKIT_PDO_DSN` (and `INDEXNOWKIT_PDO_USER` / `INDEXNOWKIT_PDO_PASSWORD`) set, so the DDL of `Schema::sql()` and the
 * DML of the store are executed on every driver, not compared as strings.
 */
final class PdoStoreKitTest extends SubmissionStoreConformanceTestCase
{
    protected function createStore(): SubmissionStoreInterface
    {
        $dsn = getenv('INDEXNOWKIT_PDO_DSN');
        $pdo = \is_string($dsn) && $dsn !== ''
            ? new PDO($dsn, getenv('INDEXNOWKIT_PDO_USER') ?: null, getenv('INDEXNOWKIT_PDO_PASSWORD') ?: null)
            : new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $table = 'indexnow_submissions_' . bin2hex(random_bytes(4)); // a table per test on a shared server
        $store = new PdoSubmissionStore($pdo, $table);
        $store->createTable();
        $this->cleanup = static function () use ($pdo, $table): void {
            $pdo->exec('DROP TABLE ' . $table);
        };

        return $store;
    }

    /** @var (Closure(): void)|null */
    private ?Closure $cleanup = null;

    protected function tearDown(): void
    {
        if ($this->cleanup !== null) {
            ($this->cleanup)();
            $this->cleanup = null;
        }
        parent::tearDown();
    }

    protected function supportsPurge(): bool
    {
        return true;
    }
}
