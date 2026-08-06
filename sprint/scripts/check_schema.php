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

try {
    $pdo = new PDO($dsn, $db_user, $db_pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5]);
} catch (Exception $e) {
    fwrite(STDERR, "Connection failed: " . $e->getMessage() . "\n");
    exit(2);
}

$expected = [
    'events','categories','users','teams','team_members','submissions','announcements','scores','judges','oauth_accounts'
];
$missing = [];
foreach ($expected as $t) {
    try {
        $stmt = $pdo->prepare("SELECT 1 FROM $t LIMIT 1");
        $stmt->execute();
    } catch (Exception $e) {
        $missing[] = $t;
    }
}

if (empty($missing)) {
    echo "All expected tables exist.\n";
    exit(0);
} else {
    echo "Missing tables: " . implode(', ', $missing) . "\n";
    if (stripos($dsn, 'sqlite') !== false || getenv('DB_USE_SQLITE') === '1') {
        echo "Run: php scripts/init_sqlite.php\n";
    } else {
        echo "Run: php scripts/init_db.php\n";
    }
    exit(3);
}
