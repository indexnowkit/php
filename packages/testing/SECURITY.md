# Security

Report vulnerabilities privately to the maintainer (see `composer.json` → `authors`) or through GitHub's private
vulnerability reporting on [indexnowkit/php](https://github.com/indexnowkit/php/security). Please do not open a
public issue for an unfixed vulnerability.

## Scope

This package is test tooling: it is meant for `require-dev` and runs inside a test suite. Nothing in it is wired
into an application at runtime. The mock IndexNow server (`resources/mock-server/router.php`) is a PHP built-in
server script for local and CI runs: it binds to the address you give `php -S`, logs the requests it receives to a
file in the system temp directory, and must never be exposed on a public interface — it authenticates nothing.

Reports are acknowledged within 5 business days; a fix or a mitigation plan follows within 30 days.
