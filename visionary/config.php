<?php

$secrets = [];
$secretsFile = __DIR__ . '/secrets.txt';
if (is_file($secretsFile) && is_readable($secretsFile)) {
    // Support two formats:
    //  - KEY=VALUE lines (preferred)
    //  - a PHP file that returns an associative array (legacy/misplaced)
    $content = @file_get_contents($secretsFile, false, null, 0, 8);
    if ($content !== false && substr(ltrim($content), 0, 2) === '<?') {
        $data = @include $secretsFile;
        if (is_array($data)) {
            foreach ($data as $k => $v) {
                $k = trim((string)$k);
                $secrets[$k] = $v;
                // also expose uppercase env-style keys for compatibility
                $secrets[strtoupper($k)] = $v;
            }
        }
    } else {
        $lines = file($secretsFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#') continue;
            if (strpos($line, '=') !== false) {
                [$k, $v] = explode('=', $line, 2);
                $secrets[trim($k)] = trim($v);
            }
        }
    }
}

function env_val($name, $default = '') {
    global $secrets;
    $v = getenv($name);
    if ($v !== false) return $v;
    if (isset($secrets[$name])) return $secrets[$name];
    return $default;
}

return [
    // GitHub OAuth
    'github_client_id' => env_val('GITHUB_CLIENT_ID', ''),
    'github_client_secret' => env_val('GITHUB_CLIENT_SECRET', ''),

    // Base URL of the app. Used to build redirect URIs.
    'base_url' => env_val('BASE_URL', ''),

    // Hack Club / OAuth2
    'hackclub_client_id' => env_val('HACKCLUB_CLIENT_ID', ''),
    'hackclub_client_secret' => env_val('HACKCLUB_CLIENT_SECRET', ''),
    'hackclub_discovery_url' => env_val('HACKCLUB_DISCOVERY_URL', 'https://auth.hackclub.com/.well-known/openid-configuration'),
    'hackclub_authorize_url' => env_val('HACKCLUB_AUTHORIZE_URL', 'https://auth.hackclub.com/oauth/authorize'),
    'hackclub_token_url' => env_val('HACKCLUB_TOKEN_URL', 'https://auth.hackclub.com/oauth/token'),
    'hackclub_userinfo_url' => env_val('HACKCLUB_USERINFO_URL', 'https://auth.hackclub.com/oauth/userinfo'),
    'hackclub_jwks_url' => env_val('HACKCLUB_JWKS_URL', 'https://auth.hackclub.com/oauth/discovery/keys'),
    'hackclub_scope' => env_val('HACKCLUB_SCOPE', 'openid profile email name slack_id verification_status'),

    // Hackatime
    'hackatime_client_id' => env_val('HACKATIME_CLIENT_ID', ''),
    'hackatime_client_secret' => env_val('HACKATIME_CLIENT_SECRET', ''),
    'hackatime_authorize_url' => env_val('HACKATIME_AUTHORIZE_URL', 'https://hackatime.hackclub.com/oauth/authorize'),
    'hackatime_token_url' => env_val('HACKATIME_TOKEN_URL', 'https://hackatime.hackclub.com/oauth/token'),
    'hackatime_userinfo_url' => env_val('HACKATIME_USERINFO_URL', 'https://hackatime.hackclub.com/api/v1/authenticated/me'),
    'hackatime_scope' => env_val('HACKATIME_SCOPE', 'profile'),

    // Microsoft / Azure (Not currently working)
    'microsoft_client_id' => env_val('MICROSOFT_CLIENT_ID', ''),
    'microsoft_client_secret' => env_val('MICROSOFT_CLIENT_SECRET', ''),
    'microsoft_scope' => env_val('MICROSOFT_SCOPE', 'openid profile User.Read email'),

    // Slack (Not currenntly working)
    'slack_client_id' => env_val('SLACK_CLIENT_ID', ''),
    'slack_client_secret' => env_val('SLACK_CLIENT_SECRET', ''),

    'admin_password' => env_val('VISIONARY_ADMIN_PASSWORD', null),
];
