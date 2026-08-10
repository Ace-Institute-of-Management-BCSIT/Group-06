<?php
/**
 * ============================================================================
 *  StockSmart — Schema Migration Runner (helpers/migrator.php)
 * ============================================================================
 *  Until now database/migrations/*.sql were applied by hand, one `mysql <file`
 *  at a time, with the README as the only record of what had been run. Nothing
 *  in the database remembered which migrations were already applied, so
 *  deploying meant reading the README and hoping the production database was
 *  where you thought it was.
 *
 *  This adds the missing piece: a `schema_migrations` ledger table plus a
 *  runner that applies only the files that are not in it yet.
 *
 *  --- BASELINING AN EXISTING DATABASE ---------------------------------------
 *  The production VPS already has migrations 001–005 applied, and a fresh
 *  install from stocksmart.sql already contains everything 001–004 did. So on
 *  the very first run against a database that clearly already has the schema
 *  (detected by the presence of the `products` table), the runner marks
 *  001–004 as applied WITHOUT executing them, and records why.
 *
 *  Why 004 specifically must never be replayed: it runs
 *  `UPDATE users SET status='active' WHERE status='pending'`. Migration 005
 *  later reintroduced 'pending' as the default for OTP registration, so
 *  replaying 004 on a live database would silently activate accounts that are
 *  legitimately awaiting OTP verification. Baselining is not an optimisation
 *  here — it is a correctness requirement.
 *
 *  005 onward are executed normally. 005 is fully guarded by
 *  INFORMATION_SCHEMA checks, so on the VPS (where it already ran) every
 *  statement is a no-op, while a fresh install that only imported
 *  stocksmart.sql genuinely needs it.
 *
 *  --- SAFETY ----------------------------------------------------------------
 *    - A MySQL named lock (GET_LOCK) means two concurrent deploys, or two web
 *      requests, can never run the same migration twice.
 *    - Each file runs statement by statement; a failure stops the run
 *      immediately and the file is NOT recorded as applied, so a partially
 *      applied migration is visible rather than silently skipped next time.
 *    - `USE <database>` lines are skipped. Migrations 001–005 hard-code
 *      `USE stocksmart;`, which would hijack the connection and target the
 *      wrong database on any deployment that named it something else. The
 *      runner always stays on the database db.php connected to.
 *    - Nothing here drops, truncates or recreates anything. It only runs the
 *      SQL the migration files contain.
 * ========================================================================== */

declare(strict_types=1);

/** Migrations at or below this number are assumed present on any provisioned database. */
const MIGRATION_BASELINE_THROUGH = 4;

const MIGRATION_LOCK_NAME = 'stocksmart_migrations';

function migrator_dir(): string
{
    return __DIR__ . '/../database/migrations';
}

/**
 * Every migration file on disk, ordered by its numeric prefix.
 *
 * @return array<int, array{version:int, name:string, path:string}>
 */
function migrator_available(): array
{
    $files = glob(migrator_dir() . '/*.sql') ?: [];
    $out = [];

    foreach ($files as $path) {
        $name = basename($path);
        if (!preg_match('/^(\d+)_/', $name, $m)) {
            continue; // not a numbered migration — ignore rather than guess
        }
        $out[] = ['version' => (int) $m[1], 'name' => $name, 'path' => $path];
    }

    usort($out, static fn (array $a, array $b): int => $a['version'] <=> $b['version']);
    return $out;
}

