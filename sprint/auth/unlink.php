<?php
require_once '../config.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . url('/sprint/public/profile.php'));
    exit;
}

$token = $_POST['csrf_token'] ?? '';
$provider = $_POST['provider'] ?? '';

if (!validate_csrf_token($token)) {
    http_response_code(400);
    $_SESSION['profile_error'] = 'Invalid CSRF token.';
    header('Location: ' . url('/sprint/public/profile.php'));
    exit;
}

$provider = trim((string)$provider);
if ($provider === '') {
    $_SESSION['profile_error'] = 'Missing provider.';
    header('Location: ' . url('/sprint/public/profile.php'));
    exit;
}

try {
    // Make sure users don't lock themselves out
    $stmt = $pdo->prepare("SELECT COUNT(*) as cnt FROM oauth_accounts WHERE user_id = ?");
    $stmt->execute([current_user_id()]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $linked = intval($row['cnt'] ?? 0);

    $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = ?");
    $stmt->execute([current_user_id()]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    $hasPassword = !empty($user['password_hash']);

    if ($linked <= 1 && !$hasPassword) {
        $_SESSION['profile_error'] = 'Cannot unlink the only login method for your account. Add another login method first.';
        header('Location: ' . url('/sprint/public/profile.php'));
        exit;
    }

    $userId = current_user_id();

    if (strtolower($provider) === 'github') {
        $stmt = $pdo->prepare("SELECT COUNT(*) as cnt FROM oauth_accounts WHERE provider = 'github' AND user_id = ?");
        $stmt->execute([$userId]);
        $beforeCnt = intval($stmt->fetch(PDO::FETCH_ASSOC)['cnt'] ?? 0);

        $stmt = $pdo->prepare("DELETE FROM oauth_accounts WHERE provider = ? AND user_id = ?");
        $stmt->execute([$provider, $userId]);

        $afterCnt = $beforeCnt > 0 ? max(0, $beforeCnt - 1) : 0;

        if ($afterCnt === 0) {
            $upd = $pdo->prepare('UPDATE users SET github_avatar_url = NULL WHERE id = ?');
            $upd->execute([$userId]);

            if (!empty($_SESSION['user'])) {
                $_SESSION['user']['github_avatar_url'] = null;
            }
        }
    } else {
        $stmt = $pdo->prepare("DELETE FROM oauth_accounts WHERE provider = ? AND user_id = ?");
        $stmt->execute([$provider, $userId]);
    }

    $_SESSION['profile_success'] = 'Unlinked ' . htmlspecialchars($provider) . ' account.';
    header('Location: ' . url('/sprint/public/profile.php'));
    exit;
} catch (Exception $e) {
    $_SESSION['profile_error'] = 'Unlink failed: ' . htmlspecialchars($e->getMessage());
    header('Location: ' . url('/sprint/public/profile.php'));
    exit;
}
