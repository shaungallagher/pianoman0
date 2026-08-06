<?php

require_once __DIR__ . '/github_preview.php';

function get_events($pdo) {

    $stmt = $pdo->query("SELECT * FROM events ORDER BY start_time ASC");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function get_event($pdo, $id) {
    $stmt = $pdo->prepare("SELECT * FROM events WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function get_event_teams($pdo, $event_id) {
    $stmt = $pdo->prepare("SELECT * FROM teams WHERE event_id = ?");
    $stmt->execute([$event_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function get_user_team($pdo, $event_id, $user_id) {
    $stmt = $pdo->prepare("
        SELECT t.*
        FROM teams t
        JOIN team_members tm ON tm.team_id = t.id
        WHERE tm.user_id = ? AND t.event_id = ?
    ");
    $stmt->execute([$user_id, $event_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function get_event_submissions($pdo, $event_id) {
    $stmt = $pdo->prepare("
        SELECT s.*, t.name AS team_name
        FROM submissions s
        JOIN teams t ON t.id = s.team_id
        WHERE s.event_id = ?
        ORDER BY s.created_at DESC
    ");
    $stmt->execute([$event_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function get_submission($pdo, $id) {
    $stmt = $pdo->prepare("SELECT * FROM submissions WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function get_event_announcements($pdo, $event_id) {
    $stmt = $pdo->prepare("SELECT * FROM announcements WHERE event_id = ? ORDER BY created_at DESC");
    $stmt->execute([$event_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Friendly abort for public pages: renders header/footer and a message.
function abort_page($message, $code = 404) {
    http_response_code($code);
    $page_title = 'Error';
    include __DIR__ . '/header.php';
    echo '<div class="card"><h1>Error</h1><p>' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</p></div>';
    include __DIR__ . '/footer.php';
    exit;
}

// CSRF helpers
function ensure_session_started() {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
}

function csrf_token() {
    ensure_session_started();
    if (empty($_SESSION['csrf_token'])) {
        try {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(24));
        } catch (Exception $e) {
            $_SESSION['csrf_token'] = bin2hex(openssl_random_pseudo_bytes(24));
        }
    }
    return $_SESSION['csrf_token'];
}

function csrf_input_field() {
    $token = htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8');
    return "<input type=\"hidden\" name=\"csrf_token\" value=\"$token\">";
}

function secure_redirect(string $url): void {
    // Prevent open-redirect by only allowing app-relative redirects.
    // This codebase uses url('...') to build paths, but enforce it anyway.
    $url = (string)$url;
    if (!preg_match('#^/[^\s]*$#', $url)) {
        $url = url('/public/index.php');
    }
    header('Location: ' . $url);
    exit;
}


function validate_csrf_token($token) {
    ensure_session_started();
    if (empty($_SESSION['csrf_token']) || empty($token)) return false;
    return hash_equals($_SESSION['csrf_token'], (string)$token);
}

function h(?string $s): string {
    return htmlspecialchars((string)($s ?? ''), ENT_QUOTES, 'UTF-8');
}

function app_request_id(): string {

    ensure_session_started();
    if (!empty($_SESSION['request_id']) && is_string($_SESSION['request_id'])) {
        return $_SESSION['request_id'];
    }

    try {
        $rid = bin2hex(random_bytes(12));
    } catch (Exception $e) {
        $rid = (string)mt_rand(100000, 999999) . '-' . (string)time();
    }

    $_SESSION['request_id'] = $rid;
    return $rid;
}

function log_event(string $level, string $message, array $context = []): void {
    // Best-effort structured logging.
    $rid = app_request_id();
    $line = json_encode([
        'ts' => date('c'),
        'level' => strtoupper($level),
        'request_id' => $rid,
        'message' => $message,
        'context' => $context,
    ], JSON_UNESCAPED_SLASHES);

    $root = __DIR__ . '/..';
    $logDir = $root . '/logs';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }
    $logFile = $logDir . '/app.log';
    @file_put_contents($logFile, $line . "\n", FILE_APPEND | LOCK_EX);
}


function gravatar_url($email, $size = 40, $default = 'identicon') {
    $e = strtolower(trim((string)$email));
    $hash = md5($e);
    return "https://www.gravatar.com/avatar/{$hash}?s=" . intval($size) . "&d=" . urlencode($default);
}

function user_avatar_url(array $user, int $size = 32): string {
    // Prefer Slack avatar when available.
    if (!empty($user['slack_avatar_url']) && is_string($user['slack_avatar_url'])) {
        return $user['slack_avatar_url'];
    }

    // Prefer GitHub avatar when available (stored in users.github_avatar_url).
    // This is what we want for “Sprint users” who authenticate via GitHub.
    if (!empty($user['github_avatar_url']) && is_string($user['github_avatar_url'])) {
        return $user['github_avatar_url'];
    }

    // Some callers may pass a pre-hydrated GitHub avatar under different keys.
    if (!empty($user['avatar_url']) && is_string($user['avatar_url'])) {
        return $user['avatar_url'];
    }

    // Fall back to Gravatar.
    $email = $user['email'] ?? '';
    return gravatar_url($email, $size);
}





// Send a simple message to Slack using a bot token configured in the environment.
function send_slack_message($text, $channel = null) {
    $token = getenv('SLACK_BOT_TOKEN');
    if (!$token) return false;
    $channel = $channel ?: getenv('SLACK_DEFAULT_CHANNEL');
    if (!$channel) return false;

    $payload = json_encode(['channel' => $channel, 'text' => $text]);

    if (function_exists('curl_init')) {
        $ch = curl_init('https://slack.com/api/chat.postMessage');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json; charset=utf-8',
            'Authorization: Bearer ' . $token,
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        $resp = curl_exec($ch);
        if ($resp === false) {
            if (function_exists('log_db_error')) log_db_error('Slack curl error: ' . curl_error($ch));
            curl_close($ch);
            return false;
        }
        curl_close($ch);
    } else {
        $opts = [
            'http' => [
                'method' => 'POST',
                'header' => "Authorization: Bearer $token\r\nContent-Type: application/json\r\n",
                'content' => $payload,
                'timeout' => 5,
            ]
        ];
        $ctx = stream_context_create($opts);
        $resp = @file_get_contents('https://slack.com/api/chat.postMessage', false, $ctx);
        if ($resp === false) {
            if (function_exists('log_db_error')) log_db_error('Slack HTTP request failed');
            return false;
        }
    }

    $data = json_decode($resp, true);
    if (!$data || empty($data['ok'])) {
        if (function_exists('log_db_error')) log_db_error('Slack API error: ' . ($data['error'] ?? $resp));
        return false;
    }

    return true;
}
