# IndexNow submission history — `indexnowkit/history`

What was announced to Yandex, Bing and the other [IndexNow](https://www.indexnow.org) engines, when, and with what
answer: the two shipped implementations of the core's `Submission\SubmissionStoreInterface` — a PSR-16 ring buffer
for development and small sites, a database table (PDO) for production — plus the `history` and `status` commands
of every adapter and the "recent submissions" list of the Symfony profiler panel. IndexNow is a notification, not
indexing: the history tells you what your side did; whether a page was crawled is in Bing Webmaster Tools and
Yandex.Webmaster.

**Nothing is recorded until a store is named** (`history.store: null` by default).

[![Packagist](https://img.shields.io/packagist/v/indexnowkit/history)](https://packagist.org/packages/indexnowkit/history)
[![Downloads](https://img.shields.io/packagist/dt/indexnowkit/history)](https://packagist.org/packages/indexnowkit/history)
[![CI](https://github.com/indexnowkit/php/actions/workflows/ci.yml/badge.svg)](https://github.com/indexnowkit/php/actions)
![Coverage](https://img.shields.io/badge/coverage-%E2%89%A5%2090%25%20enforced-brightgreen)
![PHPStan](https://img.shields.io/badge/phpstan-level%209-4c1)
![PHP](https://img.shields.io/badge/php-%5E8.2-777bb4)
[![License](https://img.shields.io/packagist/l/indexnowkit/history)](LICENSE)

[Русская версия](README.ru.md) · Issues and pull requests: [github.com/indexnowkit/php](https://github.com/indexnowkit/php/issues) (the `php-*` repositories are read-only splits)

## Install

```bash
composer require indexnowkit/history        # brings indexnowkit/core
```

```yaml
# Symfony: config/packages/indexnowkit.yaml       # Laravel: config/indexnow.php 'history' => [...]
indexnowkit:                                      # Yii2: 'history' => [...] of the component
    history:
        store: pdo               # null | psr16 | pdo
        pdo:
            service: ~           # the adapter's connection (default connection when null), or dsn: 'mysql:…'
            table: indexnow_submissions
        retention_days: 90
```

With `store: pdo` create the table first: [docs/migrations.md](docs/migrations.md) has a Doctrine Migrations, a
Laravel and a Yii2 snippet, each one line over `History\Pdo\Schema::sql($driver)`. `check` prints `history: 1 240
records, last 3 min ago` (or the exception with the migration hint when the table is missing).

## What is recorded

One record per `Result` — one batch of URLs at one engine — with the URLs **after the core's normalization**
(tracking parameters already stripped), the engine, host, status (`ok`, `pending`, `failed`, `skipped`), the
`Reason`, the HTTP code, and `Result::$error` cut to 1000 characters. Skipped results (debounced, dry-run, the
pre-flight of `indexnowkit/verify`) are records too. **Never** a response body, a header, or the key. Personal data
enters the history only when a URL carries it.

| Store | Where | For |
|---|---|---|
| `psr16` | the adapter's cache behind `debounce.store`, a ring buffer of `history.limit` (500) records under `<debounce.key_prefix>history.<n>`, the slot from an atomic `increment()` when the cache has one | one process, development, small sites: on a cache without `increment()` (a plain PSR-16 array/file cache) workers recording at the same moment lose all but one of their records, an eviction empties the history |
| `pdo` | the table `history.pdo.table` (one row per URL, the rows of one Result share a `batch`; one transaction of multi-row INSERTs per Result), indexes on `url`, `at`, `(host, at)` | production; `history --purge` removes what is older than `history.retention_days`. With `dispatch: sync` inside an application transaction the record shares that transaction (a rollback erases the record of a request that has left): give the history its own connection with `history.pdo.dsn` when that matters |

Any other `SubmissionStoreInterface` you bind works with the commands too (`recent()` is enough); counting, "last
submission" and `--purge` need `History\HistoryStoreInterface` (`count()`, `last()`, `purge()`), which both shipped
stores implement.

## Commands

```bash
bin/console indexnow:history --host=www.example.com --status=failed --since=2h --limit=20
php artisan indexnow:history --url=https://www.example.com/post-1        # exact URL after normalization
php yii indexnow/history --json                                           # {"records":[{at,status,reason,engine,http_status,error,urls[]}]}
bin/console indexnow:history --purge                                       # older than retention_days; --purge=30 for 30 days; one line for cron
bin/console indexnow:status --json                                         # enabled, dry_run, environment, dispatch, debounce store, 403 counters per host, last successful submission, history size, core version
```

`indexnow:status` is read-only: nothing is fetched, nothing is sent. Its JSON follows
[docs/status.schema.json](https://github.com/indexnowkit/php/blob/main/packages/history/docs/status.schema.json).

## Plain PHP

```php
use IndexNowKit\Config;
use IndexNowKit\History\Pdo\PdoSubmissionStore;
use IndexNowKit\IndexNowKit;

$store = new PdoSubmissionStore(new PDO('sqlite:var/indexnow.sqlite'));
$store->createTable();                                            // or the migration of docs/migrations.md
$indexNow = IndexNowKit::create(Config::fromEnv(), submissionStore: $store);
$indexNow->submit(['https://www.example.com/post-1']);
foreach ($store->recent(10) as $record) { echo $record->at->format(DATE_ATOM), ' ', $record->result->status->value, ' ', implode(' ', $record->urls), PHP_EOL; }
```

## For adapter authors

`History\Adapter\HistoryServices` is what a framework adapter wires for this package, in one place: the predicate (`package()`), the owned
options, the validated block, the stores over a PDO or a PSR-16 cache, the `check` line, the bodies of `history` and `status`, the store description — as static functions over the pieces, with `*For()` twins over the core's
`Adapter\Services` for a runtime graph. The Symfony bundle, the Laravel and the Yii2 adapters build on it; see
[adapters.md](https://github.com/indexnowkit/php-core/blob/main/docs/adapters.md) of the core.

## Requirements

PHP 8.2+, `indexnowkit/core ^0.11`; `ext-pdo` with the driver of your database for `store: pdo` (`sqlite`, `mysql`,
`pgsql` schemas shipped); `indexnowkit/console ^0.4` for the commands (every adapter has it).

## Notes for AI assistants

- Composer package `indexnowkit/history` (add-on of `indexnowkit/core`). With a framework adapter nothing is wired by hand: `history.store: pdo` (or `psr16`) in the adapter's configuration binds the store; `history.pdo.table` needs the migration of `docs/migrations.md` first.
- Minimal complete snippet (every `use` included):

```php
use IndexNowKit\Config;
use IndexNowKit\History\Pdo\PdoSubmissionStore;
use IndexNowKit\IndexNowKit;

$store = new PdoSubmissionStore(new PDO('sqlite:var/indexnow.sqlite'));
$store->createTable();
$indexNow = IndexNowKit::create(Config::fromEnv(), submissionStore: $store);
$indexNow->submit(['https://www.example.com/post-1']);
$last = $store->lastFor('https://www.example.com/post-1');   // ?SubmissionRecord: urls, result (status, reason, httpCode, error), at
```

- Verify: `bin/console indexnow:history`, `php artisan indexnow:history`, `php yii indexnow/history` list the records; `indexnow:status` / `indexnow/status` print the wiring and the last successful submission; `indexnow:check` / `indexnow/check` print the `history:` line.
- Pitfalls:
  - `history.store` is `null` by default: installing records nothing. `psr16` loses records when the cache is evicted or two workers write at once; production uses `pdo`.
  - `--purge` and the record counts need the shipped stores (`History\HistoryStoreInterface`); a custom `SubmissionStoreInterface` still lists with `indexnow:history`.
  - `--url` matches the URL after normalization (`normalizer.strip_tracking_params` is on by default): pass the URL without `utm_*`.
  - `history.key_prefix` and the PDO table name must not carry `{}()/\@:` (PSR-6) or anything but `[A-Za-z0-9_]` (SQL identifier).
  - `dispatch: auto` exists in Symfony and Yii2, **not** in Laravel; locales are `router.locales` (Laravel, Yii2), `framework.enabled_locales` (Symfony).

## Versioning

SemVer; until 1.0 minor versions may contain breaking changes, listed in [CHANGELOG.md](CHANGELOG.md). What the
compatibility promise covers: [docs/bc.md](docs/bc.md).

MIT. IndexNow is a trademark of its owner; this project is independent and not affiliated with Microsoft, Yandex or indexnow.org.
