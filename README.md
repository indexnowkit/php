# indexnowkit — PHP monorepo

IndexNow for PHP: tell Yandex, Bing, Naver, Seznam and Yep which URLs changed, the moment they change.
Packages are developed here and split into read-only repositories for Packagist.

| Package | What |
|---|---|
| [`indexnowkit/core`](packages/core) | protocol client, batching, debounce, retry policy, the `#[IndexNow]` rule model |
| [`indexnowkit/doctrine`](packages/doctrine) | Doctrine ORM listener plus a DBAL middleware, commit-safe |
| [`indexnowkit/symfony-bundle`](packages/symfony-bundle) | Symfony bundle: config, Messenger, key file route, commands, profiler panel |
| `indexnowkit/laravel` | 0.3.0 on Packagist |
| `indexnowkit/yii2` | 0.1.0 on Packagist |

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

No local PHP needed — the helper scripts run everything in Docker.

```bash
bin/composer install
bin/php vendor/bin/phpunit                                                # all packages
bin/php vendor/bin/phpunit --testsuite core                               # one suite
bin/php -d memory_limit=1G vendor/bin/phpstan analyse --memory-limit=1G   # level 9
bin/php vendor/bin/php-cs-fixer fix
PHP_VERSION=8.2 bin/php vendor/bin/phpunit                                # the CI matrix runs 8.2-8.4
```

A mock IndexNow server is available for manual testing:

```bash
php -S 127.0.0.1:8089 packages/core/tests/Support/mock-server/router.php
```

Pick a behaviour with the `X-Mock-Scenario` header: `ok200`, `pending202`, `forbidden403`, `ratelimit429` and the
others listed in the router. Point `engines` at `http://127.0.0.1:8089/indexnow` — plain HTTP is accepted on
loopback hosts only.

## Layout

```
php/
├── packages/
│   ├── core/              # indexnowkit/core          + docs/, tests/Conformance (C01-C22)
│   ├── doctrine/          # indexnowkit/doctrine      + tests/ (A01-A14)
│   └── symfony-bundle/    # indexnowkit/symfony-bundle + docs/, recipe/, tests/Functional (H01-H06)
├── CHANGELOG.md           # monorepo changelog, per package
├── CONTRIBUTING.md
└── SECURITY.md
```

Each package keeps its own `CHANGELOG.md`; [CHANGELOG.md](CHANGELOG.md) here summarises them per release with the
`<package>@<version>` tag format.

Contributing: [CONTRIBUTING.md](CONTRIBUTING.md). Security reports: [SECURITY.md](SECURITY.md). MIT.