function migrator_ensure_ledger(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS schema_migrations (
            version     INT UNSIGNED NOT NULL,
            name        VARCHAR(191) NOT NULL,
            applied_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
            note        VARCHAR(255) NULL,
            PRIMARY KEY (version)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
}

/** @return array<int, int> version => version, of everything already applied */
function migrator_applied(PDO $pdo): array
{
    $rows = $pdo->query('SELECT version FROM schema_migrations')->fetchAll();
    $out = [];
    foreach ($rows as $r) {
        $out[(int) $r['version']] = (int) $r['version'];
    }
    return $out;
}

/** True when this database already carries the application schema. */
function migrator_schema_exists(PDO $pdo): bool
{
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
        WHERE table_schema = DATABASE() AND table_name = 'products'
    ");
    $stmt->execute();
    return (int) $stmt->fetchColumn() > 0;
}

function migrator_record(PDO $pdo, int $version, string $name, ?string $note = null): void
{
    $pdo->prepare('INSERT IGNORE INTO schema_migrations (version, name, note) VALUES (:v, :n, :note)')
        ->execute([':v' => $version, ':n' => $name, ':note' => $note]);
}

/**
 * On a database that already has the schema but no ledger, record 001–004 as
 * applied without running them. See the header for why replaying 004 would be
 * actively harmful.
 */
function migrator_baseline(PDO $pdo): array
{
    $baselined = [];
    if (!migrator_schema_exists($pdo)) {
        return $baselined; // empty database — import stocksmart.sql first
    }
    if (migrator_applied($pdo) !== []) {
        return $baselined; // ledger already established
    }

    foreach (migrator_available() as $migration) {
        if ($migration['version'] > MIGRATION_BASELINE_THROUGH) {
            continue;
        }
        migrator_record(
            $pdo,
            $migration['version'],
            $migration['name'],
            'baselined — already present in this database when the ledger was created'
        );
        $baselined[] = $migration['name'];
    }

    return $baselined;
}

/** @return array<int, array{version:int, name:string, path:string}> */
function migrator_pending(PDO $pdo): array
{
    migrator_ensure_ledger($pdo);
    $applied = migrator_applied($pdo);

    return array_values(array_filter(
        migrator_available(),
        static fn (array $m): bool => !isset($applied[$m['version']])
    ));
}

/**
 * Splits a migration file into individual statements.
 *
 * Deliberately simple: the project's migrations are plain DDL plus
 * PREPARE/EXECUTE blocks with no stored routines, so semicolon splitting is
 * sufficient. Strings and -- comments are respected so a semicolon inside
 * either does not split a statement. `USE <db>` is dropped — see header.
 *
 * @return string[]
 */
function migrator_split_statements(string $sql): array
{
    $statements = [];
    $current = '';
    $len = strlen($sql);
    $inSingle = false;
    $inDouble = false;
    $inBacktick = false;
    $inLineComment = false;
    $inBlockComment = false;

    for ($i = 0; $i < $len; $i++) {
        $char = $sql[$i];
        $next = $i + 1 < $len ? $sql[$i + 1] : '';

        if ($inLineComment) {
            $current .= $char;
            if ($char === "\n") {
                $inLineComment = false;
            }
            continue;
        }
        if ($inBlockComment) {
            $current .= $char;
            if ($char === '*' && $next === '/') {
                $current .= $next;
                $i++;
                $inBlockComment = false;
            }
            continue;
        }
        if (!$inSingle && !$inDouble && !$inBacktick) {
            if ($char === '-' && $next === '-') {
                $inLineComment = true;
                $current .= $char;
                continue;
            }
            if ($char === '#') {
                $inLineComment = true;
                $current .= $char;
                continue;
            }
            if ($char === '/' && $next === '*') {
                $inBlockComment = true;
                $current .= $char;
                continue;
            }
        }

        if ($char === "'" && !$inDouble && !$inBacktick) {
            // Backslash-escaped quote stays inside the string.
            $escaped = $i > 0 && $sql[$i - 1] === '\\';
            if (!$escaped) {
                $inSingle = !$inSingle;
            }
        } elseif ($char === '"' && !$inSingle && !$inBacktick) {
            $escaped = $i > 0 && $sql[$i - 1] === '\\';
            if (!$escaped) {
                $inDouble = !$inDouble;
            }
        } elseif ($char === '`' && !$inSingle && !$inDouble) {
            $inBacktick = !$inBacktick;
        }

        if ($char === ';' && !$inSingle && !$inDouble && !$inBacktick) {
            $statements[] = $current;
            $current = '';
            continue;
        }

        $current .= $char;
    }

    if (trim($current) !== '') {
        $statements[] = $current;
    }

    $clean = [];
    foreach ($statements as $statement) {
        $trimmed = migrator_strip_comments($statement);
        if ($trimmed === '') {
            continue;
        }
        if (preg_match('/^USE\s+/i', $trimmed)) {
            continue; // never let a migration switch databases
        }
        $clean[] = $trimmed;
    }

    return $clean;
}

/**
 * Runs one migration statement and fully drains whatever it returns.
 *
 * PDO::exec() is not usable here. Migrations 005 and 006 use the
 * INFORMATION_SCHEMA + PREPARE/EXECUTE idiom for idempotency, and their
 * EXECUTE resolves to a `SELECT 1` / `SELECT '...' AS note` no-op whenever the
 * change is already present. exec() issues that query but never fetches it, so
 * the connection is left holding an open cursor and the NEXT statement dies
 * with "Cannot execute queries while other unbuffered queries are active".
 *
 * query() + nextRowset() + closeCursor() consumes every result set the
 * statement produced, leaving the connection clean for the following one.
 */
function migrator_exec(PDO $pdo, string $statement): void
{
    $stmt = $pdo->query($statement);
    if (!$stmt instanceof PDOStatement) {
        return;
    }

    try {
        // Drain any additional result sets (EXECUTE can emit more than one).
        while ($stmt->nextRowset()) {
            // discarded on purpose — migrations are run for effect, not output
        }
    } catch (PDOException $e) {
        // "no more rowsets" is reported as an exception by some drivers.
    }

    $stmt->closeCursor();
}

/** Removes leading comment lines so "is this statement empty?" is answerable. */
function migrator_strip_comments(string $statement): string
{
    $lines = preg_split('/\r\n|\r|\n/', $statement) ?: [];
    $kept = [];
    foreach ($lines as $line) {
        $t = trim($line);
        if ($t === '' || str_starts_with($t, '--') || str_starts_with($t, '#')) {
            continue;
        }
        $kept[] = $line;
    }
    return trim(implode("\n", $kept));
}

/**
 * Applies every pending migration in order.
 *
 * @param callable(string):void|null $log
 * @return array{applied:string[], baselined:string[], skipped:bool}
 * @throws RuntimeException when a migration fails — the caller decides how loud to be.
 */
function migrator_run(PDO $pdo, ?callable $log = null): array
{
    $say = $log ?? static function (string $msg): void {};

    migrator_ensure_ledger($pdo);

    // Serialise across deploys/requests. 10s is generous for DDL this small.
    $lock = $pdo->prepare('SELECT GET_LOCK(:name, 10)');
    $lock->execute([':name' => MIGRATION_LOCK_NAME]);
    if ((int) $lock->fetchColumn() !== 1) {
        $say('Another migration run holds the lock — skipping this attempt.');
        return ['applied' => [], 'baselined' => [], 'skipped' => true];
    }

    try {
        $baselined = migrator_baseline($pdo);
        foreach ($baselined as $name) {
            $say("baseline  {$name} (recorded as already applied)");
        }

        $applied = [];
        foreach (migrator_pending($pdo) as $migration) {
            $sql = file_get_contents($migration['path']);
            if ($sql === false) {
                throw new RuntimeException("Could not read migration {$migration['name']}");
            }

            $say("applying  {$migration['name']}");
            foreach (migrator_split_statements($sql) as $statement) {
                try {
                    migrator_exec($pdo, $statement);
                } catch (Throwable $e) {
                    throw new RuntimeException(
                        "Migration {$migration['name']} failed: " . $e->getMessage()
                        . "\n  statement: " . substr(preg_replace('/\s+/', ' ', $statement) ?? '', 0, 200),
                        0,
                        $e
                    );
                }
            }

            migrator_record($pdo, $migration['version'], $migration['name']);
            $applied[] = $migration['name'];
        }

        return ['applied' => $applied, 'baselined' => $baselined, 'skipped' => false];
    } finally {
        $pdo->prepare('SELECT RELEASE_LOCK(:name)')->execute([':name' => MIGRATION_LOCK_NAME]);
    }
}

/* ============================================================================
 *  OPTIONAL AUTO-APPLY (opt in with DB_AUTO_MIGRATE=true in .env)
 * ==========================================================================
 *  Off by default. When enabled, db.php calls this on request. It is NOT a
 *  "run migrations on every request" system:
 *
 *    - A marker file records a hash of the migration filenames. While the hash
 *      is unchanged (i.e. no new code has been deployed) this function returns
 *      immediately having issued ZERO database queries — the steady-state cost
 *      is one file_get_contents of a ~40-byte file.
 *    - Only after a deploy adds a migration file does the hash change, and
 *      only then is a run attempted — once, under the same GET_LOCK.
 *    - The marker is written even on failure, so a broken migration cannot put
 *      the site into a loop of retrying DDL on every page load. The failure is
 *      logged; fix it and run the CLI runner.
 *
 *  Leaving this off and running `php database/migrate.php` from your deploy
 *  script is the more predictable choice, and is what the README recommends.
 */
function migrator_marker_path(): string
{
    return __DIR__ . '/../logs/.migrations-state';
}

function migrator_signature(): string
{
    $names = array_map(static fn (array $m): string => $m['name'], migrator_available());
    return substr(hash('sha256', implode('|', $names)), 0, 32);
}

function migrator_auto(PDO $pdo): void
{
    if (app_env('DB_AUTO_MIGRATE', 'false') !== 'true') {
        return;
    }

    $marker = migrator_marker_path();
    $signature = migrator_signature();

    if (is_file($marker) && trim((string) @file_get_contents($marker)) === $signature) {
        return; // nothing new deployed — no DB work at all
    }

    $dir = dirname($marker);
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    // Write the marker FIRST so a failing migration can't retry on every request.
    @file_put_contents($marker, $signature, LOCK_EX);

    try {
        $result = migrator_run($pdo);
        if ($result['applied'] !== []) {
            error_log('StockSmart auto-migrate applied: ' . implode(', ', $result['applied']));
        }
        if ($result['skipped']) {
            @unlink($marker); // another process is running them; re-check next request
        }
    } catch (Throwable $e) {
        error_log('StockSmart auto-migrate FAILED: ' . $e->getMessage());
    }
}
