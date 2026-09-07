<?php

declare(strict_types=1);

namespace IndexNowKit\History\Adapter;

use Closure;
use IndexNowKit\Adapter\OptionalPackage;
use IndexNowKit\Adapter\Services;
use IndexNowKit\Client;
use IndexNowKit\Config;
use IndexNowKit\Debounce\DebounceStoreFactory;
use IndexNowKit\Exception\ConfigurationException;
use IndexNowKit\History\Check\HistoryCheck;
use IndexNowKit\History\Console\HistoryRunner;
use IndexNowKit\History\Console\StatusRunner;
use IndexNowKit\History\HistoryConfig;
use IndexNowKit\History\HistoryStoreInterface;
use IndexNowKit\History\Pdo\PdoSubmissionStore;
use IndexNowKit\History\Psr16SubmissionStore;
use IndexNowKit\Key\KeyProviderInterface;
use IndexNowKit\Retry\ForbiddenCounter;
use IndexNowKit\Submission\NullSubmissionStore;
use IndexNowKit\Submission\SubmissionStoreInterface;
use IndexNowKit\Url\UrlNormalizerInterface;
use PDO;
use Psr\Log\LoggerInterface;
use Psr\SimpleCache\CacheInterface;
use Throwable;

/**
 * What every framework adapter wires for this package, in one place: the predicate, the owned options, the validated
 * block, the two stores over a PDO or a PSR-16 cache, the `check` line, the bodies of `history` and `status`, and
 * the texts an adapter prints about the store. The `*For()` methods take the core's runtime graph (`Adapter\Services`:
 * Yii, plain PHP); the others take the pieces one by one, for a container that binds them itself (Laravel, Symfony).
 *
 * An adapter keeps only what its framework decides: where the block comes from, which connection or cache
 * `history.pdo.service` / `debounce.store` name and how they are looked up ({@see debounceCacheId()} says which), and
 * the facts of its queue for `status`.
 */
final class HistoryServices
{
    /**
     * The one predicate for `indexnowkit/history`: `OptionalPackage::history()` of the core, which an adapter calls
     * directly — this class lives in the package and cannot be loaded to say "not installed". null = detect, false =
     * wire as if the package were absent (tests).
     */
    public static function package(?bool $installed = null): OptionalPackage
    {
        return OptionalPackage::history($installed);
    }

    /**
     * The dotted keys of the `history` block, for the adapter's `ConfigFactory` (`HistoryConfig::OPTIONS`).
     *
     * @return list<string>
     */
    public static function options(): array
    {
        return HistoryConfig::OPTIONS;
    }

    /**
     * The validated `history` block; a broken value (a DSN together with a connection, a bad table name) switches the
     * history off with a critical log line naming $checkCommand.
     *
     * @param array<string, mixed> $block
     */
    public static function config(array $block, LoggerInterface $logger, string $checkCommand): HistoryConfig
    {
        return HistoryConfig::loadOrDisabled($block, $logger, $checkCommand);
    }

