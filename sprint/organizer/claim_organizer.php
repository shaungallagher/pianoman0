<?php
require_once '../config.php';
require_login();

// Only allow claiming if there are no existing organizers
try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'organizer'");
    $count = $stmt->fetchColumn();
} catch (Exception $e) {
    $count = 0;
}

if ($count > 0) {
    // Already have organizers
    header('Location: dashboard.php');
    exit;
}

// Backwards-compatibility: if this site uses per-event organizer upgrades,
// redirect to the organizer conversion flow.
if (isset($_GET['event_id']) && $_GET['event_id'] !== '') {
    header('Location: claim_organizer_for_event.php?event_id=' . urlencode((string)$_GET['event_id']));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!validate_csrf_token($token)) {
        abort_page('Invalid CSRF token', 400);
    }
    try {
        $stmt = $pdo->prepare("UPDATE users SET role='organizer' WHERE id = ?");
        $stmt->execute([current_user_id()]);
        // update session role
        if (!empty($_SESSION['user'])) {
            $_SESSION['user']['role'] = 'organizer';
        }
        header('Location: dashboard.php');
        exit;
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

$page_title = "Claim Organizer · Sprint";
include '../includes/header.php';
?>

<h1>Claim Organizer Role</h1>

<?php if (!empty($error)): ?>
    <p class="flash error"><?= htmlspecialchars($error) ?></p>
<?php endif; ?>

<p>This instance currently has no organizers. If you are the site owner you can claim the organizer role for your account.</p>

<form method="post">
    <?= csrf_input_field() ?>
    <button class="btn">Claim Organizer Role</button>
</form>

<?php include '../includes/footer.php'; ?>
