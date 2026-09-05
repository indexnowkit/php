<?php

declare(strict_types=1);

namespace IndexNowKit\Testing\Tests\Conformance;

use IndexNowKit\Config;
use IndexNowKit\Debounce\NullDebounceStore;
use IndexNowKit\IndexNowKit;
use IndexNowKit\Testing\Conformance\CoreConformanceTestCase;
use IndexNowKit\Testing\FakeTransport;

/**
 * The kit run against the plain core facade: what an adapter's `tests/Conformance/CoreConformanceTest.php` looks
 * like with `IndexNowKit::create()` in place of the container. Green here means the scenarios and the facade agree;
 * an adapter that goes red runs the same scenarios against its own wiring.
 */
final class CoreConformanceKitTest extends CoreConformanceTestCase
{
    private const KEY = 'abcdef1234567890abcdef1234567890';
    private const SECOND_KEY = '0123456789abcdef0123456789abcdef';

    private ?IndexNowKit $kit = null;
    private ?FakeTransport $transport = null;

    protected function kit(): IndexNowKit
    {
        return $this->kit ??= IndexNowKit::create(
            new Config(key: self::KEY, hosts: ['example.de' => self::SECOND_KEY], baseUrl: 'https://www.example.com'),
            transport: $this->transport(),
            debounce: new NullDebounceStore(),
        );
    }

    protected function transport(): FakeTransport
    {
        return $this->transport ??= new FakeTransport();
    }

    protected function secondHost(): ?string
    {
        return 'example.de';
    }
}
