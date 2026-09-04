# indexnowkit — PHP monorepo

IndexNow for PHP: tell Yandex, Bing, Naver, Seznam and Yep which URLs changed, the moment they change.
Packages are developed here and split into read-only repositories for Packagist.

| Package | What |
|---|---|
| [`indexnowkit/core`](packages/core) | protocol client, batching, debounce, retry policy, the `#[IndexNow]` rule model, the adapter kit (`Adapter\ConfigFactory`, factories, command bodies) |
| [`indexnowkit/sitemap`](packages/sitemap) | sitemap reader (index, gzip, text) and the `sitemap` command body used by every adapter |
| [`indexnowkit/doctrine`](packages/doctrine) | Doctrine ORM listener plus a DBAL middleware, commit-safe |
| [`indexnowkit/symfony-bundle`](packages/symfony-bundle) | Symfony bundle: config, Messenger, key file route, commands, profiler panel |
| [`indexnowkit/laravel`](packages/laravel) | Laravel: Eloquent observer, queue dispatch, key file route, artisan commands (Laravel 12–13) |
| [`indexnowkit/yii2`](packages/yii2) | Yii2: ActiveRecord events with verify-on-commit, yii2-queue, key file route, console controller |

Start with the package you will actually install. A Symfony application only needs the bundle README; the core
README is the reference for everything underneath it.

## Documentation

| | |
|---|---|
| Configuration, every option and env var | [core/docs/configuration.md](packages/core/docs/configuration.md) |
| The `#[IndexNow]` rule model in full | [core/docs/attribute-reference.md](packages/core/docs/attribute-reference.md) |
| Retries, queues, bulk imports | [core/docs/retries-and-queues.md](packages/core/docs/retries-and-queues.md) |
| Logging, metrics, "why was nothing submitted" | [core/docs/operations.md](packages/core/docs/operations.md) |
| Testing with the published doubles | [core/docs/testing.md](packages/core/docs/testing.md) |
| Writing an adapter for another framework | [core/docs/adapters.md](packages/core/docs/adapters.md) |
| Compatibility promise | [core/docs/bc.md](packages/core/docs/bc.md) |
| Symfony: configuration, Messenger, multi-domain, troubleshooting | [symfony-bundle/docs](packages/symfony-bundle/docs) |
| Cross-language specification and conformance suite | [../docs/spec](../docs/spec) |

Russian READMEs exist for every package (`README.ru.md`); the specification under `docs/spec` is Russian.

## Development

No local PHP needed: the `bin/` scripts run everything in the development image (`docker/php/Dockerfile`: `php:<version>-cli`
plus Composer, built on first use per `PHP_VERSION`). Each package is installed and tested **on its own**,
in `packages/<name>`, against the working copy of its `indexnowkit/*` siblings; there is no root `composer.json`.

```bash
bin/ci                                   # every package: link, composer update, phpunit, phpstan
bin/ci yii2                              # one package
bin/ci doctrine dbal3                    # a dependency flavour: the ci:install:* scripts of the package's composer.json
PHP_VERSION=8.2 bin/ci core lowest       # the CI matrix runs 8.2-8.5
bin/cs                                   # php-cs-fixer fix (bin/cs check = dry run)
bin/php -C packages/core vendor/bin/phpunit --coverage-clover coverage.xml && bin/coverage-floor core packages/core/coverage.xml
```

Coverage: the image ships pcov and intl, like the CI runners, so the numbers match; `bin/coverage-floor <package>
<clover> [--write]` compares the line coverage of core and sitemap with `packages/<package>/tests/coverage-floor.txt`
(CI fails below it; `--write` records a new floor, in a commit of its own when it lowers the floor).

Step by step, for one package:

```bash
bin/link yii2                                                       # writes packages/yii2/composer.monorepo.json
COMPOSER=composer.monorepo.json bin/composer -d packages/yii2 update
bin/php -C packages/yii2 vendor/bin/phpunit
bin/php -C packages/yii2 vendor/bin/phpstan analyse --memory-limit=1G
```

`composer.monorepo.json` (git-ignored) is the package's `composer.json` plus path repositories for the sibling packages
and `minimum-stability: dev`; Composer and the tests run on the same PHP, so no platform pin is needed. `composer.json`
itself is what the split repositories and Packagist ship. The GitHub workflow runs the same steps per package; each split
repository runs the same `ci:install:*` scripts with the siblings from Packagist.

A mock IndexNow server is available for manual testing:

```bash
php -S 127.0.0.1:8089 packages/core/tests/Support/mock-server/router.php
```

Pick a behaviour with the `X-Mock-Scenario` header: `ok200`, `pending202`, `forbidden403`, `ratelimit429` and the
others listed in the router. Point `engines` at `http://127.0.0.1:8089/indexnow` — plain HTTP is accepted on
loopback hosts only.

## Releasing

One package at a time, in dependency order (core, then sitemap, then the adapters), each with a `CHANGELOG.md` section
`## [x.y.z] — YYYY-MM-DD` and the `branch-alias` bumped in `composer.json`:

```bash
bin/tag core 0.4.0                      # push the php/ subtree, tag core@0.4.0 there; split.yml tags indexnowkit/php-core
bin/packagist-wait core 0.4.0           # poll Packagist before tagging the packages that require the new version
bin/release-notes core 0.4.0 --create   # GitHub release on the split repository from the changelog section
```

A new package needs its read-only repository `indexnowkit/php-<name>`, a write deploy key stored as the
`SPLIT_SSH_KEY_<NAME>` secret of `indexnowkit/php`, an entry in `.github/workflows/split.yml`, and the Packagist
registration after the first split push.

## Layout

```
php/
├── packages/
│   ├── core/              # indexnowkit/core          + docs/, tests/Conformance (C01-C22)
│   ├── sitemap/           # indexnowkit/sitemap       + docs/, tests/
│   ├── doctrine/          # indexnowkit/doctrine      + tests/ (A01-A21)
│   ├── symfony-bundle/    # indexnowkit/symfony-bundle + docs/, recipe/, tests/Functional (H01-H06)
│   ├── laravel/           # indexnowkit/laravel       + docs/, tests/
│   └── yii2/              # indexnowkit/yii2          + docs/, tests/
├── bin/                   # Docker wrappers: php, composer, link, ci, cs, coverage-floor; release: tag, packagist-wait, release-notes
├── docker/php/            # development image (php:<version>-cli + Composer)
├── CHANGELOG.md           # monorepo changelog, per package
├── CONTRIBUTING.md
└── SECURITY.md
```

Each package keeps its own `CHANGELOG.md`; [CHANGELOG.md](CHANGELOG.md) here summarises them per release with the
`<package>@<version>` tag format.

Contributing: [CONTRIBUTING.md](CONTRIBUTING.md). Security reports: [SECURITY.md](SECURITY.md). MIT.
