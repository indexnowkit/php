# Security

Two values these packages handle are sensitive. **The IndexNow key** is public by design (search engines fetch it from
`/{key}.txt`), but anyone holding it can submit arbitrary URLs of your host: keep it in the environment, never commit
it, rotate it by changing `INDEXNOW_KEY` (the key file route follows automatically), and do not paste full keys into
issues. Logs, exception messages and `indexnow:config` mask keys to 4 characters — the current and the previous one,
also inside `key_location` URLs. **The database DSN of `indexnowkit/history`** (`history.pdo.dsn`) may carry a password:
`indexnow:config` masks the password and the user of a DSN and never prints a `password`/`secret`/`token` key of a package
block; `check`, `status` and `about` print the table name only. `indexnow:key:generate --env-file` creates the file with
mode 0600. Package-specific notes: `packages/core/SECURITY.md`.

Report vulnerabilities privately via [GitHub security advisories](https://github.com/indexnowkit/php/security/advisories/new)
or to i.pinchuk.work@gmail.com. Please do not open public issues for security reports. Reports are acknowledged within 5 business days; a fix or a mitigation plan follows within 30 days.
