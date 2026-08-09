<?php
// scripts/migrate_add_judging_mode.php
// Adds `judging_mode` column to `events` table if missing.
$root = dirname(__DIR__);
require_once $root . '/config.php';

try {
    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    if ($driver === 'mysql') {
        $dbName = getenv('DB_NAME') ?: 'sprint';
        $stmt = $pdo->prepare('SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME=?');
        $stmt->execute([$dbName, 'events']);
        $cols = $stmt->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('judging_mode', $cols)) {
            $pdo->exec("ALTER TABLE events ADD COLUMN judging_mode ENUM('judges','peer') DEFAULT 'judges'");
            echo "Added judging_mode (mysql)\n";
        } else {
            echo "judging_mode already exists (mysql)\n";
        }
    } else {
        // SQLite
        $stmt = $pdo->query("PRAGMA table_info('events')");
        $cols = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) $cols[] = $r['name'];
        if (!in_array('judging_mode', $cols)) {
            $pdo->exec("ALTER TABLE events ADD COLUMN judging_mode TEXT DEFAULT 'judges'");
            echo "Added judging_mode (sqlite)\n";
        } else {
            echo "judging_mode already exists (sqlite)\n";
        }
    }

    echo "Done.\n";
} catch (Exception $e) {
    fwrite(STDERR, "Migration failed: " . $e->getMessage() . "\n");
    exit(1);
}
