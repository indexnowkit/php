<?php

declare(strict_types=1);

/*
 * Generates composer.monorepo.json next to a package's composer.json (see bin/link):
 *  - a path repository per sibling package, symlinked, with the version fixed to the sibling's branch alias
 *    (Composer would otherwise guess it from git and fail on a detached HEAD, which is what a pull request checkout is);
 *  - minimum-stability dev with prefer-stable, so only the linked siblings resolve to their dev version;
 *  - config.platform.php = --platform (bin/link passes PHP_VERSION, default 8.3), so the resolution matches the PHP
 *    the tests run on rather than the PHP of the Composer image.
 * composer.json itself stays untouched: it is what the split repositories and Packagist see.
 */

$packagesDir = dirname(__DIR__) . '/packages';
$platform = '8.3';
$targets = [];
foreach (array_slice($argv, 1) as $arg) {
    if (str_starts_with($arg, '--platform=')) {
        $platform = substr($arg, strlen('--platform='));
    } else {
        $targets[] = $arg;
    }
}

$all = array_map('basename', glob($packagesDir . '/*', GLOB_ONLYDIR) ?: []);
$targets = $targets ?: $all;

$read = static function (string $package) use ($packagesDir): array {
    $file = $packagesDir . '/' . $package . '/composer.json';
    if (!is_file($file)) {
        fwrite(STDERR, "unknown package: {$package}\n");
        exit(1);
    }

    return json_decode((string) file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
};

$manifests = [];
foreach ($all as $package) {
    $manifests[$package] = $read($package);
}

foreach ($targets as $target) {
    $manifest = $read($target);
    $manifest['repositories'] = [];

    foreach ($manifests as $sibling => $siblingManifest) {
        if ($sibling === $target) {
            continue;
        }
        $alias = $siblingManifest['extra']['branch-alias']['dev-main'] ?? null;
        if ($alias === null) {
            fwrite(STDERR, "{$sibling}/composer.json has no extra.branch-alias.dev-main\n");
            exit(1);
        }
        $manifest['repositories'][] = [
            'type' => 'path',
            'url' => '../' . $sibling,
            'options' => [
                'symlink' => true,
                'versions' => [$siblingManifest['name'] => $alias],
            ],
        ];
    }

    $manifest['minimum-stability'] = 'dev';
    $manifest['prefer-stable'] = true;
    $manifest['config']['platform']['php'] = $platform;

    $out = $packagesDir . '/' . $target . '/composer.monorepo.json';
    file_put_contents($out, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n");
    echo "{$target}: composer.monorepo.json (platform php {$platform}, " . count($manifest['repositories']) . " linked)\n";
}
