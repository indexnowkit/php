# Contributing

- No local PHP needed: `bin/composer install`, `bin/php vendor/bin/phpunit` run inside Docker (`PHP_VERSION=8.2` to switch).
- Every change needs tests. Adapter behaviour is specified in `../docs/spec/03-conformance.md`; name tests after the scenario id (A05, H01, ...).
- `bin/php vendor/bin/phpstan analyse --memory-limit=1G` at level 9 and `bin/php vendor/bin/php-cs-fixer fix` must pass.
- Conventional commits (`feat(symfony): ...`, `fix(core): ...`). One package per PR when possible.
- Never log a full IndexNow key; use `KeyValidator::mask()`.
