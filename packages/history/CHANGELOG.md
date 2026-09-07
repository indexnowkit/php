# Changelog

Format: [Keep a Changelog](https://keepachangelog.com/en/1.1.0/). Versioning: SemVer; until 1.0 minor versions may
contain breaking changes, listed under "Changed". What the compatibility promise covers: [docs/bc.md](docs/bc.md).

## [0.4.0] — Unreleased

### Added

- **`Adapter\HistoryServices::describeStore(?string $store, string $default, Closure $lookup)`** (wave M, spec 19 §4.10):
  the debounce store as `status` prints it — `memory` and `none` as they are, a shared store as `<id> (<ShortClass>)` of
  what the adapter's lookup resolves the id to, `<id> (missing)` when it resolves to nothing or throws. Laravel, Yii2
  and Yii3 each built that line themselves; they pass their lookup now. `debounceCacheId()` answers through the core's
  `DebounceStoreFactory::isShared()`.

- **`Console\HistoryCommand` and `Console\StatusCommand`** (wave L, spec 18): the `indexnow:history` and `indexnow:status`
  commands themselves as symfony/console classes over `Console\HistoryRunner` / `Console\StatusRunner`, each with its
  `#[AsCommand]`; the adapter builds the runner (`History\Adapter\HistoryServices::historyRunner()` / `statusRunner()`
  and their `*For()` twins, the description of the debounce store and the queue facts included) and hands it over. The
  Symfony bundle and the Yii3 package register these classes instead of copies of their own; without the package an
  adapter registers `Console\Command\HistoryNotInstalledCommand` and `StatusNotInstalledCommand` of `indexnowkit/console`
  under the same names. `HistoryCommand::DEFAULT_LIMIT` (`50`) is what a `--limit` that is not a number falls back to.
  Minor version, not patch: new public classes, by the rule of the family; the `Schema::table()` entry below was written
  for 0.3.1 and ships here.

- `Pdo\Schema::table(string $table): string` — the table name once it passed the identifier check, the form that reaches
  the SQL; `assertTable()` stays and delegates. `PdoSubmissionStore` and `Schema::sql()` keep the checked value, which is
  what Psalm's taint analysis (`@psalm-taint-escape sql`) reads as the boundary between `history.pdo.table` and the
  `CREATE TABLE` / `INSERT` statements (audit 0.13 T20).

### Changed

- `History\Adapter\HistoryServices::package()` delegates to the core's `Adapter\OptionalPackage::history()` (core
  0.13.0): the name, the marker and the feature word live there, so an adapter asks about the package without loading
  this class. Same object, same texts; adapters should call `OptionalPackage::history()` directly.
- Requires `ext-mbstring` (`RecordCodec` truncates an error text with `mb_substr()` and declared nothing; audit 0.13 W3) and `indexnowkit/core ^0.13`; the commands need `indexnowkit/console ^0.5` (`require-dev`, `suggest`).

## [0.3.0] — 2026-09-07

### Added

- **`History\Adapter\HistoryServices`** — what every framework adapter wires for this package, in one place: `package()`,
  `options()`, `config()`, `pdoFromDsn()`, `pdoStore()` (errors as exceptions), `psr16Store()`, `debounceCacheId()` (the
  cache `debounce.store` names, null for `memory`/`none`), `forbiddenCounter()`, `check()`, `historyRunner()`,
  `statusRunner()`, `describe()` (the store line of an "about" screen), and the `*For()` twins over the core's runtime
  graph (`checksFor()`, `historyRunnerFor()`, `statusRunnerFor()`). The three adapters build on it.

## [0.2.1] — 2026-09-07

### Changed

- Requires `indexnowkit/core ^0.12`.

## [0.2.0] — 2026-09-07

### Changed

- **`Pdo\PdoSubmissionStore::record()` is one transaction of multi-row INSERTs** (500 rows per statement; its own
  transaction, or the one the application has open on the connection): a batch is in the table whole or not at all, and
  10 000 URLs are twenty statements, not 10 000 commits. Note in docs: with `dispatch: sync` inside an application
  transaction the record goes with the application's rollback, although the HTTP request has left — give the history its
  own connection (`history.pdo.dsn`) when that matters.
- **`Psr16SubmissionStore` takes the slot from an atomic `increment()`** (`<prefix>history.seq`) when the cache has one
  (Laravel's repository, a Redis client), so concurrent workers get distinct slots; on a plain PSR-16 cache the
  read-modify-write race remains and the class documentation now says what it costs. The index records `history.limit`;
  a changed limit starts the ring over instead of remapping slots. `recent()` no longer relies on the order of
  `getMultiple()`.
- `indexnow:config` masks the password of `history.pdo.dsn` (console 0.4.0).
- Requires `indexnowkit/core ^0.11`, `indexnowkit/console ^0.4`.

### Fixed

- `Pdo\Schema::sql('pgsql')` gave the `BOOLEAN` column `retryable` the default `0`, which PostgreSQL refuses
  (`SQLSTATE[42804]`); the default is now `FALSE` there (`0` stays on MySQL and SQLite). A table created after
  docs/migrations.md was never affected.

## [0.1.1] — 2026-09-06

### Changed

- Requires `indexnowkit/core ^0.10` (`Attribute\ParamExtractor` became an injected object; nothing else in the core changed).

## [0.1.0] — 2026-09-06

First release (spec 17 §6.2, wave F). Requires `indexnowkit/core ^0.9`.

### Added

- **`HistoryStoreInterface extends Submission\SubmissionStoreInterface`** — `count(?host)`, `last()`,
  `purge(DateTimeInterface)`: what the shipped stores add; the commands work with any `SubmissionStoreInterface`
  and use these when present.
- **`Psr16SubmissionStore`** — a ring buffer of `history.limit` records in a PSR-16 cache under
  `<prefix>history.index` / `<prefix>history.<n>` (no PSR-6 reserved characters); for one process, development and
  small sites.
- **`Pdo\PdoSubmissionStore`** and **`Pdo\Schema::sql(driver, table)`** — one row per URL, the rows of one Result
  grouped by `batch`; indexes on `url`, `at`, `(host, at)`; table name validated; `error` cut to 1000 characters;
  schemas for `sqlite`, `mysql`, `pgsql`; `docs/migrations.md` with Doctrine Migrations, Laravel and Yii2 snippets.
- **`HistoryConfig`** — the `history` block (`store: null|psr16|pdo`, `limit`, `key_prefix`, `pdo.dsn`,
  `pdo.service`, `pdo.table`, `retention_days`; `OPTIONS`, `fromArray()`, `disabled()`, `loadOrDisabled()`, `toArray()`).
- **`Check\HistoryCheck`** — the `history.store` and `history.records` lines of `check`.
- **`Console\Definitions::history()/status()`, `Console\HistoryRunner`, `Console\StatusRunner`** — `indexnow:history
  [--host] [--status] [--url] [--since] [--limit] [--json] [--purge[=days]]` and `indexnow:status [--json]`
  (`docs/status.schema.json`).
- Both stores pass the S01–S08 kit of `indexnowkit/testing`.
