<?php

// The taint entry points of this package (audit 0.13 T20; bin/taint, .github/workflows/taint.yml): the public API called
// with request data, so that Psalm's taint analysis has a source to follow into the sinks (SQL, files, HTML, headers).
// A library has no taint source of its own — without this file Psalm reports nothing and proves nothing. Not a test:
// PHPUnit does not load it, phpstan analyses it at the level of the test suite, only psalm.xml lists it.

declare(strict_types=1);

namespace IndexNowKit\Taint;

use IndexNowKit\History\HistoryConfig;
use IndexNowKit\History\Pdo\PdoSubmissionStore;
use IndexNowKit\History\Pdo\Schema;
use PDO;

/** A request value as a string: the taint of the superglobal, none of the mixed. */
function input(string $name): string
{
    $value = $_GET[$name] ?? $_POST[$name] ?? $_SERVER[$name] ?? null;

    return \is_string($value) ? $value : '';
}

/** @var array<string, mixed> $post */
$post = $_POST;
$history = HistoryConfig::fromArray($post);
$store = new PdoSubmissionStore(new PDO(input('dsn')), input('table'));
$store->createTable();
foreach ($store->recent(10, input('host')) as $record) {
    echo implode(',', $record->urls);
}
echo $store->lastFor(input('url'))?->result->status->value;
echo \count(Schema::sql(input('driver'), input('table')));
