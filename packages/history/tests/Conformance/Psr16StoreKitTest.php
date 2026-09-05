<?php

declare(strict_types=1);

namespace IndexNowKit\History\Tests\Conformance;

use IndexNowKit\History\Psr16SubmissionStore;
use IndexNowKit\History\Tests\Support\ArrayCache;
use IndexNowKit\Submission\SubmissionStoreInterface;
use IndexNowKit\Testing\Conformance\SubmissionStoreConformanceTestCase;

final class Psr16StoreKitTest extends SubmissionStoreConformanceTestCase
{
    protected function createStore(): SubmissionStoreInterface
    {
        return new Psr16SubmissionStore(new ArrayCache(), 'app_', 50);
    }

    protected function supportsPurge(): bool
    {
        return true;
    }
}
