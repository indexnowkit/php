# Security

The only sensitive value is the IndexNow key. It is public by design (served at `/{key}.txt`), but anyone holding it can
submit arbitrary URLs of your host, so: keep it in the environment, never commit it, rotate it by changing `INDEXNOW_KEY`
(the key file route follows automatically), and do not paste full keys into issues. Logs mask keys to 4 characters.

Report vulnerabilities privately to security@indexnowkit.dev.
