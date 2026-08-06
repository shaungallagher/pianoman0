<?php
require_once '../config.php';

if (empty($_SESSION['hc_oauth_pending'])) {
    header('Location: ' . url('auth/login.php'));
    exit;
}

$pending = $_SESSION['hc_oauth_pending'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!validate_csrf_token($token)) {
        http_response_code(400);
        echo "<p>Invalid CSRF token.</p>";
        exit;
    }

    $email = trim((string)($_POST['email'] ?? ''));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please provide a valid email address.';
    } else {
        if (!empty($db_connection_failed)) {
            $user = [
                'id' => 'hc_' . ($pending['provider_user_id'] ?? uniqid()),
                'name' => $pending['name'] ?? '',
                'email' => $email,
                'role' => 'participant'
            ];
            login_user($user);
            unset($_SESSION['hc_oauth_pending']);
            header('Location: ' . url('public/index.php'));
            exit;
        }

        try {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                $slackId = $pending['slack'] ?? null;
                $profileJson = null;
                $verified = 0;
                $openid_sub = $pending['provider_user_id'] ?? null;

                $stmt = $pdo->prepare("UPDATE users SET name = ?, slack_username = ?, slack_id = ?, profile = ?, verification_status = ?, openid_sub = ? WHERE id = ?");
                $stmt->execute([$pending['name'] ?? $user['name'], $pending['slack'] ?? $user['slack_username'], $slackId, $profileJson, $verified, $openid_sub, $user['id']]);
            } else {
                $slackId = $pending['slack'] ?? null;
                $profileJson = null;
                $verified = 0;
                $openid_sub = $pending['provider_user_id'] ?? null;

                $stmt = $pdo->prepare("INSERT INTO users (name, email, password_hash, slack_username, slack_id, profile, verification_status, openid_sub) VALUES (?,?,?,?,?,?,?,?)");
                $stmt->execute([$pending['name'] ?? '', $email, '', $pending['slack'] ?? null, $slackId, $profileJson, $verified, $openid_sub]);
                $id = $pdo->lastInsertId();
                $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
                $stmt->execute([$id]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
            }

            try {
                $provUid = (string)($pending['provider_user_id'] ?? '');
                if ($provUid !== '') {
                    $stmt = $pdo->prepare("SELECT * FROM oauth_accounts WHERE provider = ? AND provider_user_id = ?");
                    $stmt->execute([$pending['provider'], $provUid]);
                    $acct = $stmt->fetch(PDO::FETCH_ASSOC);

                    $expiresAt = isset($pending['expires_in']) && $pending['expires_in'] ? date('Y-m-d H:i:s', time() + intval($pending['expires_in'])) : null;

                    if ($acct) {
                        $stmt = $pdo->prepare("UPDATE oauth_accounts SET access_token = ?, refresh_token = ?, expires_at = ?, user_id = ? WHERE id = ?");
                        $stmt->execute([$pending['access_token'], $pending['refresh_token'], $expiresAt, $user['id'], $acct['id']]);
                    } else {
                        $stmt = $pdo->prepare("INSERT INTO oauth_accounts (user_id, provider, provider_user_id, access_token, refresh_token, expires_at) VALUES (?,?,?,?,?,?)");
                        $stmt->execute([$user['id'], $pending['provider'], $provUid, $pending['access_token'], $pending['refresh_token'], $expiresAt]);
                    }
                } else {
                    log_db_error("oauth_accounts skipped: provider returned no user id (missing_email)");
                }
            } catch (Exception $e) {
                log_db_error("oauth_accounts operation failed (missing_email): " . $e->getMessage());
            }

            login_user($user);
            unset($_SESSION['hc_oauth_pending']);
            header('Location: ' . url('public/index.php'));
            exit;
        } catch (Exception $e) {
            http_response_code(500);
            echo "<p>Unexpected error: " . htmlspecialchars($e->getMessage()) . "</p>";
            exit;
        }
    }
}

$page_title = "Provide email · Sprint";
include '../includes/header.php';
?>

<h1>We need your email</h1>
<?php if (!empty($error)): ?>
    <div class="error"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<p>Hack Club Auth did not provide your email address. Please enter the email you'd like to use for your Sprint account.</p>

<form method="post" action="<?= url('auth/missing_email.php') ?>">
    <?= csrf_input_field() ?>
    <div>
        <label for="email">Email</label>
        <input id="email" name="email" type="email" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
    </div>
    <div style="margin-top:8px;">
        <button class="btn">Continue</button>
        <a class="btn" href="<?= url('auth/login.php') ?>">Cancel</a>
    </div>
</form>

<?php include '../includes/footer.php'; ?>
