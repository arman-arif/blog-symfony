<?php

declare(strict_types=1);

// Serve real static files directly; let Symfony handle mapped assets and routes.
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$file = realpath(__DIR__.'/../public'.('/' === $path ? '' : $path));
$public = realpath(__DIR__.'/../public');
if (false !== $file && str_starts_with($file, $public.DIRECTORY_SEPARATOR) && is_file($file)) {
    return false;
}

require __DIR__.'/../public/index.php';
