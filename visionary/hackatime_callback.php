<?php
require_once __DIR__ . '/auth.php';
$cfg = @include __DIR__ . '/config.php';
$clientId = $cfg['hackatime_client_id'] ?? '';
$clientSecret = $cfg['hackatime_client_secret'] ?? '';
$authorizeUrl = $cfg['hackatime_authorize_url'] ?? '';
$tokenUrl = $cfg['hackatime_token_url'] ?? '';
$userinfoUrl = $cfg['hackatime_userinfo_url'] ?? '';
$scope = $cfg['hackatime_scope'] ?? 'profile';

function detect_base_url_ht($cfg) {
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? 'localhost');
    $current = $https . '://' . $host;
    if (!empty($cfg['base_url'])) {
        $configured = rtrim($cfg['base_url'], '/');
        $configuredHost = parse_url($configured, PHP_URL_HOST) ?: '';
        $currentHost = parse_url($current, PHP_URL_HOST) ?: '';
        if ($configuredHost && strcasecmp($configuredHost, $currentHost) === 0) {
            return $configured;
        }
        $path = parse_url($configured, PHP_URL_PATH) ?: '';
        return $current . $path;
    }
    return $current;
}

function parse_oauth_response($text) {
    $data = json_decode($text, true);
    if (is_array($data)) return $data;
    parse_str($text, $data);
    return $data;
}

function make_json_request($url, $method = 'GET', $body = null, $headers = []) {
    if (function_exists('curl_version')) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }
        $res = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);
        if ($res === false) {
            return ['success' => false, 'error' => $err ?: 'curl_failed'];
        }
        return ['success' => true, 'data' => json_decode($res, true), 'raw' => $res];
    }
    $opts = ['http' => ['method' => $method, 'header' => implode("\r\n", $headers) . "\r\n", 'timeout' => 15]];
    if ($body !== null) {
        $opts['http']['content'] = $body;
    }
    $res = @file_get_contents($url, false, stream_context_create($opts));
    if ($res === false) {
        return ['success' => false, 'error' => 'request_failed'];
    }
    return ['success' => true, 'data' => json_decode($res, true), 'raw' => $res];
}

function normalize_provider_data($data) {
    if (!is_array($data)) return null;
    if (isset($data['data']) && is_array($data['data'])) {
        return $data['data'];
    }
    if (isset($data['user']) && is_array($data['user'])) {
        return $data['user'];
    }
    return $data;
}

function extract_provider_id($user) {
    if (!is_array($user)) return null;
    return $user['id'] ?? $user['sub'] ?? $user['uid'] ?? $user['user_id'] ?? null;
}

function extract_provider_email($user) {
    if (!is_array($user)) return null;
    return $user['email'] ?? $user['email_address'] ?? null;
}

function extract_provider_name($user) {
    if (!is_array($user)) return null;
    return $user['username'] ?? $user['handle'] ?? $user['login'] ?? $user['name'] ?? $user['preferred_username'] ?? null;
}

$base = detect_base_url_ht($cfg);

if (!empty($_GET['oauth'])) {
    if (!$clientId || !$authorizeUrl) {
        echo "Hackatime OAuth not configured. Set hackatime_client_id and hackatime_authorize_url in config.php.";
        exit;
    }
    $redirect = $base . '/hackatime_callback.php';
    $state = bin2hex(random_bytes(8));
    $_SESSION['hackatime_oauth_state'] = $state;
    $params = [
        'client_id' => $clientId,
        'redirect_uri' => $redirect,
        'response_type' => 'code',
        'scope' => $scope,
        'state' => $state,
    ];
    $url = $authorizeUrl . (strpos($authorizeUrl, '?') === false ? '?' : '&') . http_build_query($params);
    header('Location: ' . $url);
    exit;
}

if (!empty($_GET['code'])) {
    if (empty($_GET['state']) || $_GET['state'] !== ($_SESSION['hackatime_oauth_state'] ?? '')) {
        echo "Invalid OAuth state";
        exit;
    }
    if (!$tokenUrl) {
        echo "Token endpoint not configured.";
        exit;
    }
    $code = $_GET['code'];
    $postParams = [
        'client_id' => $clientId,
        'client_secret' => $clientSecret,
        'code' => $code,
        'redirect_uri' => $base . '/hackatime_callback.php',
        'grant_type' => 'authorization_code',
    ];
    $post = http_build_query($postParams);
    $tokenRequest = make_json_request($tokenUrl, 'POST', $post, [
        'Accept: application/json',
        'Content-Type: application/x-www-form-urlencoded',
    ]);
    if (!$tokenRequest['success'] || empty($tokenRequest['raw'])) {
        $err = $tokenRequest['error'] ?? 'Unknown error';
        echo "OAuth token exchange failed: " . h($err);
        exit;
    }
    $data = parse_oauth_response($tokenRequest['raw']);
    $accessToken = $data['access_token'] ?? null;
    if (!$accessToken) {
        $err = $data['error'] ?? 'Missing access_token';
        echo "OAuth token exchange failed: " . h($err);
        exit;
    }

    $user = null;
    if ($userinfoUrl && $accessToken) {
        $userRequest = make_json_request($userinfoUrl, 'GET', null, [
            "Authorization: Bearer $accessToken",
            'Accept: application/json',
            'User-Agent: Visionary-App',
        ]);
        if ($userRequest['success']) {
            $user = normalize_provider_data($userRequest['data'] ?? parse_oauth_response($userRequest['raw']));
        }
    }
    if (!$user) {
        echo "Failed to retrieve user information from Hackatime.";
        exit;
    }

    $providerId = extract_provider_id($user);
    $usernameCandidate = extract_provider_name($user) ?? extract_provider_email($user);
    if (!$providerId) {
        echo "Hackatime did not return a stable user id.";
        exit;
    }

    $pdo = getPDO();
    $loginKey = 'hackatime:' . $providerId;
    $stmt = $pdo->prepare('SELECT id FROM users WHERE github_id = ?');
    $stmt->execute([$loginKey]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $avatar = $user['avatar_url'] ?? $user['picture'] ?? null;
        $email = $user['email'] ?? null;
        $stmt = $pdo->prepare('UPDATE users SET avatar_url = COALESCE(avatar_url, ?), email = COALESCE(email, ?) WHERE id = ?');
        $stmt->execute([$avatar, $email, $row['id']]);
        login_user_by_id($row['id']);
        session_regenerate_id(true);
        header('Location: ideas.php');
        exit;
    }

    $username = $usernameCandidate ? preg_replace('/[^a-zA-Z0-9_\-]/', '_', mb_substr($usernameCandidate, 0, 32)) : null;
    if (!$username || strlen($username) < 3) {
        $username = 'hackatime_' . substr(sha1($providerId), 0, 8);
    }
    $avatar = $user['avatar_url'] ?? $user['picture'] ?? null;
    $email = $user['email'] ?? null;
    $now = date('c');

    try {
        $stmt = $pdo->prepare('INSERT INTO users (username, role, github_id, avatar_url, email, created_at) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->execute([$username, 'both', $loginKey, $avatar, $email, $now]);
        $id = $pdo->lastInsertId();
    } catch (Exception $e) {
        $username = $username . '_' . substr(sha1($providerId), 0, 6);
        $stmt = $pdo->prepare('INSERT INTO users (username, role, github_id, avatar_url, email, created_at) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->execute([$username, 'both', $loginKey, $avatar, $email, $now]);
        $id = $pdo->lastInsertId();
    }

    login_user_by_id($id);
    session_regenerate_id(true);
    header('Location: ideas.php');
    exit;
}

echo "No OAuth code provided.";
