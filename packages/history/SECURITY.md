# Security

Report vulnerabilities privately to the maintainer (see `composer.json` → `authors`) or through GitHub's private
vulnerability reporting on [indexnowkit/php](https://github.com/indexnowkit/php/security). Please do not open a
public issue for an unfixed vulnerability. Reports are acknowledged within a few days; fixes ship as patch releases
of the affected minor.

## What the stores write and where

- The history holds what a `Result` carries: URLs (after the core's normalization), engine, host, status, reason,
  HTTP code and `Result::$error` cut to 1000 characters. Never a response body, a header, or the IndexNow key —
  the core masks the key in every error text before a Result exists.
- The PDO store binds every value as a placeholder. The one identifier that reaches SQL from the configuration is
  the table name, validated against `[A-Za-z_][A-Za-z0-9_]*` in the constructor and in `Schema::sql()`.
- The PSR-16 store uses keys without the PSR-6 reserved characters, under the adapter's own prefix, so two
  applications sharing a cache do not read each other's history when their prefixes differ.
- `indexnow:status` and `indexnow:history` read; `--purge` deletes by date only.

Reports are acknowledged within 5 business days; a fix or a mitigation plan follows within 30 days.
