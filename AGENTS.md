# Working in this repository (for coding agents and humans alike)

This is the monorepo of the `indexnowkit/*` PHP packages: `packages/{core,testing,console,sitemap,doctrine,symfony-bundle,laravel,yii2}`.
Each package is published as a read-only split (`indexnowkit/php-<package>`) and on Packagist; issues and pull
requests live here. The specification the code follows is `docs/spec/` in the private workspace this repository is
mirrored from — the README and `docs/*.md` of each package are the public contract.

## Tooling: no local PHP

Everything runs in Docker through `bin/*`:

| Command | What |
|---|---|
| `bin/ci <package> [flavour]` | the CI pipeline of one package: link siblings, `composer update` for the flavour (`highest`, `lowest`, doctrine `dbal3`, symfony-bundle `symfony64`), phpunit, phpstan level 9 + strict rules |
| `bin/cs [check]` | php-cs-fixer over the repository (sequential on purpose) |
| `bin/php -C packages/<pkg> vendor/bin/phpunit --filter X` | one test |
| `bin/composer -d packages/<pkg> …` | Composer in the package (`COMPOSER=composer.monorepo.json` after `bin/link` to use the working copies of siblings) |
| `bin/link <pkg>` | writes `composer.monorepo.json` with path repositories for the sibling packages |
| `bin/tag <pkg> <version>` · `bin/packagist-wait` · `bin/packagist-check` · `bin/release-notes` | release tooling (maintainers) |

`PHP_VERSION=8.2 bin/ci core lowest` switches the PHP version (matrix 8.2–8.5).

## Before you change code

- Read the package README and `docs/*.md` for the area; the behaviour is specified there, not only in tests.
- Adapter behaviour is covered by the shared conformance kits of `indexnowkit/testing` (`IndexNowKit\Testing\Conformance\*`,
  ids C01–C22, A01–A21, H01–H06): a change in an adapter must keep them green unchanged. The core never depends on
  `testing` (its tests use the `IndexNowKit\Testing` doubles only): the split of the core aliases the previous minor
  until it is tagged, so the dependency would be a bootstrap cycle.
- The core is framework-agnostic: nothing under `packages/core/src` imports Symfony, Laravel, Yii, Doctrine or PHPUnit
  classes; the command bodies are `indexnowkit/console`, the conformance kits `indexnowkit/testing`.
- Keys are never logged or printed in full: `KeyValidator::mask()`.
- Error and log texts follow one rule: the fact, what is allowed, how to fix it.

## Gates before a commit

1. `bin/ci <package>` green (and `lowest` when dependencies changed).
2. `bin/cs check` clean.
3. Tests for the change; new adapter behaviour named after its conformance id when one applies.
4. `CHANGELOG.md` of the package under "Unreleased" for anything user-visible; breaking changes under "Changed" with
   the migration.
5. README EN and RU plus `docs/*.md` updated when configuration, commands or extension points changed.

## Commits and pull requests

Conventional commits (`feat(core): …`, `fix(laravel): …`, `docs: …`, `build: …`), one package per PR when possible,
no attribution trailers. The PR template lists the checklist.

## Where things are

- Configuration keys: `Config::OPTIONS` (core), `SitemapConfig::OPTIONS`, `ConfigFactory::LARAVEL_OPTIONS`,
  `ConfigFactory::YII_OPTIONS`, `IndexNowKitConfiguration` (bundle tree). Unknown keys warn at boot.
- Commands: `Console\Definitions` in `indexnowkit/console` declares arguments and options once; the adapters render them.
- Checks (`indexnow:check`): `Check\CheckInterface` implementations tagged/registered per adapter.
- Tests: `tests/Unit`, `tests/Functional` (bundle) / `tests/Feature` (Laravel, Yii2), `tests/Conformance`.
