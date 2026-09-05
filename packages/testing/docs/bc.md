# Backward compatibility

`indexnowkit/testing` follows SemVer and the tiers of the core's [docs/bc.md](https://github.com/indexnowkit/php/blob/main/packages/core/docs/bc.md).
**Before 1.0, minor versions may contain breaking changes**, listed under "Changed" in [CHANGELOG.md](../CHANGELOG.md).

| Tier | Members |
|---|---|
| **Call** — signatures only grow by appended, defaulted parameters; pass anything past the first argument by name | `KeyFileAssertions::*`, `CheckOutputAssertions::*`, `ReadmeAssertions::*` and their constants (`SECTION_EN`, `SECTION_RU`, `FAMILY_COMMANDS`, `CROSS_ADAPTER_KEYS` grow, never shrink) |
| **Extend** — the abstract driver methods of a kit only grow by appended methods with a default implementation; a scenario is only added, never removed or renumbered, in a minor | `CoreConformanceTestCase`, `OrmConformanceTestCase` |
| **Resource** — the file path and the scenario names are stable; scenarios are added, not renamed | `resources/mock-server/router.php` (`X-Mock-Scenario`: `ok200`, `pending202`, `bad400`, `forbidden403`, `unprocessable422`, `ratelimit429`, `ratelimit429-then-ok`, `flaky500-then-ok`, `timeout`; `GET /_mock/requests`, `DELETE /_mock/requests`; `MOCK_KEYS`) |

The conformance identifiers (C01–C22, A01–A21, H01–H06) are a cross-language contract of the specification: they are
frozen at 1.0 of the family. What an assertion *accepts* may become stricter in a minor when the specification does
(listed in the changelog); what it *rejects* never becomes accepted silently.

Not covered: the failure-message texts of the assertions, anything under `tests/`.

The package pins `indexnowkit/core ^0.7`: it reads `Config::OPTIONS` and the test doubles of the core, so a core
minor that renames them ships with a `testing` minor.
