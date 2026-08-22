<?php
require_once '../config.php';

function redact_oauth_json($raw) {
    $rawStr = (string)$raw;
    $arr = json_decode($rawStr, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($arr)) {
        $sensitive = ['access_token', 'refresh_token', 'id_token', 'token', 'refreshToken', 'id'];
        foreach ($sensitive as $k) {
            if (array_key_exists($k, $arr)) $arr[$k] = '[REDACTED]';
        }
        return json_encode($arr);
    }
    $clean = preg_replace('/(access_token=)[^&\s]+/i', '$1[REDACTED]', $rawStr);
    $clean = preg_replace('/("access_token"\s*:\s*")([^"]+)(")/i', '$1[REDACTED]$3', $clean);
    $clean = preg_replace('/("refresh_token"\s*:\s*")([^"]+)(")/i', '$1[REDACTED]$3', $clean);
    return $clean;
}

function oauth_debug_log($label, $raw = '') {
    if (getenv('OAUTH_DEBUG') !== '1' || strtolower(getenv('APP_ENV') ?: '') !== 'development') {
        return;
    }

    $logFile = sys_get_temp_dir() . '/sprint_oauth_debug.log';
    $clean = '';
    if ($raw !== null && $raw !== '') {
        $clean = redact_oauth_json($raw);
    }

    $entry = date('c') . " {$label}\n" . $clean . "\n\n";
    @file_put_contents($logFile, $entry, FILE_APPEND | LOCK_EX);
}

$code = $_GET['code'] ?? null;
$state = $_GET['state'] ?? null;
$sessState = $_SESSION['gh_oauth_state'] ?? null;
$cookieState = $_COOKIE['gh_oauth_state'] ?? null;
$validState = false;
if ($code && $state) {
    if ($sessState && hash_equals($sessState, $state)) $validState = true;
    elseif ($cookieState && hash_equals($cookieState, $state)) $validState = true;
}
if (!$validState) {
    $hcSessState = $_SESSION['hc_oauth_state'] ?? null;
    $hcCookieState = $_COOKIE['hc_oauth_state'] ?? null;
    if (($hcSessState && hash_equals($hcSessState, $state)) || ($hcCookieState && hash_equals($hcCookieState, $state))) {
        header('Location: ' . url('auth/callback.php') . '?code=' . urlencode($code) . '&state=' . urlencode($state));
        exit;
    }

    $_SESSION['profile_error'] = 'Invalid GitHub OAuth state. Please try again.';
    header('Location: ' . url('sprint/profile.php'));
    exit;
}

if (isset($_COOKIE['gh_oauth_state'])) {
    $cookieDomain = isset($_SERVER['HTTP_HOST']) ? preg_replace('/:\d+$/', '', $_SERVER['HTTP_HOST']) : '';
    if (PHP_VERSION_ID >= 70300) {
        $opts = ['expires' => time() - 3600, 'path' => '/', 'samesite' => 'Lax'];
        if ($cookieDomain !== '') $opts['domain'] = $cookieDomain;
        setcookie('gh_oauth_state', '', $opts);
    } else {
        setcookie('gh_oauth_state', '', time() - 3600, '/');
    }
}

if (getenv('OAUTH_DEBUG') === '1') {
    $host = $_SERVER['HTTP_HOST'] ?? '';
    $redir = getenv('GITHUB_REDIRECT_URI') ?: '(auto)';
    $dbg = "host={$host}\nGITHUB_REDIRECT_URI={$redir}\ncode_present=" . (!empty($code) ? '1' : '0') . "\nstate_present=" . (!empty($state) ? '1' : '0') . "\nsessState_present=" . (!empty($sessState) ? '1' : '0') . "\ncookieState_present=" . (!empty($cookieState) ? '1' : '0') . "\n";
    oauth_debug_log('handshake_metadata', $dbg);
}

$clientId = getenv('GITHUB_CLIENT_ID');
$clientSecret = getenv('GITHUB_CLIENT_SECRET');
if (!$clientId || !$clientSecret) {
    $_SESSION['profile_error'] = 'GitHub OAuth is not fully configured, please contact @PianoMan0';
    header('Location: ' . url('sprint/profile.php'));
    exit;
}

$redirectUri = getenv('GITHUB_REDIRECT_URI') ?: ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . url('sprint/auth/github_callback.php'));

