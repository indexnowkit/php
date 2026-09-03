# Changelog

All notable changes to the PHP packages are documented here. Format: Keep a Changelog. Tags: `<package>@<version>`.
Per-package detail lives in each package's own changelog.

## Unreleased

The URL model was rewritten across all three packages. A class no longer declares one page, it declares a **list of
rules**, and event classification, guards, locales, deletion semantics and debugging output all work per rule.

### core@0.2.0

See [packages/core/CHANGELOG.md](packages/core/CHANGELOG.md).

- **The rule model.** `#[IndexNow]` is repeatable, with one source per rule (`route`, `resolver`, `via`, `url`,
  `urls`); `#[IndexNowDefaults]` carries class policy; `#[IndexNowUrl]` marks a method as a URL family; typed
  parameter sources `Param\{Accessor,Value,Formatted,Call}` with `Placeholder::Locale|Host`; new options
  `whenFields`, `host`, `name`. Compiled into `Attribute\{UrlRule,RuleSet,RuleSource,RuleEvent}` by `RuleCompiler`,
  with `RuleRegistry` for rules registered at runtime.
- **`Url\ObjectChangeHandler`**: the shared, never-throwing "an object changed → URLs" building block every ORM
  adapter uses. `Url\ResolvedUrl` and `IndexNowKit::explain()` keep the rule that produced each URL.
- **`Reason` enum** on every non-success `Result`, with named constructors `ok()` / `skipped()` / `failed()`,
  `metricLabels()`, `retryableUrls()`, `allUrls()` and `urlsWhere()`.
- `Collector\CollectorInterface`; `Check\CheckItem`; `Transaction\TransactionStaging` moved into the core;
  `Key\KeyFileResponder`; `KeyProviderInterface::isKnownKey()` takes a host; `Http\LazyTransport`;
  `Http\Response::parseRetryAfter()`; the `IndexNowKit\Testing` doubles; `Config::OPTIONS`,
  `Config::unknownOptions()`, `strict_hosts`, per-host `base_url`; PSR-14 result events.
- **Breaking:** facade `IndexNowKit\IndexNow` → `IndexNowKit\IndexNowKit`;
  `AttributeReaderInterface::read()` → `rules(): RuleSet`; `ChangeClassifier::classify()` takes a `UrlRule`;
  `RouteUrlResolverInterface` split into `locales()` and `generate($route, $params, $locale, $host)`;
  `PublishGuard` removed; `CheckReport::items()` returns objects; deleting an object whose rule does not apply no
  longer submits.
- **Fixed:** a `when` given as a getter name (`isPublished`) no longer disables the unpublish-as-deletion
  transition; `enabled: false` is logged at `info`; `Collector::reset()` warns when it discards a non-empty buffer.

### doctrine@0.2.0

See [packages/doctrine/CHANGELOG.md](packages/doctrine/CHANGELOG.md).

- Per-rule classification through `ObjectChangeHandler`: one entity can produce an update for one page and a
  deletion for another in the same flush.
- Changed to-many associations (`post.tags`) now resubmit the owner's pages.
- Deletions and rules whose `when` turned false are resolved in `onFlush`, while the old state is live; inserts and
  ordinary updates in `postFlush`, once identifiers exist. Draft deletions submit nothing.
- **Breaking:** requires `indexnowkit/core ^0.2`; `TransactionStaging` moved to the core; the listener subscribes to
  `onFlush` and `postFlush` only.

### symfony-bundle@0.2.0

See [packages/symfony-bundle/CHANGELOG.md](packages/symfony-bundle/CHANGELOG.md).

- Full configuration tree with compile-time validation, and an invalid runtime configuration that disables IndexNow
  with a `critical` log line instead of throwing from a flush.
- `http.client` accepts a scoped `symfony/http-client`; the transport is built lazily on first use.
- New `indexnow:explain`, plus `--live/--host`, `--force/--dry-run/--json`, `--explain`, `--alphanumeric/--force`
  across the command set; `indexnow:check` reports dispatch mode, Messenger routing and Doctrine hooking.
- Profiler panel shows submission results; the `indexnow` Monolog channel covers the facade, dispatcher and
  Messenger handler.
- **Breaking:** requires `indexnowkit/core ^0.2`; `indexnowkit/doctrine` is a suggestion rather than a hard
  requirement; `serve_key_file` is a deprecated alias of `key_file.enabled`.

## 0.1.0 — 2026-09-03

### core@0.1.0
- Protocol client for the shared endpoint and per-engine endpoints (Yandex, Bing, Naver, Seznam, Yep).
- Batching (10 000 URLs, grouped by host), per-URL debounce, token-bucket throttle, typed handling of
  200/202/400/403/422/429/5xx.
- `#[IndexNow]` attribute, `Config::fromArray/fromEnv`, sitemap reader, `Checker`, pure-PHP punycode.

### doctrine@0.1.0
- `onFlush`/`postFlush` listener resolving URLs (deletions before removal, publish/unpublish transitions).
- DBAL driver middleware (DBAL 3 and 4) delivering URLs only after the outermost COMMIT.

### symfony-bundle@0.1.0
- Bundle config, Messenger dispatch with retry-after, `kernel.terminate` batching, key file route.
- Commands `indexnow:key:generate`, `indexnow:check`, `indexnow:submit`, `indexnow:submit-entity`,
  `indexnow:sitemap`. Web Profiler panel. Flex recipe in `packages/symfony-bundle/recipe`.
