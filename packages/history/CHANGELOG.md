# Changelog

Format: [Keep a Changelog](https://keepachangelog.com/en/1.1.0/). Versioning: SemVer; until 1.0 minor versions may
contain breaking changes, listed under "Changed". What the compatibility promise covers: [docs/bc.md](docs/bc.md).

## [0.1.0] — Unreleased

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
