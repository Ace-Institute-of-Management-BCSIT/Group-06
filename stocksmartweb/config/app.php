<?php
declare(strict_types=1);

/** Application configuration. Environment variables override XAMPP defaults. */

$local = __DIR__ . '/dblocal.php';

if (file_exists($local)) {
    require_once $local;
}

function app_env(string $name, string $default = ''): string
{
    $value = getenv($name);
    return $value === false || $value === '' ? $default : $value;
}

function app_is_https(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['SERVER_PORT'] ?? '') === '443');
}