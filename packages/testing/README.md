# IndexNow test kit — `indexnowkit/testing`

The test suite every part of the family shares, as a `require-dev` package: the conformance scenarios of the
specification as abstract PHPUnit cases you extend against *your* wiring (C01–C22 for anything that talks to the
protocol, A01–A21 for an ORM adapter), the assertions of the HTTP and command scenarios (H01–H05) so a framework test
parses its own response object and asserts once, an assertion for the README section AI assistants read, and the
mock IndexNow server for end-to-end runs. It is what `indexnowkit/doctrine`, `indexnowkit/symfony-bundle`,
`indexnowkit/laravel` and `indexnowkit/yii2` test themselves with; an adapter for another framework starts here.

The four test doubles (`FakeTransport`, `ArrayLogger`, `FrozenClock`, `RecordingDispatcher`) stay in
[`indexnowkit/core`](https://github.com/indexnowkit/php/tree/main/packages/core) under `IndexNowKit\Testing`: they
implement core interfaces and need no PHPUnit, so an application test suite gets them without this package.

[![Packagist](https://img.shields.io/packagist/v/indexnowkit/testing)](https://packagist.org/packages/indexnowkit/testing)
[![Downloads](https://img.shields.io/packagist/dt/indexnowkit/testing)](https://packagist.org/packages/indexnowkit/testing)
[![CI](https://github.com/indexnowkit/php/actions/workflows/ci.yml/badge.svg)](https://github.com/indexnowkit/php/actions)
![PHPStan](https://img.shields.io/badge/phpstan-level%209-4c1)
![PHP](https://img.shields.io/badge/php-%5E8.2-777bb4)
[![License](https://img.shields.io/packagist/l/indexnowkit/testing)](LICENSE)

[Русская версия](README.ru.md) · Issues and pull requests: [github.com/indexnowkit/php](https://github.com/indexnowkit/php/issues) (the `php-*` repositories are read-only splits)

## Install

```bash
composer require --dev indexnowkit/testing      # brings indexnowkit/core; PHPUnit 11 is expected in your require-dev
```

Everything lives under `IndexNowKit\Testing\Conformance\`.

## Conformance kits

Three abstract test cases turn [docs/spec/03](https://github.com/indexnowkit/spec/blob/main/03-conformance.md) into
runnable scenarios against the facade your container built:

```php
use IndexNowKit\IndexNowKit;
use IndexNowKit\Testing\Conformance\CoreConformanceTestCase;
use IndexNowKit\Testing\FakeTransport;

final class CoreConformanceTest extends CoreConformanceTestCase
{
    protected function kit(): IndexNowKit           { return $this->container()->get(IndexNowKit::class); }
    protected function transport(): FakeTransport   { return $this->container()->get(FakeTransport::class); }
    protected function secondHost(): ?string        { return 'example.de'; }   // a second entry of `hosts`, or null to skip C04
}
```

- `CoreConformanceTestCase` (C01, C03, C04, C06, C09–C12, C14, C19, C20): return the facade and the `FakeTransport`
  it is wired to; the scenarios use fresh URLs, so the debounce window is irrelevant.
- `SubmissionStoreConformanceTestCase` (S01–S08): return a fresh `Submission\SubmissionStoreInterface` from
  `createStore()` (`supportsPurge()` when it has the `purge()` of `indexnowkit/history`): S01 record and read back,
  S02 newest first, S03 host filter, S04 status filter, S05 `lastFor()`, S06 limit, S07 several URLs of one Result,
  S08 empty store and `purge()`.
- `OrmConformanceTestCase` (A01–A21, plus A05b/A05c): implement the driver — the transaction verbs of your data layer
  (`begin()`, `commit()`, `rollback()`), the end of a unit of work (`flush()`, `collectedCount()`), and fixtures
  with fixed rule shapes (`createPost()`, `createMultiPost()`, `createCategorizedPost()`, `createTag()`,
  `attachTag()`, `bulkUpdateTitle()`, …). The docblock of the class lists the rules every fixture must carry; the
  URL conventions (`postUrl()`, `ampUrl()`, `categoryUrl()`, `homeUrl()`) are overridable.

`indexnowkit/doctrine` (`tests/OrmConformanceTest.php`) and `indexnowkit/laravel` (`tests/Conformance/`) are the
reference drivers. A scenario that does not apply to your framework is documented in your README, not skipped
silently. The scenario identifiers are a cross-language contract: a scenario is added, never renumbered.

## Assertions for HTTP and command tests

The scenarios H01–H05 are the same in every framework, only the way a response or a command output is captured
differs. Parse your framework's objects, assert here:

```php
use IndexNowKit\Testing\Conformance\CheckOutputAssertions;
use IndexNowKit\Testing\Conformance\KeyFileAssertions;

// H01: 200, text/plain, the key as the body, Cache-Control with public and max-age, Vary: Host only with a hosts map
KeyFileAssertions::assertKeyFileResponse($response->getStatusCode(), $response->headers->all(), $response->getContent(), $key, maxAge: 300, expectVaryHost: true);
// H02/H03: an unknown key, another host's key, key_file.enabled: false
KeyFileAssertions::assertNotServed($response->getStatusCode());

// H04/H05: the check command
CheckOutputAssertions::assertExitCode(0, $exitCode, $output);        // the output is the failure message
CheckOutputAssertions::assertReady($output, 'www.example.com');       // "<host>: key file OK" and the closing line
CheckOutputAssertions::assertKeyFileHint($output, 403);              // the status and the hint about what the engines do
```

`Cache-Control` is compared by directive (frameworks order them differently), header names in any case, values as a
string or a list. The phrases are the ones the core's `Checker` and the `check` command print, so your test does not
carry a copy of them.

`ReadmeAssertions::assertAiNotes($packageDir, $commands, $optionKeys)` checks the "Notes for AI assistants" section
of a package README (EN and RU): present, with a PHP snippet that carries its `use` lines, naming only commands of
the family and configuration keys the package accepts. Every package of the family runs it; an adapter of yours can too.

## The mock IndexNow server

For end-to-end runs through a real PSR-18 client, without touching the engines:

```bash
php -S 127.0.0.1:8089 vendor/indexnowkit/testing/resources/mock-server/router.php
```

Point `engines` at `http://127.0.0.1:8089/indexnow` (plain HTTP is accepted on loopback hosts only) and pick the
behaviour with the `X-Mock-Scenario` header or `?scenario=`: `ok200` (default), `pending202`, `bad400`,
`forbidden403`, `unprocessable422`, `ratelimit429` (`Retry-After: 2`), `ratelimit429-then-ok` and `flaky500-then-ok`
(`?n=` failures first), `timeout`. The server validates the body like the real endpoint (host, key, `urlList`, at
most 10 000 URLs, every URL on the declared host → 422 otherwise), serves `GET /{key}.txt` for the keys listed in the
`MOCK_KEYS` environment variable (comma separated), and logs every request: `GET /_mock/requests` returns the log as
JSON, `DELETE /_mock/requests` clears it. Start it from a test with `proc_open` on a free port, as the core's
`Psr18TransportTest` does.

## Requirements

PHP 8.2+, `indexnowkit/core ^0.7`, PHPUnit 11 in your `require-dev` (the test cases extend `PHPUnit\Framework\TestCase`).

## Notes for AI assistants

- Composer package `indexnowkit/testing`, `require-dev` only: conformance test cases and assertions for a test suite that uses `indexnowkit/core` or one of its adapters; nothing here runs in an application.
- Minimal complete snippet (every `use` included) — an adapter's conformance test:

```php
use IndexNowKit\IndexNowKit;
use IndexNowKit\Testing\Conformance\CoreConformanceTestCase;
use IndexNowKit\Testing\FakeTransport;

final class CoreConformanceTest extends CoreConformanceTestCase
{
    protected function kit(): IndexNowKit { return $this->app->get(IndexNowKit::class); }              // the facade the container built
    protected function transport(): FakeTransport { return $this->app->get(FakeTransport::class); }    // the transport it is wired to
}
```

- Verify: `vendor/bin/phpunit` runs the scenarios; a red C-scenario is a wiring problem in the adapter, not in the kit.
- Pitfalls:
  - The test doubles (`FakeTransport`, `ArrayLogger`, `FrozenClock`, `RecordingDispatcher`) are `IndexNowKit\Testing\*` in the core; the kits and assertions are `IndexNowKit\Testing\Conformance\*` here. Before core 0.7 the assertions lived in the core under `IndexNowKit\Testing\*`.
  - `assertKeyFileResponse()` expects `Vary: Host` only when the application serves several hosts (a `hosts` map) and refuses it otherwise.
  - `CheckOutputAssertions::assertExitCode()` takes the whole output as its third argument so a failing test shows what the command printed.
  - The mock server accepts plain HTTP only on loopback hosts; `engines` must name the full endpoint (`http://127.0.0.1:8089/indexnow`).
  - `dispatch: auto` exists in Symfony (`auto` | `messenger` | `sync` | `none`) and Yii2 (`auto` | `queue` | `sync` | `none`), **not** in Laravel (`queue` | `sync` | `none`): a conformance test of an adapter runs with `dispatch: sync`.

## Versioning

SemVer; until 1.0 minor versions may contain breaking changes, listed in [CHANGELOG.md](CHANGELOG.md). What the
compatibility promise covers: [docs/bc.md](docs/bc.md).

MIT. IndexNow is a trademark of its owner; this project is independent and not affiliated with Microsoft, Yandex or indexnow.org.
