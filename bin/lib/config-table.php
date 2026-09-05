<?php

declare(strict_types=1);

/*
 * The "One concept, three keys" section of packages/core/docs/configuration.md, generated from the code so the
 * documentation cannot drift: the core keys (Config::OPTIONS) with the defaults the bundle's configuration tree
 * declares, the sitemap keys (SitemapConfig::OPTIONS), the adapter-only keys (LARAVEL_OPTIONS, YII_OPTIONS, the
 * bundle tree) and the synonyms — the same concept under three names.
 *
 * Runs inside packages/symfony-bundle (its vendor holds core, sitemap and the bundle); the Laravel and Yii2 key
 * lists are read from the sibling sources of the monorepo (a literal array each), not autoloaded.
 * Usage: bin/config-table [--check]   (bin/config-table is the wrapper; --check exits 1 when the file is stale)
 */

use IndexNowKit\Config;
use IndexNowKit\Sitemap\SitemapConfig;
use IndexNowKit\SymfonyBundle\DependencyInjection\IndexNowKitConfiguration;
use Symfony\Component\Config\Definition\ArrayNode;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\Config\Definition\Loader\DefinitionFileLoader;
use Symfony\Component\Config\Definition\PrototypedArrayNode;
use Symfony\Component\Config\FileLocator;

const CONFIG_TABLE_START = '<!-- config-table:start -->';
const CONFIG_TABLE_END = '<!-- config-table:end -->';

/** Same concept, one key per adapter: `null` = the adapter has no such knob. */
const CONFIG_TABLE_SYNONYMS = [
    ['Delivery mode', 'dispatch', 'dispatch', 'dispatch', '`auto` (Messenger when a transport is set, else `sync`), `messenger`, `sync`, `none` — Symfony; `queue` (default), `sync`, `none` — Laravel, no `auto`; `auto` (default: `queue` when the queue component exists, else `sync`), `queue`, `sync`, `none` — Yii2'],
    ['Queue / transport', 'messenger.transport', 'queue.connection', 'queue.component', 'Symfony: a `framework.messenger.transports` name (the bundle routes `SubmitUrlsMessage` to it); Laravel: a `queue.connections` name (default: the app default); Yii2: the yii2-queue component id (default `queue`)'],
    ['Queue delay / extras', 'messenger.delay', 'queue.delay', 'queue.delay', 'Symfony also `messenger.stamps`, `messenger.bus`; Laravel also `queue.queue`; Yii2 also `queue.ttr`, `queue.priority`'],
    ['Locales for `locales: all`', 'framework.enabled_locales', 'router.locales', 'router.languages', 'Symfony reads the framework setting; Laravel and Yii2 list them in the package configuration (`router.locale_parameter` / `router.language_parameter` name the route parameter; `router.set_app_locale` / `router.set_app_language` switch the application locale while generating)'],
    ['ORM hook switch', 'doctrine.enabled', 'eloquent.enabled', 'active_record.enabled', 'Symfony also `doctrine.listener_priority`, `doctrine.connections`; Yii2 also `active_record.models` (classes you cannot annotate)'],
    ['Key file route', 'key_file.path', 'key_file.path', 'key_file.pattern', 'Symfony/Laravel: a path with `{key}` (default `/{key}.txt`); Yii2: a URL rule pattern (default `<key:[A-Za-z0-9-]{8,128}>.txt`); all three: `key_file.enabled`, `key_file.cache_max_age`; Symfony/Laravel also `key_file.host`, `key_file.route_name`; Laravel also `key_file.middleware`'],
    ['Log destination', 'logging.channel', 'logging.channel', 'logging.category', 'Monolog channel (Symfony, default `indexnow`), log channel name (Laravel), Yii log category (default `indexnow`)'],
    ['Debounce store', 'debounce.store', 'debounce.store', 'debounce.store', 'Same key, different values: a PSR-6 pool service id (Symfony, default `cache.app`), a cache store name (Laravel, default `cache` = the default store), a cache component id (Yii2, default `cache`); `memory` and `none` everywhere'],
    ['HTTP client', 'http.client', 'http.client', 'http.client', 'Same key: a service id (PSR-18 or symfony/http-client) in Symfony, a container binding or class in Laravel, a component id or class in Yii2; unset = PSR-18 discovery'],
];

/**
 * @return array<string, array{default: string, symfony: bool}> dotted key => default rendered for the table, and whether the bundle tree has it
 */
