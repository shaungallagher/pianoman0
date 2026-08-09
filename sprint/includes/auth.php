<?php

function require_login() {
    if (!current_user_id()) {
        if (getenv('HACKCLUB_CLIENT_ID')) {
header("Location: " . url('/sprint/auth/oauth.php'));
        } else {
header("Location: " . url('/sprint/auth/login.php'));
        }
        exit;
    }
}

function login_user($user) {
    if (function_exists('session_regenerate_id')) {
        session_regenerate_id(true);
    }

    $_SESSION['user'] = [
        'id' => $user['id'],
        'name' => $user['name'] ?? '',
        'email' => $user['email'] ?? '',
        'role' => $user['role'] ?? 'participant',
        'slack_username' => $user['slack_username'] ?? ($user['slack'] ?? null),
        'slack_id' => $user['slack_id'] ?? null,
        'openid_sub' => $user['openid_sub'] ?? null,
        'verification_status' => $user['verification_status'] ?? 0,
        'profile' => $user['profile'] ?? null,
        // Optional avatar URL (Slack today; GitHub will populate as well)
        'slack_avatar_url' => $user['slack_avatar_url'] ?? null,
        'github_avatar_url' => $user['github_avatar_url'] ?? null,
    ];
}

function logout_user() {
    unset($_SESSION['user']);
}
