<?php

declare(strict_types=1);

namespace IndexNowKit\Testing\Tests\Unit;

use IndexNowKit\Testing\Conformance\Arrays;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

/**
 * The merge semantics the adapters' test fixtures rely on: an option block merges, a list replaces.
 */
final class ArraysTest extends TestCase
{
    #[TestDox('merge: a nested associative block merges key by key, keys only in the base stay')]
    public function testAssociativeBlocksMerge(): void
    {
        $base = ['key' => 'abcdefgh', 'debounce' => ['per_url' => 600, 'store' => 'memory'], 'retry' => ['max_attempts' => 3]];

        $merged = Arrays::merge($base, ['debounce' => ['per_url' => 5], 'dry_run' => true]);

        self::assertSame(['key' => 'abcdefgh', 'debounce' => ['per_url' => 5, 'store' => 'memory'], 'retry' => ['max_attempts' => 3], 'dry_run' => true], $merged);
    }

    #[TestDox('merge: a list replaces the base value as a whole, it is not appended')]
    public function testListsReplace(): void
    {
        $merged = Arrays::merge(['engines' => ['yandex', 'bing'], 'hosts' => ['a.example' => 'k1']], ['engines' => ['api'], 'hosts' => ['b.example' => 'k2']]);

        self::assertSame(['engines' => ['api'], 'hosts' => ['a.example' => 'k1', 'b.example' => 'k2']], $merged, 'engines is a list and replaces; hosts is associative and merges');
    }

    #[TestDox('merge: an empty array replaces the base block instead of leaving it untouched')]
    public function testEmptyArrayReplaces(): void
    {
        self::assertSame(['logging' => []], Arrays::merge(['logging' => ['levels' => ['ok' => 'info']]], ['logging' => []]));
    }

    #[TestDox('merge: null and scalars replace a block, a block replaces a scalar')]
    public function testScalarsAndNullReplace(): void
    {
        self::assertSame(['http' => null, 'dry_run' => false], Arrays::merge(['http' => ['timeout' => 10.0], 'dry_run' => true], ['http' => null, 'dry_run' => false]));
        self::assertSame(['http' => ['timeout' => 1.0]], Arrays::merge(['http' => 'default'], ['http' => ['timeout' => 1.0]]));
    }

    #[TestDox('merge: recursion goes as deep as the blocks do')]
    public function testDeepMerge(): void
    {
        $merged = Arrays::merge(['a' => ['b' => ['c' => 1, 'd' => 2]]], ['a' => ['b' => ['d' => 3, 'e' => 4]]]);

        self::assertSame(['a' => ['b' => ['c' => 1, 'd' => 3, 'e' => 4]]], $merged);
    }

    #[TestDox('merge: the base is not modified and empty overrides return it as it is')]
    public function testBaseIsUntouched(): void
    {
        $base = ['key' => 'abcdefgh', 'debounce' => ['per_url' => 600]];

        self::assertSame($base, Arrays::merge($base, []));
        Arrays::merge($base, ['debounce' => ['per_url' => 1]]);
        self::assertSame(600, $base['debounce']['per_url']);
    }
}
