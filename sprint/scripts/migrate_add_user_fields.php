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
$db_dsn = getenv('DB_DSN') ?: null;

$dsnCandidates = [];
if ($db_dsn) $dsnCandidates[] = $db_dsn;
$dsnCandidates[] = "mysql:host=$db_host;port=$db_port;dbname=$db_name;charset=utf8mb4";
$dsnCandidates[] = "mysql:host=localhost;dbname=$db_name;charset=utf8mb4";

$pdo = null; $lastEx = null;
foreach ($dsnCandidates as $dsn) {
    try {
        $pdo = new PDO($dsn, $db_user, $db_pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        break;
    } catch (Exception $e) {
        $lastEx = $e;
    }
}

if (!$pdo) {
    fwrite(STDERR, "Could not connect to MySQL: " . ($lastEx ? $lastEx->getMessage() : "no DSN") . "\n");
    fwrite(STDERR, "If you are using sqlite, run php scripts/init_sqlite.php instead.\n");
    exit(2);
}

$cols = [
    'slack_id' => "VARCHAR(255) DEFAULT NULL",
    'openid_sub' => "VARCHAR(255) DEFAULT NULL",
    'verification_status' => "TINYINT(1) DEFAULT 0",
    'profile' => "TEXT DEFAULT NULL",
];

foreach ($cols as $col => $def) {
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = ? AND table_name = 'users' AND column_name = ?");
        $stmt->execute([$db_name, $col]);
        $cnt = $stmt->fetchColumn();
        if (intval($cnt) === 0) {
            echo "Adding column $col...\n";
            $pdo->exec("ALTER TABLE users ADD COLUMN $col $def");
            echo "Added $col.\n";
        } else {
            echo "Column $col already exists, skipping.\n";
        }
    } catch (Exception $e) {
        fwrite(STDERR, "Failed checking/adding $col: " . $e->getMessage() . "\n");
    }
}

echo "Migration complete.\n";
