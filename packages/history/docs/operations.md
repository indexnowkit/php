# Operations

## `indexnow:history`

| Option | Meaning |
|---|---|
| `--host=<host>` | records of one host |
| `--status=ok\|pending\|failed\|skipped` | records of one status |
| `--url=<url>` | the records naming this URL, newest first (exact match after the core's normalization) |
| `--since=<value>` | an ISO date (`2026-09-01`, `2026-09-01T10:00:00+00:00`) or a relative interval (`2h`, `3d`, `45m`, `1w`) |
| `--limit=<n>` | at most this many records (50) |
| `--json` | `{"records":[{"at":"…","status":"ok","reason":null,"engine":"api","http_status":200,"error":null,"urls":["…"]}]}` |
| `--purge[=<days>]` | remove the records older than `history.retention_days` (or the given days); prints `purged {n} records older than {date}` — one line for cron; refuses on a store without `purge()` |

The table prints `at | status | reason | engine | http | url`, one URL per line, the newest record first.

## `indexnow:status`

Read-only (no GET, no POST): enabled / dry_run, the environment, dispatch (and the queue / transport the adapter
knows), the debounce store, the 403 counter per configured host with its escalation flag (from
`Retry\ForbiddenCounter` of the core, the shared cache when `debounce.store` is a cache), the last successful
submission (`last successful submission 3 min ago (2 URLs, api)`), the history size, the core version. `--json`
follows [status.schema.json](status.schema.json).

## `check`

`history: installed, no store configured (history.store)` (ok, `history.store`) · `history: pdo store
(indexnow_submissions)` / `history: psr16 store (500 records kept)` (ok) · `history: custom store (<class>)` (ok) ·
`history: the pdo store failed: <exception>. Run the migration of docs/migrations.md (table …) or fix history.pdo.*`
(error) · `history: 1 240 records, last 3 min ago` / `history: no records yet` (ok, `history.records`).

## Cron

```
15 3 * * *  bin/console indexnow:history --purge >> var/log/indexnow-purge.log
```

## Log lines

`indexnow history: invalid history configuration, nothing is recorded until it is fixed: {error} (run "{check}")`
(critical). A store that throws while recording is the core's line `indexnow: submission store failed, {count}
result(s) not recorded: {error}` (error) — the submission itself went through.