function config_table_bundle_tree(): array
{
    $builder = new TreeBuilder('indexnowkit');
    (new IndexNowKitConfiguration(true))->build(new DefinitionConfigurator($builder, new DefinitionFileLoader($builder, new FileLocator()), __FILE__, __FILE__));
    $tree = $builder->buildTree();
    \assert($tree instanceof ArrayNode);
    $out = [];
    $walk = static function (ArrayNode $node, string $prefix) use (&$walk, &$out): void {
        foreach ($node->getChildren() as $child) {
            $name = $prefix . $child->getName();
            if ($child instanceof PrototypedArrayNode) {
                $default = $child->hasDefaultValue() ? $child->getDefaultValue() : [];
                $out[$name] = ['default' => \is_array($default) && $default !== [] ? config_table_render_default($default) : '`[]`', 'symfony' => true];
                continue;
            }
            if ($child instanceof ArrayNode && $child->getChildren() !== []) {
                $walk($child, $name . '.');
                continue;
            }
            $default = $child->hasDefaultValue() ? $child->getDefaultValue() : null;
            $out[$name] = ['default' => config_table_render_default($default), 'symfony' => true];
        }
    };
    $walk($tree, '');

    return $out;
}

function config_table_render_default(mixed $value): string
{
    if ($value === null) {
        return '—';
    }
    if (\is_bool($value)) {
        return '`' . ($value ? 'true' : 'false') . '`';
    }
    if (\is_array($value)) {
        return $value === [] ? '`[]`' : '`[' . implode(', ', array_map(static fn(mixed $v): string => \is_string($v) ? $v : json_encode($v, JSON_THROW_ON_ERROR), $value)) . ']`';
    }

    return '`' . (\is_string($value) ? $value : json_encode($value, JSON_THROW_ON_ERROR)) . '`';
}

/**
 * The literal array of a `public const NAME = [...]` in a sibling package's source: read as text, so the script
 * does not need Laravel or Yii autoloaded.
 *
 * @return list<string>
 */
function config_table_constant(string $file, string $constant): array
{
    if (!is_file($file)) {
        throw new RuntimeException(\sprintf('%s is not available (the monorepo layout is needed): %s', $constant, $file));
    }
    $source = (string) file_get_contents($file);
    if (preg_match('/const ' . preg_quote($constant, '/') . ' = \[(.*?)\];/s', $source, $m) !== 1) {
        throw new RuntimeException(\sprintf('%s: no "const %s = [...]"', $file, $constant));
    }
    preg_match_all("/'([a-z_.]+)'/", $m[1], $keys);

    return $keys[1];
}

