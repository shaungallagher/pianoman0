<?php
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
        if ((substr($value, 0, 1) === '"' && substr($value, -1) === '"') || (substr($value, 0, 1) === "'" && substr($value, -1) === "'")) {
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
$db_dsn = getenv('DB_DSN') ?: null;

$dsnCandidates = [];
if ($db_dsn) $dsnCandidates[] = $db_dsn;
$dsnCandidates[] = "mysql:host=$db_host;port=$db_port;dbname=$db_name;charset=utf8mb4";
$dsnCandidates[] = "mysql:host=localhost;dbname=$db_name;charset=utf8mb4";

$pdo = null;
$lastEx = null;
foreach ($dsnCandidates as $dsn) {
    try {
        $pdo = new PDO($dsn, $db_user, $db_pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        break;
    } catch (Exception $e) {
        $lastEx = $e;
    }
}

if (!$pdo) {
    // Assume sqlite fallback.
    $useSqlite = getenv('DB_USE_SQLITE') === '1' || (getenv('DB_DSN') && stripos(getenv('DB_DSN'), 'sqlite:') !== false);
    if (!$useSqlite) $useSqlite = true;

    $sqliteFile = $root . '/data/sprint.sqlite';
    if (!is_dir($root . '/data')) @mkdir($root . '/data', 0755, true);

    try {
        $pdo = new PDO('sqlite:' . $sqliteFile, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $pdo->exec('PRAGMA foreign_keys = ON');
    } catch (Exception $e) {
        fwrite(STDERR, "Could not connect to DB for migration: " . $e->getMessage() . "\n");
        if ($lastEx) fwrite(STDERR, "MySQL attempt error: " . $lastEx->getMessage() . "\n");
        exit(2);
    }
}

$isSqlite = false;
try {
    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    $isSqlite = is_string($driver) && stripos($driver, 'sqlite') !== false;
} catch (Exception $e) {
    $isSqlite = false;
}

function column_exists(PDO $pdo, bool $isSqlite, string $table, string $column, string $schema = ''): int {
    if ($isSqlite) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM pragma_table_info(?, ?) WHERE name = ?");
        // pragma_table_info signature varies; easiest: use PRAGMA table_info and match.
        $stmt2 = $pdo->prepare("PRAGMA table_info($table)");
        $stmt2->execute();
        $rows = $stmt2->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $r) {
            if (isset($r['name']) && $r['name'] === $column) return 1;
        }
        return 0;
    }

    // MySQL: information_schema.columns
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = ? AND table_name = ? AND column_name = ?");
    $stmt->execute([$schema, $table, $column]);
    return (int)$stmt->fetchColumn();
}

$alter = [];

// events venue fields
$alter[] = ['table' => 'events', 'col' => 'venue_name', 'def' => $isSqlite ? 'TEXT DEFAULT NULL' : 'VARCHAR(255) DEFAULT NULL'];
$alter[] = ['table' => 'events', 'col' => 'venue_address', 'def' => $isSqlite ? 'TEXT DEFAULT NULL' : 'VARCHAR(255) DEFAULT NULL'];
$alter[] = ['table' => 'events', 'col' => 'venue_city', 'def' => $isSqlite ? 'TEXT DEFAULT NULL' : 'VARCHAR(255) DEFAULT NULL'];
$alter[] = ['table' => 'events', 'col' => 'venue_state', 'def' => $isSqlite ? 'TEXT DEFAULT NULL' : 'VARCHAR(255) DEFAULT NULL'];
$alter[] = ['table' => 'events', 'col' => 'venue_country', 'def' => $isSqlite ? 'TEXT DEFAULT NULL' : 'VARCHAR(255) DEFAULT NULL'];
$alter[] = ['table' => 'events', 'col' => 'venue_lat', 'def' => $isSqlite ? 'REAL DEFAULT NULL' : 'DECIMAL(10,7) DEFAULT NULL'];
$alter[] = ['table' => 'events', 'col' => 'venue_lng', 'def' => $isSqlite ? 'REAL DEFAULT NULL' : 'DECIMAL(10,7) DEFAULT NULL'];
$alter[] = ['table' => 'events', 'col' => 'venue_capacity', 'def' => $isSqlite ? 'INTEGER DEFAULT NULL' : 'INT DEFAULT NULL'];

// users preference fields
$alter[] = ['table' => 'users', 'col' => 'home_city', 'def' => $isSqlite ? 'TEXT DEFAULT NULL' : 'VARCHAR(255) DEFAULT NULL'];
$alter[] = ['table' => 'users', 'col' => 'home_state', 'def' => $isSqlite ? 'TEXT DEFAULT NULL' : 'VARCHAR(255) DEFAULT NULL'];
$alter[] = ['table' => 'users', 'col' => 'home_country', 'def' => $isSqlite ? 'TEXT DEFAULT NULL' : 'VARCHAR(255) DEFAULT NULL'];
$alter[] = ['table' => 'users', 'col' => 'home_lat', 'def' => $isSqlite ? 'REAL DEFAULT NULL' : 'DECIMAL(10,7) DEFAULT NULL'];
$alter[] = ['table' => 'users', 'col' => 'home_lng', 'def' => $isSqlite ? 'REAL DEFAULT NULL' : 'DECIMAL(10,7) DEFAULT NULL'];
$alter[] = ['table' => 'users', 'col' => 'preferred_venue_radius_km', 'def' => $isSqlite ? 'INTEGER DEFAULT NULL' : 'INT DEFAULT NULL'];
$alter[] = ['table' => 'users', 'col' => 'preferred_min_venue_capacity', 'def' => $isSqlite ? 'INTEGER DEFAULT NULL' : 'INT DEFAULT NULL'];

$schema = $db_name;

foreach ($alter as $a) {
    $exists = 0;
    try {
        $exists = column_exists($pdo, $isSqlite, $a['table'], $a['col'], $schema);
    } catch (Exception $e) {
        $exists = 0;
    }

    if ($exists === 1) {
        echo "Column {$a['table']}.{$a['col']} already exists, skipping.\n";
        continue;
    }

    try {
        echo "Adding {$a['table']}.{$a['col']}...\n";
        $sql = "ALTER TABLE {$a['table']} ADD COLUMN {$a['col']} {$a['def']}";
        $pdo->exec($sql);
        echo "Added {$a['table']}.{$a['col']}\n";
    } catch (Exception $e) {
        fwrite(STDERR, "Failed to add {$a['table']}.{$a['col']}: " . $e->getMessage() . "\n");
    }
}

echo "Migration complete.\n";

