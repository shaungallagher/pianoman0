<?php
require_once '../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . url('auth/login.php'));
    exit;
}

$host = $_SERVER['HTTP_HOST'] ?? '';
$allowDemo = getenv('ALLOW_DEMO_LOGIN') === '1' || stripos($host, 'localhost') !== false || stripos($host, '127.0.0.1') !== false || stripos($host, 'dev.') === 0;

if (!$allowDemo) {
    http_response_code(403);
    echo "<p>Demo login is disabled on this installation.</p>";
    exit;
}

$token = $_POST['csrf_token'] ?? '';
if (!validate_csrf_token($token)) {
    http_response_code(400);
    echo "<p>Invalid CSRF token.</p>";
    exit;
}

$demo_email = getenv('DEV_USER_EMAIL') ?: 'demo@localhost';
$demo_name = getenv('DEV_USER_NAME') ?: 'Demo User';

try {
    // If DB is down, create an ephemeral demo user session instead of attempting DB ops.
    if (!empty($db_connection_failed)) {
        $user = [
            'id' => 'dev_' . uniqid(),
            'name' => $demo_name,
            'email' => $demo_email,
            'role' => 'participant'
        ];
        login_user($user);
        header('Location: ' . url('public/index.php'));
        exit;
    }

    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$demo_email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        $stmt = $pdo->prepare("INSERT INTO users (name, email, password_hash) VALUES (?,?,?)");
        $stmt->execute([$demo_name, $demo_email, '']);
        $id = $pdo->lastInsertId();
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    login_user($user);
    header('Location: ' . url('public/index.php'));
    exit;
} catch (Exception $e) {
    http_response_code(500);
    echo "<p>Demo login failed: " . htmlspecialchars($e->getMessage()) . "</p>";
    exit;
}
