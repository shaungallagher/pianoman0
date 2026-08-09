<?php
require_once '../config.php';

$clientId = getenv('SLACK_CLIENT_ID');
if (!$clientId) {
    http_response_code(500);
    echo "<p>Slack OAuth not configured. Set SLACK_CLIENT_ID in .env</p>";
    exit;
}

$redirectUri = getenv('SLACK_REDIRECT_URI') ?: ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . url('auth/slack_callback.php'));

try {
    $state = bin2hex(random_bytes(16));
} catch (Exception $e) {
    $state = bin2hex(openssl_random_pseudo_bytes(16));
}

$_SESSION['slack_oauth_state'] = $state;

// cookie fallback
$cookieOptions = [
    'expires' => time() + 300,
    'path' => '/',
    'secure' => (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || getenv('FORCE_HTTPS') === '1',
    'httponly' => true,
    'samesite' => 'Lax',
];
$cookieDomain = isset($_SERVER['HTTP_HOST']) ? preg_replace('/:\d+$/', '', $_SERVER['HTTP_HOST']) : '';
if ($cookieDomain !== '') $cookieOptions['domain'] = $cookieDomain;
if (PHP_VERSION_ID >= 70300) {
    setcookie('slack_oauth_state', $state, $cookieOptions);
} else {
    setcookie('slack_oauth_state', $state, time() + 300, '/');
}

$params = [
    'client_id' => $clientId,
    'redirect_uri' => $redirectUri,
    'scope' => 'users:read chat:write',
    'state' => $state,
];

$authUrl = 'https://slack.com/oauth/v2/authorize?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
header('Location: ' . $authUrl);
exit;
