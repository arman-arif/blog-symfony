<?php

declare(strict_types=1);

// Requirements documents may mention forbidden tooling; executable project files may not use it.
$root = dirname(__DIR__);
$forbidden = ['package.json', 'package-lock.json', 'yarn.lock', 'pnpm-lock.yaml', 'bun.lockb', 'webpack.config.js', 'vite.config.js'];
foreach ($forbidden as $filename) {
    if (file_exists($root.'/'.$filename)) {
        fwrite(STDERR, 'Forbidden build-tool manifest: '.$filename.PHP_EOL);
        exit(1);
    }
}
$composer = json_decode(file_get_contents($root.'/composer.json'), true, flags: JSON_THROW_ON_ERROR);
foreach (array_keys($composer['require']) as $package) {
    if (str_contains($package, 'encore')) {
        fwrite(STDERR, 'Forbidden asset dependency: '.$package.PHP_EOL);
        exit(1);
    }
}
echo 'Tooling check passed: PHP, AssetMapper and standalone Tailwind only.'.PHP_EOL;
