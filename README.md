# indexnowkit — PHP monorepo

| Package | What |
|---|---|
| [`indexnowkit/core`](packages/core) | protocol client, batching, debounce, retry policy, `#[IndexNow]` attribute |
| [`indexnowkit/doctrine`](packages/doctrine) | Doctrine ORM listener + DBAL middleware (commit-safe) |
| [`indexnowkit/symfony-bundle`](packages/symfony-bundle) | Symfony bundle: config, Messenger, key file route, commands |
| `indexnowkit/laravel` | planned |

Development (no local PHP needed, uses Docker):

```bash
bin/composer install
bin/php vendor/bin/phpunit
bin/php -d memory_limit=1G vendor/bin/phpstan analyse --memory-limit=1G
bin/php vendor/bin/php-cs-fixer fix
PHP_VERSION=8.2 bin/php vendor/bin/phpunit
```

Mock IndexNow server for manual testing: `php -S 127.0.0.1:8089 packages/core/tests/Support/mock-server/router.php`
(scenario header `X-Mock-Scenario: ok200|pending202|forbidden403|ratelimit429|...`).

Specification: [`../docs/spec`](../docs/spec).
