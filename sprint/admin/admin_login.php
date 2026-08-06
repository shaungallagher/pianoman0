<?php
require_once '../config.php';
require_once '../includes/functions.php';

$page_title = "Admin Login · Sprint";
include '../includes/header.php';

$error = null;
$success = null;

require_once '../includes/auth.php';

// Skip login if the user is already an admin
if (is_admin()) {
    header('Location: dashboard.php');
    exit;
}

// Rate limiting for admin password attempts.
function admin_login_rate_limit_ok(int $maxAttempts = 8, int $windowSeconds = 10, int $cooldownSeconds = 30): bool {
    ensure_session_started();

    $now = time();
    $key = 'admin_login_attempts';
    $cooldownKey = 'admin_login_cooldown_until';

    $cooldownUntil = isset($_SESSION[$cooldownKey]) ? (int)$_SESSION[$cooldownKey] : 0;
    if ($cooldownUntil > $now) {
        return false;
    }

    if (!isset($_SESSION[$key]) || !is_array($_SESSION[$key])) {
        $_SESSION[$key] = [];
    }

    $_SESSION[$key] = array_values(array_filter($_SESSION[$key], fn($t) => is_int($t) || is_numeric($t)));
    $_SESSION[$key] = array_values(array_filter($_SESSION[$key], fn($t) => ((int)$t) >= ($now - $windowSeconds)));

    if (count($_SESSION[$key]) >= $maxAttempts) {
        $_SESSION[$cooldownKey] = $now + $cooldownSeconds;
        return false;
    }

    return true;
}

function admin_login_rate_limit_record_attempt(): void {
    ensure_session_started();
    $key = 'admin_login_attempts';
    $now = time();
    if (!isset($_SESSION[$key]) || !is_array($_SESSION[$key])) {
        $_SESSION[$key] = [];
    }
    $_SESSION[$key][] = $now;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!validate_csrf_token($token)) {
        abort_page('Invalid CSRF token', 400);
    }

    if (!admin_login_rate_limit_ok()) {
        $error = 'Too many admin login attempts. Please wait and try again.';
    } else {
        $password = (string)($_POST['password'] ?? '');
        $expected = (string)(getenv('ADMIN_PASSWORD') ?: '');

        if ($expected === '') {
            $error = 'Admin password is not configured. Set ADMIN_PASSWORD in your environment.';
            if (!hash_equals($expected, $password)) {
                admin_login_rate_limit_record_attempt();
                $error = 'Incorrect admin password.';

                if (function_exists('log_db_error')) {
                    $uid = current_user_id();
                    $uidPart = $uid ? (string)$uid : 'null';
                    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
                    log_db_error("SECURITY: admin_login failed for user_id={$uidPart} ip={$ip}");
                }
            } else {
                // Must be logged in to become an admin
                $uid = current_user_id();
                if (!$uid) {
                    $success = 'Login required before becoming admin.';
                    header('Location: ../auth/login.php');
                    exit;
                }

                try {
                    $stmt = $pdo->prepare("UPDATE users SET role = 'admin' WHERE id = ?");
                    $stmt->execute([$uid]);

                    if (!empty($_SESSION['user'])) {
                        $_SESSION['user']['role'] = 'admin';
                    }

                    if (function_exists('log_db_error')) {
                        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
                        log_db_error("SECURITY: admin_login success for user_id={$uid} ip={$ip}");
                    }

                    $success = 'You are now an admin.';
                    header('Location: dashboard.php');
                    exit;
                } catch (Exception $e) {
                    $error = 'Failed to update role: ' . $e->getMessage();
                }
            }
}
?>

<section class="hero container">
    <div class="ultratitle">Admin Login</div>
    <p class="lead" style="max-width: 720px;">
        Enter the admin password to elevate your account to administrator privileges.
    </p>
</section>

<section class="container" style="margin-top:-2rem;">
    <div class="card" style="max-width: 520px; margin: 0 auto;">
        <?php if (!empty($error)): ?>
            <p class="flash error"><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>
        <?php if (!empty($success)): ?>
            <p class="flash success"><?= htmlspecialchars($success) ?></p>
        <?php endif; ?>

        <form method="post" class="form">
            <?= csrf_input_field() ?>
            <label for="password">Admin password
                <input id="password" name="password" type="password" autocomplete="current-password" required>
            </label>
            <button class="btn" style="margin-top:12px; width:100%; display:block;" type="submit">Login as admin</button>
        </form>

        <p class="meta" style="margin-top:14px; text-align:center;">
            If you are not an admin, please do not attempt to log in.
        </p>
    </div>
</section>

<?php include '../includes/footer.php'; ?>

