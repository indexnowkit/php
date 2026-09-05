<?php

declare(strict_types=1);

namespace IndexNowKit\History\Tests\Unit;

use IndexNowKit\History\HistoryConfig;
use IndexNowKit\Testing\Conformance\ReadmeAssertions;
use PHPUnit\Framework\TestCase;

/**
 * The "Notes for AI assistants" section of the README (EN and RU): present, with a complete snippet, naming only
 * commands and configuration keys that exist (spec 17 §3.1).
 */
final class ReadmeAiNotesTest extends TestCase
{
    public function testTheNotesForAiAssistantsAreConsistentWithTheCode(): void
    {
        ReadmeAssertions::assertAiNotes(\dirname(__DIR__, 2), ['indexnow:history', 'indexnow:status', 'indexnow:check', 'indexnow/history', 'indexnow/status', 'indexnow/check'], HistoryConfig::OPTIONS);
    }
}
