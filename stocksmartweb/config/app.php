<?php
$local = __DIR__ . '/dblocal.php';

if (file_exists($local)) {
    require_once $local;
}
/** Application configuration. Environment variables override XAMPP defaults. */
declare(strict_types=1);

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

const APP_SESSION_TIMEOUT = 1800;
const APP_REMEMBER_TIMEOUT = 2592000;
