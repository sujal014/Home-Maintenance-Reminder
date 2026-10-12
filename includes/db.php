<?php
declare(strict_types=1);

// Some minimal PHP installs (e.g. plain `apt install php-cli`) lack the mbstring
// extension. This tiny fallback keeps the app working there.
if (!function_exists('mb_strlen')) {
    function mb_strlen(string $s): int
    {
        return preg_match_all('/./su', $s) ?: 0;
    }
}

function getDb(): PDO
{
    static $pdo = null;           // reuse one connection per request

    if ($pdo === null) {
        $dir = __DIR__ . '/../data';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $pdo = new PDO('sqlite:' . $dir . '/maintenance.db');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec('PRAGMA foreign_keys = ON');

        // Users. The UNIQUE + NOCASE rule means one account per email address,
        // no matter how it is capitalised (Jo@x.com == jo@x.com).
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS users (
                id            INTEGER PRIMARY KEY AUTOINCREMENT,
                name          TEXT NOT NULL,
                email         TEXT NOT NULL UNIQUE COLLATE NOCASE,
                password_hash TEXT NOT NULL,
                created_at    TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
            )
        ");

        // Tasks. Each task belongs to one user.
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS tasks (
                id             INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id        INTEGER REFERENCES users(id) ON DELETE CASCADE,
                title          TEXT    NOT NULL,
                category       TEXT    NOT NULL DEFAULT 'General',
                due_date       TEXT    NOT NULL,          -- YYYY-MM-DD
                frequency      TEXT    NOT NULL DEFAULT 'once',
                notes          TEXT    NOT NULL DEFAULT '',
                completed      INTEGER NOT NULL DEFAULT 0,
                last_completed TEXT,
                created_at     TEXT    NOT NULL DEFAULT CURRENT_TIMESTAMP
            )
        ");

        // Upgrade path: databases made by the first version have no user_id.
        $cols = array_column($pdo->query('PRAGMA table_info(tasks)')->fetchAll(), 'name');
        if (!in_array('user_id', $cols, true)) {
            $pdo->exec('ALTER TABLE tasks ADD COLUMN user_id INTEGER REFERENCES users(id) ON DELETE CASCADE');
        }
    }
    return $pdo;
}
