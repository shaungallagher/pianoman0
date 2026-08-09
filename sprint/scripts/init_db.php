<?php
// CLI helper to initialize the database schema from db.sql using .env credentials.
$root = dirname(__DIR__);
$envFile = $root . '/.env';
if (file_exists($envFile) && is_readable($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') continue;
        if (strpos($line, '=') === false) continue;
        list($name, $value) = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);
        if ((substr($value,0,1) === '"' && substr($value,-1) === '"') || (substr($value,0,1) === "'" && substr($value,-1) === "'")) {
            $value = substr($value, 1, -1);
        }
        if (getenv($name) === false) putenv("$name=$value");
        $_ENV[$name] = $value;
        $_SERVER[$name] = $value;
    }
}

$db_host = getenv('DB_HOST') ?: '127.0.0.1';
$db_port = getenv('DB_PORT') ?: '3306';
$db_name = getenv('DB_NAME') ?: 'sprint';
$db_user = getenv('DB_USER') ?: 'root';
$db_pass = getenv('DB_PASS') ?: '';

$dsn = "mysql:host=$db_host;port=$db_port;dbname=$db_name;charset=utf8mb4";

echo "Connecting to $db_host:$db_port/$db_name as $db_user...\n";
try {
    $pdo = new PDO($dsn, $db_user, $db_pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5]);
} catch (Exception $e) {
    fwrite(STDERR, "MySQL connection failed: " . $e->getMessage() . "\n");
    // Try sqlite fallback if available
    $sqliteFile = $root . '/data/sprint.sqlite';
    $trySqlite = getenv('DB_USE_SQLITE') === '1' || file_exists($root . '/db_sqlite.sql');
    if ($trySqlite) {
        if (!is_dir($root . '/data')) @mkdir($root . '/data', 0755, true);
        try {
            $pdo = new PDO('sqlite:' . $sqliteFile, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        } catch (Exception $e2) {
            fwrite(STDERR, "SQLite fallback failed: " . $e2->getMessage() . "\n");
            exit(2);
        }
    } else {
        exit(2);
    }
}

$sqlFile = $root . '/db.sql';
// If MySQL initialization file doesn't exist, fall back to sqlite SQL
if (!file_exists($sqlFile)) {
    fwrite(STDERR, "db.sql not found at $sqlFile\n");
    // try sqlite file
    $sqlFile = $root . '/db_sqlite.sql';
    if (!file_exists($sqlFile)) {
        fwrite(STDERR, "Neither db.sql nor db_sqlite.sql found.\n");
        exit(2);
    }
}
// Read SQL and attempt to execute. For sqlite files, execute whole file directly.
$sql = file_get_contents($sqlFile);
if (stripos($sqlFile, 'sqlite') !== false) {
    try {
        // Enable foreign keys for sqlite
        $pdo->exec('PRAGMA foreign_keys = ON');
        $pdo->exec($sql);
        echo "SQLite schema initialized successfully.\n";
        exit(0);
    } catch (Exception $e) {
        fwrite(STDERR, "SQLite initialization failed: " . $e->getMessage() . "\n");
        exit(3);
    }
} else {
    // MySQL-style splitting by statement
    $parts = preg_split('/;\s*\n/', $sql);
    $ok = 0; $failed = 0;
    foreach ($parts as $part) {
        $stmt = trim($part);
        if ($stmt === '') continue;
        try {
            $pdo->exec($stmt);
            $ok++;
        } catch (Exception $e) {
            fwrite(STDERR, "Statement failed: " . $e->getMessage() . "\nStatement: " . substr($stmt,0,200) . "...\n");
            $failed++;
        }
    }
    if ($failed === 0) {
        echo "Schema initialized successfully ($ok statements).\n";
        exit(0);
    } else {
        fwrite(STDERR, "Initialization completed with $failed failed statements. See output for details.\n");
        exit(3);
    }
}