function config_table_render(): string
{
    $root = \dirname(__DIR__, 2) . '/packages';
    $laravel = config_table_constant($root . '/laravel/src/Config/ConfigFactory.php', 'LARAVEL_OPTIONS');
    $yii = config_table_constant($root . '/yii2/src/Config/ConfigFactory.php', 'YII_OPTIONS');
    $bundle = config_table_bundle_tree();
    // Where the bundle tree leaves a key without a default, the core's own constants apply everywhere.
    $coreDefaults = [
        'retry.max_attempts' => Config::DEFAULT_RETRY_MAX_ATTEMPTS, 'retry.base_delay' => Config::DEFAULT_RETRY_BASE_DELAY,
        'retry.multiplier' => Config::DEFAULT_RETRY_MULTIPLIER, 'retry.max_delay' => Config::DEFAULT_RETRY_MAX_DELAY,
        'retry.server_error_delay' => Config::DEFAULT_RETRY_SERVER_ERROR_DELAY, 'dry_run' => false,
    ];
    foreach ($coreDefaults as $key => $value) {
        if (($bundle[$key]['default'] ?? '—') === '—') {
            $bundle[$key] = ['default' => config_table_render_default($value), 'symfony' => isset($bundle[$key])];
        }
    }
    $bundleOnly = array_values(array_filter(array_keys($bundle), static fn(string $k): bool => !\in_array($k, Config::OPTIONS, true) && !\in_array($k, SitemapConfig::OPTIONS, true) && !str_starts_with($k, 'sitemap.') && $k !== 'hosts' && $k !== 'serve_key_file'));

    $lines = [];
    $lines[] = CONFIG_TABLE_START;
    $lines[] = '_Generated by `bin/config-table` from `Config::OPTIONS`, `SitemapConfig::OPTIONS`, the bundle configuration tree,';
    $lines[] = '`ConfigFactory::LARAVEL_OPTIONS` and `ConfigFactory::YII_OPTIONS`; do not edit by hand._';
    $lines[] = '';
    $lines[] = '### Core keys: the same name in every adapter';
    $lines[] = '';
    $lines[] = 'Every key of `Config::OPTIONS` is accepted under this name by the Symfony bundle (`indexnowkit:`), the Laravel package';
    $lines[] = '(`config/indexnow.php`) and the Yii2 component (`options`). The default column is the one the core ships, as the bundle';
    $lines[] = 'declares it in its configuration tree (`—` = unset); the two exceptions are in the synonyms table: `dispatch` (`auto` in';
    $lines[] = 'Symfony and Yii2, `queue` in Laravel) and `debounce.store` (`cache.app` / `cache` / `cache`). `environment` comes from';
    $lines[] = '`kernel.environment` / `APP_ENV` / `YII_ENV` unless set.';
    $lines[] = '';
    $lines[] = '| Key | Default |';
    $lines[] = '|---|---|';
    foreach (Config::OPTIONS as $key) {
        $lines[] = \sprintf('| `%s` | %s |', $key, $key === 'serve_key_file' ? 'deprecated alias of `key_file.enabled`' : ($bundle[$key]['default'] ?? '—'));
    }
    $lines[] = '';
    $lines[] = '`hosts` (per-host keys, `hosts.<host>.{key, key_location, base_url, engines, previous_key}`) is accepted everywhere too.';
    $lines[] = '';
    $lines[] = '### Sitemap keys (`indexnowkit/sitemap`)';
    $lines[] = '';
    $lines[] = 'The `sitemap` block is the same in the three adapters and is owned by the sitemap package: ' . implode(', ', array_map(static fn(string $k): string => '`' . $k . '`', SitemapConfig::OPTIONS)) . '.';
    $lines[] = '';
    $lines[] = '### One concept, three keys';
    $lines[] = '';
    $lines[] = '| Concept | Symfony (`indexnowkit:`) | Laravel (`config/indexnow.php`) | Yii2 (`options`) | Notes |';
    $lines[] = '|---|---|---|---|---|';
    foreach (CONFIG_TABLE_SYNONYMS as [$concept, $sf, $lar, $yi, $note]) {
        $lines[] = \sprintf('| %s | %s | %s | %s | %s |', $concept, config_table_cell($sf), config_table_cell($lar), config_table_cell($yi), $note);
    }
    $lines[] = '';
    $lines[] = '### Adapter-only keys';
    $lines[] = '';
    $lines[] = '| Adapter | Keys |';
    $lines[] = '|---|---|';
    $lines[] = '| Symfony | ' . implode(', ', array_map(static fn(string $k): string => '`' . $k . '`', $bundleOnly)) . ' |';
    $lines[] = '| Laravel | ' . implode(', ', array_map(static fn(string $k): string => '`' . $k . '`', $laravel)) . ' |';
    $lines[] = '| Yii2 | ' . implode(', ', array_map(static fn(string $k): string => '`' . $k . '`', $yii)) . ' |';
    $lines[] = CONFIG_TABLE_END;

    // Every synonym named for an adapter must be a key that adapter accepts (or a framework key for Symfony).
    $known = ['symfony' => [...Config::OPTIONS, ...array_keys($bundle), 'framework.enabled_locales'], 'laravel' => [...Config::OPTIONS, ...$laravel], 'yii2' => [...Config::OPTIONS, ...$yii]];
    foreach (CONFIG_TABLE_SYNONYMS as [$concept, $sf, $lar, $yi]) {
        foreach (['symfony' => $sf, 'laravel' => $lar, 'yii2' => $yi] as $adapter => $key) {
            if ($key !== null && !\in_array($key, $known[$adapter], true)) {
                throw new RuntimeException(\sprintf('config-table: "%s" names %s key "%s", which that adapter does not accept', $concept, $adapter, $key));
            }
        }
    }

    return implode("\n", $lines) . "\n";
}

function config_table_cell(?string $key): string
{
    return $key === null ? '—' : '`' . $key . '`';
}

/** Replaces the generated section of the file; returns the new content. */
function config_table_apply(string $document, string $table): string
{
    $start = strpos($document, CONFIG_TABLE_START);
    $end = strpos($document, CONFIG_TABLE_END);
    if ($start === false || $end === false) {
        throw new RuntimeException('configuration.md has no ' . CONFIG_TABLE_START . ' … ' . CONFIG_TABLE_END . ' markers');
    }

    return substr($document, 0, $start) . rtrim($table, "\n") . substr($document, $end + \strlen(CONFIG_TABLE_END));
}

if (PHP_SAPI === 'cli' && isset($argv) && realpath($argv[0]) === __FILE__) {
    require_once \dirname(__DIR__, 2) . '/packages/symfony-bundle/vendor/autoload.php';
    $file = \dirname(__DIR__, 2) . '/packages/core/docs/configuration.md';
    $current = (string) file_get_contents($file);
    $updated = config_table_apply($current, config_table_render());
    if (\in_array('--check', $argv, true)) {
        if ($updated !== $current) {
            fwrite(STDERR, "config-table: packages/core/docs/configuration.md is stale; run bin/config-table\n");
            exit(1);
        }
        echo "config-table: up to date\n";
        exit(0);
    }
    file_put_contents($file, $updated);
    echo "config-table: written\n";
}
