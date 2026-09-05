# Migrations for `history.store: pdo`

`History\Pdo\Schema::sql(string $driver, string $table = 'indexnow_submissions'): list<string>` is the source of
truth: the `CREATE TABLE` and the three indexes (`url`, `at`, `(host, at)`) for `sqlite`, `mysql` and `pgsql`. Every
snippet below runs it; a schema for another driver is yours to write after the sqlite one.

Columns: `id` (autoincrement), `batch` (26 chars, groups the URLs of one Result), `url` (2048), `host` (255),
`engine` (32), `status` (16), `reason` (32, null), `http_status` (smallint, null), `error` (text, at most 1000
characters), `retryable` (bool), `endpoint` (255), `at` (datetime, UTC).

## Doctrine Migrations (Symfony)

```php
use Doctrine\DBAL\Schema\Schema as DbalSchema;
use Doctrine\Migrations\AbstractMigration;
use IndexNowKit\History\Pdo\Schema;

final class Version20260906000000 extends AbstractMigration
{
    public function up(DbalSchema $schema): void
    {
        foreach (Schema::sql($this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\PostgreSQLPlatform ? 'pgsql' : ($this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\SqlitePlatform ? 'sqlite' : 'mysql')) as $sql) {
            $this->addSql($sql);
        }
    }

    public function down(DbalSchema $schema): void
    {
        $this->addSql('DROP TABLE indexnow_submissions');
    }
}
```

## Laravel

```php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use IndexNowKit\History\Pdo\Schema;

return new class extends Migration {
    public function up(): void
    {
        foreach (Schema::sql(DB::connection()->getDriverName()) as $sql) {   // 'sqlite' | 'mysql' | 'pgsql'
            DB::statement($sql);
        }
    }

    public function down(): void
    {
        DB::statement('DROP TABLE indexnow_submissions');
    }
};
```

## Yii2

```php
use IndexNowKit\History\Pdo\Schema;
use yii\db\Migration;

final class m260906_000000_indexnow_submissions extends Migration
{
    public function safeUp(): void
    {
        foreach (Schema::sql($this->db->getDriverName()) as $sql) {   // 'sqlite' | 'mysql' | 'pgsql'
            $this->execute($sql);
        }
    }

    public function safeDown(): void
    {
        $this->dropTable('indexnow_submissions');
    }
}
```

## Retention

`indexnow:history --purge` runs one `DELETE … WHERE at < ?` for everything older than `history.retention_days`
(`--purge=30` for 30 days). On a big table that is one long statement and a long lock: schedule it in a quiet hour,
and keep `retention_days` at what you read back (90 days answers "what did we send last quarter"). Partitioning by
`at` is the database's job, not the package's.
