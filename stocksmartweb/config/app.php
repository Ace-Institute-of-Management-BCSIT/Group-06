<?php
declare(strict_types=1);

/** Application configuration. Environment variables override XAMPP defaults. */

$local = __DIR__ . '/dblocal.php';

if (file_exists($local)) {
    require_once $local;
}

/**
 * Loads KEY=VALUE pairs from a .env file into getenv()/$_ENV, without
 * overwriting any variable already set by the real environment (real env
 * vars always win — .env is just a local-dev/deploy convenience so secrets
 * like SMTP credentials don't have to be hardcoded or exported by hand).
 * Lines starting with # are comments; values may be wrapped in quotes.
 */
function app_load_env(string $path): void
{
    if (!is_file($path) || !is_readable($path)) {
        return;
    }

    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) {
            continue;
        }

        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);

        if (strlen($value) >= 2 && (
            ($value[0] === '"' && $value[-1] === '"') ||
            ($value[0] === "'" && $value[-1] === "'")
        )) {
            $value = substr($value, 1, -1);
        }

        if ($key === '' || getenv($key) !== false) {
            continue;
        }

        putenv("{$key}={$value}");
        $_ENV[$key] = $value;
    }
}

app_load_env(__DIR__ . '/../.env');

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
