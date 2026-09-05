<?php

declare(strict_types=1);

namespace IndexNowKit\History;

use IndexNowKit\Exception\ConfigurationException;
use Psr\Log\LoggerInterface;

/**
 * The `history` block of an adapter's configuration, validated once. `store: null` (the default) keeps nothing:
 * installing the package changes nothing until a store is named. Adapters build it with {@see fromArray()}, pick
 * the store from {@see $store} and add {@see OPTIONS} to the keys they accept.
 */
final readonly class HistoryConfig
{
    /** Every key of the block, dotted-path form, for `Config::unknownOptions()`. */
    public const OPTIONS = ['history.store', 'history.limit', 'history.key_prefix', 'history.pdo.dsn', 'history.pdo.service', 'history.pdo.table', 'history.retention_days'];

    public const STORE_PSR16 = 'psr16';
    public const STORE_PDO = 'pdo';
    public const STORES = [self::STORE_PSR16, self::STORE_PDO];
    public const DEFAULT_LIMIT = 500;
    public const DEFAULT_TABLE = 'indexnow_submissions';
    public const DEFAULT_RETENTION_DAYS = 90;

    /**
     * @param string|null $store         null = no history; `psr16` = {@see Psr16SubmissionStore} over the adapter's cache;
     *                                   `pdo` = {@see Pdo\PdoSubmissionStore}
     * @param int         $limit         records the PSR-16 ring buffer keeps
     * @param string|null $keyPrefix     cache key prefix of the PSR-16 store; null = the adapter's `debounce.key_prefix`
     * @param string|null $pdoDsn        DSN of the PDO store when no service is named
     * @param string|null $pdoService    the adapter's connection to take the PDO from (`doctrine.dbal.default_connection`,
     *                                   a Laravel connection name, a Yii `db` component id); null = the default connection
     * @param string      $pdoTable      table of the PDO store (`[A-Za-z_][A-Za-z0-9_]*`)
     * @param int         $retentionDays what `history --purge` removes beyond
     *
     * @throws ConfigurationException
     */
    public function __construct(
        public ?string $store = null,
        public int $limit = self::DEFAULT_LIMIT,
        public ?string $keyPrefix = null,
        public ?string $pdoDsn = null,
        public ?string $pdoService = null,
        public string $pdoTable = self::DEFAULT_TABLE,
        public int $retentionDays = self::DEFAULT_RETENTION_DAYS,
    ) {
        if ($store !== null && !\in_array($store, self::STORES, true)) {
            throw new ConfigurationException(\sprintf('"history.store" must be one of %s or null, got "%s".', implode(', ', self::STORES), $store));
        }
        if ($limit < 1) {
            throw new ConfigurationException(\sprintf('"history.limit" must be >= 1, got %d.', $limit));
        }
        if ($keyPrefix !== null && preg_match('/[{}()\/\\\\@:]/', $keyPrefix) === 1) {
            throw new ConfigurationException(\sprintf('"history.key_prefix" must not contain the PSR-6 reserved characters {}()/\\@:, got "%s".', $keyPrefix));
        }
        if ($pdoDsn !== null && $pdoService !== null) {
            throw new ConfigurationException('"history.pdo.dsn" and "history.pdo.service" cannot both be set: name the connection or give a DSN.');
        }
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $pdoTable) !== 1) {
            throw new ConfigurationException(\sprintf('"history.pdo.table" must match [A-Za-z_][A-Za-z0-9_]*, got "%s".', $pdoTable));
        }
        if ($retentionDays < 1) {
            throw new ConfigurationException(\sprintf('"history.retention_days" must be >= 1, got %d.', $retentionDays));
        }
    }

    /**
     * @param array<string, mixed> $block
     *
     * @throws ConfigurationException
     */
    public static function fromArray(array $block): self
    {
        $pdo = \is_array($block['pdo'] ?? null) ? $block['pdo'] : [];
        $store = $block['store'] ?? null;
        if ($store !== null && $store !== '' && !\is_string($store)) {
            throw new ConfigurationException(\sprintf('"history.store" must be one of %s or null, got "%s".', implode(', ', self::STORES), get_debug_type($store)));
        }

        return new self(
            store: \is_string($store) && $store !== '' ? strtolower($store) : null,
            limit: self::int($block['limit'] ?? null, self::DEFAULT_LIMIT, 'history.limit'),
            keyPrefix: self::str($block['key_prefix'] ?? null),
            pdoDsn: self::str($pdo['dsn'] ?? null),
            pdoService: self::str($pdo['service'] ?? null),
            pdoTable: self::str($pdo['table'] ?? null) ?? self::DEFAULT_TABLE,
            retentionDays: self::int($block['retention_days'] ?? null, self::DEFAULT_RETENTION_DAYS, 'history.retention_days'),
        );
    }

    public static function disabled(): self
    {
        return new self();
    }

    /**
     * The runtime path of an adapter: {@see fromArray()}, and when the block is invalid one `critical` line naming the
     * error and the check command, then {@see disabled()} — nothing is recorded until it is fixed.
     *
     * @param array<string, mixed> $block
     */
    public static function loadOrDisabled(array $block, LoggerInterface $logger, string $checkCommand): self
    {
        try {
            return self::fromArray($block);
        } catch (ConfigurationException $e) {
            $logger->critical('indexnow history: invalid history configuration, nothing is recorded until it is fixed: {error} (run "{check}")', ['error' => $e->getMessage(), 'check' => $checkCommand, 'exception' => $e]);

            return self::disabled();
        }
    }

    /**
     * The effective block in the form of {@see fromArray()} (`indexnow:config` prints it as the `history` section).
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return ['store' => $this->store, 'limit' => $this->limit, 'key_prefix' => $this->keyPrefix, 'pdo' => ['dsn' => $this->pdoDsn, 'service' => $this->pdoService, 'table' => $this->pdoTable], 'retention_days' => $this->retentionDays];
    }

    private static function str(mixed $value): ?string
    {
        return \is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * @throws ConfigurationException
     */
    private static function int(mixed $value, int $default, string $option): int
    {
        if ($value === null || $value === '') {
            return $default;
        }
        if (!is_numeric($value) || (string) (int) $value !== ltrim((string) $value, '+')) {
            throw new ConfigurationException(\sprintf('"%s" must be an integer, got "%s".', $option, \is_scalar($value) ? (string) $value : get_debug_type($value)));
        }

        return (int) $value;
    }
}
