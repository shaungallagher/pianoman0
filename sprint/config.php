<?php
$root = __DIR__;

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
        if (getenv($name) === false) {
            putenv("$name=$value");
        }
        $_ENV[$name] = $value;
        $_SERVER[$name] = $value;
    }
}

$cookiePath = getenv('BASE_URL') ?: '/';
if ($cookiePath === '') $cookiePath = '/';
$cookieSecure = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') || getenv('FORCE_HTTPS') === '1';
if (session_status() !== PHP_SESSION_ACTIVE) {
    $cookieDomain = isset($_SERVER['HTTP_HOST']) ? preg_replace('/:\\d+$/', '', $_SERVER['HTTP_HOST']) : '';
    if (defined('PHP_VERSION_ID') && PHP_VERSION_ID >= 70300) {
        $params = [
            'lifetime' => 0,
            'path' => $cookiePath,
            'secure' => $cookieSecure,
            'httponly' => true,
            'samesite' => 'Lax',
        ];
        if ($cookieDomain !== '') $params['domain'] = $cookieDomain;
        session_set_cookie_params($params);
    } else {
        session_set_cookie_params(0, $cookiePath, $cookieDomain !== '' ? $cookieDomain : '', $cookieSecure, true);
    }
    @session_start();
}


// Database configuration
$db_host = getenv('DB_HOST') ?: '127.0.0.1';
$db_port = getenv('DB_PORT') ?: '3306';
$db_name = getenv('DB_NAME') ?: 'sprint';
$db_user = getenv('DB_USER') ?: 'root';
$db_pass = getenv('DB_PASS') ?: '';
$db_socket = getenv('DB_SOCKET') ?: '';

$pdo = null;
$errors = [];

$logDir = $root . '/logs';
if (!is_dir($logDir)) {
    @mkdir($logDir, 0755, true);
}
$logFile = $logDir . '/db_errors.log';

function log_db_error($msg) {
    global $logFile;
    $t = date('c');
    @file_put_contents($logFile, "[$t] $msg\n", FILE_APPEND | LOCK_EX);
}

$dsnCandidates = [];
if (getenv('DB_DSN')) {
    $dsnCandidates[] = getenv('DB_DSN');
}

$dsnCandidates[] = "mysql:host=$db_host;port=$db_port;dbname=$db_name;charset=utf8mb4";

if ($db_socket) {
    $dsnCandidates[] = "mysql:unix_socket=$db_socket;dbname=$db_name;charset=utf8mb4";
}

$dsnCandidates[] = "mysql:host=localhost;dbname=$db_name;charset=utf8mb4";

$dsnCandidates[] = "mysql:host=host.docker.internal;port=$db_port;dbname=$db_name;charset=utf8mb4";

if ($db_host && is_numeric($db_port)) {
    $fp = @fsockopen($db_host, (int)$db_port, $errno, $errstr, 1);
    if ($fp) {
        fclose($fp);
    } else {
        log_db_error("Connectivity check failed to $db_host:$db_port — $errno: $errstr");
    }
}

foreach ($dsnCandidates as $dsn) {
    try {
        $pdo = new PDO($dsn, $db_user, $db_pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 5,
        ]);
        $pdo->query('SELECT 1');
        break;
    } catch (Exception $e) {
        $errors[] = ['dsn' => $dsn, 'msg' => $e->getMessage()];
        $logDsn = preg_replace('/password=[^;]*/i', 'password=REDACTED', $dsn);
        log_db_error("DSN: $logDsn => " . $e->getMessage());
    }
}