$post = http_build_query([
    'client_id' => $clientId,
    'client_secret' => $clientSecret,
    'code' => $code,
    'redirect_uri' => $redirectUri,
    'state' => $state,
]);

$ch = curl_init('https://github.com/login/oauth/access_token');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json']);
$resp = curl_exec($ch);
$httpStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);
if ($resp === false) {
    if (getenv('OAUTH_DEBUG') === '1') {
        oauth_debug_log('github_token_exchange: curl_exec_failed', curl_error($ch) . "\nhttp_status=" . $httpStatus);
    }
    $_SESSION['profile_error'] = 'Failed to complete GitHub OAuth.';
    header('Location: ' . url('sprint/profile.php'));
    exit;
}
$data = json_decode($resp, true);
$accessToken = $data['access_token'] ?? null;
if (!$accessToken) {
    if (getenv('OAUTH_DEBUG') === '1') {
        oauth_debug_log('github_token_exchange: no_access_token', $resp . "\nhttp_status=" . $httpStatus);
    }
    $_SESSION['profile_error'] = 'No access token from GitHub.';
    header('Location: ' . url('sprint/profile.php'));
    exit;
}

$ch = curl_init('https://api.github.com/user');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: token $accessToken", 'User-Agent: Sprint-App']);
$userJson = curl_exec($ch);
$user = json_decode($userJson, true);
if (!$user || empty($user['login'])) {
    $_SESSION['profile_error'] = 'Failed to fetch GitHub user data.';
    header('Location: ' . url('sprint/profile.php'));
    exit;
}

if (getenv('OAUTH_DEBUG') === '1') {
    oauth_debug_log('provider_responses', ($resp ?? '') . "\n\n" . ($userJson ?? ''));
}

$provider = 'github';
$provider_user_id = $user['login'];
$avatar_url = $user['avatar_url'] ?? null;

$intentUserId = $_SESSION['oauth_intent_user_id']['github'] ?? null;
if (empty($intentUserId) || !is_int((int)$intentUserId)) {
    unset($_SESSION['oauth_intent_user_id']['github']);
    $_SESSION['profile_error'] = 'Invalid GitHub OAuth linking session. Please try connecting again.';
    header('Location: ' . url('sprint/profile.php'));
    exit;
}
$intentUserId = (int)$intentUserId;
unset($_SESSION['oauth_intent_user_id']['github']);

try {
    $stmt = $pdo->prepare('SELECT id AS acct_id, user_id FROM oauth_accounts WHERE provider=? AND provider_user_id=? LIMIT 1');
    $stmt->execute([$provider, $provider_user_id]);
    $acct = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($acct) {
        if (!empty($acct['user_id']) && (int)$acct['user_id'] !== $intentUserId) {
            $_SESSION['profile_error'] = 'This GitHub account is already linked to another user.';
            header('Location: ' . url('sprint/profile.php'));
            exit;
        }

        $stmt = $pdo->prepare('UPDATE oauth_accounts SET access_token=?, expires_at=? WHERE id=?');
        $stmt->execute([$accessToken, null, $acct['acct_id']]);

        if ((int)$acct['user_id'] !== $intentUserId) {
            $uUpd = $pdo->prepare('UPDATE oauth_accounts SET user_id=? WHERE id=?');
            $uUpd->execute([$intentUserId, $acct['acct_id']]);
        }
    } else {
        $stmt = $pdo->prepare('INSERT INTO oauth_accounts (user_id, provider, provider_user_id, access_token, created_at) VALUES (?,?,?,?,CURRENT_TIMESTAMP)');
        $stmt->execute([$intentUserId, $provider, $provider_user_id, $accessToken]);
    }

    if (!empty($avatar_url)) {
        $upd = $pdo->prepare('UPDATE users SET github_avatar_url = ? WHERE id = ?');
        $upd->execute([$avatar_url, $intentUserId]);
    }

    $uStmt = $pdo->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
    $uStmt->execute([$intentUserId]);
    $uRow = $uStmt->fetch(PDO::FETCH_ASSOC);
    if ($uRow) {
        login_user($uRow);
        $_SESSION['profile_success'] = 'GitHub account connected.';
    } else {
        $_SESSION['profile_error'] = 'User record missing for GitHub connection.';
    }
} catch (Exception $e) {
    $_SESSION['profile_error'] = 'Failed to link GitHub: ' . $e->getMessage();
}

header('Location: ' . url('sprint/profile.php'));
exit;
