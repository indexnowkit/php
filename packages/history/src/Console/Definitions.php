<?php

declare(strict_types=1);

namespace IndexNowKit\History\Console;

use IndexNowKit\Console\CommandDefinition;
use IndexNowKit\Console\OptionDefinition;

/**
 * The options of the `history` and `status` commands, declared once for every adapter (the console package's
 * `Console\Definitions` does the same for the core commands). `history()` covers {@see HistoryOptions}.
 */
final class Definitions
{
    private function __construct() {}

    /** `history`: {@see HistoryRunner::run()}. */
    public static function history(): CommandDefinition
    {
        return new CommandDefinition(
            'List the recorded IndexNow submissions (what was sent, when, with what answer), newest first',
            [],
            [
                OptionDefinition::value('host', 'Records of this host only'),
                OptionDefinition::value('status', 'ok | pending | failed | skipped'),
                OptionDefinition::value('url', 'Records naming this URL (exact match after normalization)'),
                OptionDefinition::value('since', 'Not older than: an ISO date (2026-09-01) or a relative interval (2h, 3d, 1w)'),
                OptionDefinition::value('limit', 'At most this many records', '50'),
                OptionDefinition::flag('json', 'Machine-readable output: records with at, status, reason, engine, http_status, error, urls'),
                OptionDefinition::optionalValue('purge', 'Remove the records older than history.retention_days (or than this many days); prints one line for cron'),
            ],
        );
    }

    /** `status`: {@see StatusRunner::run()}. */
    public static function status(): CommandDefinition
    {
        return new CommandDefinition(
            'Print the IndexNow status: switches, dispatch, debounce store, 403 counters per host, the last successful submission, history size',
            [],
            [OptionDefinition::flag('json', 'Machine-readable output (schema: docs/status.schema.json of indexnowkit/history)')],
        );
    }
}
