<?php
/**
 * ============================================================================
 *  StockSmart — Migration CLI (database/migrate.php)
 * ============================================================================
 *  Applies every migration in database/migrations that this database has not
 *  had yet, tracked in the `schema_migrations` table. Run it from your deploy
 *  script; it replaces piping .sql files into mysql by hand.
 *
 *      php database/migrate.php            apply pending migrations
 *      php database/migrate.php --status   show what would run, change nothing
 *
 *  Connection settings come from the same environment/.env that the app uses
 *  (see config/app.php and db.php), so there is nothing deployment-specific
 *  to edit here.
 *
 *  CLI ONLY. Refuses to run over HTTP — schema changes should never be
 *  triggerable by an unauthenticated web request. (There is an opt-in
 *  automatic mode for deploys that cannot run a command; see
 *  DB_AUTO_MIGRATE in helpers/migrator.php and the README.)
 * ========================================================================== */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo "database/migrate.php is a command-line tool and cannot be run over HTTP.\n";
    echo "Run it on the server:  php database/migrate.php\n";
    exit(1);
}

// db.php starts a session, which is meaningless (and noisy) on CLI.
if (session_status() === PHP_SESSION_NONE) {
    session_id('cli-migrate');
}

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../helpers/migrator.php';

$statusOnly = in_array('--status', $argv, true);

$say = static function (string $line): void {
    echo $line . PHP_EOL;
};

$say('StockSmart migrations');
$say('database: ' . (string) $pdo->query('SELECT DATABASE()')->fetchColumn());
$say(str_repeat('-', 60));

try {
    migrator_ensure_ledger($pdo);

    if (!migrator_schema_exists($pdo)) {
        $say('');
        $say('This database does not contain the StockSmart schema yet.');
        $say('Import the base schema first, then re-run this command:');
        $say('');
        $say('    mysql -u <user> -p <database> < stocksmart.sql');
        $say('');
        exit(1);
    }

    $pending = migrator_pending($pdo);

    if ($statusOnly) {
        $applied = migrator_applied($pdo);
        $say('Applied: ' . (count($applied) ?: 0));
        foreach (migrator_available() as $m) {
            $mark = isset($applied[$m['version']]) ? '  [x]' : '  [ ]';
            $say($mark . ' ' . $m['name']);
        }
        $say('');
        $say(count($pending) === 0 ? 'Up to date — nothing pending.' : count($pending) . ' migration(s) pending.');
        exit(0);
    }

    if ($pending === []) {
        // Still baseline, so a first run on an already-provisioned database
        // establishes the ledger even when there is nothing new to apply.
        $result = migrator_run($pdo, $say);
        $say($result['baselined'] === [] ? 'Up to date — nothing to apply.' : 'Ledger established.');
        exit(0);
    }

    $result = migrator_run($pdo, $say);

    $say(str_repeat('-', 60));
    if ($result['skipped']) {
        $say('Another migration run is in progress — nothing applied. Try again shortly.');
        exit(1);
    }
    $say('Baselined: ' . count($result['baselined']));
    $say('Applied:   ' . count($result['applied']));
    $say('Done.');
    exit(0);
} catch (Throwable $e) {
    $say('');
    $say('MIGRATION FAILED');
    $say($e->getMessage());
    $say('');
    $say('No further migrations were applied. The failed migration is NOT recorded,');
    $say('so it will be retried once the problem is fixed.');
    exit(1);
}
