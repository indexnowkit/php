# Contributing

This repository is a read-only split of [`indexnowkit/php`](https://github.com/indexnowkit/php) (`packages/testing`).
Please open issues and pull requests there; releases are tagged in the monorepo as `testing@x.y.z` and mirrored here.

Quick rules (details in the monorepo's CONTRIBUTING.md):

- The conformance identifiers (C01–C22, A01–A21, H01–H06) are a cross-language contract: a scenario is added, never
  renumbered or removed in a minor; the driver methods of a kit only grow by appended methods with a default.
- phpstan level 9 and php-cs-fixer must pass.
- The package is a consumer of `indexnowkit/core`: nothing here may require a change in the core to work, and the
  core never depends on it (its own tests use `IndexNowKit\Testing` doubles only).
