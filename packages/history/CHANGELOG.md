# Changelog

Format: [Keep a Changelog](https://keepachangelog.com/en/1.1.0/). Versioning: SemVer; until 1.0 minor versions may
contain breaking changes, listed under "Changed". What the compatibility promise covers: [docs/bc.md](docs/bc.md).

## [0.2.0] — Unreleased

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
