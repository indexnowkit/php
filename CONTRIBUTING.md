# Contributing

- No local PHP needed. `bin/ci <package>` runs the package's pipeline in Docker (link the siblings, `composer update`,
  phpunit, phpstan level 9 with phpstan-strict-rules); `bin/cs` runs php-cs-fixer. See the README for the step-by-step
  commands and `PHP_VERSION=8.2` to switch the PHP version.
- Packages are installed one by one, there is no root `composer.json`. A new dev dependency goes into the package's
  own `composer.json`; a new dependency flavour for CI is a `ci:install:<name>` script there plus one `include` line in
  `.github/workflows/ci.yml` (monorepo) and in the package's own workflow (split repository).
- Every change needs tests. Adapter behaviour is specified in `../docs/spec/03-conformance.md`; name tests after the
  scenario id (A05, H01, ...).
- Conventional commits (`feat(symfony): ...`, `fix(core): ...`). One package per PR when possible.
- Never log a full IndexNow key; use `KeyValidator::mask()`.
- IDE: five `vendor/` trees hold the same phpunit and phpstan classes. Point PhpStorm at one package's
  `composer.json` (Settings → PHP → Composer) or exclude the other packages' `vendor/` directories.
