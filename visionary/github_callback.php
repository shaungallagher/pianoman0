<?php
require_once __DIR__ . '/auth.php';
$cfg = @include __DIR__ . '/config.php';
$clientId = $cfg['github_client_id'] ?? '';
$clientSecret = $cfg['github_client_secret'] ?? '';

function detect_base_url($cfg) {
    if (!empty($cfg['base_url'])) return rtrim($cfg['base_url'], '/');
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? 'localhost');
    return $https . '://' . $host;
}

$base = detect_base_url($cfg);

if (!empty($_GET['oauth'])) {
    if (!$clientId) {
        echo "GitHub OAuth not configured. Set GITHUB_CLIENT_ID and GITHUB_CLIENT_SECRET in environment or edit config.php.";
        exit;
    }
    $redirect = $base . '/github_callback.php';
    $state = bin2hex(random_bytes(8));
    $_SESSION['gh_oauth_state'] = $state;
    $url = 'https://github.com/login/oauth/authorize?client_id=' . urlencode($clientId) . '&redirect_uri=' . urlencode($redirect) . '&state=' . $state . '&scope=read:user%20user:email';
    header('Location: ' . $url);
    exit;
}

if (!empty($_GET['code'])) {
    if (empty($_GET['state']) || $_GET['state'] !== ($_SESSION['gh_oauth_state'] ?? '')) {
        echo "Invalid OAuth state"; exit;
    }
    $code = $_GET['code'];
    $tokenUrl = 'https://github.com/login/oauth/access_token';
    $postParams = ['client_id'=>$clientId,'client_secret'=>$clientSecret,'code'=>$code,'redirect_uri'=>$base . '/github_callback.php'];
    $post = http_build_query($postParams);
    $opts = [
        'http'=>[
            'method'=>'POST',
            'header'=>"Accept: application/json\r\nContent-Type: application/x-www-form-urlencoded\r\n",
            'content'=>$post,
            'timeout'=>10
        ]
    ];
    $res = @file_get_contents($tokenUrl, false, stream_context_create($opts));
    $data = $res ? json_decode($res, true) : null;
    if (empty($data['access_token'])) { echo "OAuth failed"; exit; }
    $token = $data['access_token'];
    $opts = [
        'http'=>[
            'method'=>'GET',
            'header'=>"User-Agent: Visionary-App\r\nAuthorization: token $token\r\nAccept: application/json\r\n",
            'timeout'=>10
        ]
    ];
    $userRes = @file_get_contents('https://api.github.com/user', false, stream_context_create($opts));
    $user = json_decode($userRes, true);
    if (empty($user['id'])) { echo "Failed to get GitHub user"; exit; }
    $pdo = getPDO();
    $stmt = $pdo->prepare('SELECT id FROM users WHERE github_id = ?');
    $stmt->execute([$user['id']]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $email = $user['email'] ?? null;
        $stmt = $pdo->prepare('UPDATE users SET avatar_url = COALESCE(avatar_url, ?), email = COALESCE(email, ?) WHERE id = ?');
        $stmt->execute([$user['avatar_url'] ?? null, $email, $row['id']]);
        login_user_by_id($row['id']);
        session_regenerate_id(true);
        header('Location: ideas.php'); exit;
    }

    $username = $user['login'] ?? ('gh' . $user['id']);
    // disallow usernames that contain forbidden words and log violations for admins
    if ($bad = check_forbidden($username, 'username', null, 'GitHub OAuth signup')) {
        $username = 'gh' . $user['id'];
    }
    $now = date('c');
    $email = $user['email'] ?? null;
    try {
        $stmt = $pdo->prepare('INSERT INTO users (username, role, github_id, avatar_url, email, created_at) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->execute([$username, 'both', $user['id'], $user['avatar_url'] ?? null, $email, $now]);
        $id = $pdo->lastInsertId();
    } catch (Exception $e) {
        $username = $username . '_' . $user['id'];
        $stmt = $pdo->prepare('INSERT INTO users (username, role, github_id, avatar_url, created_at) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([$username, 'both', $user['id'], $user['avatar_url'] ?? null, $now]);
        $id = $pdo->lastInsertId();
    }
    login_user_by_id($id);
    session_regenerate_id(true);
    header('Location: ideas.php'); exit;
}

echo "No OAuth code provided.";
