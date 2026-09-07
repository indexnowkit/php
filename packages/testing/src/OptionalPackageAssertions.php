<?php

declare(strict_types=1);

namespace IndexNowKit\Testing\Conformance;

use IndexNowKit\Adapter\OptionalPackage;
use PHPUnit\Framework\Assert;

/**
 * What an adapter's `check` must print about `indexnowkit/sitemap`, `indexnowkit/verify` and `indexnowkit/history`
 * when the predicate is left to detection (`installed: null`): the not-installed line of every package that is
 * physically absent, no such line for one that is present. The adapters' own "not installed" tests pass `false` with
 * the packages still in `vendor/`, which proves the texts, not the boot: an adapter that loaded a class of the
 * package to ask whether it is installed booted fine there and was a fatal in an application without it. The CI job
 * `optional-packages-absent` removes the three packages and runs the detection test of each adapter with
 * `INDEXNOWKIT_OPTIONAL_PACKAGES=absent`, which makes {@see assertDetected()} fail unless all three are really gone.
 */
final class OptionalPackageAssertions
{
    /** The environment variable the `optional-packages-absent` CI job sets; `absent` = the three packages were removed. */
    public const ENV = 'INDEXNOWKIT_OPTIONAL_PACKAGES';

    /** @return list<OptionalPackage> the three predicates, detection on */
    public static function packages(): array
    {
        return [OptionalPackage::sitemap(), OptionalPackage::verify(), OptionalPackage::history()];
    }

    /** Whether the test runs where the packages were removed (the CI job), so their absence is an assertion, not a branch. */
    public static function expectAbsent(): bool
    {
        return getenv(self::ENV) === 'absent';
    }

    /**
     * The `check` output names exactly the absent packages as not installed (with the install command), and none of
     * the present ones; with {@see ENV} = `absent`, every package must be absent.
     */
    public static function assertDetected(string $checkOutput): void
    {
        foreach (self::packages() as $package) {
            $line = \sprintf('%s: not installed', $package->feature);
            if (self::expectAbsent()) {
                Assert::assertFalse(class_exists($package->marker), \sprintf('%s=absent, but %s is loadable: the job did not remove %s', self::ENV, $package->marker, $package->package));
            }
            if ($package->installed()) {
                Assert::assertStringNotContainsString($line, $checkOutput, \sprintf('%s is installed', $package->package));
                continue;
            }
            Assert::assertStringContainsString($line, $checkOutput, \sprintf('%s is absent', $package->package));
            Assert::assertStringContainsString(\sprintf('composer require %s', $package->package), $checkOutput);
        }
    }
}
