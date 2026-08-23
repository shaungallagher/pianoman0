<?php
require_once '../config.php';
require_login();

$clientId = getenv('GITHUB_CLIENT_ID');
if (!$clientId) {
    http_response_code(500);
    echo "<p>GitHub OAuth not configured. Please contact @PianoMan0</p>";
    exit;
}

$redirectUri = getenv('GITHUB_REDIRECT_URI') ?: ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . url('sprint/auth/github_callback.php'));

try {
    $state = bin2hex(random_bytes(16));
} catch (Exception $e) {
    $state = bin2hex(openssl_random_pseudo_bytes(16));
}

$_SESSION['gh_oauth_state'] = $state;

if (current_user_id()) {
    $_SESSION['oauth_intent_user_id']['github'] = (int)current_user_id();
}

$cookieOptions = [
    'expires' => time() + 300,
    'path' => '/',
    'secure' => (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || getenv('FORCE_HTTPS') === '1',
    'httponly' => true,
    'samesite' => 'Lax',
];
$cookieDomain = isset($_SERVER['HTTP_HOST']) ? preg_replace('/:\\d+$/', '', $_SERVER['HTTP_HOST']) : '';
if ($cookieDomain !== '') $cookieOptions['domain'] = $cookieDomain;
if (PHP_VERSION_ID >= 70300) {
    setcookie('gh_oauth_state', $state, $cookieOptions);
} else {
    // Fallback for older PHP: set basic cookie (without samesite/domain options)
    setcookie('gh_oauth_state', $state, time() + 300, '/');
}

$params = [
    'client_id' => $clientId,
    'redirect_uri' => $redirectUri,
    'scope' => 'read:user user:email',
    'state' => $state,
    'allow_signup' => 'false'
];

$authUrl = 'https://github.com/login/oauth/authorize?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
header('Location: ' . $authUrl);
exit;
