<?php
// Admin-only DB connectivity checker.
require_once '../config.php';
require_role('admin');

$root = dirname(__DIR__);

// Load .env if present (simple parser)
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
$ok = false;
$err = '';
try {
    $pdo = new PDO($dsn, $db_user, $db_pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5]);
    $pdo->query('SELECT 1');
    $ok = true;
} catch (Exception $e) {
    $err = $e->getMessage();
}

$page_title = "DB Status · Sprint";
include '../includes/header.php';
?>

<h1>Database status</h1>

<p>
  Host: <strong><?= htmlspecialchars($db_host) ?></strong>
  Port: <strong><?= htmlspecialchars($db_port) ?></strong>
  DB: <strong><?= htmlspecialchars($db_name) ?></strong>
</p>

<?php if ($ok): ?>
  <p style="color:green;font-weight:bold;">Connected successfully.</p>
<?php else: ?>
  <p style="color:red;font-weight:bold;">Connection failed.</p>
  <h2>Error</h2>
  <pre><?= htmlspecialchars($err) ?></pre>
  <p>Check <code>logs/db_errors.log</code> for details.</p>
  <p>If the database exists but schema is missing, initialize it from <code>db.sql</code> using the included CLI script:</p>
  <pre>php scripts/init_db.php</pre>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>

