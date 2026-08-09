<?php
require_once '../config.php';
 
$clientId = getenv('HACKCLUB_CLIENT_ID');
$clientSecret = getenv('HACKCLUB_CLIENT_SECRET');
if (!$clientId || !$clientSecret) {
    http_response_code(500);
    echo "<p>Hack Club OAuth is not configured. Please contact @PianoMan0</p>";
    exit;
}

$configuredRedirect = getenv('HACKCLUB_REDIRECT_URI');
$redirectHostMismatch = false;
if ($configuredRedirect) {
    $parts = parse_url($configuredRedirect);
    $cfgHost = $parts['host'] ?? null;
    $currentHost = $_SERVER['HTTP_HOST'] ?? null;
    if ($cfgHost && $currentHost && strcasecmp($cfgHost, $currentHost) !== 0) {
        $redirectHostMismatch = true;
    }
}

if ($redirectHostMismatch) {
    $_SESSION['oauth_redirect_mismatch'] = 'The OAuth client is configured to redirect to ' . htmlspecialchars($configuredRedirect) . ' which does not match this server (' . htmlspecialchars($_SERVER['HTTP_HOST']) . '). If you see a 404 after signing in, update HACKCLUB_REDIRECT_URI or the registered redirect URL in the Hack Club app settings.';
}

if (!isset($_GET['code'])) {
    $err = $_GET['error'] ?? 'missing_code';
    http_response_code(400);
    echo "<p>OAuth error: " . htmlspecialchars($err) . "</p>";
    exit;
}

$state = $_GET['state'] ?? '';

// Accept state from session OR a short-lived cookie fallback. If the state
// actually matches another provider's state (e.g. GitHub), forward the
// callback to that provider's handler so misconfigured redirect URLs are
// handled gracefully.
$cookieState = null; // Intentionally disabled: OAuth state must be validated via session only.
$ghSessState = $_SESSION['gh_oauth_state'] ?? null;
$ghCookieState = $_COOKIE['gh_oauth_state'] ?? null;

$validState = false;
if (!empty($_SESSION['hc_oauth_state']) && hash_equals($_SESSION['hc_oauth_state'], $state)) {
    $validState = true;
    unset($_SESSION['hc_oauth_state']);
}

if (!$validState) {
    // If this state belongs to GitHub, redirect to the GitHub callback.
    if (($ghSessState && hash_equals($ghSessState, $state)) || ($ghCookieState && hash_equals($ghCookieState, $state))) {
        $qry = http_build_query(['code' => $_GET['code'] ?? '', 'state' => $state]);
        header('Location: ' . url('auth/github_callback.php') . '?' . $qry);
        exit;
    }


    http_response_code(400);
    $loginUrl = url('auth/login.php');
    $profileUrl = url('public/profile.php');
    $loginUrlEsc = htmlspecialchars($loginUrl);
    $profileUrlEsc = htmlspecialchars($profileUrl);
    echo "<h1>Invalid OAuth state</h1>";
    echo "<p>No matching OAuth session was found. To sign in, please start the login flow from <a href=\"$loginUrlEsc\">the login page</a> on this site.</p>";
    echo "<p>If you tested the redirect from an OAuth app settings page, that test doesn't create a session on this site — use the Login or Connect buttons instead. You can also visit your <a href=\"$profileUrlEsc\">profile</a> and try again.</p>";
    if (getenv('OAUTH_DEBUG') === '1') {
        $dbg = "session_hc=" . ($_SESSION['hc_oauth_state'] ?? '[none]') . " cookie_hc=" . ($cookieState ?? '[none]') . " session_gh=" . ($ghSessState ?? '[none]') . " cookie_gh=" . ($ghCookieState ?? '[none]');
        echo "<pre style=\"white-space:pre-wrap;background:#f8f9fa;padding:8px;border-radius:6px;margin-top:12px;\">" . htmlspecialchars($dbg) . "</pre>";
        @file_put_contents(__DIR__ . '/../logs/oauth_debug.log', date('c') . " hc_state_check: " . $dbg . "\n", FILE_APPEND | LOCK_EX);
    }
    exit;
}

$code = $_GET['code'];
$redirectUri = getenv('HACKCLUB_REDIRECT_URI') ?: ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . url('auth/callback.php'));

$tokenUrl = 'https://auth.hackclub.com/oauth/token';
$post = http_build_query([
    'client_id' => $clientId,
    'client_secret' => $clientSecret,
    'redirect_uri' => $redirectUri,
    'code' => $code,
    'grant_type' => 'authorization_code',
]);

$opts = [
    'http' => [
        'method' => 'POST',
        'header' => "Content-Type: application/x-www-form-urlencoded\r\nAccept: application/json\r\n",
        'content' => $post,
        'timeout' => 10,
    ]
];

