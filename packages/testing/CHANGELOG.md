# Changelog

Format: [Keep a Changelog](https://keepachangelog.com/en/1.1.0/). Versioning: SemVer; until 1.0 minor versions may
contain breaking changes, listed under "Changed". What the compatibility promise covers: [docs/bc.md](docs/bc.md).

## [0.3.1] — 2026-09-07

### Changed

- Requires `indexnowkit/core ^0.12`.

## [0.3.0] — 2026-09-07

### Changed

- **`ReadmeAssertions::assertAiNotes()` also requires the notes to name every command of the package** (`$commands`):
  a new command forgotten in "Notes for AI assistants" fails the test, not only a mention of a command that does not exist.
- Requires `indexnowkit/core ^0.11`.

### Added

- `ConformanceIdsTest` (monorepo): C01–C22, A01–A21 (+A05b/A05c), H01–H06 per adapter, S01–S08 — every id defined exactly
  once per suite, none missing, none beyond the frozen range.

## [0.2.1] — 2026-09-06

### Changed

- Requires `indexnowkit/core ^0.10` (`Attribute\ParamExtractor` became an injected object; nothing else in the core changed).

## [0.2.0] — 2026-09-06

### Added

- **`SubmissionStoreConformanceTestCase` (S01–S08)**: the kit every `Submission\SubmissionStoreInterface`
  implementation runs (`createStore()`, `supportsPurge()` for the `purge()` of `indexnowkit/history`): S01 record and
  read back, S02 newest first, S03 host filter, S04 status filter (skipped results are records), S05 `lastFor()` after
  two records of one URL, S06 limit, S07 several URLs of one Result stay one record, S08 empty store and `purge()`.
  The ids are frozen with C01–C22, A01–A21 and H01–H06.
- `ReadmeAssertions::FAMILY_COMMANDS` knows `indexnow:history`, `indexnow:status`, `indexnow/history`, `indexnow/status`.

### Changed

- Requires `indexnowkit/core ^0.9`.

## [0.1.1] — 2026-09-06

### Changed

- Requires `indexnowkit/core ^0.8` (`CheckItem::$code`).

### Added

- `CheckOutputAssertions::assertEveryItemHasCode(CheckReport $report, string ...$expectedCodes)`: every line of a check
  report names its check (`CheckItem::$code`, core 0.8). Requires `indexnowkit/core ^0.8`.
- `ReadmeAssertions::FAMILY_COMMANDS` knows `indexnow:config` and `indexnow/config`.

## [0.1.0] — 2026-09-06

First release: the test kit of the family, split out of `indexnowkit/core` 0.6 (spec 17 §4.1). The core keeps its
four test doubles (`IndexNowKit\Testing\{FakeTransport, ArrayLogger, FrozenClock, RecordingDispatcher}`, no PHPUnit);
everything that extends or calls PHPUnit lives here. Requires `indexnowkit/core ^0.7`.

### Added

- `IndexNowKit\Testing\Conformance\CoreConformanceTestCase` (C01, C03, C04, C06, C09–C12, C14, C19, C20) and
  `OrmConformanceTestCase` (A01–A21, A05b/A05c), moved from the core with their FQCN unchanged.
- `IndexNowKit\Testing\Conformance\KeyFileAssertions` (H01–H03), `CheckOutputAssertions` (H04–H05) and
  `ReadmeAssertions` (the "Notes for AI assistants" README section), moved from `IndexNowKit\Testing\*` in the core:
  change the `use` line.
- `resources/mock-server/router.php`: the mock IndexNow server for end-to-end runs (`php -S 127.0.0.1:8089
  vendor/indexnowkit/testing/resources/mock-server/router.php`), moved from the core's test support.
