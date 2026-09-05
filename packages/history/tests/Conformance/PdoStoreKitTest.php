<?php

declare(strict_types=1);

namespace IndexNowKit\History\Tests\Conformance;

use IndexNowKit\History\Pdo\PdoSubmissionStore;
use IndexNowKit\Submission\SubmissionStoreInterface;
use IndexNowKit\Testing\Conformance\SubmissionStoreConformanceTestCase;
use PDO;

final class PdoStoreKitTest extends SubmissionStoreConformanceTestCase
{
    protected function createStore(): SubmissionStoreInterface
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $store = new PdoSubmissionStore($pdo);
        $store->createTable();

        return $store;
    }

    protected function supportsPurge(): bool
    {
        return true;
    }
}
