<?php

declare(strict_types=1);

namespace IndexNowKit\Testing\Tests\Unit;

use IndexNowKit\Testing\Conformance\ReadmeAssertions;
use PHPUnit\Framework\TestCase;

/**
 * The "Notes for AI assistants" section of the core's README (EN and RU): present, with a complete snippet, naming
 * only commands and configuration keys that exist (spec 17 §3.1). The test lives here and not in the core because
 * the core cannot depend on this package (spec 17 §4.1: the split of the core aliases the previous minor until it is
 * tagged, so `require-dev: indexnowkit/testing` there would be a bootstrap cycle); it runs in the monorepo, where the
 * core is the sibling directory, and is skipped in the split repository.
 */
final class CoreReadmeAiNotesTest extends TestCase
{
    public function testTheNotesForAiAssistantsOfTheCoreAreConsistentWithTheCode(): void
    {
        $core = \dirname(__DIR__, 3) . '/core';
        if (!is_file($core . '/README.md')) {
            self::markTestSkipped('the core is not next to this package (split repository): the monorepo runs this test');
        }

        ReadmeAssertions::assertAiNotes($core, [], []);
    }
}
