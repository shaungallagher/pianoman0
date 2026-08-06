<?php
require_once '../config.php';

$code = $_GET['code'] ?? null;
$state = $_GET['state'] ?? null;
$sessState = $_SESSION['slack_oauth_state'] ?? null;
$cookieState = $_COOKIE['slack_oauth_state'] ?? null;
$validState = false;
if ($code && $state) {
    if ($sessState && hash_equals($sessState, $state)) $validState = true;
    elseif ($cookieState && hash_equals($cookieState, $state)) $validState = true;
}
if (!$validState) {
    $_SESSION['profile_error'] = 'Invalid Slack OAuth state.';
    header('Location: ' . url('/sprint/public/profile.php'));
    exit;
}

if (isset($_COOKIE['slack_oauth_state'])) {
    if (PHP_VERSION_ID >= 70300) setcookie('slack_oauth_state', '', ['expires' => time() - 3600, 'path' => '/', 'samesite' => 'Lax']);
    else setcookie('slack_oauth_state', '', time() - 3600, '/');
}

$clientId = getenv('SLACK_CLIENT_ID');
$clientSecret = getenv('SLACK_CLIENT_SECRET');
if (!$clientId || !$clientSecret) {
    $_SESSION['profile_error'] = 'Slack OAuth is not working right now, please contact @PianoMan0';
    header('Location: ' . url('/sprint/public/profile.php'));
    exit;
}

$redirectUri = getenv('SLACK_REDIRECT_URI') ?: ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . url('/sprint/auth/slack_callback.php'));

$post = http_build_query([
    'client_id' => $clientId,
    'client_secret' => $clientSecret,
    'code' => $code,
    'redirect_uri' => $redirectUri,
]);

$ch = curl_init('https://slack.com/api/oauth.v2.access');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json']);
$resp = curl_exec($ch);
if ($resp === false) {
    $_SESSION['profile_error'] = 'Failed to complete Slack OAuth.';
    header('Location: ' . url('/sprint/public/profile.php'));
    exit;
}
$data = json_decode($resp, true);
if (empty($data) || empty($data['ok'])) {
    $_SESSION['profile_error'] = 'Slack OAuth failed: ' . ($data['error'] ?? 'unknown');
    header('Location: ' . url('/sprint/public/profile.php'));
    exit;
}

$provider = 'slack';
$provider_user_id = $data['authed_user']['id'] ?? null;
$authed_user_token = $data['authed_user']['access_token'] ?? null;

