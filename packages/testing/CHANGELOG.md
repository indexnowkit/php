# Changelog

Format: [Keep a Changelog](https://keepachangelog.com/en/1.1.0/). Versioning: SemVer; until 1.0 minor versions may
contain breaking changes, listed under "Changed". What the compatibility promise covers: [docs/bc.md](docs/bc.md).

## [Unreleased]

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
