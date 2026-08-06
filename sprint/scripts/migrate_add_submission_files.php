<?php
$root = dirname(__DIR__);
require_once $root . '/config.php';

try {
    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    if ($driver === 'mysql') {
        $dbName = getenv('DB_NAME') ?: 'sprint';
        $cols = [];
        $stmt = $pdo->prepare('SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME=?');
        $stmt->execute([$dbName, 'submissions']);
        $cols = $stmt->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('screenshot_path', $cols)) {
            $pdo->exec("ALTER TABLE submissions ADD COLUMN screenshot_path VARCHAR(255) DEFAULT NULL");
            echo "Added screenshot_path\n";
        }
        if (!in_array('video_path', $cols)) {
            $pdo->exec("ALTER TABLE submissions ADD COLUMN video_path VARCHAR(255) DEFAULT NULL");
            echo "Added video_path\n";
        }
    } else {
        $stmt = $pdo->query("PRAGMA table_info('submissions')");
        $cols = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) $cols[] = $r['name'];
        if (!in_array('screenshot_path', $cols)) {
            $pdo->exec("ALTER TABLE submissions ADD COLUMN screenshot_path TEXT DEFAULT NULL");
            echo "Added screenshot_path (sqlite)\n";
        }
        if (!in_array('video_path', $cols)) {
            $pdo->exec("ALTER TABLE submissions ADD COLUMN video_path TEXT DEFAULT NULL");
            echo "Added video_path (sqlite)\n";
        }
    }
    echo "Done.\n";
} catch (Exception $e) {
    fwrite(STDERR, "Migration failed: " . $e->getMessage() . "\n");
    exit(1);
}
