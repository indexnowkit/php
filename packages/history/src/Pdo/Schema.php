<?php

declare(strict_types=1);

namespace IndexNowKit\History\Pdo;

use IndexNowKit\Exception\ConfigurationException;
use IndexNowKit\History\HistoryConfig;

/**
 * The table of {@see PdoSubmissionStore}, one row per URL of a Result: the source of truth for the migrations of
 * docs/migrations.md (each calls {@see sql()}), and what the store creates with {@see PdoSubmissionStore::createTable()}.
 */
final class Schema
{
    public const DRIVERS = ['sqlite', 'mysql', 'pgsql'];

    private function __construct() {}

    /**
     * The statements creating the table and its indexes for a PDO driver name (`sqlite`, `mysql`, `pgsql`).
     *
     * @return list<string>
     *
     * @throws ConfigurationException on another driver or an invalid table name
     */
    public static function sql(string $driver, string $table = HistoryConfig::DEFAULT_TABLE): array
    {
        self::assertTable($table);
        $id = match ($driver) {
            'sqlite' => 'INTEGER PRIMARY KEY AUTOINCREMENT',
            'mysql' => 'BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY',
            'pgsql' => 'BIGSERIAL PRIMARY KEY',
            default => throw new ConfigurationException(\sprintf('indexnowkit/history knows no schema for the PDO driver "%s" (sqlite, mysql, pgsql); create the table yourself after docs/migrations.md.', $driver)),
        };
        $datetime = $driver === 'pgsql' ? 'TIMESTAMP' : 'DATETIME';
        $bool = $driver === 'pgsql' ? 'BOOLEAN' : 'TINYINT(1)';
        if ($driver === 'sqlite') {
            $bool = 'INTEGER';
        }

        return [
            \sprintf('CREATE TABLE %s (id %s, batch VARCHAR(26) NOT NULL, url VARCHAR(2048) NOT NULL, host VARCHAR(255) NOT NULL, engine VARCHAR(32) NOT NULL, status VARCHAR(16) NOT NULL, reason VARCHAR(32) NULL, http_status SMALLINT NULL, error TEXT NULL, retryable %s NOT NULL DEFAULT 0, endpoint VARCHAR(255) NOT NULL DEFAULT \'\', at %s NOT NULL)', $table, $id, $bool, $datetime),
            \sprintf('CREATE INDEX %s_url ON %s (url%s)', $table, $table, $driver === 'mysql' ? '(255)' : ''),
            \sprintf('CREATE INDEX %s_at ON %s (at)', $table, $table),
            \sprintf('CREATE INDEX %s_host_at ON %s (host, at)', $table, $table),
        ];
    }

    /**
     * @throws ConfigurationException
     */
    public static function assertTable(string $table): void
    {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $table) !== 1) {
            throw new ConfigurationException(\sprintf('"history.pdo.table" must match [A-Za-z_][A-Za-z0-9_]*, got "%s".', $table));
        }
    }
}
