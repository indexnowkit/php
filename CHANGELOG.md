# Changelog

All notable changes to the PHP packages are documented here, newest release wave first. Tags: `<package>@<version>`.
Per-package detail (and the migration notes for every breaking change) lives in each package's own changelog.

## 2026-09-04 — laravel@0.4.0, symfony-bundle@0.3.1

### laravel@0.4.0

See [packages/laravel/CHANGELOG.md](packages/laravel/CHANGELOG.md).

- **Breaking:** requires Laravel 12 or 13 (`illuminate/* ^12.0 || ^13.0`). Laravel 11 left its security-fix window in
  March 2026 and every 11.x release carries advisories; stay on 0.3.x for Laravel 11.
- `Eloquent\IndexNowObserver` reads route binding fields through `Eloquent\RouteBindingFieldsInterface` instead of the
  resolver class; `src/Eloquent` depends only on `illuminate/database` and the core. No behaviour change.

### symfony-bundle@0.3.1

- Requires `symfony/http-kernel ^6.4.13 || ^7.0`: earlier 6.4 patches leak the exception handler registered during
  request handling (risky tests under PHPUnit 10+).

## 2026-09-04 — core@0.3.1, yii2@0.1.0

### core@0.3.1

See [packages/core/CHANGELOG.md](packages/core/CHANGELOG.md).

- **`Transaction\VerifyingStaging`**: commit-safety for data layers without commit or savepoint signals. URLs staged
  inside a transaction carry a verifier that re-reads the row by primary key on commit; changes that did not land are
  dropped with every URL they produced.
- `Console\Vocabulary` carries the `check` / `submit` / `explain` command names for frameworks whose commands are not
  `indexnow:<name>`.
- **Fixed:** `via:` pointing at an iterable object with rules (Yii ActiveRecord) is one related object, not a collection.

### yii2@0.1.0

See [packages/yii2/CHANGELOG.md](packages/yii2/CHANGELOG.md). First release: Yii2 ≥ 2.0.45, PHP 8.2–8.5.

- `IndexNowComponent` (`components.indexnow` + `bootstrap`) builds the core graph from `options`, registers the key
  file URL rule and controller, the console controller and the flush points; every piece replaceable by property.
- `ActiveRecord\IndexNowBehavior` or the `active_record.models` list hook ActiveRecord classes; URLs resolved in the
  event while the old state is live; verify-on-commit through the core's `VerifyingStaging` (Yii2 has no savepoint
  events).
- `php yii indexnow/check|submit|submit-record|explain|sitemap|key-generate`; `dispatch: auto` picks yii2-queue when
  present; checks for queue, cache, pretty URLs, ActiveRecord hooks, sitemap spool.

## 2026-09-04 — core@0.3.0, doctrine@0.2.1, symfony-bundle@0.3.0, laravel@0.3.0

The console command bodies moved into the core: every framework adapter prints the same output and only parses its
own input.

### core@0.3.0

- **`Console\*`**: `CheckRunner`, `SubmitRunner`, `SubmitSubjectsRunner`, `ExplainRunner`, `SitemapRunner`,
  `KeyGenerateRunner` render to a `SymfonyStyle` and return an `ExitCode`; `ResultRenderer` / `ResultFormatterInterface`,
  `ResultSummary`, `SubmitterFactory` / `SubmitterFactoryInterface`, `SubjectLoaderInterface`, `Vocabulary`.
  `symfony/console` is a suggested dependency.
- **`Check\SitemapSpoolCheck`**: the sitemap spool lines of `check`, previously copied into both adapters.

### doctrine@0.2.1

- Requires `indexnowkit/core ^0.2.2 || ^0.3`; the test suite runs the shared ORM conformance kit (A01–A21). No runtime
  change.

### symfony-bundle@0.3.0

- **Breaking:** requires `indexnowkit/core ^0.3`; `Command\EntityLoaderInterface`, `SubmitterFactoryInterface`,
  `ResultFormatterInterface` are replaced by the core `Console\*` interfaces (service ids unchanged, aliased).
- `indexnow:check` wiring lines are `indexnowkit.check` services (`Check\WiringCheck`, core `SitemapSpoolCheck`).

### laravel@0.3.0

- **Breaking:** requires `indexnowkit/core ^0.3`; `Console\ResultRenderer`, `ResultSummary`, `SubmitterFactory` are the
  core classes; `Console\ModelLoader` implements `SubjectLoaderInterface`; `Check\EloquentCheck` is a tagged check.

## 2026-09-04 — core@0.2.1, core@0.2.2, laravel@0.2.0, laravel@0.2.1

### core@0.2.1

- **Fixed:** `Http\Response::parseRetryAfter()` uses `Response::HTTP_DATE` instead of `DateTimeInterface::RFC7231`,
  deprecated in PHP 8.5.

### core@0.2.2

- **`Attribute\SubjectReaderInterface`** and `ParamExtractor::registerReader()`: an adapter teaches the accessor DSL to
  read objects it cannot see into (Eloquent attributes behind `__get()`).
- Route model binding for `Stringable` models; `ObjectChangeHandler::renamed()` accepts a pre-update copy and the
  fields a `self` route parameter depends on.
- **`Testing\Conformance\OrmConformanceTestCase`**: the ORM conformance scenarios A01–A21 as an abstract PHPUnit case.
- **Fixed:** a non-PSR-18 Guzzle is a `ConfigurationException`, not a type error.

### laravel@0.2.0

See [packages/laravel/CHANGELOG.md](packages/laravel/CHANGELOG.md). First release, Laravel 11 and 12.

- `Eloquent\IndexNowable` + `Eloquent\IndexNowObserver`: synchronous, URLs resolved while the old state is live, handed
  over through `Connection::afterCommit()`; SoftDeletes; renamed pages.
- `Eloquent\EloquentSubjectReader`, `Url\LaravelRouteUrlResolver` (route model binding, `base_url` rebasing, locales),
  `Queue\SubmitUrlsJob`, cache debounce, key file route, artisan commands, `Facades\IndexNowKit`, `config/indexnow.php`.

### laravel@0.2.1

- Laravel 13 (`illuminate/* ^13.0`, PHP 8.3+).
- **Fixed:** `IndexNowable::bootIndexNowable()` registers the model events directly; `Model::observe()` is rejected by
  Laravel 13 while the model boots.

## 2026-09-04 — core@0.2.0, doctrine@0.2.0, symfony-bundle@0.2.0

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

## 2026-09-03 — symfony-bundle@0.1.1

- Web Profiler routing import works on Symfony 6.4; `indexnow:check` reports the resolved dispatch mode;
  DoctrineBundle 3 allowed.

## 2026-09-03 — core@0.1.0, doctrine@0.1.0, symfony-bundle@0.1.0

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
