<?php

$root = dirname(__DIR__);
require_once $root . '/config.php';

try {
    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

    if ($driver === 'mysql') {
        $dbName = getenv('DB_NAME') ?: 'sprint';
        $stmt = $pdo->prepare('SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME=?');
        $stmt->execute([$dbName, 'users']);
        $cols = $stmt->fetchAll(PDO::FETCH_COLUMN);

        if (!in_array('github_avatar_url', $cols)) {
            $pdo->exec("ALTER TABLE users ADD COLUMN github_avatar_url VARCHAR(512) DEFAULT NULL");
            echo "Added github_avatar_url (mysql)\n";
        } else {
            echo "github_avatar_url already exists (mysql)\n";
        }
    } else {
        // SQLite
        $stmt = $pdo->query("PRAGMA table_info('users')");
        $cols = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) $cols[] = $r['name'];

        if (!in_array('github_avatar_url', $cols)) {
            $pdo->exec("ALTER TABLE users ADD COLUMN github_avatar_url TEXT DEFAULT NULL");
            echo "Added github_avatar_url (sqlite)\n";
        } else {
            echo "github_avatar_url already exists (sqlite)\n";
        }
    }

    echo "Done.\n";
} catch (Exception $e) {
    fwrite(STDERR, "Migration failed: " . $e->getMessage() . "\n");
    exit(1);
}

