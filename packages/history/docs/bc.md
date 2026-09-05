# Backward compatibility

`indexnowkit/history` follows SemVer and the tiers of the core's [docs/bc.md](https://github.com/indexnowkit/php/blob/main/packages/core/docs/bc.md).
**Before 1.0, minor versions may contain breaking changes**, listed under "Changed" in [CHANGELOG.md](../CHANGELOG.md).

| Tier | Members |
|---|---|
| **Call** — signatures only grow by appended, defaulted parameters | `Psr16SubmissionStore`, `Pdo\PdoSubmissionStore` (constructors, `createTable()`), `Pdo\Schema::sql()`, `assertTable()`, `HistoryConfig` (constructor, `fromArray()`, `disabled()`, `loadOrDisabled()`, `toArray()`), `Check\HistoryCheck`, `Console\HistoryRunner`, `Console\StatusRunner`, `Console\HistoryOptions`, `Console\Definitions::*` |
| **Implement** — methods are not added without a major version | `HistoryStoreInterface` |
| **Value objects** — `final readonly`, properties only appended with defaults | `HistoryConfig` |
| **Constants** — referenced, not hard-coded | `HistoryConfig::OPTIONS`, `STORES`, `DEFAULT_*`, `Schema::DRIVERS`, `HistoryCheck::CODE_*` |
| **Documents** — the shape only grows by optional members | `docs/status.schema.json`, the JSON of `history --json`; the table of `Pdo\Schema` (a column is only added, nullable or with a default) |

Not covered: `RecordCodec` (`@internal`), the printed texts, anything under `tests/`. The cache keys of the PSR-16
store (`<prefix>history.index`, `<prefix>history.<n>`) and the `batch` format are implementation details: a minor
may change them, and the ring buffer then starts over.
