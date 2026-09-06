<?php

declare(strict_types=1);

namespace IndexNowKit\Testing\Tests\Unit;

use FilesystemIterator;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * The conformance identifiers are a cross-language contract (spec 17 §7): C01–C22 (the core suite, `core/tests/Conformance`;
 * the adapter kit re-runs eleven of them), A01–A21 with A05b/A05c/A10b (`OrmConformanceTestCase`), S01–S08
 * (`SubmissionStoreConformanceTestCase`), H01–H06 (one `HttpConformanceTest` per framework adapter). Every id is a
 * `#[TestDox('Xnn …')]`, each exactly once per suite, none missing, none beyond the frozen range. Suites that are not next
 * door (a split repository) are skipped.
 */
final class ConformanceIdsTest extends TestCase
{
    #[TestDox('the kits define A01–A21 and S01–S08 once each, and only C ids from the core range')]
    public function testKits(): void
    {
        $ids = self::ids(\dirname(__DIR__, 2) . '/src');
        self::assertSame(self::range('A', 21, ['A05b', 'A05c', 'A10b']), self::sorted($ids, 'A'));
        self::assertSame(self::range('S', 8), self::sorted($ids, 'S'));
        foreach (self::sorted($ids, 'C') as $id) {
            self::assertContains($id, self::range('C', 22), $id . ' is beyond the frozen core range');
        }
        self::assertSame(array_unique($ids), $ids, 'no id is defined twice in the kits');
    }

    #[TestDox('the core suite defines C01–C22 once each')]
    public function testCore(): void
    {
        $dir = \dirname(__DIR__, 3) . '/core/tests/Conformance';
        if (!is_dir($dir)) {
            self::markTestSkipped('the core tests are not next door (split repository)');
        }
        $ids = self::ids($dir);
        self::assertSame(self::range('C', 22), self::sorted($ids, 'C'));
        self::assertSame(array_unique($ids), $ids);
    }

    #[TestDox('every framework adapter defines H01–H06 once each')]
    public function testAdapters(): void
    {
        $checked = 0;
        foreach (['symfony-bundle', 'laravel', 'yii2'] as $adapter) {
            $dir = \dirname(__DIR__, 3) . '/' . $adapter . '/tests';
            if (!is_dir($dir)) {
                continue;
            }
            ++$checked;
            $ids = self::sorted(self::ids($dir), 'H');
            self::assertSame(array_unique($ids), $ids, $adapter . ': an H id is defined twice');
            foreach (self::range('H', 6) as $id) {
                self::assertContains($id, $ids, $adapter . ' lacks ' . $id);
            }
            foreach ($ids as $id) {
                self::assertLessThanOrEqual(6, (int) substr($id, 1, 2), $adapter . ': ' . $id . ' is beyond the frozen range (H01b-style variants of an existing id are fine)');
            }
        }
        if ($checked === 0) {
            self::markTestSkipped('no adapter tests next door (split repository)');
        }
    }

    /**
     * @param list<string> $extra
     *
     * @return list<string>
     */
    private static function range(string $prefix, int $count, array $extra = []): array
    {
        $out = [];
        for ($n = 1; $n <= $count; ++$n) {
            $out[] = \sprintf('%s%02d', $prefix, $n);
        }
        $out = [...$out, ...$extra];
        sort($out);

        return $out;
    }

    /**
     * @param list<string> $ids
     *
     * @return list<string>
     */
    private static function sorted(array $ids, string $prefix): array
    {
        $out = array_values(array_filter($ids, static fn(string $id): bool => $id[0] === $prefix));
        sort($out);

        return $out;
    }

    /**
     * The TestDox ids of every PHP file under $dir, in file order (duplicates kept).
     *
     * @return list<string>
     */
    private static function ids(string $dir): array
    {
        $ids = [];
        $files = [];
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)) as $file) {
            if ($file instanceof SplFileInfo && $file->getExtension() === 'php' && !str_contains($file->getPathname(), '/vendor/')) {
                $files[] = $file->getPathname();
            }
        }
        sort($files);
        foreach ($files as $file) {
            preg_match_all("/#\\[TestDox\\('((?:[^'\\\\]|\\\\.)*)'/", (string) file_get_contents($file), $dox);
            foreach ($dox[1] as $text) {
                preg_match_all('/(?<![A-Za-z0-9])([CAHS]\\d{2}[bc]?)(?![A-Za-z0-9])/', $text, $found);
                $ids = [...$ids, ...$found[1]];
            }
        }

        return $ids;
    }
}
