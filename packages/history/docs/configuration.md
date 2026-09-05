# Configuration — the `history` block

The block lives next to the core options in the adapter's configuration (`indexnowkit.history` in the bundle,
`'history' => [...]` in `config/indexnow.php`, `'history' => [...]` of the Yii2 component); `HistoryConfig::OPTIONS`
lists its keys in dotted form and the adapters add them to the keys they accept. In plain PHP the stores are
constructed directly (`new PdoSubmissionStore($pdo)`, `new Psr16SubmissionStore($cache, $prefix, $limit)`).

| Key | Default | Meaning |
|---|---|---|
| `history.store` | `null` | `null`: nothing is recorded (`Submission\NullSubmissionStore`). `psr16`: a ring buffer in the cache behind `debounce.store`. `pdo`: a database table. |
| `history.limit` | `500` | Records the `psr16` ring buffer keeps; the oldest is overwritten. |
| `history.key_prefix` | `debounce.key_prefix` | Cache key prefix of the `psr16` store (`<prefix>history.index`, `<prefix>history.<n>`); no `{}()/\@:`. |
| `history.pdo.service` | `null` | Which connection of the adapter gives the PDO: the bundle's `doctrine.dbal.<name>_connection` (default connection when null), a Laravel connection name (`DB::connection(name)`), a Yii2 `db` component id. |
| `history.pdo.dsn` | `null` | A PDO DSN when the history has its own database (`sqlite:var/indexnow.sqlite`, `mysql:host=…;dbname=…`); ignored when `pdo.service` resolves. Credentials go in the adapter's own PDO options, not here. |
| `history.pdo.table` | `indexnow_submissions` | The table; `[A-Za-z_][A-Za-z0-9_]*` — it is the one identifier that reaches SQL from the configuration. Create it with [migrations.md](migrations.md). |
| `history.retention_days` | `90` | What `indexnow:history --purge` removes beyond. |

An invalid block is one `critical` log line and nothing is recorded (`HistoryConfig::loadOrDisabled()`); `check`
prints the error (`history.store`).
