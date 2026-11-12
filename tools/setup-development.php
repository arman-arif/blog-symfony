<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$paths = [$root.'/var/development.env', $root.'/.env.dev.local', $root.'/.env.test.local'];
foreach ($paths as $path) {
    if (file_exists($path)) {
        fwrite(STDERR, 'Refusing to replace existing local configuration: '.$path.PHP_EOL);
        exit(1);
    }
}

if (!is_dir($root.'/var') && !mkdir($root.'/var', 0700, true)) {
    throw new RuntimeException('Cannot create the local configuration directory.');
}

$password = bin2hex(random_bytes(24));
$databaseUrl = 'mysql://fieldnotes:'.$password.'@127.0.0.1:33068/fieldnotes?serverVersion=8.4&charset=utf8mb4';
$files = [
    $paths[0] => 'MYSQL_PASSWORD='.$password."\nMYSQL_ROOT_PASSWORD=".bin2hex(random_bytes(24))."\n",
    $paths[1] => 'APP_SECRET='.bin2hex(random_bytes(32))."\nAPP_UI_PREVIEW=1\nDATABASE_URL=\"".$databaseUrl."\"\n",
    $paths[2] => 'DATABASE_URL="'.$databaseUrl."\"\n",
];

foreach ($files as $path => $content) {
    if (false === file_put_contents($path, $content) || !chmod($path, 0600)) {
        throw new RuntimeException('Cannot write protected local configuration: '.$path);
    }
}

echo "Generated protected, git-ignored development credentials.\n";
echo "Start services: docker compose --env-file var/development.env up -d --wait database\n";
echo "Optional Redis: docker compose --env-file var/development.env --profile redis up -d --wait redis\n";
