<!-- One package per PR when possible. Conventional commit title: feat(core): …, fix(laravel): …, docs: … -->

## What and why

<!-- The behaviour before, the behaviour after, and the reason. Link the issue or the spec section. -->

## Checklist (from CONTRIBUTING.md)

- [ ] `bin/ci <package>` is green (phpunit, phpstan level 9 with strict rules) — and `bin/ci <package> lowest` when dependencies changed
- [ ] `bin/cs check` has nothing to fix
- [ ] Tests cover the change; adapter behaviour is named after its conformance id (A05, H01, …) when one applies
- [ ] A user-visible change has a line in the package's `CHANGELOG.md` under "Unreleased"; a breaking change is under "Changed" with the migration
- [ ] Docs updated (README EN and RU, `docs/*.md`) when configuration, commands or extension points changed
- [ ] No full IndexNow key in code, tests, fixtures or logs (`KeyValidator::mask()`)