$ctx = stream_context_create($opts);
$res = @file_get_contents($tokenUrl, false, $ctx);
if ($res === false) {
    $msg = isset($http_response_header) ? implode("\n", $http_response_header) : 'no response';
    http_response_code(500);
    echo "<p>Token exchange failed: " . htmlspecialchars($msg) . "</p>";
    exit;
}

$data = json_decode($res, true);
$accessToken = $data['access_token'] ?? null;
$refreshToken = $data['refresh_token'] ?? null;
$expiresIn = isset($data['expires_in']) ? intval($data['expires_in']) : null;

// If provider returned an ID token (OIDC), try to extract claims for email/name/sub
$idTokenClaims = null;
if (!empty($data['id_token']) && is_string($data['id_token'])) {
    $parts = explode('.', $data['id_token']);
    if (count($parts) >= 2) {
        $payload = $parts[1];
        $remainder = strlen($payload) % 4;
        if ($remainder > 0) $payload .= str_repeat('=', 4 - $remainder);
        $decoded = base64_decode(strtr($payload, '-_', '+/'));
        $claims = json_decode($decoded, true);
        if (is_array($claims)) $idTokenClaims = $claims;
    }
}

if (!$accessToken) {
    http_response_code(500);
    echo "<p>Token exchange returned no access token.</p>";
    exit;
}

// Fetch user info
$meUrl = 'https://auth.hackclub.com/api/v1/me';
$opts = [
    'http' => [
        'method' => 'GET',
        'header' => "Authorization: Bearer $accessToken\r\nAccept: application/json\r\n",
        'timeout' => 10,
    ]
];

$ctx = stream_context_create($opts);
$meRes = @file_get_contents($meUrl, false, $ctx);
if ($meRes === false) {
    $msg = isset($http_response_header) ? implode("\n", $http_response_header) : 'no response';
    http_response_code(500);
    echo "<p>Failed to fetch user info: " . htmlspecialchars($msg) . "</p>";
    exit;
}

// Optional debug log of provider /me response (enable by setting OAUTH_DEBUG=1 in .env)
if (getenv('OAUTH_DEBUG') === '1') {
    $dbg = substr($meRes, 0, 4000);
    @file_put_contents(__DIR__ . '/../logs/oauth_debug.log', date('c') . "\n" . $dbg . "\n\n", FILE_APPEND | LOCK_EX);
}

$me = json_decode($meRes, true);

// Robust provider user id extraction
$providerId = $me['id'] ?? $me['user_id'] ?? $me['sub'] ?? $me['uid'] ?? null;
$name = $me['name'] ?? ($me['display_name'] ?? ($me['user']['name'] ?? null));
$slack = $me['slack_id'] ?? ($me['user']['slack_id'] ?? null);

// Attempt to extract an email address from several possible response shapes.
$email = null;
if (!empty($me['email']) && is_string($me['email'])) {
    $email = $me['email'];
} elseif (!empty($me['emails']) && is_array($me['emails'])) {
    $first = $me['emails'][0] ?? null;
    if (is_array($first) && !empty($first['email'])) $email = $first['email'];
    elseif (is_string($first)) $email = $first;
} elseif (!empty($me['primary_email'])) {
    $email = $me['primary_email'];
} elseif (!empty($me['user']['email'])) {
    $email = $me['user']['email'];
} elseif (!empty($me['profile']['email'])) {
    $email = $me['profile']['email'];
}

$email = $email ? trim($email) : null;

// If email still missing, try ID token claims
if (!$email && !empty($idTokenClaims)) {
    $email = $idTokenClaims['email'] ?? $idTokenClaims['preferred_username'] ?? null;
    if (!$providerId) $providerId = $idTokenClaims['sub'] ?? null;
    if (!$name) $name = $idTokenClaims['name'] ?? ($idTokenClaims['given_name'] ?? null);
}

if (!$email) {
    // Store minimal OAuth info and ask the user for an email address.
    $_SESSION['hc_oauth_pending'] = [
        'provider' => 'hackclub',
        'provider_user_id' => (string)$providerId,
        'name' => $name ?? '',
        'slack' => $slack ?? null,
        'access_token' => $accessToken,
        'refresh_token' => $refreshToken,
        'expires_in' => $expiresIn,
    ];

    header('Location: ' . url('/sprint/auth/missing_email.php'));
    exit;
}

// Find or create local user
// If the DB is down, create an ephemeral session so the user can still be logged in
if (!empty($db_connection_failed)) {
    $user = [
        'id' => 'hc_' . ($providerId ?? uniqid()),
        'name' => $name ?? '',
        'email' => $email,
        'role' => 'participant'
    ];
    login_user($user);
    header('Location: ' . url('/sprint/public/index.php'));
    exit;
}