if (!$pdo) {
    $sqliteFile = $root . '/data/sprint.sqlite';
    $useSqlite = getenv('DB_USE_SQLITE') === '1' || (getenv('DB_DSN') && stripos(getenv('DB_DSN'), 'sqlite:') !== false);
    if (!$useSqlite) {
        $useSqlite = true;
    }

    if ($useSqlite) {
        if (!is_dir($root . '/data')) @mkdir($root . '/data', 0755, true);
        try {
            $pdo = new PDO('sqlite:' . $sqliteFile, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $pdo->exec('PRAGMA foreign_keys = ON');

            try {
                $tbl = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='events'");
                $hasEvents = $tbl && $tbl->fetch();
                if (!$hasEvents) {
                    $sqlFile = $root . '/db_sqlite.sql';
                    if (file_exists($sqlFile)) {
                        $sql = file_get_contents($sqlFile);
                        $pdo->exec($sql);
                        log_db_error("Initialized SQLite schema from $sqlFile");
                    }
                }
            } catch (Exception $e) {
                $errors[] = ['dsn' => 'sqlite_init', 'msg' => $e->getMessage()];
                log_db_error("SQLite init failed: " . $e->getMessage());
                $pdo = null;
            }

            $db_connection_failed = false;
            putenv('USING_SQLITE=1');
            $_ENV['USING_SQLITE'] = '1';
            $_SERVER['USING_SQLITE'] = '1';
            log_db_error("Falling back to SQLite at $sqliteFile");
        } catch (Exception $e) {
            $errors[] = ['dsn' => 'sqlite:' . $sqliteFile, 'msg' => $e->getMessage()];
            log_db_error("SQLite fallback failed: " . $e->getMessage());
            $pdo = null;
        }
    }
}

if (!$pdo) {
    // If the DB connection fails, the site still runs in degraded mode
    $db_connection_failed = true;
    $db_errors = $errors;
    log_db_error("All DSNs failed; entering degraded mode.");

    class NullPDOStatement {
        public function execute($params = null) { return true; }
        public function fetch($mode = null) { return false; }
        public function fetchAll($mode = null) { return []; }
        public function fetchColumn($col = 0) { return false; }
        public function rowCount() { return 0; }
        public function bindParam() { return true; }
        public function bindValue() { return true; }
        public function errorInfo() { return [null, null, 'DB unavailable']; }
    }

    class NullPDO {
        public function prepare($sql = null) { log_db_error("NullPDO prepare called: " . ($sql ?: '')); return new NullPDOStatement(); }
        public function query($sql = null) { log_db_error("NullPDO query called: " . ($sql ?: '')); return new NullPDOStatement(); }
        public function lastInsertId() { return 0; }
        public function beginTransaction() { return false; }
        public function commit() { return false; }
        public function rollBack() { return false; }
        public function setAttribute() { return true; }
        public function exec($sql) { log_db_error("NullPDO exec called: " . ($sql ?: '')); return false; }
    }

    $pdo = new NullPDO();
} else {
    $db_connection_failed = false;
}

// Base URL helper: set BASE_URL env var to override (e.g. '/sprint').
// Default to empty string so URLs are generated relative to host root.
define('BASE_URL', rtrim(getenv('BASE_URL') ?: '', '/'));

function url($path = '') {
    $p = ltrim($path, '/');

    // If BASE_URL is set, avoid duplicating it when templates already
    // include the base prefix (some templates use '/sprint/...').
    if (defined('BASE_URL') && BASE_URL !== '') {
        $base = rtrim(BASE_URL, '/');
        $normalizedBase = ltrim($base, '/');

        // If the provided path already begins with the base segment,
        // return the normalized absolute path.
        if ($normalizedBase !== '') {
            if (strpos($p, $normalizedBase . '/') === 0 || $p === $normalizedBase) {
                return '/' . $p;
            }
        }

        return $base . ($p !== '' ? '/' . $p : '');
    }

    return '/' . $p;
}

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/roles.php';
require_once __DIR__ . '/includes/judge_functions.php';

function current_user() {
    return $_SESSION['user'] ?? null;
}

function current_user_id() {
    return $_SESSION['user']['id'] ?? null;
}

// Maintenance mode
$maintenance_file = $root . '/MAINTENANCE';
if (file_exists($maintenance_file)) {
    $maintenance_message = trim(@file_get_contents($maintenance_file) ?: 'The site is temporarily unavailable for maintenance.');
    $maintenance_mode = true;
} else {
    $maintenance_message = '';
    $maintenance_mode = false;
}

if (php_sapi_name() !== 'cli') {
    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: no-referrer-when-downgrade');
    header('X-XSS-Protection: 1; mode=block');

    header("Content-Security-Policy: default-src 'self'; base-uri 'self'; object-src 'none'; frame-ancestors 'self'; img-src 'self' data: https:; style-src 'self'; script-src 'self'; connect-src 'self' https: wss:; font-src 'self' data: https:; media-src 'self' https: data:; worker-src 'none'");
}
