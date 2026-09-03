# Security

The only sensitive value handled by these packages is the IndexNow key. It is public by design (search engines fetch it
from `/{key}.txt`), but anyone holding it can submit arbitrary URLs of your host: keep it in the environment, never
commit it, rotate it by changing `INDEXNOW_KEY` (the key file route follows automatically), and do not paste full keys
into issues. Logs and exception messages mask keys to 4 characters. Package-specific notes: `packages/core/SECURITY.md`.

Report vulnerabilities privately via [GitHub security advisories](https://github.com/indexnowkit/php/security/advisories/new)
or to i.pinchuk.work@gmail.com. Please do not open public issues for security reports.