try {
    // Prefer matching by provider account first.
    $user = null;
    $providerKey = (string)($providerId ?? '');

    if ($providerKey !== '') {
        $stmt = $pdo->prepare("SELECT ua.id AS acct_id, ua.provider, ua.provider_user_id, ua.access_token AS acct_access_token, ua.refresh_token AS acct_refresh_token, ua.expires_at AS acct_expires_at, u.id AS user_id, u.name AS user_name, u.email AS user_email, u.role AS user_role, u.slack_username AS user_slack_username, u.slack_id AS user_slack_id, u.openid_sub AS user_openid_sub, u.verification_status AS user_verification_status, u.profile AS user_profile FROM oauth_accounts ua JOIN users u ON u.id = ua.user_id WHERE ua.provider = ? AND ua.provider_user_id = ? LIMIT 1");
        $stmt->execute(['hackclub', $providerKey]);
        $acctRow = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($acctRow) {
            $user = [
                'id' => $acctRow['user_id'],
                'name' => $acctRow['user_name'],
                'email' => $acctRow['user_email'],
                'role' => $acctRow['user_role'] ?? 'participant',
                'slack_username' => $acctRow['user_slack_username'] ?? null,
                'slack_id' => $acctRow['user_slack_id'] ?? null,
                'openid_sub' => $acctRow['user_openid_sub'] ?? null,
                'verification_status' => $acctRow['user_verification_status'] ?? 0,
                'profile' => $acctRow['user_profile'] ?? null,
            ];
        }
    }

    if (!$user) {
        // Fallback: match by email
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            // Update profile fields including OpenID extras
            $slackId = $me['slack_id'] ?? $me['slackId'] ?? $me['slack_user_id'] ?? null;
            $profileJson = json_encode($me);
            $verified = !empty($me['email_verified']) || !empty($idTokenClaims['email_verified']) || !empty($me['verified']) || !empty($me['verification_status']) ? 1 : 0;
            $openid_sub = $idTokenClaims['sub'] ?? $me['sub'] ?? $providerId;

            $stmt = $pdo->prepare("UPDATE users SET name = ?, slack_username = ?, slack_id = ?, profile = ?, verification_status = ?, openid_sub = ? WHERE id = ?");
            $stmt->execute([
                $name ?? $user['name'],
                $slack ?? ($user['slack_username'] ?? null),
                $slackId ?? ($user['slack_id'] ?? null),
                $profileJson,
                $verified,
                $openid_sub,
                $user['id']
            ]);
        } else {
            // Create new user with OpenID extras
            $slackId = $me['slack_id'] ?? $me['slackId'] ?? $me['slack_user_id'] ?? null;
            $profileJson = json_encode($me);
            $verified = !empty($me['email_verified']) || !empty($idTokenClaims['email_verified']) || !empty($me['verified']) || !empty($me['verification_status']) ? 1 : 0;
            $openid_sub = $idTokenClaims['sub'] ?? $me['sub'] ?? $providerId;

            $stmt = $pdo->prepare("INSERT INTO users (name, email, password_hash, slack_username, slack_id, profile, verification_status, openid_sub) VALUES (?,?,?,?,?,?,?,?)");
            $stmt->execute([$name ?? '', $email, '', $slack ?? null, $slackId ?? null, $profileJson, $verified, $openid_sub]);
            $id = $pdo->lastInsertId();
            $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$id]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
        }
    }

    // Persist OAuth tokens (optional table). If table missing, ignore errors.
    try {
        $expiresAt = $expiresIn ? date('Y-m-d H:i:s', time() + $expiresIn) : null;

        if ($providerKey !== '') {
            $stmt = $pdo->prepare("SELECT * FROM oauth_accounts WHERE provider = ? AND provider_user_id = ?");
            $stmt->execute(['hackclub', $providerKey]);
            $acct = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($acct) {
                $stmt = $pdo->prepare("UPDATE oauth_accounts SET access_token = ?, refresh_token = ?, expires_at = ?, user_id = ? WHERE id = ?");
                $stmt->execute([$accessToken, $refreshToken, $expiresAt, $user['id'], $acct['id']]);
            } else {
                $stmt = $pdo->prepare("INSERT INTO oauth_accounts (user_id, provider, provider_user_id, access_token, refresh_token, expires_at) VALUES (?,?,?,?,?,?)");
                $stmt->execute([$user['id'], 'hackclub', $providerKey, $accessToken, $refreshToken, $expiresAt]);
            }
        } else {
            // Provider didn't return an identifier — log and skip linking.
            log_db_error("oauth: provider returned no stable user id for hackclub (email={$email})");
        }
    } catch (Exception $e) {
        log_db_error("oauth_accounts operation failed: " . $e->getMessage());
    }

    // Log user in
    login_user($user);
    header('Location: ' . url('/sprint/public/index.php'));
    exit;
} catch (Exception $e) {
    http_response_code(500);
    // Avoid leaking internal exception details to the client.
    if (function_exists('log_db_error')) {
        log_db_error('OAuth callback error: ' . $e->getMessage());
    } else {
        error_log('OAuth callback error: ' . $e->getMessage());
    }
    echo "<p>Unexpected error.</p>";
    exit;
}

