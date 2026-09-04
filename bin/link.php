<?php

declare(strict_types=1);

/*
 * Generates composer.monorepo.json next to a package's composer.json (see bin/link):
 *  - a path repository per sibling package, symlinked, with the version fixed to the sibling's branch alias
 *    (Composer would otherwise guess it from git and fail on a detached HEAD, which is what a pull request checkout is);
 *  - minimum-stability dev with prefer-stable, so only the linked siblings resolve to their dev version.
 * composer.json itself stays untouched: it is what the split repositories and Packagist see. No platform pin:
 * bin/composer runs on the same PHP as the tests (docker/php/Dockerfile), and in CI Composer runs on the matrix PHP.
 */

$packagesDir = dirname(__DIR__) . '/packages';
$all = array_map('basename', glob($packagesDir . '/*', GLOB_ONLYDIR) ?: []);
$targets = array_slice($argv, 1) ?: $all;

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

    $out = $packagesDir . '/' . $target . '/composer.monorepo.json';
    file_put_contents($out, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n");
    echo "{$target}: composer.monorepo.json (" . count($manifest['repositories']) . " siblings linked)\n";
}