    /** A PDO of `history.pdo.dsn`, throwing on every error (the store expects exceptions, not false). */
    public static function pdoFromDsn(string $dsn): PDO
    {
        return new PDO($dsn, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    }

    /** The `pdo` store over a connection's PDO (an application connection or {@see pdoFromDsn()}); errors become exceptions. */
    public static function pdoStore(PDO $pdo, HistoryConfig $history): PdoSubmissionStore
    {
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        return new PdoSubmissionStore($pdo, $history->pdoTable);
    }

    /** The `psr16` store: `history.key_prefix` (default `debounce.key_prefix`) and `history.limit` over the cache. */
    public static function psr16Store(CacheInterface $cache, HistoryConfig $history, Config $config): Psr16SubmissionStore
    {
        return new Psr16SubmissionStore($cache, $history->keyPrefix ?? $config->debounceKeyPrefix, $history->limit);
    }

    /**
     * The cache the `psr16` store shares with the debounce store: the id `debounce.store` names, or null when it names
     * no cache (`memory`, `none`) — the adapter then uses its application's default cache.
     */
    public static function debounceCacheId(Config $config): ?string
    {
        $store = $config->debounceStore;

        return $store === null || \in_array($store, [DebounceStoreFactory::MEMORY, DebounceStoreFactory::NONE], true) ? null : $store;
    }

    /** The reader of the 403 counters the client keeps (the same cache, prefix, threshold and TTL as `Client`). */
    public static function forbiddenCounter(Config $config, ?CacheInterface $cache, LoggerInterface $logger): ForbiddenCounter
    {
        return new ForbiddenCounter($cache, $config->debounceKeyPrefix, $config->forbiddenEscalation, Client::FAILURE_CACHE_TTL, $logger);
    }

    /**
     * The store `history.store` names, over what the framework decides and nothing else: `pdo` over a PDO built from
     * `history.pdo.dsn`, else over the PDO of the framework connection `history.pdo.service` names ($pdoFor, called
     * with the id or null for the default connection); `psr16` over the framework cache the debounce store shares
     * ({@see debounceCacheId()}; $cacheFor, called with the id or null for $defaultCache). The order of the checks,
     * the texts and the exception (`ConfigurationException`) are the same in every adapter.
     *
     * @param Closure(string|null): PDO            $pdoFor       the PDO of a framework connection by id (null = the default one)
     * @param Closure(string|null): CacheInterface $cacheFor     a PSR-16 cache by id (null = the default cache)
     * @param string|null                          $defaultCache the id `debounce.store` means when it names no cache, for the error text
     *
     * @throws ConfigurationException when `history.store` names nothing this method builds, or a lookup fails
     */
    public static function storeFor(HistoryConfig $history, Config $config, Closure $pdoFor, Closure $cacheFor, ?string $defaultCache = null): SubmissionStoreInterface
    {
        if ($history->store === HistoryConfig::STORE_PDO) {
            if ($history->pdoDsn !== null) {
                return self::pdoStore(self::pdoFromDsn($history->pdoDsn), $history);
            }
            try {
                $pdo = $pdoFor($history->pdoService);
            } catch (Throwable $e) {
                throw new ConfigurationException(\sprintf('history.pdo.service "%s" does not give a PDO connection: %s', $history->pdoService ?? '(default)', $e->getMessage()), 0, $e);
            }

            return self::pdoStore($pdo, $history);
        }
        if ($history->store !== HistoryConfig::STORE_PSR16) {
            throw new ConfigurationException(\sprintf('history.store "%s" is set but no store was built for it (pdo, psr16, or a store bound by the application).', (string) $history->store));
        }
        $id = self::debounceCacheId($config) ?? $defaultCache;
        try {
            $cache = $cacheFor($id);
        } catch (Throwable $e) {
            throw new ConfigurationException(\sprintf('history.store "psr16" needs a PSR-16 cache under "%s": %s', $id ?? '(default)', $e->getMessage()), 0, $e);
        }

        return self::psr16Store($cache, $history, $config);
    }

    /** The `history.store` and `history.records` lines of `check`, over the graph's store (the null store when there is none). */
    public static function check(HistoryConfig $history, ?SubmissionStoreInterface $store): HistoryCheck
    {
        return new HistoryCheck($history, $store ?? new NullSubmissionStore());
    }

    /** @return list<HistoryCheck> */
    public static function checksFor(HistoryConfig $history, Services $services): array
    {
        return [self::check($history, $services->submissionStore())];
    }

    /** The body of the `history` command. */
    public static function historyRunner(HistoryConfig $history, ?SubmissionStoreInterface $store, UrlNormalizerInterface $normalizer): HistoryRunner
    {
        return new HistoryRunner($store ?? new NullSubmissionStore(), $history, $normalizer);
    }

    public static function historyRunnerFor(HistoryConfig $history, Services $services): HistoryRunner
    {
        return self::historyRunner($history, $services->submissionStore(), $services->normalizer());
    }

    /**
     * The body of the `status` command.
     *
     * @param string                                        $debounceDescription `memory`, `none`, or `<store> (<driver>)` as the adapter describes it
     * @param (Closure(): array<string, scalar|null>)|null $facts               the adapter's queue facts with an asynchronous dispatch, null otherwise
     */
    public static function statusRunner(Config $config, KeyProviderInterface $keys, ForbiddenCounter $forbidden, string $debounceDescription, ?SubmissionStoreInterface $store, ?Closure $facts): StatusRunner
    {
        return new StatusRunner($config, $keys, $forbidden, $debounceDescription, $store, $facts);
    }

    /** @param (Closure(): array<string, scalar|null>)|null $facts */
    public static function statusRunnerFor(Services $services, string $debounceDescription, ?Closure $facts): StatusRunner
    {
        return self::statusRunner($services->config, $services->keys(), $services->forbiddenCounter(), $debounceDescription, $services->submissionStore(), $facts);
    }

    /**
     * One line about the store for an "about" screen: off, where it is and how many records it holds, or why it
     * cannot say (`off (history.store)`, `pdo (indexnow_submissions), 1 204 records`, `custom (App\MyStore)`).
     */
    public static function describe(HistoryConfig $history, ?SubmissionStoreInterface $store): string
    {
        if ($history->store === null) {
            return 'off (history.store)';
        }
        $where = $history->store === HistoryConfig::STORE_PDO ? \sprintf('pdo (%s)', $history->pdoTable) : \sprintf('psr16 (%d records kept)', $history->limit);
        if (!$store instanceof HistoryStoreInterface) {
            return $store === null || $store instanceof NullSubmissionStore ? $where : \sprintf('custom (%s)', $store::class);
        }
        try {
            return \sprintf('%s, %s records', $where, HistoryCheck::number($store->count()));
        } catch (Throwable $e) {
            return \sprintf('%s, store failed: %s', $where, $e->getMessage());
        }
    }
}