try {
    if ($provider_user_id) {
        $stmt = $pdo->prepare('SELECT id AS acct_id, user_id FROM oauth_accounts WHERE provider=? AND provider_user_id=? LIMIT 1');
        $stmt->execute([$provider, $provider_user_id]);
        $acct = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($acct) {
                    $stmt = $pdo->prepare('UPDATE oauth_accounts SET access_token=?, expires_at=? WHERE id=?');
                    $stmt->execute([$authed_user_token, null, $acct['acct_id']]);

                    if (!empty($authed_user_token)) {
                        $avatar = null;
                        try {
                            $ch2 = curl_init('https://slack.com/api/users.info?user=' . urlencode($provider_user_id));
                            curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
                            curl_setopt($ch2, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $authed_user_token, 'Accept: application/json']);
                            $info = curl_exec($ch2);
                            $infoData = json_decode($info, true);
                            if (!empty($infoData['ok']) && !empty($infoData['user'])) {
                                $profile = $infoData['user']['profile'] ?? [];
                                $avatar = $profile['image_512'] ?? $profile['image_192'] ?? $profile['image_72'] ?? $profile['image_48'] ?? $profile['image_32'] ?? $profile['image_24'] ?? null;
                            }
                        } catch (Exception $e) {
                            $avatar = null;
                        }
                        if (!empty($avatar)) {
                            $upd = $pdo->prepare('UPDATE users SET slack_avatar_url = ? WHERE id = ?');
                            $upd->execute([$avatar, $acct['user_id']]);
                        }
                    }

                    if (!empty($acct['user_id'])) {
                $uStmt = $pdo->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
                $uStmt->execute([$acct['user_id']]);
                $uRow = $uStmt->fetch(PDO::FETCH_ASSOC);
                if ($uRow) {
                    login_user($uRow);
                    $_SESSION['profile_success'] = 'Logged in via Slack.';
                } else {
                    $_SESSION['profile_error'] = 'Linked Slack account found but user record is missing.';
                }
            } else {
                $_SESSION['profile_error'] = 'Slack account found but not linked to any user.';
            }

        } else {
            $email = null; $name = null; $avatar = null;
            if ($authed_user_token) {
                $ch2 = curl_init('https://slack.com/api/users.info?user=' . urlencode($provider_user_id));
                curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch2, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $authed_user_token, 'Accept: application/json']);
                $info = curl_exec($ch2);
                $infoData = json_decode($info, true);
                if (!empty($infoData['ok']) && !empty($infoData['user'])) {
                    $name = $infoData['user']['real_name'] ?? $infoData['user']['name'] ?? null;
                    $email = $infoData['user']['profile']['email'] ?? null;

                    $profile = $infoData['user']['profile'] ?? [];
                    $avatar = $profile['image_512'] ?? $profile['image_192'] ?? $profile['image_72'] ?? $profile['image_48'] ?? $profile['image_32'] ?? $profile['image_24'] ?? null;
                }
            }

            if (current_user_id()) {
                $stmt = $pdo->prepare('INSERT INTO oauth_accounts (user_id, provider, provider_user_id, access_token, created_at) VALUES (?,?,?,?,CURRENT_TIMESTAMP)');
                $stmt->execute([current_user_id(), $provider, $provider_user_id, $authed_user_token]);
                $_SESSION['profile_success'] = 'Slack account linked.';
            } else {
                if ($email) {
                    $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
                    $stmt->execute([$email]);
                    $user = $stmt->fetch(PDO::FETCH_ASSOC);
                    if ($user) {
                        $stmt = $pdo->prepare('INSERT INTO oauth_accounts (user_id, provider, provider_user_id, access_token, created_at) VALUES (?,?,?,?,CURRENT_TIMESTAMP)');
                        $stmt->execute([$user['id'], $provider, $provider_user_id, $authed_user_token]);
                        login_user($user);
                        $_SESSION['profile_success'] = 'Logged in via Slack.';
                    } else {
                        $stmt = $pdo->prepare('INSERT INTO users (name, email, password_hash, slack_username, slack_id, profile, slack_avatar_url, role) VALUES (?,?,?,?,?,?,?,?)');
                        $stmt->execute([
                            $name ?? '',
                            $email,
                            '',
                            null,
                            $provider_user_id,
                            json_encode($data),
                            $avatar,
                            'participant'
                        ]);
                        $id = $pdo->lastInsertId();
                        $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
                        $stmt->execute([$id]);
                        $user = $stmt->fetch(PDO::FETCH_ASSOC);
                        $stmt = $pdo->prepare('INSERT INTO oauth_accounts (user_id, provider, provider_user_id, access_token, created_at) VALUES (?,?,?,?,CURRENT_TIMESTAMP)');
                        $stmt->execute([$user['id'], $provider, $provider_user_id, $authed_user_token]);
                        login_user($user);
                        $_SESSION['profile_success'] = 'Registered and logged in via Slack.';
                    }
                } else {
                    $_SESSION['profile_error'] = 'Slack did not provide an email address; please log in first to link your Slack account.';
                }
            }
        }
    } else {
        $_SESSION['profile_error'] = 'Slack OAuth returned no user id.';
    }
} catch (Exception $e) {
    $_SESSION['profile_error'] = 'Failed to process Slack OAuth: ' . $e->getMessage();
}

header('Location: ' . url('/sprint/public/profile.php'));
exit;
