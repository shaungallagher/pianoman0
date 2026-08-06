<?php
require_once '../config.php';

require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . url('/sprint/public/profile.php'));
    exit;
}

$token = trim((string)($_POST['hackatime_token'] ?? ''));
if ($token === '') {
    $_SESSION['profile_error'] = 'Missing Hackatime token.';
    header('Location: ' . url('/sprint/auth/hackatime.php'));
    exit;
}

if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
    http_response_code(400);
    $_SESSION['profile_error'] = 'Invalid CSRF token.';
    header('Location: ' . url('/sprint/auth/hackatime.php'));
    exit;
}

$apiBase = rtrim(getenv('HACKATIME_API_BASE') ?: 'https://hackatime.hackclub.com', '/');

// Validate token + resolve user id using Hackatime “current” endpoints.
$todayUrl = $apiBase . '/api/hackatime/v1/users/current/statusbar/today';
$statsUrl = $apiBase . '/api/hackatime/v1/users/current/stats/last_7_days';

function hackatime_http_get_json(string $url, string $token): array {
    $opts = [
        'http' => [
            'method' => 'GET',
            'header' => "Authorization: Bearer $token\r\nAccept: application/json\r\n",
            'timeout' => 10,
        ]
    ];
    $ctx = stream_context_create($opts);
    $res = @file_get_contents($url, false, $ctx);
    if ($res === false) return [null, null];
    return [json_decode($res, true), $res];
}

function extract_provider_user_id_from_last_7_days($payload): ?string {
    if (!is_array($payload)) return null;
    $data = $payload['data'] ?? $payload;

    // From the spec example: data.user_id is present.
    if (!empty($data['user_id']) && is_string($data['user_id'])) return $data['user_id'];
    if (!empty($data['username']) && is_string($data['username'])) return $data['username'];

    return null;
}

function extract_provider_user_id_from_today($payload): ?string {
    // Spec example for statusbar/today does not include user id.
    // Keep this for future compatibility if provider adds it.
    if (!is_array($payload)) return null;
    $data = $payload['data'] ?? $payload;
    if (!empty($data['user_id']) && is_string($data['user_id'])) return $data['user_id'];
    return null;
}

// 1) Try stats endpoint (more likely to include user_id)
[$statsJson, $statsRaw] = hackatime_http_get_json($statsUrl, $token);
if (!$statsJson) {
    $_SESSION['profile_error'] = 'Hackatime token validation failed (cannot query /last_7_days).';
    $_SESSION['hackatime_last_error'] = is_string($statsRaw) ? substr($statsRaw, 0, 5000) : null;
    header('Location: ' . url('/sprint/auth/hackatime.php'));
    exit;
}

$providerUserId = extract_provider_user_id_from_last_7_days($statsJson);

// 2) Fallback to statusbar endpoint
if (!$providerUserId) {
    [$todayJson, $todayRaw] = hackatime_http_get_json($todayUrl, $token);
    $providerUserId = extract_provider_user_id_from_today($todayJson);
}

if (!$providerUserId) {
    $_SESSION['profile_error'] = 'Hackatime token validated, but we could not resolve your Hackatime user id.';
    header('Location: ' . url('/sprint/auth/hackatime.php'));
    exit;
}

$provider = 'hackatime';

try {
    $stmt = $pdo->prepare('SELECT id, user_id FROM oauth_accounts WHERE provider=? AND provider_user_id=? LIMIT 1');
    $stmt->execute([$provider, (string)$providerUserId]);
    $acct = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($acct) {
        $upd = $pdo->prepare('UPDATE oauth_accounts SET access_token=? WHERE id=?');
        $upd->execute([$token, $acct['id']]);

        if (!empty($acct['user_id']) && intval($acct['user_id']) !== intval(current_user_id())) {
            $upd2 = $pdo->prepare('UPDATE oauth_accounts SET user_id=? WHERE id=?');
            $upd2->execute([current_user_id(), $acct['id']]);
        }

        $_SESSION['profile_success'] = 'Hackatime account linked.';
    } else {
        $ins = $pdo->prepare('INSERT INTO oauth_accounts (user_id, provider, provider_user_id, access_token, refresh_token, expires_at, created_at) VALUES (?,?,?,?,NULL,NULL,CURRENT_TIMESTAMP)');
        $ins->execute([current_user_id(), $provider, (string)$providerUserId, $token]);
        $_SESSION['profile_success'] = 'Hackatime account linked.';
    }
} catch (Exception $e) {
    $_SESSION['profile_error'] = 'Failed to link Hackatime: ' . htmlspecialchars($e->getMessage());
}

header('Location: ' . url('/sprint/public/profile.php'));
exit;

